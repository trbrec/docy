const {chromium}=require('playwright');
const path=require('path');
const assert=require('assert/strict');
const image='data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="green"/></svg>');
const second=image+'#second',third=image+'#third';
const entity={kind:'artist',id:45,name:'Arkell',bio:'Primo paragrafo.\n\nSecondo paragrafo.',links:[{label:'Spotify',url:'https://example.test/original'}],image_id:10,image,gallery_ids:[10,20],gallery:[{id:10,url:image},{id:20,url:second}],url:'https://editor.test/artisti/arkell/',position_x:50,position:27,brightness:112,version:'original',manual:false};
const manifest={pages:[{id:1,title:'Alberto',url:'https://editor.test/artisti/alberto/?trb-edit=1'},{id:22,title:'Arkell',url:'https://editor.test/artisti/arkell/?trb-edit=1'}],sources:[],entities:[entity],forms:[],history:[]};
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  for(const empty of [false,true]){
   const page=await browser.newPage();const writes=[];
   const e=empty?{...entity,image_id:0,image:'',gallery_ids:[],gallery:[],links:[]}:entity;
   await page.route('https://editor.test/api/**',async route=>{
    if(route.request().method()==='POST')writes.push(route.request().postDataJSON());
    await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(route.request().method()==='GET'?{...manifest,entities:[e]}:{ok:true})});
   });
   await page.setContent(`<main><article class="trb-artist-page" data-trb-studio-entity="artist:45"><header class="trb-artist-page-heading"><h1>Arkell</h1></header>${empty?'':`<section class="trb-artist-gallery"><a><img class="trb-directory-image" src="${image}"></a><a><img class="trb-directory-image" src="${second}"></a></section>`}<div class="trb-artist-page-story"><section><div class="trb-artist-bio-full"><p>Primo paragrafo.</p><p>Secondo paragrafo.</p></div></section>${empty?'':'<aside class="trb-artist-page-links"><nav><a href="https://example.test/original">Spotify</a></nav></aside>'}</div></article></main>`);
   await page.evaluate(({image,third})=>{
    window.TRBStudio={postId:'22',endpoint:'https://editor.test/api/',native:'https://editor.test/admin/',exit:'https://editor.test/artisti/arkell/'};
    window.wp={media:()=>{let selected;return{on:(event,fn)=>{selected=fn;},state:()=>({get:()=>({first:()=>({toJSON:()=>({id:30,url:third,mime:'image/jpeg'})})})}),open:()=>selected()};}};
   },{image,third});
   await page.addScriptTag({path:path.join(__dirname,'trb-site-studio/editor.js')});
   await page.getByRole('status').filter({hasText:'Pronto'}).waitFor();
   assert.equal(await page.getByLabel('Pagina del sito').inputValue(),manifest.pages[1].url,'Localized string post ID selects current page');
   assert.equal(await page.getByLabel('Luminosità').inputValue(),'112','Brightness above native range default is retained');
   await page.getByLabel('Nome pubblico dell’artista').fill('Arkell aggiornato');
   assert.equal(await page.locator('main h1').innerText(),'Arkell aggiornato');
   await page.getByLabel('Biografia completa').fill('Prima parte.\n\nSeconda parte.');
   assert.equal(await page.locator('.trb-artist-bio-full p').count(),2,'Paragraph preview is retained');
   await page.getByLabel('Collegamenti: nome | indirizzo, uno per riga').fill('Instagram | https://example.test/new');
   assert.equal(await page.locator('.trb-artist-page-links nav a').getAttribute('href'),'https://example.test/new','Personal page links preview immediately');
   if(!empty){
    await page.getByRole('button',{name:'Rimuovi foto 1',exact:true}).click();
    assert.equal(await page.locator('.trb-artist-gallery img').count(),1);
    assert.equal(await page.locator('.trb-artist-gallery img').first().getAttribute('src'),second,'Removed primary image does not reappear in gallery preview');
   }
   await page.getByRole('button',{name:'Aggiungi fotografia',exact:true}).click();
   assert.equal(await page.locator('.trb-artist-gallery img').count(),empty?1:2,'Gallery previews on an empty profile as well');
   assert.equal(await page.locator('.trb-artist-gallery img').last().getAttribute('src'),third);
   if(!empty){
    await page.getByRole('button',{name:'Scegli / carica immagine',exact:true}).first().click();
    assert.equal(await page.locator('.trb-artist-gallery img').first().getAttribute('src'),third,'Primary photo preview is reordered consistently');
   }
   await page.getByRole('button',{name:'Salva e pubblica',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('#trb-studio-panel')===null);
   assert.equal(writes.length,1,'One save request');
   assert.equal(writes[0].entities[0].patch.name,'Arkell aggiornato');
   assert.deepEqual(writes[0].entities[0].patch.gallery_ids,empty?[30]:[30,20]);
   assert.equal(writes[0].entities[0].version,'original','Concurrency version retained');
   await page.close();
  }
  console.log('Visual editor: current page, brightness, biography, social links, gallery and save payload passed.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
