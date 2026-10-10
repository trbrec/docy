const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');

(async () => {
  const browser = await chromium.launch({headless: true, channel: process.env.TRB_QA_BROWSER_CHANNEL || undefined});
  const wpRoot = process.env.TRB_WP_TEST_ROOT || path.resolve('..', '.audit-runtime/wp/wordpress');
  try {
    for (const storageBlocked of [false, true]) {
      const page = await browser.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', route => route.abort());
      await page.setContent(`<div id="docy-article-rating" data-post-id="123">
        <div class="docy-rating-stars" role="radiogroup">${[1,2,3,4,5].map(n => `<button class="docy-rating-star" data-rating="${n}" role="radio" aria-label="Vota ${n} su 5">${n}</button>`).join('')}</div>
      </div>`);
      await page.addScriptTag({path: path.join(wpRoot, 'wp-includes/js/jquery/jquery.min.js')});
      await page.evaluate(blocked => {
        window.savedVotes = {};
        Object.defineProperty(window, 'localStorage', {value: {
          getItem(key) { if (blocked) throw new DOMException('Storage disabled', 'SecurityError'); return savedVotes[key] || null; },
          setItem(key, value) { if (blocked) throw new DOMException('Storage disabled', 'SecurityError'); savedVotes[key] = value; }
        }});
        window.docy_rating_params = {ajax_url: '/blocked-fixture', nonce: 'fixture', thank_you_text: 'Grazie <img src=x onerror=alert(1)> & a presto'};
        window.requests = [];
        jQuery.ajax = options => { requests.push(options); };
      }, storageBlocked);
      await page.addScriptTag({path: 'assets/js/article-rating.js'});
      await page.evaluate(() => new Promise(resolve => jQuery(resolve)));
      await page.getByRole('radio', {name: 'Vota 1 su 5'}).focus();
      await page.keyboard.press('End');
      assert(await page.getByRole('radio', {name: 'Vota 5 su 5'}).evaluate(node => node === document.activeElement));
      await page.keyboard.press('Enter');
      assert.equal(await page.evaluate(() => requests.length), 1);
      assert.equal(await page.locator('button:disabled').count(), 5);
      await page.evaluate(() => { jQuery('.docy-rating-star').first().trigger('click'); });
      assert.equal(await page.evaluate(() => requests.length), 1, 'A pending request must not accept another vote');
      assert.equal(await page.locator('#docy-rating-live-region').textContent(), 'Invio del voto…');
      await page.evaluate(() => { requests[0].error(); requests[0].complete(); });
      assert.equal(await page.locator('button:disabled').count(), 0);
      assert.equal(await page.locator('#docy-rating-live-region').textContent(), 'Impossibile salvare il voto. Riprova.');
      assert.equal(await page.locator('#docy-article-rating').getAttribute('aria-busy'), null);
      await page.getByRole('radio', {name: 'Vota 3 su 5'}).click();
      await page.evaluate(() => { requests[1].success({success: true, data: {message: 'incomplete receipt'}}); requests[1].complete(); });
      assert.equal(await page.locator('.docy-rating-thankyou').count(), 0, 'An incomplete response must not confirm the vote');
      assert.deepEqual(await page.evaluate(() => savedVotes), {});
      assert.equal(await page.locator('button:disabled').count(), 0);
      await page.getByRole('radio', {name: 'Vota 4 su 5'}).click();
      await page.evaluate(() => {
        window.previousStar = jQuery('.docy-rating-star').first();
        requests[2].success({success: true, data: {votes: 3, avg_rating: 4}});
        requests[2].complete();
      });
      assert.equal(await page.locator('.docy-rating-thankyou-text').textContent(), 'Grazie <img src=x onerror=alert(1)> & a presto');
      assert.equal(await page.locator('.docy-rating-thankyou img').count(), 0);
      assert.equal(await page.locator('#docy-rating-live-region').textContent(), 'Grazie <img src=x onerror=alert(1)> & a presto');
      await page.evaluate(() => { previousStar.trigger('click'); });
      assert.equal(await page.evaluate(() => requests.length), 3, 'An accepted vote must remain accepted even if localStorage is blocked');
      assert.deepEqual(await page.evaluate(() => savedVotes), storageBlocked ? {} : {docy_article_rated_123: 'true'});
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('Article rating UI: pending guard, keyboard controls, failed/malformed receipts, retry, blocked storage and escaped Italian text passed.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
