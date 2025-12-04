// Profile page module (single clean implementation with 2FA method selection)
const api = { list: '/profile/photos', upload: '/profile/photos', set: id => `/profile/photos/${id}/set-profile`, delete: id => `/profile/photos/${id}`, presence: '/profile/presence', heartbeat: '/profile/heartbeat' };
const detailsApi = {
	names: '/profile/details/names',
	gender: '/profile/details/gender',
	birthdate: '/profile/details/birthdate',
	location: '/profile/details/location',
	speciality: '/profile/details/speciality'
};
let _pollInterval = null, _hbInterval = null;
const POLL_PERIOD = 5000;
let _visibilityHandler = null, _beforeUnloadHandler = null;
let _btnChangePhotoHandler = null, _inputPhotoHandler = null;
let _avatarClickHandler = null, _galleryClickHandler = null;
let _reopen2faSubmitHandler = null, _reopen2faCancelHandler = null;
let _resetPasswordMenuHandler = null, _toggle2faHandler = null;
let _profileMetaEl = null;
let _profileState = {};
let _detailMenuHandlers = [];

async function refreshGallery(){
	try {
		const r=await axios.get(api.list); const photos=r.data?.photos||[]; const container=$('#photo-gallery'); if(!container) return; container.empty();
		if(!photos.length){ container.append('<p class="text-muted">No hay fotos en la galería.</p>'); return; }
		photos.forEach(p=>{ const origin=location.origin; const metaDefault=(document.querySelector('meta[name="default-avatar"]')||{}).content; const defaultAvatar=window.__defaultAvatar||metaDefault||(origin+'/images/default-avatar.png'); const src=p.url||p.secure_url||(p.path?origin+'/storage/'+p.path.replace(/^\/+/, ''):defaultAvatar); container.append(`<div class="position-relative m-1" style="width:120px"><img src="${src}" alt="${p.caption||''}" width="120" height="120" class="rounded object-fit-cover gallery-photo" data-photo-id="${p.id}"><div class="position-absolute top-0 end-0 m-1 d-flex flex-column gap-1"><button type="button" class="btn btn-sm btn-light gallery-preview-btn" title="Ver" data-photo-id="${p.id}"><i class="bi bi-arrows-fullscreen"></i></button><button type="button" class="btn btn-sm btn-success gallery-set-btn" title="Usar como perfil" data-photo-id="${p.id}"><i class="bi bi-person-circle"></i></button><button type="button" class="btn btn-sm btn-danger gallery-delete-btn" title="Eliminar" data-photo-id="${p.id}"><i class="bi bi-trash"></i></button></div></div>`); });
		let profilePhoto=photos.find(pp=>pp.is_profile||pp.is_profile===1)||photos[0];
		if(profilePhoto){ const origin=location.origin; const profSrc=profilePhoto.url||profilePhoto.secure_url||(profilePhoto.path?origin+'/storage/'+profilePhoto.path.replace(/^\/+/, ''):null); if(profSrc){ const busted=profSrc+'?_=' + Date.now(); document.querySelectorAll('#profile-avatar-img, #nav-avatar-img, .profile-avatar-img, img[data-profile-photo]').forEach(img=>{ try{ img.src=busted; }catch(_){} }); try{ window.dispatchEvent(new CustomEvent('profile-photo-changed',{detail:{src:busted}})); }catch(_){} } }
	}catch(e){ modalNotification?.('Error','No se pudo cargar la galería',{template:'danger'},true,{xhr:e?.response}); }
}

async function fetchStatus(){ try{ const r=await axios.get('/profile/status'); if(r.data?.ok){ const status=r.data.status||'offline'; const labels={online:'Online',busy:'Ocupado',dnd:'No molestar',away:'Ausente',offline:'No disponible'}; const elDesc=document.getElementById('profile-presence-desc'); if(elDesc) elDesc.textContent=labels[status]||status; const colors={online:'#28a745',busy:'#fd7e14',dnd:'#dc3545',away:'#ffc107',offline:'#6c757d'}; const el=document.getElementById('profile-presence'); if(el) el.style.background=colors[status]||colors.offline; }}catch(_){} }
function startPolling(){ if(_pollInterval) return; fetchStatus(); _pollInterval=setInterval(fetchStatus,POLL_PERIOD); }
function stopPolling(){ if(_pollInterval){ clearInterval(_pollInterval); _pollInterval=null; } }
async function sendHeartbeat(){ try{ await axios.post(api.heartbeat); }catch(_){} }
function startHeartbeat(){ if(_hbInterval) return; sendHeartbeat(); _hbInterval=setInterval(sendHeartbeat,30000); }
function stopHeartbeat(){ if(_hbInterval){ clearInterval(_hbInterval); _hbInterval=null; } }

