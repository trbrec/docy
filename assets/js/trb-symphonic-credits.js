(function () {
'use strict';
function validate(track) {
 var groups=track.querySelectorAll('[data-symphonic-group]');
 if(!groups.length)return;
 var advisory=track.querySelector('[data-track-advisory]'), vocal=advisory&&advisory.value&&advisory.value!=='no_lyrics';
 groups.forEach(function(group){
  var key=group.dataset.contributorGroup, roles=[], first=group.querySelector('input[name$="[name]"]');
  group.querySelectorAll('.trb-contributor-row').forEach(function(row){
   var name=row.querySelector('input[name$="[name]"]');
   if(!name||!name.value.trim())return;
   if(key==='writers')row.querySelectorAll('input[type="checkbox"]:checked').forEach(function(input){roles.push(input.value);});
   else {var select=row.querySelector('select');if(select&&select.value)roles.push(select.value);}
  });
  var message='';
  if(key==='engineering'&&roles.indexOf('Producer')<0)message='Indica almeno un Producer.';
  if(vocal&&key==='writers'&&roles.indexOf('Lyricist')<0)message='Il brano contiene un testo: indica chi lo ha scritto con il ruolo Lyricist.';
  if(vocal&&key==='performers'&&roles.indexOf('Vocals')<0)message='Il brano contiene voce: indica chi canta con il ruolo Vocals.';
  if(first)first.setCustomValidity(message);
  var error=group.querySelector('[data-credit-error]');if(error)error.textContent=message;
 });
}
document.addEventListener('DOMContentLoaded',function(){
 var form=document.querySelector('[data-release-form]');if(!form)return;
 var style=document.createElement('style');style.textContent='.trb-symphonic-writers{min-width:0}.trb-symphonic-writers summary{cursor:pointer;padding:10px;border:1px solid #cbd5e1;border-radius:8px}.trb-symphonic-writers .trb-writer-roles{display:block!important;max-height:220px;overflow:auto}.trb-symphonic-writers .trb-writer-roles label{display:flex!important;gap:8px;padding:5px}.trb-symphonic-writers label[hidden]{display:none!important}[data-credit-error]{color:#a12727}[data-symphonic-group] select{width:100%;max-width:100%}[data-symphonic-group] [data-role-filter]{width:100%;margin-bottom:6px}';document.head.appendChild(style);
 form.addEventListener('invalid',function(event){var details=event.target.closest('details');if(details)details.open=true;},true);
 form.addEventListener('input',function(event){
  var input=event.target;
  if(input.matches('[data-role-filter]')){
   var scope=input.closest('details')||input.closest('label'),term=input.value.toLowerCase();
   scope.querySelectorAll('option').forEach(function(option){option.hidden=!!option.value&&option.textContent.toLowerCase().indexOf(term)<0&&!option.selected;});
   scope.querySelectorAll('.trb-writer-roles label').forEach(function(label){label.hidden=label.textContent.toLowerCase().indexOf(term)<0;});
  }
  var track=input.closest('[data-track]');if(track)validate(track);
 });
 form.addEventListener('change',function(event){var track=event.target.closest('[data-track]');if(track)validate(track);});
 var tracks=form.querySelector('[data-tracks]');
 if(tracks)new MutationObserver(function(){tracks.querySelectorAll('[data-track]').forEach(validate);}).observe(tracks,{childList:true});
 form.addEventListener('click',function(event){if(event.target.closest('[data-remove-contributor],[data-add-contributor]'))queueMicrotask(function(){form.querySelectorAll('[data-track]').forEach(validate);});});
 form.addEventListener('submit',function(event){form.querySelectorAll('[data-track]').forEach(validate);if(!form.checkValidity()){event.preventDefault();event.stopImmediatePropagation();form.reportValidity();}},true);
 form.querySelectorAll('[data-track]').forEach(validate);
});
}());
