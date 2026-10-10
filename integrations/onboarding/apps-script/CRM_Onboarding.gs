/** New adhesions only. Routing authenticates with the existing CRM contract secret. */
var TRBONB_MODELS={dds_pimd_49:'1uiETHRrjQXHSI7wMyuYXF8RNrBq18oa3ffDZuuk-Yiw',ddb_csae_600:'1xsUOkcDT9LjwAXR7qjrTGH9OxEsH7MqSjxEQWkwgnkc',ddb_ccad_600:'1JtCIYt7g64eusvVCQdWE_ghM292ibY71N8R3moDqrPQ',ddb_ccad_800:'1CeWuvHPA2d6fv8w_wXosDX4WmNIrWuLYydANHTOa9IA',ddb_ccad_1200:'1l_4ENK1B5tcEulWjyAs5Byd-LedlgkiH2QeAqmhYe_U',ddb_trb_ccad_2000:'1Bm-ZOIabbdjANAP8ZFeE59E1Ajo9yvT-oi7VCK4OH3Y',ddb_trb_ccad_3000:'1M25Fr0oaJu7Ld1QI_VD_zAyeAEqoSX12GPi31OL_-wI',ddb_trb_ccad_4000:'1OKkazZXljhR7n8wuxVW_tNG5zGZcTo3Mx8IUs25ti0o',ddb_trb_ccad_6000:'1nwkUgDhcvGf5D8pRLuBDCtPl6LJVNQ9elk5Dt58V3JI',trb_ccde:'11QreYLe5GW4PsMW5pNR55MFxWR9N9PAj-1BfgLNAY3Q'};
function TRBONB_sha_(value){return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,value).map(function(b){return ('0'+((b+256)%256).toString(16)).slice(-2);}).join('');}
function TRBONB_models_(){var out={};Object.keys(TRBONB_MODELS).forEach(function(k){var doc=DocumentApp.openById(TRBONB_MODELS[k]);var text=[doc.getBody(),doc.getHeader(),doc.getFooter()].filter(Boolean).map(function(s){return s.getText();}).join('\n');out[k]={id:TRBONB_MODELS[k],sha256:TRBONB_sha_(text),anchors:['#otp_firma_artista_1#','#otp_firma_artista_2#','#otp_firma_trb_1#','#otp_firma_trb_2#'].every(function(a){return text.split(a).length===2;})};});return out;}
function TRBONB_modelManifest(){console.log(JSON.stringify(TRBONB_models_()));}
function TRBONB_command_(p){
 var props=PropertiesService.getScriptProperties(),secret=props.getProperty('TRB_CRM_CONTRACT_SECRET');
 if(!secret||p.secret!==secret||p.confirm!==true)throw new Error('Richiesta adesione non autorizzata.');
 if(/^crm_onboarding_drive_(health|folder|grant|verify|read)$/.test(p.action)){if(p.action!=='crm_onboarding_drive_health'&&!/^[a-f0-9]{32}$/.test(String(p.practice_id||'')))throw new Error('Identificativo adesione non valido.');return TRBONB_driveCommand_(p);}
 if(p.action==='crm_onboarding_health')return {success:true,version:'2026.2',models:TRBONB_models_(),otp_configured:!!props.getProperty('OTP_EMAIL')&&!!props.getProperty('OTP_PASSWORD')};
 if(!/^[a-f0-9]{32}$/.test(String(p.practice_id||'')))throw new Error('Identificativo adesione non valido.');
 if(p.action==='crm_onboarding_document')return TRBONB_document_(p);
 if(!/^[a-f0-9]{64}$/.test(String(p.request_key||'')))throw new Error('Identificativo firma non valido.');
 if(p.action==='crm_onboarding_signature')return TRBONB_signature_(p);
 var key='TRBONB_SIG_'+p.request_key,receipt=JSON.parse(props.getProperty(key)||'null');
 if(!receipt||receipt.practice_id!==p.practice_id)throw new Error('Invio firma non associato alla pratica.');
 if(!receipt.dossier_id)return {success:true,status:'uncertain'};
 if(p.dossier_id&&String(p.dossier_id)!==receipt.dossier_id)throw new Error('Dossier non corrispondente.');
 var dossier=TRBONB_otpGet_('/dossiers/'+receipt.dossier_id),proof=TRBONB_proof_(dossier,receipt);
 if(p.action==='crm_onboarding_signature_status')return proof;
 if(p.action!=='crm_onboarding_signed_archive'||proof.status!=='completed')throw new Error('Firma non completata.');
 var uploads=p.uploads||{},out={success:true};
 if(uploads.signed_pdf){var signed=(dossier.documents||[]).filter(function(d){return d.label==='signed_document';});if(signed.length!==1||!/^https:\/\/(app\.|api\.)?otpservice\.io\//.test(signed[0].url||''))throw new Error('Documento firmato non disponibile.');var response=UrlFetchApp.fetch(signed[0].url,{muteHttpExceptions:true});if(response.getResponseCode()!==200)throw new Error('Download firma non confermato.');var blob=response.getBlob().setName('Contratto-'+receipt.contract_number+'-firmato.pdf');if(String(blob.getDataAsString()).slice(0,5)!=='%PDF-')throw new Error('PDF firmato non valido.');var signedSha=TRBONB_sha_(blob.getBytes());if(signed[0].file_hash&&signed[0].file_hash!==signedSha)throw new Error('Impronta firma non corrispondente.');TRBONB_upload_(blob,uploads.signed_pdf);out.signed_pdf_sha256=signedSha;}
 if(uploads.signature_audit){var audit=TRBONB_otpGet_('/dossiers/'+receipt.dossier_id+'/audit_trail',true);if(!/<AuditTrail[ >]/.test(audit))throw new Error('Prova di firma non disponibile.');var auditBlob=Utilities.newBlob(audit,'application/xml','Contratto-'+receipt.contract_number+'-prova-firma.xml');TRBONB_upload_(auditBlob,uploads.signature_audit);out.signature_audit_sha256=TRBONB_sha_(auditBlob.getBytes());}
 return out;
}
function TRBONB_activationLabels_(text){
 var legacy='AVVIA LA PROCEDURA DI ATTIVAZIONE',lines=['CLICCA QUI PER AVVIARE','LA PROCEDURA DI ATTIVAZIONE'];
 var count=function(label){return text.split(label).length-1;};
 if(count(legacy)===1&&count(lines[0])===0&&count(lines[1])===1)return [legacy];
 if(count(legacy)===0&&count(lines[0])===1&&count(lines[1])===1&&text.indexOf(lines.join('\n'))>=0)return lines;
 throw new Error('Pulsante di attivazione mancante o duplicato.');
}
function TRBONB_document_(p){
 var s=p.snapshot||{},model=TRBONB_MODELS[s.template_key];if(!model||model!==s.template_document_id||!['proposal','final'].includes(p.phase))throw new Error('Modello adesione non autorizzato.');
 var original=DocumentApp.openById(model),text=[original.getBody(),original.getHeader(),original.getFooter()].filter(Boolean).map(function(x){return x.getText();}).join('\n');if(TRBONB_sha_(text)!==s.source_sha256)throw new Error('Il modello è cambiato: verifica le condizioni prima di preparare la proposta.');var activationLabels=TRBONB_activationLabels_(text);
 var invite=String(p.invite_url||'');if(!/^https:\/\/artist\.trbrec\.com\/adesione\/#invite=[a-f0-9]{64}$/.test(invite))throw new Error('Collegamento adesione non valido.');
 var binding=TRBONB_sha_(JSON.stringify([p.practice_id,p.phase,s,p.appendix||null,p.details||null,invite])),key='TRBONB_DOC_'+p.practice_id+'_'+p.phase,props=PropertiesService.getScriptProperties(),lock=LockService.getScriptLock();lock.waitLock(30000);
 try{var old=JSON.parse(props.getProperty(key)||'null'),pdf;
  if(old){if(old.binding!==binding)throw new Error('Documento adesione già fissato.');pdf=DriveApp.getFileById(old.id);}
  else{
   var copy=p.upload&&p.upload.provider==='google_drive'?DriveApp.getFileById(model).makeCopy('Adesione '+s.contract_number+' '+p.phase,TRBONB_driveOwner_(p.upload.artist_folder_id)):DriveApp.getFileById(model).makeCopy('Adesione '+s.contract_number+' '+p.phase),privateCopy=p.upload&&p.upload.provider==='google_drive'?copy.setSharing(DriveApp.Access.PRIVATE,DriveApp.Permission.NONE):copy,doc=DocumentApp.openById(copy.getId()),sections=[doc.getBody(),doc.getHeader(),doc.getFooter()].filter(Boolean);
   var merge={'@colB@':Utilities.formatDate(new Date(),'Europe/Rome','dd/MM/yyyy'),'@colC@':s.contract_number,'@colD@':s.artist_name,'@colE@':s.first_name,'@colF@':s.last_name,'@onboarding_plan@':p.phase==='final'?String((p.appendix||{}).plan&&p.appendix.plan.label||'')+'; importi e scadenze riportati nell’allegato integrante del contratto.':'Da selezionare nel portale prima del primo versamento; il documento definitivo riporterà la formula confermata e le relative scadenze.'};
   sections.forEach(function(section){Object.keys(merge).forEach(function(k){if(!String(merge[k]||'').trim())throw new Error('Campo proposta mancante.');section.replaceText(k,String(merge[k]));});activationLabels.forEach(function(label){var hit=section.findText(label);if(hit){var t=hit.getElement().asText(),start=hit.getStartOffset(),end=hit.getEndOffsetInclusive();t.setLinkUrl(start,end,invite);t.setFontSize(start,end,11);t.setBold(start,end,true);t.setForegroundColor(start,end,'#ffffff');t.setUnderline(start,end,false);}});if(/@col[A-Za-z]+@|@upload_url@|@onboarding_plan@/.test(section.getText()))throw new Error('Il documento contiene campi non compilati.');});
   if(p.phase==='final'){
    var a=p.appendix||{},details=p.details||{},plan=a.plan||{};if(a.practice_id!==p.practice_id||!a.sha256||!plan.label||!['single','two_installments','monthly','recurring','free'].includes(plan.kind))throw new Error('Formula contrattuale non fissata.');
    var body=doc.getBody();body.appendPageBreak();body.appendParagraph('ALLEGATO ALLA PROPOSTA '+s.contract_number).setHeading(DocumentApp.ParagraphHeading.HEADING1);body.appendParagraph('Formula scelta e dati amministrativi').setHeading(DocumentApp.ParagraphHeading.HEADING2);body.appendParagraph('La formula scelta è parte integrante del presente accordo: '+plan.label+'.');
    var billing=details.billing||{};body.appendParagraph('Intestatario: '+s.first_name+' '+s.last_name+' — '+[billing.address_1,billing.postcode,billing.city,billing.state,billing.country].filter(Boolean).join(', ')+'. Codice fiscale: '+String(details.tax_code||'')+'.');
    var invoice=details.invoice||{};body.appendParagraph('Dati fiscali aggiuntivi: '+Object.keys(invoice).filter(function(k){return !!invoice[k];}).map(function(k){return k+': '+invoice[k];}).join('; '));
    if(plan.kind==='free')body.appendParagraph('Nessuna quota di attivazione prevista.');else{body.appendParagraph('Primo versamento confermato: '+a.first_payment_date+'. Importi in euro, IVA inclusa.');var rows=[['Quota','Scadenza','Importo']];(a.schedule||[]).forEach(function(r){rows.push([String(r.number),r.due_date,(r.amount_cents/100).toFixed(2).replace('.',',')+' €']);});body.appendTable(rows);if(plan.kind==='recurring')body.appendParagraph('Sono riportate le prime dodici scadenze. La quota di € 49,00 prosegue mensilmente durante il rapporto, alle condizioni previste dal contratto. L’elenco non stabilisce un limite di durata.');}
    body.appendParagraph('In mancanza della quota scaduta i servizi sono sospesi dal giorno seguente; accesso, versamenti, documenti e assistenza restano disponibili.');body.appendParagraph('Riferimento piano: '+a.sha256);
   }
   var finalText=sections.map(function(x){return x.getText();}).join('\n');['#otp_firma_artista_1#','#otp_firma_artista_2#','#otp_firma_trb_1#','#otp_firma_trb_2#'].forEach(function(anchor){if(finalText.split(anchor).length!==2)throw new Error('Punto firma mancante o duplicato.');});
   doc.saveAndClose();pdf=DriveApp.createFile(copy.getAs('application/pdf').setName('Contratto-'+s.contract_number+'-'+p.phase+'.pdf'));if(p.upload&&p.upload.provider==='google_drive'){pdf.setSharing(DriveApp.Access.PRIVATE,DriveApp.Permission.NONE);pdf.moveTo(TRBONB_driveOwner_(p.upload.artist_folder_id));}old={id:pdf.getId(),binding:binding};props.setProperty(key,JSON.stringify(old));
  }
  var blob=pdf.getBlob();if(p.metadata_only!==true)TRBONB_upload_(blob,p.upload);return {success:true,drive_pdf_id:pdf.getId(),sha256:TRBONB_sha_(blob.getBytes())};
 }finally{lock.releaseLock();}
}
function TRBONB_upload_(blob,grant){if(grant&&grant.provider==='google_drive')return TRBONB_driveUpload_(blob,grant);if(!grant||!/^https:\/\/(api|eapi)\.pcloud\.com\/uploadtolink$/.test(grant.upload_endpoint||'')||!grant.code||blob.getBytes().length>10485760)throw new Error('Archivio destinazione non valido.');var props=PropertiesService.getScriptProperties(),key='TRBONB_UPLOAD_'+TRBONB_sha_(grant.code),sha=TRBONB_sha_(blob.getBytes()),old=props.getProperty(key);if(old){if(old!==sha)throw new Error('Documento remoto discordante.');return;}var r=UrlFetchApp.fetch(grant.upload_endpoint,{method:'post',payload:{code:grant.code,nopartial:'1',file:blob},muteHttpExceptions:true});var data;try{data=JSON.parse(r.getContentText());}catch(e){throw new Error('Archivio remoto non confermato.');}if(r.getResponseCode()!==200||data.result!==0)throw new Error('Archivio remoto non confermato.');props.setProperty(key,sha);}
/** OTP's public /countries catalog, all six pages checked 2026-10-10. */
function TRBONB_signaturePhone_(value){
 if(typeof value!=='string')throw new Error('Inserisci un numero SMS con prefisso internazionale valido.');
 var phone=value.replace(/[\s.()\-]+/g,'').replace(/^00/,'+');
 if(/^3\d{9}$/.test(phone))phone='+39'+phone;
 if(!/^\+[1-9]\d{6,14}$/.test(phone)||(phone.indexOf('+39')===0&&!/^\+393\d{9}$/.test(phone)))throw new Error('Inserisci un cellulare SMS con prefisso internazionale valido.');
 var prefixes='1 20 211 212 213 216 218 220 221 222 223 224 225 226 227 228 229 230 231 232 233 234 235 236 237 238 239 240 241 242 243 244 245 246 248 249 250 251 252 253 254 255 256 257 258 260 261 262 263 264 265 266 267 268 269 27 290 291 297 298 299 30 31 32 33 34 350 351 352 353 354 355 356 357 358 359 36 370 371 372 373 374 375 376 377 378 380 381 382 383 385 386 387 389 39 40 41 420 421 423 43 44 45 46 47 48 49 500 501 502 503 504 505 506 507 508 509 51 52 53 54 55 56 57 58 590 591 592 593 594 595 596 597 598 599 60 61 62 63 64 65 66 670 672 673 674 675 676 677 678 679 680 681 682 683 685 686 687 688 689 690 691 692 7 81 82 84 850 852 853 855 856 86 880 886 90 91 92 93 94 95 960 961 962 963 964 965 966 967 968 970 971 972 973 974 975 976 977 98 992 993 994 995 996 998'.split(' '),digits=phone.slice(1);
 for(var length=3;length>=1;length--){var prefix=digits.slice(0,length);if(prefixes.indexOf(prefix)>=0&&digits.length>length)return {prefix:prefix,number:digits.slice(length)};}
 throw new Error('Prefisso internazionale non presente nel catalogo del servizio di firma.');
}
function TRBONB_signature_(p){
 if(p.owner_approved!==true)throw new Error('Approvazione del titolare necessaria.');var snapshot=p.snapshot||{},blob=DriveApp.getFileById(p.drive_pdf_id).getBlob();if(TRBONB_sha_(blob.getBytes())!==p.document_sha256)throw new Error('Documento firma modificato.');
 var details=p.details||{},phone=TRBONB_signaturePhone_((details.billing||{}).phone);
 var config=getOtpConfig_();if(config.API_URL!=='https://api.otpservice.io/api/v1')throw new Error('Provider firma non autorizzato.');
 var props=PropertiesService.getScriptProperties(),key='TRBONB_SIG_'+p.request_key,lock=LockService.getScriptLock();lock.waitLock(30000);
 try{var old=JSON.parse(props.getProperty(key)||'null');if(old){if(old.practice_id!==p.practice_id||old.document_sha256!==p.document_sha256)throw new Error('Invio firma discordante.');if(!old.dossier_id)throw new Error('Invio già avviato con esito da verificare. Nessun nuovo dossier creato.');return {success:true,dossier_id:old.dossier_id};}
  var artist={seq:1,first_name:snapshot.first_name,last_name:snapshot.last_name,email:snapshot.email,phone_prefix:phone.prefix,phone_number:phone.number,language:'it'},company={seq:2,first_name:'Andrea',last_name:'Tognassi',email:'andrea.tognassi@trbrec.com',phone_prefix:'39',phone_number:'3755003081',language:'it'};
  var anchors=['#otp_firma_artista_1#','#otp_firma_artista_2#','#otp_firma_trb_1#','#otp_firma_trb_2#'],permissions=[];anchors.forEach(function(a,i){[1,2].forEach(function(seq){permissions.push({recipient_seq:seq,sign_point_key:a,signature_type:seq===(i<2?1:2)?'always':'never'});});});
  var payload={type:config.DOSSIER_TYPE,send_unsigned_document_by_email:true,send_signed_document_by_email:true,customer_contract_hash:'onboarding:'+p.practice_id+':'+p.request_key,recipients:[artist,company],unsigned_document:{filename:blob.getName(),content:Utilities.base64Encode(blob.getBytes()),sign_points:anchors.map(function(a){return {key:a,label:a.indexOf('artista')>=0?'Firma Artista':'Firma TRB rec',dynamic_position:true};}),sign_point_permissions:permissions}};
  var token=getOtpToken();var receipt={practice_id:p.practice_id,request_key:p.request_key,document_sha256:p.document_sha256,contract_number:snapshot.contract_number,artist_email:snapshot.email.toLowerCase(),company_email:company.email,state:'sending'};props.setProperty(key,JSON.stringify(receipt));
  var response=UrlFetchApp.fetch(config.API_URL+'/dossiers',{method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+token,'Accept-Language':'it'},payload:JSON.stringify(payload),muteHttpExceptions:true}),data;try{data=JSON.parse(response.getContentText());}catch(e){throw new Error('Esito OTP incerto: nessun nuovo invio automatico.');}
  if([200,201].indexOf(response.getResponseCode())<0||!data.id)throw new Error('Esito OTP da verificare: nessun nuovo invio automatico.');receipt.dossier_id=String(data.id);receipt.state='dispatched';props.setProperty(key,JSON.stringify(receipt));return {success:true,dossier_id:receipt.dossier_id};
 }finally{lock.releaseLock();}
}
function TRBONB_otpGet_(path,xml){var config=getOtpConfig_();if(config.API_URL!=='https://api.otpservice.io/api/v1'||!/^\/dossiers\/[0-9]+(?:\/audit_trail)?$/.test(path))throw new Error('Richiesta provider non valida.');var response=UrlFetchApp.fetch(config.API_URL+path,{headers:{Authorization:'Bearer '+getOtpToken()},muteHttpExceptions:true});if(response.getResponseCode()!==200)throw new Error('Verifica provider non confermata.');return xml?response.getContentText():JSON.parse(response.getContentText());}
function TRBONB_proof_(d,r){
 if(String(d.id)!==r.dossier_id)throw new Error('Dossier provider discordante.');var unsigned=(d.documents||[]).filter(function(x){return x.label==='unsigned_document';});if(unsigned.length!==1||unsigned[0].file_hash!==r.document_sha256)throw new Error('Impronta documento provider discordante.');
 var recipients=d.recipients||[],artist=recipients.filter(function(x){return String(x.email||'').toLowerCase()===r.artist_email;}),company=recipients.filter(function(x){return String(x.email||'').toLowerCase()===r.company_email;});if(recipients.length!==2||artist.length!==1||company.length!==1)throw new Error('Firmatari provider discordanti.');
 var done=d.state==='completed'&&!!d.signed_at&&!!artist[0].signed_at&&!!company[0].signed_at;
 return {success:true,dossier_id:r.dossier_id,status:done?'completed':'pending',artist_signed:!!artist[0].signed_at,company_signed:!!company[0].signed_at};
}