export function init(){
	setupProfileState();
	setupProfileForms();
	// Foto: subida
	const btn=document.getElementById('btn-change-photo'); const input=document.getElementById('input-photo');
	if(btn&&input){ _btnChangePhotoHandler=()=>input.click(); btn.addEventListener('click',_btnChangePhotoHandler); _inputPhotoHandler=async function(){ const f=this.files[0]; if(!f) return; const fd=new FormData(); fd.append('photo',f); try{ const r=await axios.post(api.upload,fd); if(r.data?.ok){ await refreshGallery(); modalNotification?.('Foto subida','Foto subida correctamente',{template:'success'});} else modalNotification?.('Error','No se pudo subir la foto',{template:'danger'}); }catch(err){ modalNotification?.('Error','No se pudo subir la foto',{template:'danger'},true,{xhr:err?.response}); } finally { try{ this.value=''; }catch(_){} } }; input.addEventListener('change',_inputPhotoHandler); }
	// Presencia & heartbeat
	startPolling(); if(document.visibilityState==='visible') startHeartbeat(); _visibilityHandler=()=>{ if(document.visibilityState==='visible') startHeartbeat(); else stopHeartbeat(); }; document.addEventListener('visibilitychange',_visibilityHandler); _beforeUnloadHandler=()=>{ navigator.sendBeacon && navigator.sendBeacon(api.heartbeat); }; window.addEventListener('beforeunload',_beforeUnloadHandler);
	// Modal vista previa
	if(!document.getElementById('profileImagePreviewModal')) document.body.insertAdjacentHTML('beforeend','<div class="modal fade" id="profileImagePreviewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-body text-center p-0"><img id="profileImagePreviewModalImg" src="" style="width:100%;height:auto" alt="preview"></div></div></div></div>');
	// Modal reapertura 2FA
	(function(){ let m=document.getElementById('reopen2faModal'); if(!m){ document.body.insertAdjacentHTML('beforeend','<div class="modal fade" id="reopen2faModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmar reapertura de sesión</h5></div><div class="modal-body"><p>Introduce el código de 6 dígitos enviado.</p><input id="reopen2faCode" class="form-control mb-2" maxlength="6" inputmode="numeric" placeholder="Código"><div id="reopen2faError" class="text-danger small" style="display:none"></div></div><div class="modal-footer"><button id="reopen2faCancel" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button id="reopen2faSubmit" type="button" class="btn btn-primary">Confirmar</button></div></div></div></div>'); m=document.getElementById('reopen2faModal'); }
		const inp=m.querySelector('#reopen2faCode'); const err=m.querySelector('#reopen2faError'); const sub=m.querySelector('#reopen2faSubmit'); const can=m.querySelector('#reopen2faCancel');
		_reopen2faSubmitHandler=async()=>{ err.style.display='none'; const code=inp.value.trim(); if(!/^[0-9]{6}$/.test(code)){ err.textContent='Código inválido'; err.style.display='block'; return; } sub.disabled=true; try{ const r=await fetch('/profile/heartbeat/confirm',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({code})}); const j=await r.json(); if(j.ok){ (bootstrap.Modal.getInstance(m)||new bootstrap.Modal(m)).hide(); location.reload(); } else { err.textContent=j.message||'Código incorrecto'; err.style.display='block'; } }catch(_){ err.textContent='Error de red'; err.style.display='block'; } finally { sub.disabled=false; } };
		sub.addEventListener('click',_reopen2faSubmitHandler); _reopen2faCancelHandler=()=>{ err.style.display='none'; inp.value=''; }; can.addEventListener('click',_reopen2faCancelHandler); window.showReopen2faModal=()=>{ err.style.display='none'; inp.value=''; (bootstrap.Modal.getInstance(m)||new bootstrap.Modal(m)).show(); setTimeout(()=>inp.focus(),250); };
	})();
	// Item reset password
	const itemReset=document.getElementById('profileResetPasswordItem'); if(itemReset){ _resetPasswordMenuHandler=e=>{ if(e) e.preventDefault(); modalConfirm({ title:'Cambiar contraseña', body:'<p>Se enviará un enlace a tu correo. ¿Continuar?</p>', confirmLabel:'Enviar', cancelLabel:'Cancelar', onClickYes: async()=>{ try{ const r=await axios.post('/profile/password/reset-email'); if(r.data?.ok) modalConfirm({title:'Enviado', body:'<p>Revisa tu correo.</p>', btnsType:'ac'},'normal'); else modalConfirm({title:'Error', body:`<p>${r.data?.message||'No se pudo enviar.'}</p>`, btnsType:'ac'},'normal'); }catch(err){ let msg='No se pudo enviar.'; const rs=err.response; if(rs){ if(rs.status===429) msg='Demasiadas solicitudes.'; else if(rs.status===419) msg='Sesión expirada.'; else if(rs.data?.message) msg=rs.data.message; } modalConfirm({title:'Error', body:`<p>${msg}</p>`, btnsType:'ac'},'normal'); } }} ,'normal'); }; itemReset.addEventListener('click',_resetPasswordMenuHandler); }
	// Toggle 2FA con selección método
	const item2fa=document.getElementById('profileToggle2faItem'); if(item2fa){ _toggle2faHandler=async e=>{ if(e) e.preventDefault(); const current=item2fa.dataset.twoFactorEnabled==='1'; if(current){ item2fa.classList.add('disabled'); try{ const r=await axios.post('/profile/2fa/toggle'); if(r.data?.ok){ item2fa.dataset.twoFactorEnabled='0'; item2fa.textContent='Activar 2FA'; modalNotification?.('2FA','2FA desactivado',{template:'success'});} else modalNotification?.('Error','No se pudo desactivar',{template:'danger'}); }catch(_){ modalNotification?.('Error','Error de red',{template:'danger'});} finally { item2fa.classList.remove('disabled'); } return; }
		let m=document.getElementById('twoFactorEnableModal'); if(!m){ document.body.insertAdjacentHTML('beforeend','<div class="modal fade" id="twoFactorEnableModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Activar 2FA</h5></div><div class="modal-body"><p>Selecciona cómo recibir el código.</p><div class="mb-2"><div class="form-check"><input class="form-check-input" type="radio" name="twofa_method" id="twofa_method_email" value="email" checked><label class="form-check-label" for="twofa_method_email">Email</label></div><div class="form-check"><input class="form-check-input" type="radio" name="twofa_method" id="twofa_method_phone" value="phone"><label class="form-check-label" for="twofa_method_phone">Teléfono (SMS)</label></div></div><div id="twofaPhoneWrap" class="mb-3" style="display:none"><label class="form-label" for="twofa_phone_input">Teléfono</label><input id="twofa_phone_input" class="form-control" maxlength="25" placeholder="Ej: +1 809 555 1234"><div id="twofa_phone_error" class="text-danger small" style="display:none"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button id="twofa_enable_btn" type="button" class="btn btn-primary">Activar</button></div></div></div></div>'); m=document.getElementById('twoFactorEnableModal'); const radios=m.querySelectorAll('input[name="twofa_method"]'); const phoneWrap=m.querySelector('#twofaPhoneWrap'); const phoneInput=m.querySelector('#twofa_phone_input'); const phoneErr=m.querySelector('#twofa_phone_error'); radios.forEach(r=>r.addEventListener('change',()=>{ const v=m.querySelector('input[name="twofa_method"]:checked')?.value; phoneWrap.style.display=v==='phone'?'':'none'; })); m.querySelector('#twofa_enable_btn').addEventListener('click', async()=>{ phoneErr.style.display='none'; const method=m.querySelector('input[name="twofa_method"]:checked')?.value||'email'; let phoneVal=''; if(method==='phone'){ phoneVal=(phoneInput.value||'').trim(); if(!phoneVal){ phoneErr.textContent='Ingresa teléfono'; phoneErr.style.display='block'; return; } }
			const btn=m.querySelector('#twofa_enable_btn'); btn.disabled=true; try{ if(method==='phone'&&phoneVal){ await axios.post('/profile/phone',{phone:phoneVal}); }
				const r=await axios.post('/profile/2fa/toggle',{method}); if(r.data?.ok && r.data.enabled){ item2fa.dataset.twoFactorEnabled='1'; item2fa.textContent='Desactivar 2FA'; modalNotification?.('2FA','2FA activado ('+(method==='phone'?'teléfono':'email')+')',{template:'success'}); (bootstrap.Modal.getInstance(m)||new bootstrap.Modal(m)).hide(); } else if(r.data?.phone_required){ phoneErr.textContent=r.data.message||'Se requiere teléfono'; phoneErr.style.display='block'; } else { modalNotification?.('Error','No se pudo activar 2FA',{template:'danger'}); } }catch(errAct){ modalNotification?.('Error', errAct?.response?.data?.message || 'Error de red',{template:'danger'}); } finally { btn.disabled=false; }
		}); }
		(bootstrap.Modal.getInstance(m)||new bootstrap.Modal(m)).show();
	}; item2fa.addEventListener('click',_toggle2faHandler); }
	// Avatar preview
	const avatar=document.getElementById('profile-avatar'); const modalImg=document.getElementById('profileImagePreviewModalImg'); _avatarClickHandler=()=>{ const src=document.getElementById('profile-avatar-img')?.src; if(!src) return; modalImg.src=src; (bootstrap.Modal.getInstance(document.getElementById('profileImagePreviewModal'))||new bootstrap.Modal(document.getElementById('profileImagePreviewModal'))).show(); }; if(avatar) avatar.addEventListener('click',_avatarClickHandler);
	// Interacciones galería
	_galleryClickHandler=e=>{ const t=e.target; const btn=t.closest?.('.gallery-preview-btn, .gallery-set-btn, .gallery-delete-btn'); const modalEl=document.getElementById('profileImagePreviewModal'); const modalImg=document.getElementById('profileImagePreviewModalImg'); if(!btn){ const img=t.closest?.('.gallery-photo'); if(img){ modalImg.src=img.src; (bootstrap.Modal.getInstance(modalEl)||new bootstrap.Modal(modalEl)).show(); } return; } const pid=btn.dataset.photoId; if(!pid) return; if(btn.classList.contains('gallery-preview-btn')){ const img=document.querySelector(`[data-photo-id="${pid}"]`); if(img){ modalImg.src=img.src; (bootstrap.Modal.getInstance(modalEl)||new bootstrap.Modal(modalEl)).show(); } return; } if(btn.classList.contains('gallery-set-btn')){ (async()=>{ try{ const r=await axios.post(api.set(pid)); if(r.data?.ok){ modalNotification?.('Foto perfil','Actualizada',{template:'success'}); await refreshGallery(); } else modalNotification?.('Error','No se pudo establecer',{template:'danger'}); }catch(e2){ modalNotification?.('Error','No se pudo establecer',{template:'danger'},true,{xhr:e2?.response}); } })(); return; } if(btn.classList.contains('gallery-delete-btn')){ const doDelete=async()=>{ try{ const r=await axios.delete(api.delete(pid)); if(r.data?.ok){ modalNotification?.('Foto eliminada','Eliminada correctamente',{template:'success'}); await refreshGallery(); } else modalNotification?.('Error','No se pudo eliminar',{template:'danger'}); }catch(e2){ modalNotification?.('Error','No se pudo eliminar',{template:'danger'},true,{xhr:e2?.response}); } }; modalConfirm({ title:'Eliminar foto', body:'<p>¿Eliminar esta foto?</p>', buttons:[{text:'Cancelar', className:'btn-outline-secondary', dismiss:true},{text:'Eliminar', className:'btn-danger', onClick:()=>doDelete()}] },'normal'); } };
	const gallery=document.getElementById('photo-gallery'); if(gallery) gallery.addEventListener('click',_galleryClickHandler);
	refreshGallery();
}

