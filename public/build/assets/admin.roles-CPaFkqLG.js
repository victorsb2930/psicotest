const p=".adminRoles";let d=null,u=null;const g=["bg-primary","bg-secondary","bg-success","bg-danger","bg-warning","bg-info","bg-dark","bg-light"];async function v(){const a=await(async()=>{try{const t="/bootstrap-icons-list.json",e="bootstrap-icons-list-v1";try{const i=localStorage.getItem(e);if(i)return JSON.parse(i)}catch{}const o=await fetch(t,{cache:"no-cache"});if(!o.ok)return null;const c=await o.json();if(c&&Array.isArray(c.icons)){try{localStorage.setItem(e,JSON.stringify(c.icons))}catch{}return c.icons}return null}catch{return null}})();if(a&&a.length)return a;const s=(()=>{try{const t=new Set;for(const e of Array.from(document.styleSheets))try{const o=e.cssRules||[];for(const c of Array.from(o)){if(!c.selectorText)continue;const i=c.selectorText.split(",");for(const m of i){const f=m.trim().match(/\.bi-([a-z0-9-]+)(\s*::?\s*before)?$/i);f&&f[1]&&t.add(`bi-${f[1]}`)}}}catch{continue}return Array.from(t).sort()}catch{return null}})();if(s&&s.length)return s;const r=Array.from(document.querySelectorAll('link[rel="stylesheet"]')).find(t=>/bootstrap-icons/i.test(t.href||""));if(r&&r.href)try{const e=await(await fetch(r.href,{credentials:"omit"})).text(),o=new Set,c=/\.bi-([a-z0-9-]+)\s*::?\s*before\s*\{/gi;let i;for(;(i=c.exec(e))!==null;)o.add(`bi-${i[1]}`);return Array.from(o).sort()}catch{}return["bi-people","bi-person","bi-shield-lock","bi-briefcase","bi-house","bi-gear"]}function w(){return typeof modalConfirm<"u"?modalConfirm:function(n,a="dialog",l={}){const s=n.modalId||"modal_"+Math.random().toString(36).slice(2,8);let r=$("#"+s);if(r.length===0){const e=n.body||"",o=n.title||"",c=`
						<div class="modal fade" id="${s}" tabindex="-1" aria-hidden="true">
							<div class="modal-dialog ${l.size==="lg"?"modal-lg":""} ${l.centered?"modal-dialog-centered":""}">
								<div class="modal-content">
									<div class="modal-header"><h5 class="modal-title">${o}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
									<div class="modal-body">${e}</div>
									<div class="modal-footer"></div>
								</div>
							</div>
						</div>`;$("body").append(c),r=$("#"+s)}const t=new bootstrap.Modal(r[0],{backdrop:"static"});if(r.on("hidden.bs.modal",function(){try{r.remove()}catch{}}),r.find(".modal-footer").empty(),n.btnsType==="ac"){const e=$("<button/>",{type:"button",class:"btn btn-primary"}).text("OK");e.on("click",function(){typeof n.onClickYes=="function"&&n.onClickYes(),t.hide()});const o=$("<button/>",{type:"button",class:"btn btn-secondary","data-bs-dismiss":"modal"}).text("Cancelar");r.find(".modal-footer").append(o,e)}t.show()}}const y=w();function k(n){u=document.getElementById(n);const a=$(`
	<div>
		<input type="text" class="form-control mb-3" placeholder="Buscar icono..." id="iconFilter">
		<div id="iconGrid" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-2" style="max-height:60vh; overflow:auto"></div>
	</div>
	`);y({modalId:"biIconPicker",title:"Elegir icono",body:a[0].outerHTML,btnsType:"ac",onClickYes:()=>{}},"dialog",{size:"lg",scrollable:!0,centered:!0});const l=$("#biIconPicker"),s=l.find("#iconGrid"),r=l.find("#iconFilter");v().then(t=>{const e=o=>{if(s.empty(),!Array.isArray(o)||o.length===0){s.append('<div class="col-12"><div class="alert alert-secondary mb-0">No se encontraron iconos locales. Asegúrate de ejecutar <code>npm run generate-icons</code> y recargar.</div></div>');return}const c=$(document.createDocumentFragment());o.forEach(i=>{const m=$("<div/>",{class:"col"}),b=$(`
			<button type="button" class="btn btn-light w-100 d-flex align-items-center justify-content-start gap-2" data-icon="${i}">
			<i class="bi ${i}"></i><span class="small">bi ${i}</span>
			</button>
		`);b.on("click",()=>{u&&(u.value=`bi ${i}`);const f=$("#biIconPicker");bootstrap.Modal.getInstance(f[0])?.hide()}),m.append(b),c.append(m)}),s.append(c)};e(t),r.on("input",function(){const o=(this.value||"").toLowerCase();e(t.filter(c=>c.toLowerCase().includes(o)))})})}function I(n){u=document.getElementById(n);const a=$(`
	<div>
		<div class="mb-3">
		<label class="form-label">Clases de Bootstrap</label>
		<div class="d-flex flex-wrap gap-2 mb-2" id="colorClassGrid"></div>
		</div>
		<div>
		<label class="form-label">Color personalizado</label>
		<input type="color" id="colorHexInput" class="form-control form-control-color" value="#0d6efd" title="Elige un color">
		</div>
	</div>
	`);y({modalId:"badgeColorPicker",title:"Elegir color",body:a[0].outerHTML,btnsType:"ac",onClickYes:()=>{}},"dialog",{centered:!0});const l=$("#badgeColorPicker"),s=l.find("#colorClassGrid"),r=l.find("#colorHexInput");s.empty(),g.forEach(t=>{const e=$("<button/>",{type:"button",class:`btn btn-sm ${t}`,title:t}).css("minWidth","3rem");e.on("click",()=>{u&&(u.value=t);const o=$("#badgeColorPicker");bootstrap.Modal.getInstance(o[0])?.hide()}),s.append(e)}),r.on("input",function(){u&&(u.value=this.value)})}function h(){if(typeof window.$>"u"&&typeof window.jQuery>"u"){let n=0;d=setInterval(()=>{n++,typeof window.$<"u"||typeof window.jQuery<"u"?(clearInterval(d),d=null,h()):n>100&&(clearInterval(d),d=null)},50);return}$(document).on("click"+p,'[data-role="open-icon-picker"]',function(){k(this.dataset.target)}),$(document).on("click"+p,'[data-role="open-color-picker"]',function(){I(this.dataset.target)})}function C(){try{$(document).off(p)}catch{}d&&(clearInterval(d),d=null),["#biIconPicker","#badgeColorPicker"].forEach(n=>{try{const a=$(n);a.length&&(bootstrap.Modal.getInstance(a[0])?.hide(),a.remove())}catch{}})}function A(){h()}function P(){C()}export{P as destroy,A as init};
