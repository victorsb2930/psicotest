//#region Variables globales
//#endregion

import { modalConfirm } from "./utils/modalConfirm";

//#region Constantes globales
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const fieldsToCheck = ['reg_titulo', 'reg_cedula', 'reg_cv', 'reg_exequatur'];
//#endregion


//#region DOCUMENT READY
// Export init/destroy so PJAX loader can manage lifecycle
const NS = '.loginRegister';

export function init() {
	setElements();
	setEvents();
	initFromHash();
	showServerMessages();
}

export function destroy() {
	try { $(window).off('hashchange.loginreg'); } catch (_) {}
	try { $('#register_form').off('submit'); $('#register_form').off('input change'); } catch(_){}
}
//#endregion

//#region Funciones auxiliares

//#region markField
// Marca un campo de formulario como válido o inválido (añade clases .is-valid/.is-invalid al contenedor .type)
function markField($input, ok) {
	const $wrap = $input.closest('.type');
	$wrap.toggleClass('is-valid', !!ok).toggleClass('is-invalid', !ok);
}
//#endregion

//#region toggleProBlocks
// Muestra/oculta los bloques de campos para profesionales según isPro (booleano)
function toggleProBlocks(isPro) {
	const $proBlocks = $('.professional-only');
	$proBlocks.toggleClass('d-none', !isPro);
	$('#reg_type').closest('.type').removeClass('is-valid is-invalid');
	if (!isPro) {
		$proBlocks.find('.type').removeClass('is-valid is-invalid');
		const fieldsToClear = ['reg_titulo', 'reg_cedula', 'reg_cv', 'reg_exequatur'];
		fieldsToClear.forEach(fldId => {
			$(`#${fldId}`).val('');
		});
	} else {
		fieldsToCheck.forEach(fldId => {
			const $fld = $(`#${fldId}`);
			markField($fld, ($fld[0]?.files?.length || 0) > 0);
		});
	}
}
//#endregion

//#region resetRegisterForm
function resetRegisterForm() {
	try {
		const $form = $('#register_form');
		if ($form.length) {
			$form[0].reset();
			$form.find('.type').removeClass('is-valid is-invalid');
		}
		const el = document.getElementById('reg_type');
		if (el && el.tomselect) {
			el.tomselect.setValue('3', true); // Usuario simple por defecto
			el.tomselect.blur();
		}
		// Asegura ocultar bloques de profesional y limpiar archivos
		toggleProBlocks(false);
		const fieldsToClear = ['reg_titulo', 'reg_cedula', 'reg_cv', 'reg_exequatur'];
		fieldsToClear.forEach(fldId => {
			$(`#${fldId}`).val('');
		});
	} catch (_) {}
}
//#endregion

//#region Funciones principales
// Resetea el formulario de registro a estado inicial

//#region showServerMessages
// Muestra notificaciones provenientes del servidor (flash session / errors)
function showServerMessages() {
	try {
		const flash = window.__flash;
		if (!flash) return;
		if (flash.success) {
			modalNotification('Registro exitoso', flash.success, { template: 'success' });
			resetRegisterForm();
		}
		if (Array.isArray(flash.errors) && flash.errors.length) {
			const html = '<ul class="mb-0 ps-3">' + flash.errors.map(e => `<li>${window.escapeHtml(e)}</li>`).join('') + '</ul>';
			modalNotification('Corrige el formulario', html, { template: 'warning' }, false);
		}
	} catch (_) {}
}
//#endregion

