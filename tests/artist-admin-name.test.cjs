const assert = require('node:assert/strict');
const {chromium} = require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage();
  await page.setContent('<table><tbody><tr><td><span class="trb-stage-name">Edmondo Romano</span><small>Legacy</small><button type="button" class="trb-name-edit" data-user="41" data-name="" data-display="Edmondo Romano">Modifica rapida</button></td></tr></tbody></table>');
  await page.evaluate(()=>{
   window.trbAdminStageName={url:'https://example.test/ajax',nonce:'fixture'};
   window.fetch=async(url,args)=>{window.sent=Object.fromEntries(args.body);return {ok:true,json:async()=>({success:true,data:{name:window.sent.name}})}};
  });
  await page.addScriptTag({path:'assets/js/trb-admin-stage-name.js'});
  await page.locator('.trb-name-edit').click();
  await page.getByLabel('Nome d’arte').fill('Edmondo Romano e Simona Fasano');
  await page.getByRole('button',{name:'Aggiorna',exact:true}).click();
  await page.waitForFunction(()=>document.querySelector('.trb-stage-name').textContent==='Edmondo Romano e Simona Fasano');
  const result=await page.evaluate(()=>({expected:sent.expected,action:sent.action,nonce:sent.nonce,name:sent.name,formCount:document.querySelectorAll('.trb-name-inline-edit').length,focused:document.activeElement.className}));
  assert.equal(result.expected,''); assert.equal(result.formCount,0); assert.equal(result.action,'trb_artist_admin_name'); assert.equal(result.nonce,'fixture');
  assert.match(result.focused,/trb-name-edit/);
  await page.locator('.trb-name-edit').click();
  await page.getByLabel('Nome d’arte').fill('Do not save');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('.trb-name-inline-edit').count(),0);
  assert.equal(await page.locator('.trb-stage-name').textContent(),'Edmondo Romano e Simona Fasano');
  await page.evaluate(()=>{window.fetch=async()=>({ok:false,json:async()=>({success:false,data:{message:'Conflitto'}})})});
  await page.locator('.trb-name-edit').click();
  await page.getByRole('button',{name:'Aggiorna',exact:true}).click();
  await page.getByRole('status').filter({hasText:'Conflitto'}).waitFor();
  assert(await page.getByRole('button',{name:'Aggiorna',exact:true}).isEnabled());
  assert.equal(await page.locator('.trb-stage-name').textContent(),'Edmondo Romano e Simona Fasano');
  console.log('Artist quick edit: save, cancellation, focus restoration and conflict retry passed.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
