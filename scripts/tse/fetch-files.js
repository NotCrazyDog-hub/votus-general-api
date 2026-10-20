const { chromium } = require('playwright');

const readStdin = () => new Promise((resolve) => {
  let data = '';
  process.stdin.on('data', (c) => (data += c));
  process.stdin.on('end', () => resolve(data));
});

(async () => {
  const input = JSON.parse(await readStdin());
  const browser = await chromium.launch({ headless: !!input.headless });
  try {
    const page = await (await browser.newContext({ locale: 'pt-BR' })).newPage();
    await page.goto(input.startUrl, {
      waitUntil: 'domcontentloaded',
      timeout: (input.navigationTimeout || 60) * 1000,
    });

    const files = {};
    for (const f of input.files || []) {
      files[f.key] = await page.evaluate(async ({ url, timeoutMs }) => {
        try {
          const ctrl = new AbortController();
          const t = setTimeout(() => ctrl.abort(), timeoutMs);
          const r = await fetch(url, { signal: ctrl.signal, credentials: 'include' });
          clearTimeout(t);
          if (!r.ok) return { status: r.status };
          const blob = await r.blob();
          const base64 = await new Promise((res, rej) => {
            const fr = new FileReader();
            fr.onload = () => res(String(fr.result).split(',')[1]);
            fr.onerror = rej;
            fr.readAsDataURL(blob);
          });
          return { status: r.status, contentType: blob.type, base64 };
        } catch (e) {
          return { status: null, error: String(e) };
        }
      }, { url: f.url, timeoutMs: (input.fetchTimeout || 30) * 1000 });
    }

    process.stdout.write(JSON.stringify({ success: true, status: 200, data: { files } }));
  } catch (e) {
    process.stdout.write(JSON.stringify({ success: false, error: 'NODE_FAILED', message: String(e) }));
  } finally {
    await browser.close();
  }
})();