export function destroy(){
	stopPolling(); stopHeartbeat();
	teardownProfileForms();
	try{ if(_btnChangePhotoHandler) document.getElementById('btn-change-photo')?.removeEventListener('click',_btnChangePhotoHandler);}catch(_){}
	try{ if(_inputPhotoHandler) document.getElementById('input-photo')?.removeEventListener('change',_inputPhotoHandler);}catch(_){}
	try{ if(_avatarClickHandler) document.getElementById('profile-avatar')?.removeEventListener('click',_avatarClickHandler);}catch(_){}
	try{ if(_galleryClickHandler) document.getElementById('photo-gallery')?.removeEventListener('click',_galleryClickHandler);}catch(_){}
	try{ if(_reopen2faSubmitHandler) document.getElementById('reopen2faSubmit')?.removeEventListener('click',_reopen2faSubmitHandler);}catch(_){}
	try{ if(_reopen2faCancelHandler) document.getElementById('reopen2faCancel')?.removeEventListener('click',_reopen2faCancelHandler);}catch(_){}
	try{ if(_resetPasswordMenuHandler) document.getElementById('profileResetPasswordItem')?.removeEventListener('click',_resetPasswordMenuHandler);}catch(_){}
	try{ if(_toggle2faHandler) document.getElementById('profileToggle2faItem')?.removeEventListener('click',_toggle2faHandler);}catch(_){}
	try{ if(_visibilityHandler) document.removeEventListener('visibilitychange',_visibilityHandler);}catch(_){}
	try{ if(_beforeUnloadHandler) window.removeEventListener('beforeunload',_beforeUnloadHandler);}catch(_){}
	_profileMetaEl=null; _profileState={};
	_btnChangePhotoHandler=_inputPhotoHandler=_avatarClickHandler=_galleryClickHandler=_visibilityHandler=_beforeUnloadHandler=_reopen2faSubmitHandler=_reopen2faCancelHandler=_resetPasswordMenuHandler=_toggle2faHandler=null;
}

