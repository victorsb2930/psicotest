// Ratings interaction logic for user area
// Assumes presence of container #pg-rating-pending-wrapper with .rating-item children

function initRatings(){
  const wrapper = document.getElementById('pg-rating-pending-wrapper');
  if(!wrapper) return;
  wrapper.querySelectorAll('.rating-item').forEach(item=>setupItem(item, wrapper));
}

function setupItem(item, wrapper){
  const starsContainer = item.querySelector('.rating-stars');
  const submitBtn = item.querySelector('.rating-submit');
  const skipBtn = item.querySelector('.rating-skip');
  const commentEl = item.querySelector('.rating-comment');
  let current = 0;
  starsContainer.querySelectorAll('.rating-star').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const score = parseInt(btn.dataset.score,10);
      // Toggle off if clicking the same selected score
      if(current === score){
        current = 0;
      } else {
        current = score;
      }
      starsContainer.dataset.selected = String(current);
      updateVisual(starsContainer, current);
      submitBtn.disabled = current === 0 || !hasComment(commentEl);
    });
  });
  // Live validation on comment input
  commentEl.addEventListener('input', ()=>{
    submitBtn.disabled = current === 0 || !hasComment(commentEl);
    // remove previous inline error when user types
    const err = item.querySelector('.rating-error'); if(err) err.remove();
  });
  submitBtn.addEventListener('click', ()=>{
    if(current===0){ return; }
    if(!hasComment(commentEl)){
      showInlineError(item, 'Debes ingresar un comentario para enviar la calificación.');
      submitBtn.disabled = true;
      return;
    }
    submitBtn.disabled = true;
    const apptId = item.dataset.apptId;
    const payload = { rating: current, comment: commentEl.value.trim() };
    // Backend defines singular '/appointments/{appointment}/rating' route
    const url = apptId ? `/appointments/${encodeURIComponent(apptId)}/rating` : '/ratings';
    postJSON(url, payload)
      .then((resp)=>{
        if(!resp || resp.ok !== true){ throw new Error(resp && resp.message ? resp.message : 'error'); }
        item.classList.add('opacity-50');
        item.querySelectorAll('button,textarea').forEach(el=>{ el.disabled = true; });
        item.insertAdjacentHTML('beforeend','<div class="mt-2 text-success small">¡Gracias! Calificación registrada.</div>');
      })
      .catch(err=>{
        console.error(err);
        submitBtn.disabled = current === 0 || !hasComment(commentEl);
        const msgBase = (err && err.message) ? String(err.message) : '';
        // Distinguish validation vs generic
        if(msgBase.includes('rating')){
          showInlineError(item, 'Error de validación en la puntuación.');
        } else if(msgBase.includes('comment') || msgBase.includes('Comentario')){
          showInlineError(item, 'El comentario es obligatorio.');
        } else {
          showInlineError(item, 'Error al enviar, intenta nuevamente.');
        }
      });
  });
  skipBtn.addEventListener('click', ()=>{
    item.remove();
    if(!wrapper.querySelector('.rating-item')){
      wrapper.insertAdjacentHTML('beforeend','<div class="small">No quedan citas por calificar ahora.</div>');
    }
  });
}

function updateVisual(container, selected){
  container.querySelectorAll('.rating-star').forEach(btn=>{
    const icon = btn.querySelector('i');
    const score = parseInt(btn.dataset.score,10);
    if(score <= selected){
      icon.classList.remove('bi-star');
      icon.classList.add('bi-star-fill');
    } else {
      icon.classList.remove('bi-star-fill');
      icon.classList.add('bi-star');
    }
  });
}

function postJSON(url, data){
  return fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': getCsrf(),
      'Accept': 'application/json'
    },
    body: JSON.stringify(data)
  }).then(async resp => {
    if(!resp.ok){
      const txt = await resp.text();
      throw new Error(txt || 'Error ' + resp.status);
    }
    try { return await resp.json(); } catch(_) { return {}; }
  });
}

function getCsrf(){
  const el = document.querySelector('meta[name=csrf-token]');
  return el ? el.getAttribute('content') : '';
}

function hasComment(el){ return !!(el && el.value && el.value.trim().length > 0); }
function showInlineError(item, text){
  if(!item) return;
  const prev = item.querySelector('.rating-error'); if(prev) prev.remove();
  item.insertAdjacentHTML('beforeend', `<div class="mt-2 rating-error text-danger small">${escapeHtml(text)}</div>`);
}
function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','>':'&gt;','"':'&quot;'}[c])); }

// Expose init for page loader
export function init(){ initRatings(); }
export function destroy(){}