//#region setElements
// Inicializa elementos de la página (Tom Select, etc.)
function setElements(){
	// DropDown con Tom Select (sin búsqueda, desde DB)
	const fromDb = Array.isArray(window.__signupRoles) ? window.__signupRoles : [];
	const userTypeOptions = fromDb.length ? fromDb : [
		// fallback si DB no define opciones
		{ value: '3', text: 'Usuario simple', slug: 'user', requires_docs: false },
		{ value: '2', text: 'Profesional', slug: 'professional', requires_docs: true },
	];
	const $select = $('#reg_type');
	const el = $select[0];
	if (!el) return;
	if (el.tomselect) el.tomselect.destroy();
	// Si hay <option> en HTML, los removemos para usar la fuente JSON
	el.innerHTML = '';
	const ts = new TomSelect(el, {
		plugins: [],
		create: false,
		allowEmptyOption: false,
		options: userTypeOptions,
		items: (userTypeOptions[0]?.value ?? '3'),
		searchField: [], // sin búsqueda (son 2 opciones)
		persist: false,
		maxOptions: 50,
		onInitialize: function() {
			// Evita escritura en el control (solo dropdown)
			this.control_input.setAttribute('readonly', 'readonly');
		}
	});
	$select.closest('.type').removeClass('is-valid is-invalid');
	const isPro = (val) => {
		const found = userTypeOptions.find(r => String(r.value) === String(val));
		const slug = String(found?.slug || '').toLowerCase();
		return !!found?.requires_docs || slug === 'professional' || slug === 'profesional';
	};
	$select.off('change.ts').on('change.ts', function () { toggleProBlocks(isPro(this.value)); });
	toggleProBlocks(isPro(ts.getValue()));
}
//#endregion