function setupProfileState(){
	_profileMetaEl=document.getElementById('profileMeta');
	if(!_profileMetaEl){ _profileState={}; return; }
	_profileState={
		name:_profileMetaEl.dataset.name||'',
		lastname:_profileMetaEl.dataset.lastname||'',
		gender:_profileMetaEl.dataset.gender||'',
		birthdate:_profileMetaEl.dataset.birthdate||'',
		location:_profileMetaEl.dataset.location||'',
		speciality:_profileMetaEl.dataset.speciality||'',
		email:_profileMetaEl.dataset.email||'',
		isProfessional:_profileMetaEl.dataset.isProfessional==='1'
	};
}

function setupProfileForms(){
	teardownProfileForms();
	const defs=buildProfileFormDefinitions();
	defs.forEach(def=>{
		const trigger=document.getElementById(def.triggerId);
		if(!trigger) return;
		const handler=e=>{ e?.preventDefault(); openProfileDetailModal(def); };
		trigger.addEventListener('click',handler);
		_detailMenuHandlers.push({element:trigger, handler});
	});
}

function teardownProfileForms(){
	_detailMenuHandlers.forEach(item=>{
		try{ item.element?.removeEventListener('click',item.handler);}catch(_){ }
	});
	_detailMenuHandlers=[];
}

