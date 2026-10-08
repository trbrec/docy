(() => {
 'use strict';
 let active = null;
 document.addEventListener('click', event => {
  const button = event.target.closest('.trb-name-edit');
  if (!button || active?.busy) return;
  event.preventDefault();
  if (active) active.close();
  const sourceRow = button.closest('tr');
  const row = document.createElement('tr');
  const cell = document.createElement('td');
  cell.colSpan = sourceRow.children.length;
  row.className = 'trb-name-inline-edit';
  cell.style.padding = '16px';
  row.append(cell);
  const label = document.createElement('label');
  label.textContent = 'Nome d’arte ';
  const input = document.createElement('input');
  input.type = 'text'; input.value = button.dataset.name || button.dataset.display;
  input.style.width = 'min(600px, 80%)';
  input.required = true;
  label.append(input); cell.append(label);
  const message = document.createElement('p');
  message.setAttribute('role', 'status'); message.setAttribute('aria-live', 'polite');
  cell.append(message);
  const save = document.createElement('button');
  save.type = 'button'; save.className = 'button button-primary'; save.textContent = 'Aggiorna';
  const cancel = document.createElement('button');
  cancel.type = 'button'; cancel.className = 'button'; cancel.textContent = 'Annulla';
  cancel.style.marginLeft = '8px'; cell.append(save, cancel);
  sourceRow.after(row);
  const state = {busy:false, close:()=>{row.remove(); active=null; button.focus();}};
  active = state; input.focus(); input.select();
  cancel.addEventListener('click', ()=>{if (!state.busy) state.close();});
  row.addEventListener('keydown', event=>{
   if (event.key==='Escape' && !state.busy) {event.preventDefault(); state.close();}
   if (event.key==='Enter' && event.target===input) {event.preventDefault(); save.click();}
  });
  save.addEventListener('click', async()=>{
   if (state.busy || !input.reportValidity()) return;
   state.busy=true; save.disabled=true; cancel.disabled=true; input.disabled=true;
   message.textContent='Salvataggio…';
   try {
    const body=new URLSearchParams({action:'trb_artist_admin_name',nonce:trbAdminStageName.nonce,user_id:button.dataset.user,name:input.value,expected:button.dataset.name});
    const response=await fetch(trbAdminStageName.url,{method:'POST',credentials:'same-origin',body});
    const result=await response.json();
    if (!response.ok || !result.success) throw new Error(result.data?.message || 'Salvataggio non riuscito.');
    const column=button.closest('td');
    column.querySelector('.trb-stage-name').textContent=result.data.name;
    column.querySelector('small')?.remove();
    button.dataset.name=result.data.name; button.dataset.display=result.data.name;
    state.close();
   } catch(error) {
    message.textContent=error.message || 'Connessione non disponibile. Riprova.';
    state.busy=false; save.disabled=false; cancel.disabled=false; input.disabled=false; input.focus();
   }
  });
 });
})();
