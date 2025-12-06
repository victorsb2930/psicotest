let p=[];function _(e=document){p.forEach(a=>{try{a.dispose()}catch{}}),p=[],Array.from((e||document).querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(a=>{try{p.push(new bootstrap.Tooltip(a))}catch{}})}function S(e){const r=e.currentTarget||e.target,a=r.getAttribute("data-user-id"),n=r.getAttribute("data-user-name"),m=`
		<p>Vas a cambiar el estado de <strong>${window.escapeHtml?window.escapeHtml(n):n}</strong>. Si estás desactivando, por favor indica la razón (opcional):</p>
		<div class="mb-3"><textarea id="deactivateReasonInput" class="form-control" rows="3" placeholder="Motivo (opcional)"></textarea></div>
	`;modalConfirm({title:"Confirmar cambio de estado",body:m,confirmLabel:"Confirmar",cancelLabel:"Cancelar",onClickYes:async function(){const c=document.getElementById("deactivateReasonInput"),o=c?c.value:"";try{const s=`/admin/users/${a}/ban`,u=(await axios.post(s,{reason:o},{headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content")}})).data||{};u.ok?(typeof modalNotification=="function"&&modalNotification("Éxito","Estado actualizado",{template:"success"}),setTimeout(()=>location.reload(),600)):typeof modalNotification=="function"&&modalNotification("Error",u.message||"No se pudo cambiar el estado")}catch{typeof modalNotification=="function"&&modalNotification("Error","Error al cambiar el estado")}}},"normal")}function k(e){const n=`/admin/users/${(e.currentTarget||e.target).getAttribute("data-user-id")}/sessions`;axios.get(n).then(function(m){const c=m.data||{};if(!c.ok){typeof modalNotification=="function"&&modalNotification("Atención","No se pudo obtener el historial de sesiones");return}const o=c.sessions||[];function s(t){return String(t).padStart(2,"0")}function l(t){if(!t)return"-";try{const d=t.replace(" ","T"),i=new Date(d);return isNaN(i.getTime())?t:`${s(i.getDate())}-${s(i.getMonth()+1)}-${i.getFullYear()} ${s(i.getHours())}:${s(i.getMinutes())}:${s(i.getSeconds())}`}catch{return t}}function u(t){if(t===null||typeof t>"u")return"-";if(t=Number(t)||0,t<60)return t+(t===1?" segundo":" segundos");const d=Math.floor(t/60);if(d<60)return d+(d===1?" minuto":" minutos");const i=Math.floor(d/60);if(i<24)return i+(i===1?" hora":" horas");const b=Math.floor(i/24);return b+(b===1?" día":" días")}let f="-",h="-";o.length&&(f=l(o[o.length-1].started_at??null),h=l(o[0].started_at??null));const y=o.length,v=o.reduce((t,d)=>t+(Number(d.duration_seconds)||0),0);let g=`	<div class="mb-3"><strong>Primer acceso:</strong> ${f}<br><strong>Último acceso:</strong> ${h}</div>
			<div class="mb-2"><strong>Total sesiones:</strong> ${y} &nbsp; <strong>Duración total:</strong> ${u(v)}</div>
			<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Inicio</th><th>Fin</th><th>Duración</th><th>IP</th><th>Agente</th></tr></thead><tbody>
		`;o.forEach(function(t){const d=l(t.started_at??null),i=l(t.ended_at??null),b=u(t.duration_seconds??null),N=t.ip??"-",E=t.user_agent??"-";g+=`<tr><td>${d}</td><td>${i}</td><td>${b}</td><td>${N}</td><td><small class="text-muted">${E}</small></td></tr>`}),g+="</tbody></table></div>",modalConfirm({title:"Historial de sesiones",body:g,noFooter:!0},"dialog",{centered:!0,backdrop:!0,size:"xl"})}).catch(function(){typeof modalNotification=="function"&&modalNotification("Atención","Error al consultar historial")})}function w(e){const r=e.currentTarget||e.target,a=r.getAttribute("data-delete-url"),n=r.getAttribute("data-user-name")||"";a&&modalConfirm({title:"Eliminar usuario",body:`<p>¿Estás seguro de eliminar la cuenta de <strong>${window.escapeHtml?window.escapeHtml(n):n}</strong>? Esta acción realizará un <em>soft-delete</em>.</p>`,confirmLabel:"Eliminar",cancelLabel:"Cancelar",onClickYes:async function(){try{const c=(await axios.delete(a,{headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content")}})).data||{};c.ok?(typeof modalNotification=="function"&&modalNotification("Eliminado","Usuario eliminado correctamente",{template:"success"}),setTimeout(()=>location.reload(),600)):typeof modalNotification=="function"&&modalNotification("Error",c.message||"No se pudo eliminar al usuario")}catch{typeof modalNotification=="function"&&modalNotification("Error","Error al eliminar usuario")}}},"normal")}function A(){modalConfirm({title:"Agregar usuario",body:`
		<form id="addUserFormJS">
			<div class="row g-3">
				<div class="col-md-6">
					<label class="form-label">Nombres *</label>
					<input type="text" name="name" class="form-control" required>
				</div>
				<div class="col-md-6">
					<label class="form-label">Apellidos</label>
					<input type="text" name="lastname" class="form-control">
				</div>
				<div class="col-md-6">
					<label class="form-label">Email *</label>
					<input type="email" name="email" class="form-control" required>
				</div>
				<div class="col-12">
					<div class="alert alert-info small mb-0">La contraseña será generada automáticamente y enviada al email proporcionado. Pide al usuario que la cambie tras iniciar sesión.</div>
				</div>
				<div class="col-md-4">
					<label class="form-label">Fecha de nacimiento</label>
					<input type="date" name="birthdate" class="form-control">
				</div>
				<div class="col-md-4">
					<label class="form-label">Género</label>
					<select name="gender" class="form-select">
						<option value="">--</option>
						<option value="masculino">Masculino</option>
						<option value="femenino">Femenino</option>
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label">Rol inicial</label>
					<select name="role_id" class="form-select">
						<option value="">Sin rol</option>
						<!-- roles will be injected dynamically -->
					</select>
				</div>
			</div>
		</form>
		`,confirmLabel:"Crear usuario",cancelLabel:"Cancelar",onShow:function(r){try{const a=['select[name="role_id"]','select[name="roles[]"]','select[name^="roles"]'];let n=null;for(let c of a){const o=document.querySelector(c);if(o&&o.innerHTML&&o.options&&o.options.length){n=o.innerHTML;break}}const m=r.querySelector('select[name="role_id"]');m&&n&&(m.innerHTML=n)}catch{}},onClickYes:async function(r){const a=document.getElementById("addUserFormJS");if(!a)return;const n=a.querySelector('input[name="name"]').value.trim(),m=a.querySelector('input[name="email"]').value.trim();if(!n||!m){typeof modalNotification=="function"&&modalNotification("Atención","Nombre y email son obligatorios");return}const c=document.querySelector('meta[name="csrf-token"]').getAttribute("content"),o=new FormData(a);o.append("_token",c);try{const l=(await axios.post("/admin/users",o,{headers:{"X-Requested-With":"XMLHttpRequest"}})).data||{};l.ok?(typeof modalNotification=="function"&&modalNotification("Creado","Usuario creado correctamente",{template:"success"}),setTimeout(()=>location.reload(),700)):typeof modalNotification=="function"&&modalNotification("Error",l.message||"No se pudo crear el usuario")}catch(s){try{const l=s.response&&s.response.data?s.response.data:null;if(l&&l.errors){const u=Object.values(l.errors).map(f=>Array.isArray(f)?f.join(" "):f).join(`
`);typeof modalNotification=="function"&&modalNotification("Error",u);return}}catch{}modalNotification("Error","Error al crear el usuario")}}},"normal",{size:"lg"})}function C(){const e=document.getElementById("app-content")||document;_(e),$(document).on("click.adminUsers",".action-deactivate",S),$(document).on("click.adminUsers",".action-show-sessions",k),$(document).on("click.adminUsers",".action-delete",w),$(document).on("click.adminUsers","#btn-add-user",function(r){r.preventDefault(),A()})}function T(){try{$(document).off(".adminUsers")}catch{}p.forEach(e=>{try{e.dispose()}catch{}}),p=[];try{if(_bsDeactivateModal){try{_bsDeactivateModal.hide()}catch{}try{_bsDeactivateModal.dispose()}catch{}_bsDeactivateModal=null}}catch{}}export{T as destroy,C as init};
