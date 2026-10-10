const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

(async () => {
  const browser = await chromium.launch({headless: true, channel: process.env.TRB_QA_BROWSER_CHANNEL || undefined});
  const wpRoot = process.env.TRB_WP_TEST_ROOT || path.resolve('..', '.audit-runtime/wp/wordpress');
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => route.abort());
    await page.setContent(`<form class="cart"><input class="qty" value="2"><a href="#" class="buy_now_btn" data-product-id="123">Acquista subito</a></form>
      <form class="cart variations_form"><input name="product_id" value="456"><input name="variation_id" value="0"><input class="qty" value="3">
      <select name="attribute_size"><option value="M">M</option></select><button class="buy_now_variable">Acquista variante</button></form>`);
    await page.addScriptTag({path: path.join(wpRoot, 'wp-includes/js/jquery/jquery.min.js')});
    await page.evaluate(() => {
      window.requests = [];
      window.alerts = [];
      window.alert = text => alerts.push(text);
      window.docy_buy_now_params = {ajax_url: '/isolated-fixture', checkout_url: 'about:blank#checkout-fixture', nonce: 'fixture'};
      jQuery.ajax = options => { requests.push(options); };
    });
    await page.addScriptTag({path: 'assets/js/woocommerce.js'});
    await page.evaluate(() => new Promise(resolve => jQuery(resolve)));
    const simple = page.getByRole('link', {name: 'Acquista subito'});
    await simple.click();
    await simple.click();
    assert.equal(await page.evaluate(() => requests.length), 1, 'An anchor must not bypass the pending-request guard');
    assert.deepEqual(await page.evaluate(() => requests[0].data), {action: 'docy_buy_now_add_to_cart', product_id: 123, quantity: '2', nonce: 'fixture'});
    await page.evaluate(() => { requests[0].error({responseJSON: {success: false, data: {message: 'Carrello non disponibile. Riprova più tardi.'}}}); });
    assert.equal(await simple.getAttribute('class'), 'buy_now_btn');
    assert.equal(await page.evaluate(() => alerts.at(-1)), 'Carrello non disponibile. Riprova più tardi.');
    await simple.click();
    await page.evaluate(() => { requests[1].success(null); });
    assert.equal(await page.evaluate(() => alerts.at(-1)), 'Impossibile aggiungere il prodotto al carrello. Riprova.');
    assert(!page.url().includes('checkout-fixture'));
    await simple.click();
    await page.evaluate(() => { requests[2].success({success: true}); });
    assert(!page.url().includes('checkout-fixture'), 'Malformed success must not navigate to checkout');
    const variable = page.getByRole('button', {name: 'Acquista variante'});
    await variable.click();
    assert.equal(await page.evaluate(() => requests.length), 3);
    assert.equal(await page.evaluate(() => alerts.at(-1)), 'Seleziona le opzioni del prodotto prima di acquistare.');
    await page.locator('[name="variation_id"]').fill('789');
    await variable.click();
    await page.evaluate(() => { jQuery('.buy_now_variable').trigger('click'); });
    assert.equal(await page.evaluate(() => requests.length), 4);
    assert.deepEqual(await page.evaluate(() => requests[3].data), {action: 'docy_buy_now_add_to_cart', product_id: '456', quantity: '3', variation_id: '789', variation: {attribute_size: 'M'}, nonce: 'fixture'});
    await page.evaluate(() => { requests[3].error({}); });
    assert(await variable.isEnabled());
    assert.equal(await page.evaluate(() => alerts.at(-1)), 'Impossibile aggiungere il prodotto al carrello. Riprova.');
    await variable.click();
    await page.evaluate(() => { requests[4].success({success: true, data: {message: 'Prodotto aggiunto al carrello.'}}); });
    await page.waitForURL('about:blank#checkout-fixture');
    assert(await variable.isDisabled());
    assert.deepEqual(errors, []);
    console.log('Cart UI: simple/variable pending guards, Italian HTTP errors, malformed receipts, retry and confirmed navigation passed. No real checkout.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
