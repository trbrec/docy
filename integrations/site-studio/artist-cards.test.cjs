const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
 const browser = await chromium.launch({headless:true});
 const page = await browser.newPage();
 const live = process.argv.includes('--live');
 const shots = process.env.TRB_ROSTER_SCREENSHOTS;
 if (shots) fs.mkdirSync(shots,{recursive:true});
 try {
  for (const width of [320,390,768,1024,1366,1920]) {
   await page.setViewportSize({width,height:1000});
   if(live)await page.goto('https://new1.trbrec.com/artisti/',{waitUntil:'networkidle'});
   else await page.setContent(fs.readFileSync('/tmp/trb-roster-fixture.html','utf8'));
   if(live&&!await page.locator('.trb-directory-roster').count()) { if(shots){await page.screenshot({path:path.join(shots,`unavailable-${width}.png`),fullPage:true});fs.writeFileSync(path.join(shots,`unavailable-${width}.html`),await page.content());} throw new Error(`Published roster unavailable: ${await page.title()} at ${page.url()}`); }
   const result=await page.evaluate(()=>{
    const grid=document.querySelector('.trb-directory-roster');
    if(!grid)throw Error('New roster renderer missing');
    const cards=[...grid.querySelectorAll(':scope>.trb-artist-profile')];
    return {overflow:document.documentElement.scrollWidth>innerWidth+1,columns:getComputedStyle(grid).gridTemplateColumns.split(' ').length,
     heights:cards.map(el=>el.getBoundingClientRect().height),
     noBios:cards.every(el=>!el.querySelector('.trb-artist-bio-preview')),
     links:cards.map(el=>el.querySelector('a').getAttribute('href')),
     images:cards.map(el=>{const r=el.querySelector('.trb-artist-identity').getBoundingClientRect();return {width:r.width,height:r.height};}),
     count:cards.length};
   });
   assert.ok(result.count>0);
   assert.equal(result.overflow,false,`No horizontal overflow at ${width}px`);
   assert.equal(result.columns,width>=1200?4:width>=960?3:2,`Responsive columns at ${width}px`);
   assert.ok(Math.max(...result.heights)-Math.min(...result.heights)<2,`Closed cards have equal heights at ${width}px: ${result.heights}`);
   assert.equal(result.noBios,true);
   assert.ok(result.links.every(url=>/\/artisti\/[^/]+\/$/.test(url)));
   assert.ok(result.images.every(r=>Math.abs(r.width/r.height-.8)<.02));
   await page.locator('.trb-artist-tile-link').first().focus();
   assert.ok(await page.locator('.trb-artist-tile-link').first().evaluate(e=>e===document.activeElement));
   if(shots)await page.screenshot({path:path.join(shots,`roster-${width}.png`),fullPage:true});
   console.log(`PASS: ${live?'live':'fixture'} ${width}px, ${result.columns} columns, uniform cards, accessible biography/social/index disclosures`);
  }
 } finally {await browser.close();}
})().catch(err=>{console.error(err);process.exit(1);});
