const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({
        headless: false,
    });

    const page = await browser.newPage();

    await page.goto('https://divulgacandcontas.tse.jus.br/divulga/', {
        waitUntil: 'domcontentloaded',
        timeout: 60000,
    });

    const result = await page.evaluate(async () => {
        const url =
            'https://divulgacandcontas.tse.jus.br/divulga/rest/v1/candidatura/listar/2026/CE/20322002026/7/candidatos';

        const response = await fetch(url, {
            credentials: 'include',
        });

        const text = await response.text();

        return {
            status: response.status,
            contentType: response.headers.get('content-type'),
            url: response.url,
            bodyStart: text.substring(0, 500),
        };
    });

    console.log(JSON.stringify(result, null, 2));

    await browser.close();
})();