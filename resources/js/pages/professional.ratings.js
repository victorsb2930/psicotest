// Professional ratings page logic

export function init(){
  enhanceRatingsTable();
  setupFilters();
}
export function destroy(){}

function setupFilters(){
  const form = document.getElementById('ratings-filters');
  if(!form) return;
  form.addEventListener('submit', e => {
    // Let normal navigation (PJAX) handle; we build query string
    const params = new URLSearchParams(new FormData(form));
    const url = '/professional/ratings?' + params.toString();
    e.preventDefault();
    try { if(window.__pjaxEnabled){ history.pushState({},'',url); window.dispatchEvent(new PopStateEvent('popstate')); return; } } catch(_){}
    window.location.href = url;
  });
  document.getElementById('reset-filters')?.addEventListener('click', ()=>{
    setTimeout(()=>{
      try { if(window.__pjaxEnabled){ history.pushState({},'','/professional/ratings'); window.dispatchEvent(new PopStateEvent('popstate')); return; } } catch(_){}
      window.location.href = '/professional/ratings';
    },10);
  });
}

function enhanceRatingsTable(){
  const table = document.getElementById('ratings-table');
  if(!table) return;
  table.querySelectorAll('tr[data-id]').forEach(row => {
    const id = row.dataset.id;
    const visToggle = row.querySelector('.visibility-toggle');
    const resp = row.querySelector('.response-text');
    const btn = row.querySelector('.save-response');
    if(resp){
      resp.addEventListener('input', ()=>{ btn.disabled = false; });
    }
    if(btn){
      btn.addEventListener('click', ()=>{
        btn.disabled = true;
        moderate(id, { response_text: resp.value.trim() || null }).then(()=>{
          row.classList.add('table-success');
          setTimeout(()=>row.classList.remove('table-success'), 1200);
        }).catch(()=>{ btn.disabled = false; markError(row); });
      });
    }
    if(visToggle){
      visToggle.addEventListener('change', ()=>{
        moderate(id, { is_public: visToggle.checked }).then(()=>{
          row.classList.add('table-success');
          setTimeout(()=>row.classList.remove('table-success'), 1200);
        }).catch(()=>{ markError(row); visToggle.checked = !visToggle.checked; });
      });
    }
  });
}

function moderate(id, payload){
  return fetch('/professional/ratings/' + id, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': getCsrf(),
      'Accept': 'application/json'
    },
    body: JSON.stringify(payload)
  }).then(async r => { if(!r.ok) throw new Error(await r.text()); return r.json(); });
}

function markError(row){
  row.classList.add('table-danger');
  setTimeout(()=>row.classList.remove('table-danger'), 1500);
}

function getCsrf(){
  const el = document.querySelector('meta[name=csrf-token]');
  return el ? el.getAttribute('content') : '';
}