//#region setEvents
// Asigna eventos a elementos de la página
function setEvents(){
	// Toggle entre login y register
	const $container = $('.login-container');
	const setActive = (isActive, updateHash = true) => { 
		$container.toggleClass('active', !!isActive);
		// Guardar preferencia (active=true => registro, active=false => login)
		try { localStorage.setItem('pg_auth_tab', isActive ? 'registro' : 'login'); } catch(_){}
		if (!updateHash) return;
		try {
			if (isActive) { // REGISTRO
				if (window.location.hash !== '#registro') {
					history.replaceState(null, '', '#registro');
				}
			} else { // LOGIN
				if (window.location.hash) {
					history.replaceState(null, '', window.location.pathname + window.location.search);
				}
			}
		} catch(_){}
	};
	$(".btnSign-in").off('click').on('click', function (e) { e.preventDefault(); setActive(true); });
	$(".btnSign-up").off('click').on('click', function (e) { e.preventDefault(); setActive(false); });

	// Cambia según hash
	$(window).off('hashchange.loginreg').on('hashchange.loginreg', function(){
		applyHash(window.location.hash);
	});

	// Validación simple al enviar el formulario de login
	const setLoginBtn = (disabled, label) => {
		try {
			const btn = document.getElementById('login_submit_btn');
			if (!btn) return;
			btn.disabled = !!disabled;
			if (typeof label === 'string') btn.innerText = label;
		} catch(_){}
	};
	$('#login_form').off('submit').on('submit', function (e) {
		const email = $('#login_email').val()?.toString().trim();
		const password = $('#login_password').val()?.toString().trim();
		if (!email || !password) {
			e.preventDefault();
			modalNotification('Error', 'Por favor ingresa email y contraseña.');
			return;
		}
		// disable button and change label to avoid double submits
		setLoginBtn(true, 'Iniciando...');
		// allow form to submit normally; in case of server validation errors the page
		// will reload with errors; for AJAX flows the axios handlers below will
		// need to re-enable button on errors (handled in quickLogin/other code)
		setTimeout(() => { /* safety: if somehow the form didn't navigate, re-enable after 10s */ setLoginBtn(false, 'Iniciar Sesión'); }, 10000);
	});

	// Live validation en el formulario de registro
	$('#register_form').on('input change', 'input, select', function () {
		const $field = $(this);
		if ($field.attr('id') === 'reg_type') return;
		let ok = true;
		const type = ($field.attr('type') || '').toLowerCase();
		if (type === 'email') ok = emailRegex.test(($field.val() || '').toString().trim());
		else if (type === 'file') ok = ($field[0]?.files?.length || 0) > 0;
		else ok = (($field.val() || '').toString().trim() !== '');
		markField($field, ok);
	});

	// Validación + envío AJAX del formulario de registro
	$('#register_form').off('submit').on('submit', async function (e) {
		e.preventDefault();
		// disable register button immediately to avoid duplicate posts
		try { const rbtn = document.getElementById('register_submit_btn'); if (rbtn) { rbtn.disabled = true; rbtn.innerText = 'Registrando...'; } } catch(_){}
		const $form = $(this);
		const type = $('#reg_type').val();
		const name = $('#reg_name').val().toString().trim();
		const email = $('#reg_email').val().toString().trim();
		const pass = $('#reg_password').val().toString().trim();
		const pass2 = $('#reg_password_confirm').val().toString().trim();

		// Detectar si el rol seleccionado es profesional (requiere documentos),
		// sin depender de IDs concretos (p. ej., "2").
		let isProSelected = false;
		try {
			const fromDb = Array.isArray(window.__signupRoles) ? window.__signupRoles : [];
			if (fromDb.length) {
				const found = fromDb.find(r => String(r.value) === String(type));
				isProSelected = !!found?.requires_docs || found?.slug === 'professional' || found?.slug === 'profesional';
			} else {
				// Fallback a la misma heurística usada en setElements()
				isProSelected = (type === '2');
			}
		} catch(_) {}

		$('#register_form .type').removeClass('is-valid is-invalid');

		let isValid = true;
		const validName = !!name; markField($('#reg_name'), validName); if (!validName) isValid = false;
		const validEmail = !!email && emailRegex.test(email); markField($('#reg_email'), validEmail); if (!validEmail) isValid = false;
		const validPass = !!pass && pass.length >= 6; markField($('#reg_password'), validPass); if (!validPass) isValid = false;
		const validPass2 = pass2 === pass && pass2.length > 0; markField($('#reg_password_confirm'), validPass2); if (!validPass2) isValid = false;
		if (isProSelected) {
			fieldsToCheck.forEach(fldId => {
				const $fld = $(`#${fldId}`);
				const valid = ($fld[0]?.files?.length || 0) > 0;
				markField($fld, valid);
				if (!valid) {
					isValid = false;
					const messages = {
						'reg_titulo': 'Por favor, sube tu título profesional (PDF/JPG/PNG).',
						'reg_cedula': 'Por favor, sube tu cédula escaneada (PDF/JPG/PNG).',
						'reg_cv': 'Por favor, sube tu curriculum vitae (PDF/JPG/PNG).',
						'reg_exequatur': 'Por favor, sube tu documento exequátur (PDF/JPG/PNG).'
					};
					modalNotification(`Falta ${fldId.replace('reg_', '')}`, messages[fldId] || 'Por favor, sube el documento requerido.', { template: 'warning' });
				}
			});
		}

		if (!isValid) {
			modalNotification('Formulario incompleto', 'Corrige los campos marcados en rojo.');
			return;
		}

		const action = $form.attr('action') || '/register';
		const fd = new FormData($form[0]);
		// Try to encrypt password fields client-side
		try {
			const pubResp = await fetch('/auth/public-key');
			const pubJson = await pubResp.json();
			const pub = pubJson.public_key;
			if (pub) {
				const b64 = pub.replace(/-----BEGIN PUBLIC KEY-----/g,'').replace(/-----END PUBLIC KEY-----/g,'').replace(/\s+/g,'');
				const raw = Uint8Array.from(atob(b64), c=>c.charCodeAt(0));
				// Use SHA-1 for OAEP to match server-side OpenSSL default
				const key = await crypto.subtle.importKey('spki', raw.buffer, { name: 'RSA-OAEP', hash: 'SHA-1' }, false, ['encrypt']);
				const encBuf = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, key, new TextEncoder().encode(pass));
				const encB64 = btoa(String.fromCharCode(...new Uint8Array(encBuf)));
				fd.set('reg_password_enc', encB64);
				fd.set('reg_password_confirm_enc', encB64);
				fd.delete('reg_password'); fd.delete('reg_password_confirmation');
			}
		} catch (_) { /* ignore: will send plaintext */ }
	// Let the browser set the multipart Content-Type (including boundary)
	// forcing the header manually removes the boundary and breaks Laravel CSRF/form parsing
	const restoreRegisterBtn = () => {
		try { const rbtn = document.getElementById('register_submit_btn'); if (rbtn) { rbtn.disabled = false; rbtn.innerText = 'Registrarte'; } } catch(_){ }
	};

	window.axios.post(action, fd)
			.then((resp) => {
				const data = resp?.data || {};
				if (data.ok) {
					modalNotification('Registro exitoso', data.message || 'Ahora puedes iniciar sesión.', { template: 'success' });
					// Limpia el formulario y vuelve a la pestaña de login
					resetRegisterForm();
					const $container = $('.login-container');
					setTimeout(() => { $container.toggleClass('active', false); }, 500);
				} else {
					// Caso éxito no-JSON (p.ej. redirect HTML). Muestra aviso genérico.
					modalNotification('Registro enviado', 'Registro completado. Si no ves cambios, recarga la página.', { template: 'info' });
				}
			})
			.catch((err) => {
				const res = err?.response;
				// re-enable register button on error so user can retry
				try { const rbtn = document.getElementById('register_submit_btn'); if (rbtn) { rbtn.disabled = false; rbtn.innerText = 'Registrarte'; } } catch(_){ }
				if (res?.status === 422 && res?.data?.errors) {
					const list = Object.values(res.data.errors).flat();
					const html = '<ul class="mb-0 ps-3">' + list.map(e => `<li>${window.escapeHtml(e)}</li>`).join('') + '</ul>';
					modalNotification('Corrige el formulario', html, { template: 'warning' });
				} else {
					const concise = 'Se produjo un error en el servidor. Haz clic para ver detalles.';
					modalNotification('Error en registro', window.escapeHtml(concise), { template: 'danger' }, true, { xhr: res, fncErr: 'register', page: 'loginRegister' });
				}
			})
			.finally(() => { restoreRegisterBtn(); });

	});
}
//#endregion

