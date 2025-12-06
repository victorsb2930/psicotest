function h(){try{if(document.getElementById("card-component-helpers"))return;const t=`
			.pf-card { border-radius: 1.5rem; padding: 1.5rem; background: #fff; border: 1px solid rgba(0,0,0,0.03); box-shadow: 0 8px 30px rgba(15,23,42,0.08); transition: transform .2s ease, box-shadow .2s ease; }
			.pf-card:hover { transform: translateY(-6px); box-shadow: 0 18px 35px rgba(15,23,42,0.15); }
			.pf-thumb-btn { border: none; padding: 0; border-radius: 50%; width: 88px; height: 88px; background: transparent; }
			.pf-thumb-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; box-shadow: inset 0 0 0 4px rgba(13,110,253,.15); }
			.pf-pill { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; }
			.pf-meta { font-size: .9rem; color: #6c757d; }
			.pf-meta strong { color: #212529; }
			.pf-actions { margin-top: auto; }
			.skeleton-card { border-radius: 1.5rem; padding: 1.5rem; background: linear-gradient(120deg, #f8f9fa 25%, #eceff3 50%, #f8f9fa 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite; min-height: 220px; }
			@keyframes skeleton-loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
			@media (prefers-reduced-motion: reduce) { .pf-card, .pf-card:hover { transition: none; transform: none; } .skeleton-card { animation: none; } }
		`,n=document.createElement("style");n.id="card-component-helpers",n.textContent=t,document.head.appendChild(n)}catch{}}function o(t){try{if(window.escapeHtml)return window.escapeHtml(t)}catch{}const n=document.createElement("div");return n.innerText=t||"",n.innerHTML}function x(t){const n=t.photo||"/images/default-avatar.png",s=t.speciality||"General",a=(t.ratings_count||0)>0,m=a?Number(t.ratings_avg)||0:null,r=a?`<button type="button" class="btn btn-sm btn-light border btn-show-ratings" data-id="${o(String(t.id))}" aria-label="Ver reseñas"><span class="fw-semibold">${m.toFixed(1)}★</span> · ${t.ratings_count} reseña${t.ratings_count===1?"":"s"}</button>`:'<span class="badge bg-secondary-subtle text-secondary">Sin reseñas</span>',g=t.location||"No especificada",l=`${t.name||""} ${t.lastname||""}`.trim()||"Profesional",f=t.email||"";return`
		<div class="col">
			<article class="pf-card h-100 d-flex flex-column">
				<div class="d-flex align-items-center gap-3 flex-wrap">
					<button type="button" class="pf-thumb-btn js-pf-thumb" data-photo-src="${o(n)}" aria-label="Ver foto de ${o(l)}">
						<img src="${o(n)}" alt="${o(l)}">
					</button>
					<div class="flex-grow-1">
						<h5 class="mb-1 fw-semibold text-dark">${o(l)}</h5>
						<p class="mb-0 text-muted small text-break">${o(f)}</p>
					</div>
					<div class="text-end">${r}</div>
				</div>
				<div class="mt-3 d-flex flex-column gap-2 pf-meta">
					<div><span class="text-muted">Especialidad:</span> <strong>${o(s)}</strong></div>
					<div><span class="text-muted">Ubicación:</span> <strong>${o(g)}</strong></div>
					<div><span class="text-muted">Tipos de cita:</span> <strong>${o(Array.isArray(t.appointment_types)?t.appointment_types.join(", "):t.appointment_types||"Consulta general")}</strong></div>
				</div>
				<div class="pf-actions d-flex flex-wrap gap-2 mt-4">
					<a href="/professional/profile/${encodeURIComponent(t.id)}" class="btn btn-outline-primary flex-grow-1">Ver perfil</a>
					<button data-id="${o(String(t.id))}"
							data-name="${o(t.name||"")}"
							data-title="${o(s)}"
							class="btn btn-primary flex-grow-1 btn-request">Solicitar cita</button>
				</div>
			</article>
		</div>`}function I(){const t=document.getElementById("pf_q"),n=document.getElementById("pf_speciality"),s=document.getElementById("pf_search"),a=document.getElementById("pf_results"),m=document.getElementById("pf_empty"),r=document.getElementById("pf_status"),g=document.getElementById("pf_filters");function l(e,i=!0){if(r){if(!e||!i){r.classList.add("d-none"),r.textContent="";return}r.textContent=e,r.classList.remove("d-none")}}function f(){return["","",""].map(()=>'<div class="col"><div class="skeleton-card"></div></div>').join("")}function b(){document.getElementById("pfImagePreviewModal")||document.body.insertAdjacentHTML("beforeend",`
				<div class="modal fade" id="pfImagePreviewModal" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-dialog-centered modal-lg">
						<div class="modal-content">
							<div class="modal-body text-center p-0">
								<img id="pfImagePreviewModalImg" src="" style="width:100%; height:auto;" alt="preview">
							</div>
						</div>
					</div>
				</div>`),Array.from(document.querySelectorAll(".js-pf-thumb")).forEach(e=>{e.addEventListener("click",()=>{const i=e.getAttribute("data-photo-src"),u=document.getElementById("pfImagePreviewModalImg");u&&i&&(u.src=i);const c=document.getElementById("pfImagePreviewModal");c&&new bootstrap.Modal(c).show()})}),Array.from(document.querySelectorAll(".btn-request")).forEach(e=>{e.addEventListener("click",()=>{const i=e.getAttribute("data-id");let u=e.getAttribute("data-name")||"",c=e.getAttribute("data-title")||"";if(!u)try{const p=e.closest(".pf-card")?.querySelector("h5");p&&(u=p.textContent.trim())}catch{}if(!c)try{const p=e.closest(".pf-card")?.querySelector(".pf-meta strong");p&&(c=p.textContent.trim())}catch{}const y=new URLSearchParams({professional_id:i||"",professional_name:u||"",professional_title:c||""});if(typeof window.requestAppointmentFlow=="function")try{window.requestAppointmentFlow(i||"",u||"",c||"");return}catch{}window.location.href="/appointments?"+y.toString()})}),Array.from(document.querySelectorAll(".btn-show-ratings")).forEach(e=>{e.addEventListener("click",async()=>{const i=e.getAttribute("data-id");i&&await w(i)})})}async function d(){h();const e={q:t?.value||"",speciality:n?.value||""};a.setAttribute("aria-busy","true"),a.innerHTML=f(),l("Buscando profesionales…",!0),m.classList.add("d-none");try{const i=document.querySelector('meta[name="professionals-search-url"]')?.getAttribute("content")||"/professionals/search",c=(await axios.get(i,{params:e}))?.data||[];if(!Array.isArray(c)||c.length===0){a.innerHTML="",m.classList.remove("d-none"),l("Sin resultados para la búsqueda aplicada.",!0);return}l("",!1),m.classList.add("d-none"),a.innerHTML=c.map(x).join(""),b()}catch{a.innerHTML='<div class="col"><div class="alert alert-danger">Ocurrió un error al buscar profesionales.</div></div>',l("No se pudo completar la búsqueda. Intenta nuevamente.",!0)}finally{a.setAttribute("aria-busy","false")}}const v=e=>{e.key==="Enter"&&(e.preventDefault(),d())};[t,n].forEach(e=>e&&e.addEventListener("keydown",v)),s?.addEventListener("click",e=>{e.preventDefault(),d()}),g?.addEventListener("submit",e=>{e.preventDefault(),d()}),d()}async function w(t,n){try{document.getElementById("profRatingsModal")||document.body.insertAdjacentHTML("beforeend",`
				<div class="modal fade" id="profRatingsModal" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-dialog-centered modal-lg">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title">Calificaciones</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<div id="profRatingsModalContent" class="py-2"></div>
							</div>
						</div>
					</div>
				</div>`);const s=document.getElementById("profRatingsModal"),a=document.getElementById("profRatingsModalContent");if(!s||!a)return;a.innerHTML='<div class="text-center text-muted py-3">Cargando...</div>';const m=`/professionals/${encodeURIComponent(t)}/ratings/public`;let r=null;try{const d=await fetch(m,{headers:{Accept:"application/json"}});if(!d.ok)throw new Error(await d.text());r=await d.json()}catch{a.innerHTML='<div class="text-danger">Error al cargar reseñas.</div>',new bootstrap.Modal(s).show();return}const g=Array.isArray(r.items)?r.items:[],l=(r.ratings_avg||0).toFixed(2),f=r.ratings_count||0;let b=`<div class="d-flex justify-content-between align-items-center mb-2">
				<div><strong>Promedio:</strong> ${l} ⭐ (${f} reseña${f===1?"":"s"})</div>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
			</div>`;if(f===0)a.innerHTML='<div class="text-muted">Sin calificaciones públicas aún.</div>';else{const d=g.map(v=>$(v)).join("");a.innerHTML=b+`<div class="list-group">${d}</div>`}new bootstrap.Modal(s).show()}catch{}}function $(t){const n=E(t.score),s=t.created_at?new Date(t.created_at).toLocaleDateString():"",a=(t.comment||"").trim()!==""?o(t.comment):'<span class="text-muted">(Sin comentario)</span>',m=(t.response||"").trim()!==""?`<div class="small mt-1"><strong>Respuesta:</strong> ${o(t.response)}</div>`:"";return`<div class="list-group-item">
		<div class="d-flex justify-content-between align-items-center">
			<div>${n}</div>
			<div class="small text-muted">${s}</div>
		</div>
		<div class="mt-1">${a}</div>
		${m}
	</div>`}function E(t){t=Number(t)||0;const n=[];for(let s=1;s<=5;s++)n.push(`<i class="bi ${s<=t?"bi-star-fill text-warning":"bi-star text-secondary"}"></i>`);return n.join("")}export{I as default};
