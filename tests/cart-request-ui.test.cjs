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
      <select name="attribute_size"><option value="M">M</option></select><button class="buy_now_variable">Acquista variante</button></form>
      <div id="quantity-fixtures"><div class="product-qty"><button class="ar_top">Aumenta</button><input id="bounded" type="number" class="qty" min="1" max="4" step="1" value="4"><button class="ar_down">Diminuisci</button></div>
      <div class="product-qty"><button class="ar_top">Aumenta</button><input id="fractional" type="number" class="qty" min="0.5" max="2" step="0.5" value="1"><button class="ar_down">Diminuisci</button></div>
      <div class="product-qty"><button class="ar_top">Aumenta</button><input id="readonly" type="text" class="qty" readonly value="2"><button class="ar_down">Diminuisci</button></div>
      <div class="product-qty"><button class="ar_top">Aumenta</button><input id="disabled" type="number" class="qty" disabled value="2"><button class="ar_down">Diminuisci</button></div>
      <a class="cart_btn ajax_add_to_cart" data-quantity="7">Prodotto diverso</a></div>`);
    await page.addScriptTag({path: path.join(wpRoot, 'wp-includes/js/jquery/jquery.min.js')});
    await page.evaluate(() => {
      window.requests = [];
      window.alerts = [];
      window.alert = text => alerts.push(text);
      window.docy_buy_now_params = {ajax_url: '/isolated-fixture', checkout_url: 'about:blank#checkout-fixture', nonce: 'fixture'};
      jQuery.ajax = options => { requests.push(options); };
      window.quantityEvents = [];
      jQuery(document).on('input change', '#quantity-fixtures input', event => quantityEvents.push(event.target.id + ':' + event.type));
    });
    await page.addScriptTag({path: 'assets/js/woocommerce.js'});
    await page.evaluate(() => new Promise(resolve => jQuery(resolve)));
    const increase = id => page.locator('#' + id).locator('..').locator('.ar_top');
    const decrease = id => page.locator('#' + id).locator('..').locator('.ar_down');
    await increase('bounded').click();
    assert.equal(await page.locator('#bounded').inputValue(), '4', 'Stock maximum must be respected');
    assert.deepEqual(await page.evaluate(() => quantityEvents), [], 'Unchanged quantities must not emit updates');
    await decrease('bounded').click();
    assert.equal(await page.locator('#bounded').inputValue(), '3');
    assert.deepEqual(await page.evaluate(() => quantityEvents), ['bounded:input', 'bounded:change']);
    await page.locator('#bounded').fill('1');
    await decrease('bounded').click();
    assert.equal(await page.locator('#bounded').inputValue(), '1', 'Minimum purchase quantity must be respected');
    await increase('fractional').click();
    assert.equal(await page.locator('#fractional').inputValue(), '1.5', 'Decimal purchase steps must be respected');
    await increase('readonly').click();
    await decrease('readonly').click();
    await increase('disabled').click();
    assert.equal(await page.locator('#readonly').inputValue(), '2');
    assert.equal(await page.locator('#disabled').inputValue(), '2');
    assert.equal(await page.locator('#quantity-fixtures a').getAttribute('data-quantity'), '7', 'Another product must not inherit this quantity');
    await page.evaluate(() => {
      jQuery('#quantity-fixtures').append('<div class="product-qty"><button class="ar_top">Aumenta</button><input id="refreshed" type="number" class="qty" min="0" max="3" step="1" value="1"><button class="ar_down">Diminuisci</button></div>');
    });
    await increase('refreshed').click();
    assert.equal(await page.locator('#refreshed').inputValue(), '2', 'Cart fragment replacements must retain working controls');
    await page.locator('#refreshed').fill('0');
    await decrease('refreshed').click();
    assert.equal(await page.locator('#refreshed').inputValue(), '0', 'The cart may reach zero for removal but not go negative');
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
    console.log('Cart UI: quantity bounds, decimal steps, readonly/disabled controls, isolated products, dynamic fragments and change events; pending guards, Italian errors, receipts, retry and navigation passed. No real checkout.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
