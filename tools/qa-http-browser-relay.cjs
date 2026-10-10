/* Standard browser bootstrap, then isolated fixture requests over private SSH. */
const { spawn } = require('node:child_process');
const readline = require('node:readline');

function validate(packet) {
  if (!/^https:\/\/artist\.trbrec\.com\/(?:trb-audit-http-[a-f0-9]{24}\/index\.php|wp-login\.php)$/.test(packet.url)) throw Error('destination');
  for (const [name, value] of Object.entries(packet.headers || {})) {
    if (!['X-TRB-QA-Token', 'X-TRB-QA-Route'].includes(name) || /[\r\n]/.test(value)) throw Error('header');
  }
}

function buildOptions(packet) {
  validate(packet);
  const options = { headers: packet.headers && !Array.isArray(packet.headers) ? packet.headers : {}, maxRedirects: 0, timeout: 35000 };
  if (packet.fields !== null) {
    options.method = 'POST'; options.multipart = {};
    for (const [name, value] of Object.entries(packet.fields)) {
      if (!/^[a-zA-Z0-9_\[\]-]+$/.test(name)) throw Error('field');
      if (value && typeof value === 'object') {
        if (!/^[a-zA-Z0-9_.-]+$/.test(value.filename) || !['image/png', 'text/plain'].includes(value.mime)) throw Error('upload');
        const buffer = Buffer.from(value.base64, 'base64');
        if (buffer.length > 65536) throw Error('upload_size');
        options.multipart[name] = { name: value.filename, mimeType: value.mime, buffer };
      } else options.multipart[name] = String(value);
    }
  }
  return options;
}

async function main() {
  const { chromium } = require('playwright');
  const separator = process.argv.indexOf('--');
  if (separator < 0 || !process.argv[separator + 1]) throw Error('arguments');
  let browser, child, timer, stage = 'browser_bootstrap', requests = 0, initialStatus = 0, nativeStatus = 0;
  try {
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ locale: 'it-IT' });
    const page = await context.newPage();
    const initial = await page.goto('https://artist.trbrec.com/wp-login.php', { waitUntil: 'networkidle', timeout: 45000 });
    initialStatus = initial.status();
    // Normal page JavaScript may complete an automatic provider check. Never
    // click, solve or alter any challenge, nor disable hosting protections.
    if (initial.status() !== 200) {
      await page.waitForTimeout(5000);
      const native = await context.request.get('https://artist.trbrec.com/wp-login.php', { maxRedirects: 0, timeout: 30000 });
      nativeStatus = native.status();
      if (native.status() !== 200) throw Error('browser_access_unconfirmed');
    }
    const anonymous = await browser.newContext({ locale: 'it-IT' });
    await anonymous.addCookies(await context.cookies());
    child = spawn(process.argv[separator + 1], process.argv.slice(separator + 2), { stdio: ['pipe', 'pipe', 'ignore'] });
    timer = setTimeout(() => child.kill(), 300000);
    let final;
    for await (const line of readline.createInterface({ input: child.stdout, crlfDelay: Infinity })) {
      const packet = JSON.parse(line);
      if (packet.relay !== 'isolated-http-v1') { final = packet; continue; }
      stage = 'fixture_request';
      const active = packet.authenticated ? context : anonymous;
      const options = buildOptions(packet);
      const response = await active.request.fetch(packet.url, options);
      const body = await response.body();
      if (body.length > 4 * 1024 * 1024) throw Error('response_size');
      const cookies = ['# Netscape HTTP Cookie File'];
      for (const cookie of await active.cookies(packet.url)) cookies.push([cookie.domain, cookie.domain.startsWith('.') ? 'TRUE' : 'FALSE', cookie.path, cookie.secure ? 'TRUE' : 'FALSE', Math.max(0, Math.floor(cookie.expires)), cookie.name, cookie.value].join('\t'));
      const headers = Object.entries(response.headers()).map(([name, value]) => name + ': ' + value + '\r\n').join('');
      child.stdin.write(JSON.stringify({ status: response.status(), headers, body_base64: body.toString('base64'), cookies: cookies.join('\n') + '\n' }) + '\n');
      requests++;
      await response.dispose();
    }
    stage = 'fixture_result';
    if (!final || typeof final.completed !== 'boolean') throw Error('result');
    console.log(JSON.stringify({ ...final, http_client: 'standard_chromium_browser_context' }));
    process.exitCode = final.completed ? 0 : 1;
  } catch (error) {
    if (child) child.kill();
    console.log(JSON.stringify({ completed: false, fatal: { stage, class: error.name, reason: error.message === 'browser_access_unconfirmed' ? error.message : 'client_operation_failed', initial_status: initialStatus, native_status: nativeStatus, completed_requests: requests } }));
    process.exitCode = 1;
  } finally { clearTimeout(timer); if (browser) await browser.close(); }
}
module.exports = { validate, buildOptions };
if (require.main === module) main();
