#!/usr/bin/env node
'use strict';

/**
 * Ponte Node <-> API DivulgaCandContas (TSE).
 *
 * O TSE/Akamai devolve 403 para cURL/PHP (e até para Chromium headless), mas
 * deixa passar um Chromium de verdade com janela. Este script é essa janela:
 * ele sobe o Playwright, navega até o portal e executa os `fetch()` DENTRO da
 * página, de modo que a requisição carrega o mesmo UA, os mesmos cookies e a
 * mesma origem de um navegador humano.
 *
 * O Laravel fala com ele por dois caminhos:
 *
 *  - produção: `TseBrowserGateway` envia um JSON pelo STDIN e lê o JSON que
 *    sai pelo STDOUT (é assim que o `sync:candidates-tse` usa);
 *  - validação manual: argumentos de linha de comando, ex.:
 *
 *      xvfb-run --auto-servernum node scripts/tse/fetch-candidates.js \
 *        --list-url "https://divulgacandcontas.tse.jus.br/divulga/rest/v1/candidatura/listar/2026/CE/20322002026/7/candidatos"
 *
 * CONTRATO DE STDOUT: só o JSON final. Qualquer log vai para STDERR, porque o
 * PHP interpreta o stdout como um único objeto JSON.
 *
 * Contrato de sucesso:  {"success": true,  "status": 200, "data": {...}}
 * Contrato de falha:    {"success": false, "status": 403, "error": "TSE_ACCESS_DENIED", "message": "..."}
 */

const { chromium } = require('playwright');

const DEFAULT_START_URL = 'https://divulgacandcontas.tse.jus.br/divulga/';
const DEFAULT_NAVIGATION_TIMEOUT = 60000;
const DEFAULT_FETCH_TIMEOUT = 30000;

/** Logs nunca podem ir para o stdout: o Laravel lê o stdout como JSON puro. */
function log(...args) {
    console.error('[tse]', ...args);
}

async function emit(payload) {
    // Aguarda a confirmação da escrita: um process.exit() prematuro descarta
    // buffers pendentes de pipe e o Laravel leria stdout vazio.
    await new Promise((resolve) => process.stdout.write(JSON.stringify(payload), resolve));
}

function failure(error, status, message) {
    return {
        success: false,
        status: typeof status === 'number' && Number.isFinite(status) ? status : 0,
        error: error || 'NODE_FAILED',
        message: message || '',
    };
}

function readStdin() {
    return new Promise((resolve, reject) => {
        let raw = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => {
            raw += chunk;
        });
        process.stdin.on('end', () => resolve(raw));
        process.stdin.on('error', reject);
    });
}

function lastSegment(url) {
    const parts = String(url).split('/').filter(Boolean);
    return parts.length > 0 ? parts[parts.length - 1] : String(url);
}

function parseArgs(argv) {
    const parsed = {};
    const value = (index) => {
        if (index + 1 >= argv.length) {
            throw new Error(`Valor ausente para o argumento ${argv[index]}.`);
        }
        return argv[index + 1];
    };

    for (let i = 0; i < argv.length; i++) {
        switch (argv[i]) {
            case '--list-url':
                parsed.listUrl = value(i);
                i++;
                break;
            case '--detail-template':
                parsed.detailUrlTemplate = value(i);
                i++;
                break;
            case '--detail': {
                const url = value(i);
                i++;
                parsed.details = [...(parsed.details || []), { key: lastSegment(url), url }];
                break;
            }
            case '--start-url':
                parsed.startUrl = value(i);
                i++;
                break;
            case '--headless':
                parsed.headless = true;
                break;
            case '--navigation-timeout':
                parsed.navigationTimeout = Number(value(i));
                i++;
                break;
            case '--fetch-timeout':
                parsed.fetchTimeout = Number(value(i));
                i++;
                break;
            default:
                throw new Error(`Argumento desconhecido: ${argv[i]}`);
        }
    }

    return parsed;
}

function asPositiveInt(input, fallback) {
    const number = Number(input);
    return Number.isFinite(number) && number > 0 ? Math.trunc(number) : fallback;
}

function normalize(raw) {
    if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) {
        throw new Error('A entrada deve ser um objeto JSON.');
    }

    return {
        startUrl: typeof raw.startUrl === 'string' && raw.startUrl !== '' ? raw.startUrl : DEFAULT_START_URL,
        headless: raw.headless === true || raw.headless === 'true',
        navigationTimeout: asPositiveInt(raw.navigationTimeout, DEFAULT_NAVIGATION_TIMEOUT),
        fetchTimeout: asPositiveInt(raw.fetchTimeout, DEFAULT_FETCH_TIMEOUT),
        listUrl: typeof raw.listUrl === 'string' && raw.listUrl !== '' ? raw.listUrl : null,
        detailUrlTemplate:
            typeof raw.detailUrlTemplate === 'string' && raw.detailUrlTemplate !== ''
                ? raw.detailUrlTemplate
                : null,
        details: Array.isArray(raw.details)
            ? raw.details
                  .filter((item) => item && typeof item.url === 'string' && item.url !== '')
                  .map((item) => ({
                      key:
                          item.key === undefined || item.key === null || item.key === ''
                              ? lastSegment(item.url)
                              : String(item.key),
                      url: item.url,
                  }))
            : [],
    };
}

