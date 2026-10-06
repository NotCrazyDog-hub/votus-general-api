<?php

namespace App\Services\Tse;

use Illuminate\Process\Exceptions\ProcessIdleTimedOutException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * Executa consultas ao DivulgaCandContas dentro de um Chromium de verdade.
 *
 * O TSE/Akamai responde 403 para cURL/PHP e até para Chromium headless; só
 * deixa passar uma janela real. Este gateway é a fronteira entre o Laravel e o
 * `scripts/tse/fetch-candidates.js`, que sobe o Playwright e faz os `fetch()`
 * de dentro da página.
 *
 * Protocolo com o script:
 *
 *  - entrada: JSON no stdin com `{startUrl, headless, listUrl, detailUrlTemplate, details}`;
 *  - saída:   JSON no stdout, `{"success":true,"status":200,"data":{...}}` ou
 *             `{"success":false,"status":403,"error":"TSE_ACCESS_DENIED",...}`;
 *  - logs do navegador: stderr, para não contaminar o stdout.
 *
 * Toda a tradução de "o que deu errado" — processo Node que morreu, stdout sem
 * JSON, timeout, HTTP diferente de 200 — acontece aqui, em um único lugar.
 */
class TseBrowserGateway
{
    /**
     * Executa uma consulta e devolve o `data` do retorno do script Node.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws TseGatewayException
     */
    public function request(array $input): array
    {
        $config = config('services.tse.divulgacandcontas');
        $command = $this->command($config);

        $retries = max(1, (int) ($config['retries'] ?? 1));
        $delay = max(0, (int) ($config['retry_delay'] ?? 0));

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->send($command, $input, $config);
            } catch (TseGatewayException $exception) {
                if (! $this->retryable($exception) || $attempt >= $retries) {
                    throw $exception;
                }

                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function send(array $command, array $input, array $config): array
    {
        try {
            $result = Process::command($command)
                ->input((string) json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->timeout((int) ($config['process_timeout'] ?? 900))
                ->run();
        } catch (ProcessTimedOutException | ProcessIdleTimedOutException) {
            throw new TseGatewayException(
                'NODE_TIMEOUT',
                null,
                sprintf('O processo Node/Playwright estourou o timeout de %ds.', (int) ($config['process_timeout'] ?? 900)),
            );
        }

        $output = trim((string) $result->output());

        // O script sempre escreve algo (o contrato exige JSON no stdout). Stdout
        // vazio significa que o processo morreu antes de conseguir responder —
        // Chromium não instalado, OOM, falta de DISPLAY sem Xvfb...
        if ($output === '') {
            throw new TseGatewayException(
                'NODE_FAILED',
                null,
                trim(sprintf(
                    'O processo Node terminou sem retorno (exit %s). %s',
                    $result->exitCode(),
                    (string) $result->errorOutput(),
                )),
            );
        }

        $payload = json_decode($output, true);

        if (! is_array($payload)) {
            throw new TseGatewayException(
                'INVALID_NODE_OUTPUT',
                null,
                'Retorno do processo Node não é JSON válido: '.mb_substr($output, 0, 300),
            );
        }

        if (($payload['success'] ?? false) !== true) {
            throw $this->fromPayload($payload);
        }

        $data = $payload['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Converte `{"success":false,...}` em exceção, preservando o código e o
     * status HTTP que o script reportou.
     *
     * @param  array<string, mixed>  $payload
     */
    private function fromPayload(array $payload): TseGatewayException
    {
        $status = $payload['status'] ?? null;
        $status = is_int($status) && $status > 0 ? $status : null;
        $error = (string) ($payload['error'] ?? 'NODE_FAILED');
        $detail = trim((string) ($payload['message'] ?? ''));

        if ($status !== null) {
            $message = sprintf('TSE DivulgaCandContas respondeu %d', $status);

            if ($detail !== '') {
                $message .= ' — '.$detail;
            }
        } else {
            $message = $detail !== '' ? $detail : $error;
        }

        return new TseGatewayException($error, $status, $message);
    }

    /**
     * Repetir só o que ainda pode se recuperar: 5xx e rate limit. Um 403/404
     * é definitivo para a mesma sessão — rodar o Chromium de novo mudaria
     * pouco; mas um 500 pontual do TSE é exatamente o caso do retry.
     */
    private function retryable(TseGatewayException $exception): bool
    {
        $status = $exception->status();

        if ($status !== null) {
            return $status >= 500 || $status === 429;
        }

        return $exception->errorCode() === 'NODE_TIMEOUT';
    }

    /**
     * Monta a linha de comando, respeitando um eventual wrapper.
     *
     * No Render não há display: `wrapper` recebe `xvfb-run --auto-servernum`
     * (o entrypoint.sh já o define quando existe xvfb-run). Em máquina com
     * X11, ou em Windows, `wrapper` fica vazio e o Chromium abre direto.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    private function command(array $config): array
    {
        $command = [];

        $wrapper = trim((string) ($config['wrapper'] ?? ''));

        if ($wrapper !== '') {
            foreach (preg_split('/\s+/', $wrapper) ?: [] as $part) {
                $command[] = $part;
            }
        }

        $command[] = (string) ($config['node'] ?? 'node');
        // `script` é caminho relativo à raiz do projeto: path absoluto em
        // config faria o arquivo valer só na máquina que o gerou.
        $command[] = base_path((string) ($config['script'] ?? 'scripts/tse/fetch-candidates.js'));

        return $command;
    }

    /**
     * Input comum a toda chamada: o script visita `startUrl` antes de buscar
     * qualquer dado, que é o gesto que faz o TSE reconhecer o navegador.
     *
     * @return array<string, mixed>
     */
    public function browserOptions(): array
    {
        $config = config('services.tse.divulgacandcontas');

        return [
            'startUrl' => rtrim((string) ($config['browser_url'] ?? ''), '/').'/',
            'headless' => (bool) ($config['headless'] ?? false),
            'navigationTimeout' => (int) ($config['navigation_timeout'] ?? 60),
            'fetchTimeout' => (int) ($config['timeout'] ?? 30),
        ];
    }
}
