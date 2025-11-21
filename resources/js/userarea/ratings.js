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
      current = parseInt(btn.dataset.score,10);
      starsContainer.dataset.selected = String(current);
      updateVisual(starsContainer, current);
      submitBtn.disabled = current === 0;
    });
  });
  submitBtn.addEventListener('click', ()=>{
    if(current===0 || submitBtn.disabled) return;
    submitBtn.disabled = true;
    const apptId = item.dataset.apptId;
    const payload = { appointment_id: apptId, score: current, comment: commentEl.value.trim() };
    postJSON('/ratings', payload)
      .then(()=>{
        item.classList.add('opacity-50');
        item.querySelectorAll('button,textarea').forEach(el=>{ el.disabled = true; });
        item.insertAdjacentHTML('beforeend','<div class="mt-2 text-success small">¡Gracias! Calificación registrada.</div>');
      })
      .catch(err=>{
        console.error(err);
        submitBtn.disabled = false;
        item.insertAdjacentHTML('beforeend','<div class="mt-2 text-danger small">Error al enviar, intenta nuevamente.</div>');
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

// Expose init for page loader
export function init(){ initRatings(); }
export function destroy(){}
