/* WordPress administrator visual editor; server validates every write. */
(async()=>{
'use strict';const c=window.TRBStudio;if(!c)return;
const panel=document.createElement('aside');panel.id='trb-studio-panel';panel.setAttribute('aria-label','Editor sito TRB');
panel.innerHTML='<header class="studio-head"><div><small>TRB SITE STUDIO</small><h2>Editor sito</h2></div><button type="button" id="studio-collapse" aria-label="Riduci pannello" aria-expanded="true">−</button></header><div id="studio-body"><p class="studio-tip">Clicca un testo, una foto o una sezione nella pagina.</p><label for="studio-page">Pagina del sito</label><select id="studio-page"></select><label for="studio-area">Area da modificare</label><select id="studio-area"></select><label for="studio-element">Elemento</label><select id="studio-element"></select><div id="studio-fields"></div><details id="studio-style"><summary>Dimensioni, spazi e colori</summary><div id="studio-style-fields"></div></details><div class="studio-actions"><button type="button" id="trb-studio-save" disabled>Salva e pubblica</button><button type="button" id="trb-studio-cancel" class="studio-secondary">Annulla modifiche</button></div><p id="trb-studio-status" role="status" aria-live="polite">Caricamento…</p><details><summary>Versioni precedenti</summary><div id="studio-history-list"></div></details><div class="studio-links"><a id="studio-native">Editor WordPress</a><a id="studio-exit">Esci dall’editor</a></div></div>';
document.body.append(panel);document.body.classList.add('trb-studio-editing');
const $=s=>panel.querySelector(s),status=$('#trb-studio-status'),save=$('#trb-studio-save'),fields=$('#studio-fields'),area=$('#studio-area'),elements=$('#studio-element'),page=$('#studio-page');
$('#studio-native').href=c.native;$('#studio-exit').href=c.exit;
let manifest,current,saving=false;const sources=new Map(),entities=new Map(),forms=new Map(),targets=new Map(),previewImages=new Map();
const dirty=()=>sources.size+entities.size+forms.size>0;
const notice=s=>{status.textContent=s;save.disabled=saving||!dirty();};
const request=async(method,body)=>{const r=await fetch(c.endpoint+c.postId,{method,credentials:'same-origin',headers:{'X-WP-Nonce':c.nonce,'Content-Type':'application/json'},body:body?JSON.stringify(body):undefined});const d=await r.json();if(!r.ok)throw Error(d.message||'Operazione non riuscita.');return d;};
const clear=()=>document.querySelectorAll('[data-trb-studio-selected]').forEach(e=>e.removeAttribute('data-trb-studio-selected'));
const input=(caption,value,fn,type='text')=>{const l=document.createElement('label'),e=document.createElement(type==='textarea'?'textarea':'input');l.textContent=caption;e.id='studio-field-'+fields.querySelectorAll('input,textarea').length;l.htmlFor=e.id;if(type!=='textarea')e.type=type;e.value=value??'';fields.append(l,e);e.addEventListener('input',()=>fn(e.value));return e;};
const button=(caption,fn)=>{const b=document.createElement('button');b.type='button';b.className='studio-secondary';b.textContent=caption;fields.append(b);b.addEventListener('click',fn);return b;};
const option=(v,s)=>{const o=document.createElement('option');o.value=v;o.textContent=s;return o;};
const sourcePatch=(t,p)=>{const g=sources.get(t.source.id)||{id:t.source.id,version:t.source.version,changes:new Map()},old=g.changes.get(t.item.key)||{key:t.item.key};g.changes.set(t.item.key,{...old,...p,spacing:{...old.spacing,...p.spacing}});sources.set(g.id,g);notice('Anteprima aggiornata · modifiche da salvare.');};
const choosePhoto=fn=>{if(!window.wp?.media){notice('Ricarica la pagina per aprire la libreria media.');return;}const frame=wp.media({title:'Scegli o carica una foto',button:{text:'Usa questa immagine'},library:{type:'image'},multiple:false});frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();if(!['image/jpeg','image/png','image/webp'].includes(a.mime)){notice('Scegli JPG, PNG o WebP.');return;}fn(a);});frame.open();};
const photo=(url,fn)=>{if(url){const img=document.createElement('img');img.className='studio-image-preview';img.src=url;img.alt='Immagine selezionata';fields.append(img);}button('Scegli / carica immagine',()=>choosePhoto(fn));};
const styleControls=t=>{
const wrap=$('#studio-style-fields');wrap.replaceChildren();$('#studio-style').hidden=!t;if(!t)return;const css=t.e?getComputedStyle(t.e):null;
const update=(prop,v)=>{const n=typeof v==='number'?v+'px':v;t.e?.style.setProperty(prop,n,'important');sourcePatch(t,{spacing:{[prop]:v}});};
for(const [prop,caption,min,max] of [['font-size','Dimensione testo',10,96],['margin-top','Margine sopra',0,160],['margin-bottom','Margine sotto',0,160],['padding-top','Spazio interno sopra',0,160],['padding-bottom','Spazio interno sotto',0,160],['padding-left','Spazio a sinistra',0,96],['padding-right','Spazio a destra',0,96],['max-width','Larghezza massima',160,1920],['border-radius','Angoli',0,64]]){
if(prop==='font-size'&&t.item.kind!=='text')continue;const l=document.createElement('label'),e=document.createElement('input');l.textContent=caption+' (px)';e.type='number';e.min=min;e.max=max;e.value=css?parseInt(css.getPropertyValue(prop))||'':'';l.append(e);wrap.append(l);e.addEventListener('input',()=>{if(e.value!==''&&e.checkValidity())update(prop,Number(e.value));});}
for(const [prop,caption] of [['color','Colore testo'],['background-color','Colore sfondo']]){const l=document.createElement('label'),e=document.createElement('input');l.textContent=caption;e.type='color';const rgb=css?.getPropertyValue(prop).match(/\d+/g);e.value=rgb?.length>=3?'#'+rgb.slice(0,3).map(v=>Number(v).toString(16).padStart(2,'0')).join(''):'#173328';l.append(e);wrap.append(l);e.addEventListener('input',()=>update(prop,e.value));}
};
const selectSource=t=>{
clear();t.e?.setAttribute('data-trb-studio-selected','1');fields.replaceChildren();const p=sources.get(t.source.id)?.changes.get(t.item.key)||{};
if(t.item.kind==='text'){const l=document.createElement('label');l.textContent='Testo';l.htmlFor='studio-rich-text';fields.append(l);const tools=document.createElement('div');tools.className='studio-format';const rich=document.createElement('div');rich.id='studio-rich-text';rich.contentEditable='true';rich.setAttribute('role','textbox');rich.setAttribute('aria-label','Testo');rich.setAttribute('aria-multiline','true');rich.innerHTML=p.html??t.item.html;
for(const [name,cmd] of [['Grassetto','bold'],['Corsivo','italic']]){const b=document.createElement('button');b.type='button';b.className='studio-secondary';b.textContent=name;b.addEventListener('mousedown',e=>e.preventDefault());b.addEventListener('click',()=>{rich.focus();document.execCommand(cmd);rich.dispatchEvent(new Event('input'));});tools.append(b);}fields.append(tools,rich);rich.addEventListener('input',()=>{if(t.e)t.e.innerHTML=rich.innerHTML;sourcePatch(t,{html:rich.innerHTML});});}
if(t.item.tag==='a')input('Destinazione del collegamento',p.href??t.item.attrs.href,v=>{t.e?.setAttribute('href',v);sourcePatch(t,{href:v});});
if(t.item.kind==='image'){
photo(previewImages.get(t.item.key)||t.e?.currentSrc||t.item.attrs.src,a=>{previewImages.set(t.item.key,a.url);if(t.e){t.e.src=a.url;t.e.removeAttribute('srcset');t.e.removeAttribute('sizes');}sourcePatch(t,{image_id:a.id});selectSource(t);});
input('Descrizione della foto',p.alt??t.item.attrs.alt,v=>{if(t.e)t.e.alt=v;sourcePatch(t,{alt:v});});
const pos=input('Inquadratura verticale',p.spacing?.['object-position']??(parseInt(t.e?getComputedStyle(t.e).objectPosition.split(' ')[1]:50)||50),v=>{t.e?.style.setProperty('object-position','center '+v+'%','important');sourcePatch(t,{spacing:{'object-position':Number(v)}});},'range');pos.min=0;pos.max=100;
const light=input('Luminosità',p.spacing?.brightness??100,v=>{t.e?.style.setProperty('filter','brightness('+v/100+')','important');sourcePatch(t,{spacing:{brightness:Number(v)}});},'range');light.min=90;light.max=125;
}
const parent=t.e?.parentElement?.closest('[data-trb-studio-key]'),pTarget=parent&&targets.get(parent.dataset.trbStudioSource+':'+parent.dataset.trbStudioKey);if(pTarget)button('Modifica la sezione che contiene questo elemento',()=>selectInArea(pTarget));styleControls(t);notice('Elemento selezionato · anteprima immediata.');
};
const entityNode=e=>document.querySelector('[data-trb-studio-entity="'+e.kind+':'+e.id+'"]');
const previewEntity=(e,d)=>{
const node=entityNode(e);if(!node)return;const h=node.querySelector(e.kind==='artist'?'h1,h3':'h1');if(h)h.textContent=e.kind==='artist'?d.name:d.title;
let img=node.querySelector(e.kind==='artist'?'.trb-artist-identity img,.trb-artist-gallery img':'.trb-release-hero img');const url=previewImages.get(e.kind+':'+e.id)||d.image;
if(url&&!img&&e.kind==='artist'){const wrap=node.querySelector('.trb-artist-identity');if(wrap){img=document.createElement('img');img.className='trb-directory-image';wrap.replaceChildren(img);}}
if(img){if(url){img.src=url;img.removeAttribute('srcset');img.removeAttribute('sizes');}img.style.setProperty('object-position','center '+d.position+'%','important');img.style.setProperty('filter','brightness('+d.brightness/100+')','important');}
if(e.kind==='artist'){const b=node.querySelector('.trb-artist-bio-preview'),full=node.querySelector('.trb-artist-bio-full');if(b){const s=d.bio.replace(/\s+/g,' ').trim();b.textContent=Array.from(s).slice(0,239).join('')+(Array.from(s).length>239?'…':'');}if(full)full.textContent=d.bio;const links=node.querySelector('.trb-artist-socials nav');if(links){links.replaceChildren();for(const x of d.links){const a=document.createElement('a');a.textContent=x.label;a.href=x.url;links.append(a);}}}
else{const p=node.querySelector('.prose');if(p&&d.presentation!==undefined)p.textContent=d.presentation;}
};
const entityPatch=(e,p)=>{const key=e.kind+':'+e.id,g=entities.get(key)||{kind:e.kind,id:e.id,version:e.version,patch:{}};delete g.reset;g.patch={...g.patch,...p};entities.set(key,g);previewEntity(e,{...e,...g.patch});notice('Anteprima aggiornata · modifiche protette dalla sincronizzazione.');};
const selectEntity=e=>{
clear();entityNode(e)?.setAttribute('data-trb-studio-selected','1');fields.replaceChildren();styleControls(null);const d={...e,...entities.get(e.kind+':'+e.id)?.patch};const badge=document.createElement('p');badge.className='studio-badge';badge.textContent=e.manual?'Modifica manuale protetta':'Dati importati · puoi personalizzarli';fields.append(badge);
input(e.kind==='artist'?'Nome pubblico dell’artista':'Titolo della release',e.kind==='artist'?d.name:d.title,v=>entityPatch(e,{[e.kind==='artist'?'name':'title']:v}));
input(e.kind==='artist'?'Biografia completa':'Presentazione della release',e.kind==='artist'?d.bio:d.presentation,v=>entityPatch(e,{[e.kind==='artist'?'bio':'presentation']:v}),'textarea');
photo(previewImages.get(e.kind+':'+e.id)||d.image,a=>{previewImages.set(e.kind+':'+e.id,a.url);entityPatch(e,{image_id:a.id});selectEntity(e);});
if(e.kind==='artist'){
 const heading=document.createElement('h3');heading.textContent='Galleria fotografica';fields.append(heading);
 const ids=d.gallery_ids||[],known=new Map((e.gallery||[]).map(p=>[p.id,p.url]));
 const previewGallery=next=>{const gallery=entityNode(e)?.querySelector('.trb-artist-gallery');if(!gallery)return;gallery.replaceChildren();gallery.className='trb-artist-gallery trb-gallery-count-'+next.length;for(const id of next){const url=previewImages.get('photo:'+id)||known.get(id);if(!url)continue;const a=document.createElement('a'),img=document.createElement('img');a.className='trb-gallery-photo';a.href=url;img.src=url;img.alt=d.name;img.className='trb-directory-image';a.append(img);gallery.append(a);}};
 for(const [index,id] of ids.entries()){
 const url=previewImages.get('photo:'+id)||known.get(id);photo(url,a=>{previewImages.set('photo:'+a.id,a.url);const next=[...ids];next[index]=a.id;entityPatch(e,{gallery_ids:next});previewGallery(next);selectEntity(e);});
 button('Rimuovi foto '+(index+1),()=>{const next=ids.filter((_,i)=>i!==index);entityPatch(e,{gallery_ids:next});previewGallery(next);selectEntity(e);});
 }
 if(ids.length<3)button('Aggiungi fotografia',()=>choosePhoto(a=>{previewImages.set('photo:'+a.id,a.url);const next=[...new Set([...ids,a.id])];entityPatch(e,{gallery_ids:next});previewGallery(next);selectEntity(e);}));
}
const pos=input('Inquadratura verticale',d.position,v=>entityPatch(e,{position:Number(v)}),'range');pos.min=0;pos.max=100;
const light=input('Luminosità',d.brightness,v=>entityPatch(e,{brightness:Number(v)}),'range');light.min=90;light.max=125;
input('Collegamenti: nome | indirizzo, uno per riga',(d.links||[]).map(l=>l.label+' | '+l.url).join('\n'),v=>entityPatch(e,{links:v.split('\n').filter(l=>l.trim()).map(l=>{const i=l.indexOf('|');return {label:i<0?'':l.slice(0,i).trim(),url:i<0?l.trim():l.slice(i+1).trim()};})}),'textarea');
const hint=document.createElement('small');hint.textContent='Le modifiche riguardano la presentazione pubblica. Contratti e anagrafiche conservano i dati originali.';fields.append(hint);
if(e.url){const a=document.createElement('a');a.className='studio-secondary';a.href=e.url+(e.url.includes('?')?'&':'?')+'trb-edit=1';a.textContent=e.kind==='artist'?'Apri la pagina dell’artista':'Apri la pagina della release';fields.append(a);}
button('Torna ai dati automatici',()=>{if(!confirm('Rimuovere le personalizzazioni di questa scheda al prossimo salvataggio?'))return;entities.set(e.kind+':'+e.id,{kind:e.kind,id:e.id,version:e.version,reset:true});notice('Ripristino dei dati automatici pronto da salvare.');});
};
const selectForm=(f,i)=>{
clear();fields.replaceChildren();styleControls(null);const old=forms.get(f.id)?.changes.get(i.key);
input(i.label,old?.value??i.value,v=>{const g=forms.get(f.id)||{id:f.id,version:f.version,changes:new Map()};g.changes.set(i.key,{key:i.key,value:v});forms.set(f.id,g);
for(const form of document.querySelectorAll('form.fluent_form_'+f.id)){const control=Array.from(form.elements).find(e=>e.name===i.name);if(i.property==='placeholder'&&control)control.placeholder=v;else if(i.property==='label'&&control){const l=control.closest('.ff-el-group')?.querySelector('.ff-el-input--label label');if(l)for(const n of l.childNodes)if(n.nodeType===3)n.textContent=v;}else if(i.property==='help_message'&&control){const help=control.closest('.ff-el-group')?.querySelector('.ff-el-help-message');if(help)help.textContent=v;}else if(i.property==='text'){const b=form.querySelector('button[type=submit]');if(b)b.textContent=v;}}
notice('Testo del modulo aggiornato in anteprima.');},i.property==='help_message'?'textarea':'text');
const a=document.createElement('a');a.href=f.url;a.textContent='Struttura e regole del modulo';a.className='studio-secondary';fields.append(a);
};
const selectInArea=t=>{area.value='source:'+t.source.id;renderArea();elements.value=t.item.key;selectSource(t);};
const renderElement=()=>{const v=area.value;if(v.startsWith('source:')){const t=targets.get(v.slice(7)+':'+elements.value);if(t)selectSource(t);}else if(v==='artists'||v==='catalog'){const e=manifest.entities.find(e=>e.kind===(v==='artists'?'artist':'catalog')&&e.id===Number(elements.value));if(e)selectEntity(e);}else if(v.startsWith('form:')){const f=manifest.forms.find(f=>f.id===Number(v.slice(5))),i=f?.items.find(i=>i.key===elements.value);if(i)selectForm(f,i);}};
const renderArea=()=>{elements.replaceChildren();fields.replaceChildren();styleControls(null);const v=area.value;if(v.startsWith('source:')){const s=manifest.sources.find(s=>s.id===Number(v.slice(7)));for(const i of s.items)elements.append(option(i.key,(i.kind==='layout'?'Sezione · ':i.kind==='image'?'Foto · ':'')+i.label.slice(0,85)));}else if(v==='artists'||v==='catalog'){for(const e of manifest.entities.filter(e=>e.kind===(v==='artists'?'artist':'catalog')))elements.append(option(e.id,e.name));}else if(v.startsWith('form:')){const f=manifest.forms.find(f=>f.id===Number(v.slice(5)));for(const i of f.items)elements.append(option(i.key,i.label.slice(0,100)));}renderElement();};
try{
manifest=await request('GET');for(const p of manifest.pages)page.append(option(p.url,p.title));current=manifest.pages.find(p=>p.id===c.postId);if(current)page.value=current.url;
for(const s of manifest.sources){area.append(option('source:'+s.id,s.label));for(const i of s.items){const el=document.querySelector('[data-trb-studio-source="'+s.id+'"][data-trb-studio-key="'+i.key+'"]');targets.set(s.id+':'+i.key,{source:s,item:i,e:el});}}
if(manifest.entities.some(e=>e.kind==='artist'))area.append(option('artists','Schede artisti'));if(manifest.entities.some(e=>e.kind==='catalog'))area.append(option('catalog','Release e press kit'));for(const f of manifest.forms)area.append(option('form:'+f.id,'Modulo · '+f.title));
for(const h of manifest.history){const b=document.createElement('button');b.type='button';b.className='studio-secondary';b.textContent=new Date(h.at).toLocaleString('it-IT')+' · '+h.label;$('#studio-history-list').append(b);b.addEventListener('click',async()=>{if(dirty()){notice('Salva o annulla le modifiche prima del ripristino.');return;}if(!confirm('Ripristinare i contenuti precedenti a questo salvataggio?'))return;saving=true;notice('Ripristino…');panel.inert=true;try{await request('POST',{action:'undo',history_id:h.id});location.reload();}catch(e){panel.inert=false;saving=false;notice(e.message);}});}
if(!manifest.history.length)$('#studio-history-list').textContent='Disponibili dopo il primo salvataggio.';
renderArea();notice('Pronto. Clicca un elemento oppure scegli un’area.');
}catch(e){notice(e.message);return;}
page.addEventListener('change',()=>{if(dirty()&&!confirm('Scartare le modifiche non salvate e cambiare pagina?')){if(current)page.value=current.url;return;}sources.clear();entities.clear();forms.clear();location.assign(page.value);});
area.addEventListener('change',renderArea);elements.addEventListener('change',renderElement);
document.addEventListener('click',ev=>{
if(panel.contains(ev.target)||ev.target.closest('.media-modal,.media-modal-backdrop,#wpadminbar'))return;
const entity=ev.target.closest('[data-trb-studio-entity]');if(entity){ev.preventDefault();ev.stopPropagation();const [kind,id]=entity.dataset.trbStudioEntity.split(':'),e=manifest.entities.find(e=>e.kind===kind&&e.id===Number(id));if(e){area.value=kind==='artist'?'artists':'catalog';renderArea();elements.value=id;selectEntity(e);}return;}
const node=ev.target.closest('[data-trb-studio-key]');if(node){const t=targets.get(node.dataset.trbStudioSource+':'+node.dataset.trbStudioKey);if(t){ev.preventDefault();ev.stopPropagation();selectInArea(t);}return;}
const form=ev.target.closest('form');if(form){ev.preventDefault();const id=Number(form.getAttribute('data-form_id')||form.id.match(/\d+/)?.[0]),f=manifest.forms.find(f=>f.id===id);if(f){area.value='form:'+id;renderArea();const i=f.items.find(i=>i.name===ev.target.name&&i.property==='label');if(i){elements.value=i.key;selectForm(f,i);}}}
},true);
document.addEventListener('submit',ev=>{if(!panel.contains(ev.target)){ev.preventDefault();notice('Esci dall’editor prima di inviare il modulo.');}},true);
window.addEventListener('beforeunload',ev=>{if(dirty()){ev.preventDefault();ev.returnValue='';}});
$('#trb-studio-cancel').addEventListener('click',()=>{if(dirty()&&!confirm('Annullare tutte le modifiche non salvate?'))return;sources.clear();entities.clear();forms.clear();location.reload();});
$('#studio-exit').addEventListener('click',ev=>{if(dirty()&&!confirm('Scartare le modifiche non salvate e uscire?')){ev.preventDefault();return;}sources.clear();entities.clear();forms.clear();});
$('#studio-collapse').addEventListener('click',()=>{const collapsed=panel.classList.toggle('studio-collapsed');$('#studio-collapse').textContent=collapsed?'+':'−';$('#studio-collapse').setAttribute('aria-expanded',String(!collapsed));document.body.classList.toggle('studio-panel-collapsed',collapsed);});
save.addEventListener('click',async()=>{if(saving||!dirty())return;saving=true;notice('Salvataggio…');panel.inert=true;try{await request('POST',{sources:Array.from(sources.values()).map(g=>({...g,changes:Array.from(g.changes.values())})),entities:Array.from(entities.values()),forms:Array.from(forms.values()).map(g=>({...g,changes:Array.from(g.changes.values())}))});sources.clear();entities.clear();forms.clear();location.reload();}catch(e){panel.inert=false;saving=false;notice(e.message);}});
})();