async function resolveInput() {
    const argv = process.argv.slice(2);

    if (argv.length > 0) {
        return normalize(parseArgs(argv));
    }

    const raw = await readStdin();

    if (raw.trim() === '') {
        throw new Error('Nenhum argumento e nenhum JSON recebido pelo stdin.');
    }

    return normalize(JSON.parse(raw));
}

/**
 * Executa um GET de dentro do contexto da página.
 *
 * `credentials: "include"` faz o fetch levar os cookies do portal — é parte do
 * que faz o TSE reconhecer a chamada como vinda de um navegador.
 */
async function fetchInPage(page, url, timeout) {
    return page.evaluate(
        async ({ target, timeoutMs }) => {
            try {
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), timeoutMs);
                const response = await fetch(target, {
                    credentials: 'include',
                    signal: controller.signal,
                });
                const text = await response.text();
                clearTimeout(timer);

                return { ok: true, status: response.status, text };
            } catch (error) {
                return { ok: false, error: String((error && error.message) || error) };
            }
        },
        { target: url, timeoutMs: timeout },
    );
}

/** Traduz a resposta HTTP crua no formato `{status, data}` ou `{status, error}`. */
function interpret(res) {
    if (!res.ok) {
        return {
            status: 0,
            error: 'FETCH_FAILED',
            message: res.error || 'Falha de rede no fetch da página.',
        };
    }

    const status = res.status;

    if (status === 401 || status === 403) {
        return { status, error: 'TSE_ACCESS_DENIED', message: 'O TSE recusou o acesso (WAF/Akamai).' };
    }

    if (status !== 200) {
        return { status, error: `TSE_HTTP_${status}`, message: `O TSE respondeu HTTP ${status}.` };
    }

    try {
        const data = JSON.parse(res.text);

        if (data === null || typeof data !== 'object') {
            return { status, error: 'INVALID_JSON', message: 'A resposta não é um objeto JSON.' };
        }

        return { status, data };
    } catch (error) {
        return { status, error: 'INVALID_JSON', message: String((error && error.message) || error) };
    }
}

function extractIds(list) {
    const candidatos = list && Array.isArray(list.candidatos) ? list.candidatos : [];
    const ids = [];

    for (const candidato of candidatos) {
        if (candidato && candidato.id !== null && candidato.id !== undefined && candidato.id !== '') {
            ids.push(String(candidato.id));
        }
    }

    return ids;
}

async function main() {
    let input;

    try {
        input = await resolveInput();
    } catch (error) {
        await emit(failure('INVALID_INPUT', 0, String((error && error.message) || error)));
        return;
    }

    log(`abrindo Chromium (headless=${input.headless}) em ${input.startUrl}`);

    let browser = null;

    try {
        // headless:false é obrigatório: o TSE bloqueia Chromium headless junto
        // com o cURL. No Render a janela fica invisível por causa do Xvfb.
        browser = await chromium.launch({ headless: input.headless });

        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto(input.startUrl, {
            waitUntil: 'domcontentloaded',
            timeout: input.navigationTimeout,
        });

        log('página carregada; executando fetch() dentro do contexto');

        const data = { list: null, details: {} };

        if (input.listUrl) {
            const listed = interpret(await fetchInPage(page, input.listUrl, input.fetchTimeout));

            log(`listagem -> HTTP ${listed.status}`);

            if (listed.error) {
                await emit(failure(listed.error, listed.status, listed.message));
                return;
            }

            data.list = listed.data;

            if (input.detailUrlTemplate) {
                const ids = extractIds(listed.data);

                log(`buscando ${ids.length} detalhe(s), sequencialmente, no mesmo navegador`);

                for (const id of ids) {
                    const url = input.detailUrlTemplate.replace('{id}', encodeURIComponent(id));
                    data.details[id] = interpret(await fetchInPage(page, url, input.fetchTimeout));
                }
            }
        }

        for (const target of input.details) {
            log(`detalhe ${target.key}`);
            data.details[target.key] = interpret(await fetchInPage(page, target.url, input.fetchTimeout));
        }

        await emit({ success: true, status: 200, data });
    } catch (error) {
        const message = String((error && error.message) || error);
        log('falha:', message);
        await emit(failure('BROWSER_FAILED', 0, message));
    } finally {
        if (browser) {
            try {
                await browser.close();
            } catch (error) {
                log('erro ao fechar o navegador:', String((error && error.message) || error));
            }
        }
    }
}

process.on('unhandledRejection', async (error) => {
    const message = String((error && error.message) || error);
    log('rejeição não tratada:', message);
    await emit(failure('NODE_FAILED', 0, message));
    process.exitCode = 1;
});

main().catch(async (error) => {
    const message = String((error && error.message) || error);
    log('falha fatal:', message);
    await emit(failure('NODE_FAILED', 0, message));
    process.exitCode = 1;
});