function buildProfileFormDefinitions(){
	const defs=[
		{
			key:'names',
			triggerId:'profileEditNamesItem',
			modalId:'profileDetailNames',
			endpoint:detailsApi.names,
			title:'Editar nombres y apellidos',
			successMessage:'Nombres actualizados.',
			getFields:()=>[
				{type:'text', name:'name', label:'Nombres', value:_profileState.name||'', required:true, maxLength:255, placeholder:'Ej. Ana María'},
				{type:'text', name:'lastname', label:'Apellidos', value:_profileState.lastname||'', required:true, maxLength:255, placeholder:'Ej. Pérez Gómez'}
			],
			onSuccess:fields=>{
				updateProfileState({ name: fields.name || '', lastname: fields.lastname || '' });
				setFieldDisplay('name', fields.name);
				setFieldDisplay('lastname', fields.lastname);
				refreshFullName(fields.full_name);
			}
		},
		{
			key:'gender',
			triggerId:'profileEditGenderItem',
			modalId:'profileDetailGender',
			endpoint:detailsApi.gender,
			title:'Actualizar género',
			successMessage:'Género actualizado.',
			getFields:()=>[
				{type:'select', name:'gender', label:'Género', value:(_profileState.gender||'').toLowerCase(), required:true, options:[
					{value:'', label:'Selecciona una opción'},
					{value:'masculino', label:'Masculino'},
					{value:'femenino', label:'Femenino'},
				]}
			],
			onSuccess:fields=>{
				const genderVal=(fields.gender||'');
				updateProfileState({ gender: genderVal });
				setFieldDisplay('gender', fields.gender_label || formatGenderLabel(genderVal));
			}
		},
		{
			key:'birthdate',
			triggerId:'profileEditBirthdateItem',
			modalId:'profileDetailBirthdate',
			endpoint:detailsApi.birthdate,
			title:'Actualizar fecha de nacimiento',
			successMessage:'Fecha de nacimiento actualizada.',
			getFields:()=>[
				{type:'date', name:'birthdate', label:'Fecha de nacimiento', value:_profileState.birthdate||'', required:true, max:new Date().toISOString().slice(0,10)}
			],
			onSuccess:fields=>{
				const iso=fields.birthdate || '';
				updateProfileState({ birthdate: iso });
				setFieldDisplay('birthdate', iso ? formatDateForDisplay(iso) : '');
				const ageDisplay=calculateAgeDisplay(iso, fields.age);
				setFieldDisplay('age', ageDisplay);
			}
		},
		{
			key:'location',
			triggerId:'profileEditLocationItem',
			modalId:'profileDetailLocation',
			endpoint:detailsApi.location,
			title:'Actualizar ubicación',
			successMessage:'Ubicación actualizada.',
			getFields:()=>[
				{type:'text', name:'location', label:'Ubicación', value:_profileState.location||'', required:true, maxLength:255, placeholder:'Ciudad, provincia'}
			],
			onSuccess:fields=>{
				updateProfileState({ location: fields.location || '' });
				setFieldDisplay('location', fields.location);
			}
		}
	];
	if (_profileState.isProfessional) {
		defs.push({
			key:'speciality',
			triggerId:'profileEditSpecialityItem',
			modalId:'profileDetailSpeciality',
			endpoint:detailsApi.speciality,
			title:'Actualizar especialidad',
			successMessage:'Especialidad actualizada.',
			getFields:()=>[
				{type:'text', name:'speciality', label:'Especialidad', value:_profileState.speciality||'', required:true, maxLength:255, placeholder:'Ej. Psicología clínica'}
			],
			onSuccess:fields=>{
				updateProfileState({ speciality: fields.speciality || '' });
				setFieldDisplay('speciality', fields.speciality);
			}
		});
	}
	return defs;
}

