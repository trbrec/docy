const {chromium}=require('playwright');
const fs=require('fs');
const path=require('path');
const assert=require('assert/strict');
(async()=>{
 const folder=process.argv[2];
 const out=process.argv[3];
 fs.mkdirSync(out,{recursive:true});
 const browser=await chromium.launch({headless:true});
 const results=[];
 const failures=[];
 try {
  for(const name of fs.readdirSync(folder).filter(n=>n.endsWith('.html'))) {
   for(const width of [320,390,768,1024,1366,1920]) {
    const page=await browser.newPage({viewport:{width,height:960}});
    // Exported public page markup only: keep this layout check completely offline.
    await page.route('http**',r=>r.abort());
    await page.goto('file://'+path.resolve(folder,name),{waitUntil:'load'});
    const data=await page.evaluate(()=>({
     title:document.title,
     h1:document.querySelectorAll('main h1').length,
     overflow:document.documentElement.scrollWidth>innerWidth+1,
     overflowElements:[...document.querySelectorAll('main *')].filter(e=>e.getBoundingClientRect().right>innerWidth+2&&getComputedStyle(e).position!=='absolute').slice(0,8).map(e=>e.tagName+'.'+e.className),
     cards:[...document.querySelectorAll('.trb-directory-roster>.trb-artist-profile')].map(e=>e.getBoundingClientRect().height),
     columns:document.querySelector('.trb-directory-roster')?getComputedStyle(document.querySelector('.trb-directory-roster')).gridTemplateColumns.split(' ').length:0,
     imageFailures:[...document.querySelectorAll('main img')].filter(i=>i.src.startsWith('data:')&&i.naturalWidth===0).length
    }));
    const item={page:name,width,...data};results.push(item);
    try {
     assert.equal(data.h1,1,'Exactly one page heading');
     assert.equal(data.overflow,false,'Horizontal overflow: '+data.overflowElements.join(', '));
     assert.equal(data.imageFailures,0,'Public images decode');
     if(name==='artisti.html') {
      assert.equal(data.cards.length,14,'All fourteen artists');
      assert.ok(Math.max(...data.cards)-Math.min(...data.cards)<2,'Equal closed card heights');
      assert.equal(data.columns,width>=1200?4:width>=960?3:width>=600?2:1,'Responsive columns');
      const summaries=page.locator('.trb-artist-biography>summary');
      for(let i=0;i<await summaries.count();i++) {
       await summaries.nth(i).focus();await summaries.nth(i).press('Space');
       assert.equal(await summaries.nth(i).evaluate(e=>e.parentElement.open),true,'Keyboard opens biography');
       await summaries.nth(i).press('Space');
      }
     }
    } catch(e) {failures.push({page:name,width,error:e.message});}
    if([390,768,1366].includes(width)) await page.screenshot({path:path.join(out,name.replace('.html','')+'-'+width+'.png'),fullPage:true});
    await page.close();
   }
  }
 } finally {await browser.close();}
 fs.writeFileSync(path.join(out,'results.json'),JSON.stringify({mode:'anonymous server-rendered snapshot, offline layout QA',results,failures},null,2));
 for(const x of results) console.log(`${x.page} ${x.width}: h1=${x.h1} overflow=${x.overflow} columns=${x.columns}`);
 for(const x of failures) console.error(JSON.stringify(x));
 if(failures.length) process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1;});
