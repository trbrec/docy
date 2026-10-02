(() => {
  'use strict';
  const cfg=window.TRBOnboarding, $=id=>document.getElementById(id);
  let practice=null, busy=false, formPractice=null, transfer=null;
  const documentSlots={identity_front:"Carta d’identità — fronte",tax_front:"Tessera sanitaria o tesserino del codice fiscale — fronte"}, confirmedFiles={};
  let invite=new URLSearchParams(location.hash.slice(1)).get('invite')||'';
  if(invite)history.replaceState(null,'',location.pathname+location.search);
  const money=cents=>new Intl.NumberFormat('it-IT',{style:'currency',currency:'EUR'}).format(cents/100);
  const states={invited:'Completa i tuoi dati e carica i documenti.',identity_review:'I documenti richiedono una revisione di TRB rec. Ti aggiorneremo su questa pagina.',identity_matched:'Dati verificati. Leggi la proposta e scegli la formula.',minor_blocked:'La maggiore età non è stata confermata. Contatta TRB rec per la tua pratica.',payment_pending:'Conferma il primo versamento attraverso il circuito di pagamento.',owner_review:'Il primo passaggio è completato. TRB rec sta revisionando la tua pratica.',signature_ready:'La pratica è approvata. TRB rec sta preparando l’invio alla firma.',signature_pending:'Firma in corso. Segui le istruzioni ricevute via email da OTPService.',activation_ready:'Contratto firmato e archiviato. Puoi creare il tuo account artista.',active:'Account attivo.',cancelled:'Questa adesione è stata annullata.'};
  const freeContract=p=>p.group_code==='TRB'&&Object.values(p.plans||{}).length===1&&Object.values(p.plans)[0].kind==='free';
  const stateText=p=>freeContract(p)&&['identity_matched','owner_review'].includes(p.state)?'Dati verificati. La pratica è pronta per l’approvazione finale e l’invio alla firma.':states[p.state]||'Pratica da verificare.';
  function progress(text,percent=null){show('upload-progress',true);$('upload-progress-label').textContent=text;const bar=$('upload-progress-bar');if(percent===null)bar.removeAttribute('value');else bar.value=Math.max(0,Math.min(100,percent));$('upload-progress-value').textContent=percent===null?'Attendi senza chiudere questa pagina.':`${Math.floor(percent)}% · Attendi senza chiudere questa pagina.`;}
  window.addEventListener('beforeunload',e=>{if(transfer){e.preventDefault();e.returnValue='';}});
  function notice(text,error=false){$('notice').textContent=text;$('notice').classList.toggle('error',error);$('notice').hidden=false;}
  async function api(action,data={}){const r=await fetch(cfg.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-TRB-Onboarding-CSRF':cfg.csrf},body:JSON.stringify({...data,action})});let p;try{p=await r.json();}catch(_){throw new Error('Risposta non confermata. Aggiorna lo stato prima di ripetere l’operazione.');}if(!r.ok||p.error||p.code&&p.message)throw new Error(p.error||p.message||'Operazione non confermata.');return p;}
  async function run(fn){if(busy)return;busy=true;document.querySelectorAll('button,input[type=file]').forEach(b=>b.disabled=true);try{await fn();}catch(e){notice(e.message,true);if(transfer){$('upload-progress-label').textContent='Operazione interrotta: puoi riprovare.';$('upload-progress-value').textContent=e.message;}}finally{busy=false;transfer=null;$('save-and-continue').textContent='Salva e continua';document.querySelectorAll('button,input[type=file]').forEach(b=>b.disabled=false);}}
  function show(id,yes){$(id).hidden=!yes;}
  function render(p){practice=p;show('email-step',false);show('practice-summary',true);$('artist-title').textContent=p.artist_name||`${p.first_name} ${p.last_name}`;$('practice-status').textContent=`${p.contract_number} · ${stateText(p)}`;
    const editable=['invited','identity_review'].includes(p.state);show('details-step',editable&&!cfg.account);show('plans-step',p.state==='identity_matched'&&!freeContract(p)&&!cfg.account);show('register-step',p.state==='activation_ready'&&!cfg.account);show('signed-contract',['activation_ready','active'].includes(p.state));show('signature-audit',['activation_ready','active'].includes(p.state));
    if(editable&&formPractice!==p.id){for(const [k,v] of Object.entries(p.details?.billing||{})){const field=$('details-form').elements.namedItem(k);if(field)field.value=v;}for(const [k,v] of Object.entries({...p.details?.invoice,tax_code:p.details?.tax_code||''})){const field=$('details-form').elements.namedItem(k);if(field)field.value=v;}}
    const uploadList=$('upload-list');
    if(formPractice!==p.id){
      uploadList.replaceChildren();for(const [slot,label] of Object.entries(documentSlots)){
        delete confirmedFiles[slot];
        const div=document.createElement('div');div.className='upload-row';const labelEl=document.createElement('label');labelEl.textContent=label;
        const input=document.createElement('input');input.type='file';input.accept='.jpg,.jpeg,.png,.pdf';input.dataset.slot=slot;input.id=`document-${slot}`;labelEl.append(input);div.append(labelEl);
        const status=document.createElement('span');status.className='upload-status muted';status.id=`status-${slot}`;status.setAttribute('aria-live','polite');div.append(status);uploadList.append(div);
      }formPractice=p.id;
    }
    for(const slot of Object.keys(documentSlots)){
      $(`document-${slot}`).required=!p.files?.[slot];
      $(`status-${slot}`).textContent=p.files?.[slot]?'Documento caricato e confermato.':'Nessun documento caricato.';
    }
    show('plan-schedule-note',Object.values(p.plans||{}).some(plan=>['monthly','recurring','two_installments'].includes(plan.kind)));
    const list=$('plan-list');list.replaceChildren();for(const [key,plan] of Object.entries(freeContract(p)?{}:p.plans||{})){const label=document.createElement('label');label.className='plan';const radio=document.createElement('input');radio.type='radio';radio.name='plan_key';radio.value=key;radio.required=true;label.append(radio,document.createTextNode(plan.label));const small=document.createElement('p');small.className='muted';small.textContent=plan.kind==='recurring'?`${money(plan.amounts_cents[0])} al mese, IVA inclusa. Ogni quota copre il relativo periodo mensile.`:`${plan.amounts_cents.length===1?'Un versamento':plan.amounts_cents.length+' versamenti mensili'} da ${money(plan.amounts_cents[0])}. Totale ${money(plan.total_cents)}, IVA inclusa.${plan.discount_basis_points?' Riduzione del '+(plan.discount_basis_points/100)+'% rispetto alla quota standard.':' Importo standard, senza riduzioni.'}${plan.amounts_cents.length>1?' La prima quota è dovuta alla conferma della formula; le successive scadono ogni mese.':''}`;label.append(small);list.append(label);}
    const payable=p.selected_plan&&p.selected_plan.kind!=='free'&&!['invited','identity_review','identity_matched','minor_blocked','cancelled'].includes(p.state);show('payment-step',!!payable);$('payment-note').textContent=p.access?.reason==='overdue'?'Una quota è scaduta. I servizi riprendono dopo il versamento confermato. Accesso, documenti e assistenza restano disponibili.':p.access?.reason==='payment_review'?'Un versamento richiede verifica. Contatta TRB rec prima di un nuovo tentativo.':'Le quote confermate e le scadenze sono riportate qui sotto.';
    const target=$('installments');target.replaceChildren();const rows=p.installments||[];if(rows.length){const table=document.createElement('table'),head=document.createElement('thead'),tr=document.createElement('tr');for(const t of ['Quota','Scadenza','Da versare']){const th=document.createElement('th');th.textContent=t;tr.append(th);}head.append(tr);table.append(head);const body=document.createElement('tbody');for(const row of rows){const r=document.createElement('tr');for(const v of [row.number,row.due_date,money(Math.max(0,row.amount_cents-row.confirmed_cents))]){const td=document.createElement('td');td.textContent=v;r.append(td);}body.append(r);}table.append(body);target.append(table);}
    const unpaid=rows.find(r=>+r.confirmed_cents<+r.amount_cents),today=(()=>{const parts=new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Rome',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());const get=t=>parts.find(p=>p.type===t).value;return `${get('year')}-${get('month')}-${get('day')}`;})();show('checkout',!!payable&&p.access?.reason!=='payment_review'&&(!rows.length||unpaid&&unpaid.due_date<=today));
  }
  function validateFile(file){if(!/\.(jpg|jpeg|png|pdf)$/i.test(file.name)||file.size<1||file.size>10485760)throw new Error('Usa un’immagine o un PDF fino a 10 MB per documento.');}
  async function upload(slot,file){
    validateFile(file);
    const extension=file.name.split('.').pop().toLowerCase(),mime=extension==='pdf'?'application/pdf':extension==='png'?'image/png':'image/jpeg';
    const name=`${slot}.${extension}`;
    const grant=await api('upload',{slot,file:{name,size:file.size,mime}}),url=new URL(grant.upload_endpoint);
    if(grant.provider!=='google_drive'||url.protocol!=='https:'||url.hostname!=='www.googleapis.com'||!/^\/upload\/drive\/v3\/files\/[A-Za-z0-9_-]+$/.test(url.pathname)||!url.searchParams.has('upload_id'))throw new Error('Destinazione caricamento non valida.');
    notice(`Caricamento ${documentSlots[slot].toLowerCase()}…`);
    $(`status-${slot}`).textContent='Caricamento in corso…';
    progress('Caricamento in corso…',transfer.total?transfer.done/transfer.total*100:0);
    const uploaded=await new Promise(resolve=>{const xhr=new XMLHttpRequest();xhr.open('PUT',url.href);xhr.withCredentials=false;xhr.timeout=300000;xhr.setRequestHeader('Content-Type',mime);xhr.upload.addEventListener('progress',e=>{if(e.lengthComputable)progress('Caricamento in corso…',Math.min(99,(transfer.done+Math.min(file.size,e.loaded))/transfer.total*100));});xhr.onload=()=>resolve(xhr.status>=200&&xhr.status<300);xhr.onerror=xhr.ontimeout=xhr.onabort=()=>resolve(false);xhr.send(file);});
    progress('Conferma del documento in corso…');
    try{render(await api('uploaded',{slot}));confirmedFiles[slot]=file;transfer.done+=file.size;progress('Caricamento in corso…',Math.min(99,transfer.done/transfer.total*100));}catch(e){$(`status-${slot}`).textContent='Caricamento da riprovare. Gli altri documenti confermati restano salvati.';if(!uploaded)throw new Error('Caricamento non confermato. I dati restano salvati: premi «Salva e continua» per riprovare.');throw e;}
  }
  function openDocument(file){
    if(!file||!['application/pdf','image/jpeg','image/png','application/xml'].includes(file.mime)||!file.data||file.size<1||file.size>10485760)throw new Error('Documento non disponibile.');
    const binary=atob(file.data),bytes=Uint8Array.from(binary,c=>c.charCodeAt(0));if(bytes.length!==file.size)throw new Error('Documento incompleto.');
    const url=URL.createObjectURL(new Blob([bytes],{type:file.mime})),a=document.createElement('a');a.href=url;a.download=file.name||'documento';a.click();setTimeout(()=>URL.revokeObjectURL(url),60000);
  }
  $('send-code').addEventListener('click',()=>run(async()=>{await api('challenge',{token:invite||undefined});show('email-form',true);notice('Codice inviato all’indirizzo della proposta.');}));
  $('email-form').addEventListener('submit',e=>{e.preventDefault();run(async()=>{render(await api('verify_email',{code:e.target.elements.code.value}));notice('Email confermata.');});});
  $('details-form').addEventListener('submit',e=>{e.preventDefault();run(async()=>{
    const selected={};for(const slot of Object.keys(documentSlots)){
      const file=$(`document-${slot}`).files?.[0];if(file)validateFile(file);
      if(!file&&!practice.files?.[slot])throw new Error(`Seleziona ${documentSlots[slot].toLowerCase()}.`);selected[slot]=file;
    }
    const pending=Object.keys(documentSlots).filter(slot=>selected[slot]&&confirmedFiles[slot]!==selected[slot]);
    transfer={done:0,total:pending.reduce((total,slot)=>total+selected[slot].size,0)};
    progress('Salvataggio dei dati in corso…');$('save-and-continue').textContent='Operazione in corso…';$('upload-progress').scrollIntoView({behavior:'smooth',block:'center'});
    const values=Object.fromEntries(new FormData(e.target));
    notice('Salvataggio dei dati in corso…');
    render(await api('details',{billing:{address_1:values.address_1,city:values.city,postcode:values.postcode,country:values.country,state:values.state,phone:values.phone},tax_code:values.tax_code,invoice:{vat_number:values.vat_number,sdi_code:values.sdi_code,pec:values.pec},privacy_acknowledged:values.privacy_acknowledged==='on'}));
    for(const slot of Object.keys(documentSlots))if(selected[slot]&&confirmedFiles[slot]!==selected[slot])await upload(slot,selected[slot]);
    progress('Caricamento completato. Verifica dei dati in corso…');notice('Caricamento completato. Verifica dei dati in corso: attendi senza chiudere questa pagina.');const result=await api('identity');progress('Operazione completata.',100);render(result.practice);notice(stateText(result.practice));
  });});
  $('plans-form').addEventListener('submit',e=>{e.preventDefault();run(async()=>{const f=new FormData(e.target);render(await api('choose',{plan_key:f.get('plan_key'),proposal_read:f.get('proposal_read')==='on',proposal_sha256:practice.proposal_sha256}));notice('Formula confermata.');});});
  $('refresh').addEventListener('click',()=>run(async()=>{render(await api(cfg.account?'account':'refresh'));notice('Stato aggiornato.');}));
  $('checkout').addEventListener('click',()=>run(async()=>{const result=await api(cfg.account?'account_checkout':'checkout');if(result.practice)render(result.practice);if(result.checkout_url){const url=new URL(result.checkout_url);if(url.protocol!=='https:'||url.hostname!=='store.trbrec.com')throw new Error('Collegamento pagamento non valido.');location.assign(url.href);}else notice(result.next_due_date?`La prossima quota scade il ${result.next_due_date}.`:'Versamento confermato.');}));
  document.querySelectorAll('[data-document]').forEach(button=>button.addEventListener('click',()=>run(async()=>{const result=await api(cfg.account?'account_document':'document',{slot:button.dataset.document});openDocument(result.file);})));
  $('register-form').addEventListener('submit',e=>{e.preventDefault();run(async()=>{const f=e.target.elements;const result=await api('register',{password:f.password.value,repeat_password:f.repeat_password.value});f.password.value='';f.repeat_password.value='';if(result.registered)location.assign(result.login_url);});});
  if(!cfg.enabled){show('email-step',false);notice('Questo percorso di adesione è in preparazione. TRB rec ti informerà quando sarà disponibile.');return;}
  run(async()=>{if(cfg.account&&!cfg.logged_in){show('email-step',false);notice('Accedi al portale per vedere versamenti e documenti.');const a=document.createElement('a');a.href=cfg.login_url;a.textContent='Accedi';a.className='button';$('notice').append(document.createElement('br'),a);return;}try{render(await api(cfg.account?'account':'view'));}catch(e){if(cfg.account)throw e;const browser=await api('browser_status');if(browser.challenge_pending){show('email-form',true);notice('Inserisci il codice ricevuto via email, oppure richiedine uno nuovo.');}else if(!invite){notice('Apri il collegamento personale ricevuto con la proposta per accedere alla tua adesione.');$('send-code').hidden=true;}}});
})();
