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
   const result=await page.evaluate(()=>{
    const grid=document.querySelector('.trb-directory-roster');
    if(!grid)throw Error('New roster renderer missing');
    const cards=[...grid.querySelectorAll(':scope>.trb-artist-profile')];
    return {overflow:document.documentElement.scrollWidth>innerWidth+1,columns:getComputedStyle(grid).gridTemplateColumns.split(' ').length,
     heights:cards.map(el=>el.getBoundingClientRect().height),
     previews:cards.map(el=>({height:el.querySelector('.trb-artist-bio-preview').getBoundingClientRect().height,length:[...el.querySelector('.trb-artist-bio-preview').textContent].length})),
     images:cards.map(el=>{const r=el.querySelector('.trb-artist-identity').getBoundingClientRect();return {width:r.width,height:r.height};}),
     count:cards.length};
   });
   assert.ok(result.count>0);
   assert.equal(result.overflow,false,`No horizontal overflow at ${width}px`);
   assert.equal(result.columns,width>=1400?4:width>=960?3:width>=600?2:1,`Responsive columns at ${width}px`);
   assert.ok(Math.max(...result.heights)-Math.min(...result.heights)<2,`Closed cards have equal heights at ${width}px: ${result.heights}`);
   assert.ok(result.previews.every(p=>p.length<=240));
   assert.ok(Math.max(...result.previews.map(p=>p.height))-Math.min(...result.previews.map(p=>p.height))<1);
   assert.ok(result.images.every(r=>Math.abs(r.width/r.height-1.5)<.02));
   const biography=page.locator('.trb-artist-biography').first();
   const full=biography.locator('.trb-artist-bio-full');
   const original=await full.textContent();
   assert.equal(await full.isVisible(),false);
   await biography.locator('summary').press('Enter');
   assert.equal(await full.isVisible(),true);
   assert.equal(await full.textContent(),original);
   assert.equal(await biography.locator('.trb-bio-open').isVisible(),true);
   assert.equal(await biography.locator('..').locator('.trb-artist-bio-preview').isVisible(),false);
   await biography.locator('summary').press('Enter');
   assert.equal(await full.isVisible(),false);
   assert.equal(await biography.locator('..').locator('.trb-artist-bio-preview').isVisible(),true);
   const socials=page.locator('.trb-artist-socials').first();
   if(await socials.count()){
    await socials.locator('summary').click();
    assert.equal(await socials.locator('nav').isVisible(),true);
    await socials.locator('summary').click();
   }
   const index=page.locator('.trb-roster-index');
   await index.locator('summary').click();
   assert.equal(await index.locator('nav').isVisible(),true);
   await index.locator('summary').click();
   if(shots)await page.screenshot({path:path.join(shots,`roster-${width}.png`),fullPage:true});
   console.log(`PASS: ${live?'live':'fixture'} ${width}px, ${result.columns} columns, uniform cards, accessible biography/social/index disclosures`);
  }
 } finally {await browser.close();}
})().catch(err=>{console.error(err);process.exit(1);});
