const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

(async () => {
  const browser = await chromium.launch({headless: true, channel: process.env.TRB_QA_BROWSER_CHANNEL || undefined});
  const wpRoot = process.env.TRB_WP_TEST_ROOT || path.resolve('..', '.audit-runtime/wp/wordpress');
  try {
    for (const script of ['main.js', 'main.min.js']) {
      const page = await browser.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', route => route.abort());
      await page.setContent(`<div class="csf-options" data-unique="fixture"><form id="csf-form">
        <div class="csf-header"><input type="button" class="csf-save csf-save-ajax" value="Salva" data-save="Salvataggio…"></div>
        <div class="csf-content"><input name="fixture[name]" aria-label="Nome" value="Bozza conservata"></div>
        <div class="csf-form-success csf-form-show notice notice-error inline">Errore precedente</div>
        <div class="csf-form-warning" style="display:none">Modifiche da salvare</div>
      </form></div>`);
      await page.addScriptTag({path: path.join(wpRoot, 'wp-includes/js/jquery/jquery.min.js')});
      await page.evaluate(() => {
        window.csf_vars = {i18n: {}, color_palette: []};
        window.requests = [];
        window.wp = {ajax: {post(action, data) {
          const deferred = jQuery.Deferred();
          requests.push({action, data, deferred});
          return deferred.promise();
        }}};
      });
      await page.addScriptTag({path: 'inc/csf/assets/js/plugins.js'});
      await page.addScriptTag({path: `inc/csf/assets/js/${script}`});
      await page.evaluate(() => new Promise(resolve => jQuery(resolve)));
      await page.waitForTimeout(1200);
      assert(await page.getByText('Errore precedente', {exact: true}).isVisible(), `${script}: a failed synchronous save must remain visible`);
      const button = page.getByRole('button', {name: 'Salva', exact: true});
      await button.click();
      assert(await page.locator('.csf-save').isDisabled());
      await page.evaluate(() => { requests[0].deferred.reject({error: 'Salvataggio fallito <script>errore</script>'}); });
      assert(await button.isEnabled(), `${script}: a failure must allow retry`);
      assert.equal(await page.getByLabel('Nome').inputValue(), 'Bozza conservata');
      assert.equal(await page.locator('.csf-form-success script').count(), 0);
      assert.match(await page.locator('.csf-form-success').textContent(), /Salvataggio fallito <script>/);
      assert.equal(await page.locator('.csf-options.csf-saving').count(), 0);
      await button.click();
      assert.equal(await page.evaluate(() => requests.length), 2);
      await page.evaluate(() => { requests[1].deferred.resolve({errors: {}, notice: 'Impostazioni salvate <strong>verificate</strong>'}); });
      assert(await button.isEnabled());
      assert.equal(await page.locator('.csf-form-success.notice-error').count(), 0);
      assert.equal(await page.locator('.csf-form-success strong').count(), 0);
      assert.equal(await page.getByLabel('Nome').inputValue(), 'Bozza conservata');
      await button.click();
      await page.evaluate(() => { requests[2].deferred.reject(); });
      assert(await button.isEnabled());
      assert.equal(await page.locator('.csf-form-success').textContent(), 'Impossibile salvare le impostazioni. Riprova.');
      await page.waitForTimeout(1200);
      assert(await page.locator('.csf-form-success').isVisible(), `${script}: a previous success animation must not hide a later error`);
      await button.click();
      await page.evaluate(() => { requests[3].deferred.resolve({unexpected: 'invalid receipt'}); });
      assert(await button.isEnabled());
      assert.equal(await page.locator('.csf-form-success').textContent(), 'Impossibile salvare le impostazioni. Riprova.');
      assert.deepEqual(errors, [], `${script}: unexpected browser errors`);
      await page.close();
    }
    console.log('Theme settings UI: source and minified scripts preserve errors/drafts, restore controls, retry saves and escape notices.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