function openProfileDetailModal(def){
	if (typeof modalConfirm !== 'function') return;
	const modalId = def.modalId;
	const formId = `${modalId}_form`;
	const errorId = `${modalId}_error`;
	const fields = typeof def.getFields === 'function' ? def.getFields() : (def.fields || []);
	const description = def.description ? `<p class="text-muted small mb-3">${def.description}</p>` : '';
	const bodyHtml = `${description}<form id="${formId}" novalidate>${fields.map(renderProfileField).join('')}<div class="alert alert-danger d-none" id="${errorId}"></div></form>`;
	modalConfirm({
		title: def.title || 'Editar',
		body: bodyHtml,
		btnsType: 'ac',
		confirmLabel: def.confirmLabel || 'Guardar',
		cancelLabel: def.cancelLabel || 'Cancelar',
		closeClick: false,
		modalId,
		onClickYes: async ()=>{
			const form=document.getElementById(formId);
			const errorBox=document.getElementById(errorId);
			const confirmBtn=document.getElementById(`modalConfirmBtn_${modalId}`);
			if(!form) return;
			errorBox?.classList.add('d-none');
			if(confirmBtn) confirmBtn.disabled=true;
			const formData=new FormData(form);
			const payload={};
			fields.forEach(field=>{
				const raw=formData.get(field.name);
				payload[field.name]=typeof raw==='string'?raw.trim():raw;
			});
			try{
				const response=await axios.post(def.endpoint, payload);
				if(response?.data?.ok){
					const returnedFields=response.data.fields || {};
					if(typeof def.onSuccess === 'function') def.onSuccess(returnedFields, response.data);
					if(typeof modalNotification === 'function') modalNotification('Perfil', def.successMessage || 'Datos actualizados',{template:'success'});
					const modalEl=document.getElementById(modalId);
					if(modalEl){
						try{ (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide(); }catch(_){ }
					}
				} else {
					const msg=response?.data?.message || 'No se pudo guardar.';
					displayProfileFormError(errorBox, msg);
				}
			}catch(err){
				const msg=extractProfileErrorMessage(err) || 'No se pudo guardar.';
				displayProfileFormError(errorBox, msg);
			} finally {
				if(confirmBtn) confirmBtn.disabled=false;
			}
		}
	}, 'normal', { size: 'md' });
}

function renderProfileField(field){
	const label=field.label ? `<label class="form-label" for="${field.name}">${field.label}</label>` : '';
	const help=field.help ? `<div class="form-text">${field.help}</div>` : '';
	const commonAttrs=[
		field.required ? 'required' : '',
		field.maxLength ? `maxlength="${field.maxLength}"` : '',
		field.placeholder ? `placeholder="${escapeHtml(field.placeholder)}"` : '',
		field.autofocus ? 'autofocus' : '',
		field.min ? `min="${typeof field.min==='function'?field.min():field.min}"` : '',
		field.max ? `max="${typeof field.max==='function'?field.max():field.max}"` : ''
	].filter(Boolean).join(' ');
	let control='';
	if(field.type==='select'){
		const options=(field.options||[]).map(opt=>{
			const val=opt.value ?? '';
			const isSelected = (field.value ?? '') === val;
			return `<option value="${escapeHtml(String(val))}"${isSelected?' selected':''}>${escapeHtml(String(opt.label ?? ''))}</option>`;
		}).join('');
		control=`<select class="form-select" id="${field.name}" name="${field.name}" ${commonAttrs}>${options}</select>`;
	} else {
		const type=field.type==='date' ? 'date' : 'text';
		const value=field.value ?? '';
		control=`<input type="${type}" class="form-control" id="${field.name}" name="${field.name}" value="${escapeHtml(String(value))}" ${commonAttrs}>`;
	}
	return `<div class="mb-3">${label}${control}${help}</div>`;
}

function displayProfileFormError(errorBox, message){
	if(!errorBox) return;
	errorBox.textContent=message;
	errorBox.classList.remove('d-none');
}

function extractProfileErrorMessage(err){
	const resp=err?.response;
	if(!resp){ return 'Error de red. Inténtalo de nuevo.'; }
	if(resp.data?.errors){
		const firstKey=Object.keys(resp.data.errors)[0];
		if(firstKey) return resp.data.errors[firstKey][0];
	}
	return resp.data?.message || 'No se pudo guardar.';
}

function updateProfileState(patch){
	Object.entries(patch).forEach(([key,val])=>{
		_profileState[key]=val ?? '';
		if(!_profileMetaEl) return;
		if(val===undefined || val===null || val===''){
			delete _profileMetaEl.dataset[key];
		} else {
			_profileMetaEl.dataset[key]=val;
		}
	});
}

const PROFILE_FIELD_FALLBACKS={
	name:'No especificado',
	lastname:'No especificado',
	gender:'No especificado',
	birthdate:'No especificada',
	age:'',
	location:'No especificada',
	speciality:'No especificada',
	full_name:'Sin nombre'
};

function setFieldDisplay(field, value){
	const nodes=document.querySelectorAll(`[data-profile-field="${field}"]`);
	if(!nodes.length) return;
	const fallback=PROFILE_FIELD_FALLBACKS[field] ?? '—';
	const resolved=(value !== undefined && value !== null && String(value).trim() !== '') ? String(value) : fallback;
	nodes.forEach(node=>{ node.textContent=resolved; });
}

function refreshFullName(fullName){
	let value=fullName && String(fullName).trim();
	if(!value){
		value=[_profileState.name||'', _profileState.lastname||''].map(v=>String(v).trim()).filter(Boolean).join(' ').trim();
	}
	if(!value) value=_profileState.email || 'Sin nombre';
	setFieldDisplay('full_name', value);
}

function formatDateForDisplay(dateStr){
	if(!dateStr) return '';
	const date=new Date(dateStr);
	if(Number.isNaN(date.getTime())) return dateStr;
	try {
		return new Intl.DateTimeFormat('es-ES', { day:'2-digit', month:'long', year:'numeric' }).format(date);
	} catch (_) {
		return date.toISOString().slice(0,10);
	}
}

function calculateAgeDisplay(dateStr, providedAge){
	if(typeof providedAge === 'number' && providedAge >= 0) return `${providedAge} años`;
	if(!dateStr) return '';
	const birth=new Date(dateStr);
	if(Number.isNaN(birth.getTime())) return '';
	const today=new Date();
	let age=today.getFullYear()-birth.getFullYear();
	const m=today.getMonth()-birth.getMonth();
	if(m<0 || (m===0 && today.getDate()<birth.getDate())){ age--; }
	return age>=0 ? `${age} años` : '';
}

function formatGenderLabel(value){
	if(!value) return 'No especificado';
	const map={
		masculino:'Masculino',
		femenino:'Femenino',
		'no binario':'No binario',
		otro:'Otro',
		'prefiero no decir':'Prefiero no decir'
	};
	const key=String(value).toLowerCase();
	return map[key] || (key.charAt(0).toUpperCase()+key.slice(1));
}
