const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright');
(async () => {
  const browser = await chromium.launch({headless: true, channel: process.env.TRB_QA_BROWSER_CHANNEL || undefined});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => route.abort());
    await page.setContent(`<div id="comments"><h2 class="c_head">Commenti</h2><ul class="comment_box">
      <li id="comment-7" class="post_comment" data-comment-id="7"><div class="comment-txt">Testo precedente</div>
      <div class="comment_actions"><button class="docy-comment-edit-link">Modifica</button><textarea class="docy-comment-edit-source" hidden>Testo precedente</textarea></div></li>
      </ul></div><div class="docy-comment-response" hidden></div><form id="commentform"><textarea id="comment" name="comment"></textarea><button id="submit" type="submit">Invia</button></form>`);
    const wpRoot = process.env.TRB_WP_TEST_ROOT || path.resolve('..', '.audit-runtime/wp/wordpress');
    await page.addScriptTag({path: path.join(wpRoot, 'wp-includes/js/jquery/jquery.min.js')});
    await page.evaluate(() => {
      window.requests = [];
      window.docy_comments_params = {ajax_url: '/isolated-comment-fixture', edit_nonce: 'fixture', editing_enabled: 1, is_logged_in: 1, i18n: {save: 'Salva', cancel: 'Annulla', saving: 'Salvataggio…', posting: 'Pubblicazione…', generic_error: 'Si è verificato un errore. Riprova.', empty_comment: 'Scrivi un commento.'}};
      jQuery.ajax = options => { const deferred = jQuery.Deferred(); requests.push({options, deferred}); return deferred.promise(); };
      jQuery.fx.off = true;
    });
    await page.addScriptTag({path: 'assets/js/comments.js'});
    await page.evaluate(() => new Promise(resolve => jQuery(resolve)));
    const draft = page.locator('#comment');
    const submit = page.getByRole('button', {name: 'Invia'});
    await draft.fill('Bozza da conservare');
    await submit.click();
    await page.evaluate(() => jQuery('#commentform').trigger('submit'));
    assert.equal(await page.evaluate(() => requests.length), 1);
    assert(await draft.getAttribute('readonly') !== null);
    assert.equal(await page.evaluate(() => requests[0].options.timeout), 20000);
    await page.evaluate(() => { requests[0].deferred.resolve({success: true, data: {message: 'Ricevuta incompleta'}}); });
    assert.equal(await draft.inputValue(), 'Bozza da conservare');
    assert(await submit.isEnabled());
    assert.equal(await page.locator('.docy-comment-response').textContent(), 'Ricevuta incompleta');
    assert.equal(await page.locator('.docy-comment-response').getAttribute('role'), 'alert');
    await submit.click();
    await page.evaluate(() => { requests[1].deferred.reject({responseJSON: {data: {message: 'Impossibile pubblicare il commento. Riprova.'}}}); });
    assert.equal(await draft.inputValue(), 'Bozza da conservare');
    assert(await draft.getAttribute('readonly') === null);
    await submit.click();
    await page.evaluate(() => { requests[2].deferred.resolve({success: true, data: {comment_id: 8, parent: 0, approved: 0, comment_html: '<li id="comment-8">Bozza da conservare</li>', count: 1, count_text: 'Un commento', message: 'Commento in moderazione.'}}); });
    assert.equal(await draft.inputValue(), '');
    assert.equal(await page.locator('#comment-8').count(), 1);
    await page.getByRole('button', {name: 'Modifica'}).click();
    const field = page.locator('.docy-comment-edit-field');
    await field.fill('Nuova bozza');
    await page.getByRole('button', {name: 'Salva', exact: true}).click();
    assert(await page.getByRole('button', {name: 'Annulla'}).isDisabled());
    assert(await field.getAttribute('readonly') !== null);
    await page.evaluate(() => { requests[3].deferred.resolve({success: true, data: {comment_id: 99, source: 'Altro commento', content_html: 'Altro commento', message: 'Incompleta'}}); });
    assert.equal(await field.inputValue(), 'Nuova bozza');
    assert.equal(await page.locator('#comment-7 .comment-txt').textContent(), 'Testo precedente');
    assert(await page.getByRole('button', {name: 'Annulla'}).isEnabled());
    await page.getByRole('button', {name: 'Salva', exact: true}).click();
    await page.evaluate(() => { requests[4].deferred.reject({responseJSON: {data: {message: 'Commento non più disponibile <script>.'}}}); });
    assert.equal(await field.inputValue(), 'Nuova bozza');
    assert.equal(await page.locator('.docy-comment-response script').count(), 0);
    await page.getByRole('button', {name: 'Salva', exact: true}).click();
    await page.evaluate(() => { requests[5].deferred.resolve({success: true, data: {comment_id: 7, source: 'Nuova bozza', content_html: '<p>Nuova bozza</p>', message: 'Commento aggiornato.'}}); });
    assert.equal(await page.locator('.docy-comment-editor').count(), 0);
    assert.equal(await page.locator('#comment-7 .comment-txt').textContent(), 'Nuova bozza');
    assert.equal(await page.locator('.docy-comment-edit-source').inputValue(), 'Nuova bozza');
    assert.deepEqual(errors, []);
    console.log('Comment UI: incomplete receipts and HTTP failures retain drafts; pending controls, native jQuery retry and verified update passed. No real comments or mail.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
