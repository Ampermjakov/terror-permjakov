// Получает HTML со списком террористов/экстремистов через headless-браузер —
// fedsfm.ru блокирует обычные HTTP-клиенты так же, как cbr.ru (см. cbr.permjakov.ru/fetch-cbr.js).
// stdout: печатает статус, тело пишет в файл-аргумент. exit 0/1.

const { chromium } = require('playwright');
const fs = require('fs');

const URL = process.argv[2];
const OUT_FILE = process.argv[3];
const TIMEOUT_MS = 30000;
const HARD_DEADLINE_MS = 180000;

if (!URL || !OUT_FILE) {
    console.error('Usage: node fetch-terror.js <url> <output-file>');
    process.exit(1);
}

let browser;

const watchdog = setTimeout(() => {
    console.error('fetch-terror error: hard deadline exceeded (' + HARD_DEADLINE_MS + 'ms)');
    try { browser?.process()?.kill('SIGKILL'); } catch (_) {}
    process.exit(1);
}, HARD_DEADLINE_MS);

(async () => {
    try {
        browser = await chromium.launch({ headless: true });
        const context = await browser.newContext({
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            locale: 'ru-RU',
            ignoreHTTPSErrors: true, // старый Debian на сервере — устаревший набор корневых сертификатов
        });
        const page = await context.newPage();

        const resp = await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: TIMEOUT_MS });
        if (!resp) throw new Error('no response');
        if (!resp.ok()) throw new Error('http status ' + resp.status());

        const text = await resp.text();
        if (!text.includes('terrorist-list')) {
            throw new Error('response does not look like the expected page (no terrorist-list marker)');
        }

        fs.writeFileSync(OUT_FILE, text);
        clearTimeout(watchdog);
        await browser.close();
        process.exit(0);
    } catch (e) {
        clearTimeout(watchdog);
        console.error('fetch-terror error: ' + e.message);
        if (browser) await browser.close();
        process.exit(1);
    }
})();