//#endregion Funciones auxiliares

// Lee el hash y aplica la pestaña adecuada
function applyHash(hash){
	try {
		const h = (hash || '').toLowerCase();
		const $container = $('.login-container');
		if (!$container.length) return;
		const goRegister = h.includes('registro') || h.includes('signup') || h.includes('registrate') || h.includes('crear');
		const goLogin = h.includes('login') || h.includes('signin') || h.includes('iniciar');
		// En esta vista: active = true => REGISTRO, active = false => LOGIN
		if (goRegister) {
			$container.toggleClass('active', true);
			if (window.location.hash !== '#registro') history.replaceState(null, '', '#registro');
			try { localStorage.setItem('pg_auth_tab', 'registro'); } catch(_){}
		}
		else if (goLogin) {
			$container.toggleClass('active', false);
			if (window.location.hash) history.replaceState(null, '', window.location.pathname + window.location.search);
			try { localStorage.setItem('pg_auth_tab', 'login'); } catch(_){}
		}
		// Si no hay hash o no coincide, no cambiamos el estado actual
	} catch(_){}
}

function initFromHash(){
	const hash = window.location.hash;
	if (hash) {
		applyHash(hash);
		return;
	}
	// Sin hash: usar preferencia guardada
	try {
		const pref = localStorage.getItem('pg_auth_tab');
		const $container = $('.login-container');
		if (!$container.length || !pref) return;
		if (pref === 'registro') $container.toggleClass('active', true);
		else if (pref === 'login') $container.toggleClass('active', false);
	} catch(_){}
}