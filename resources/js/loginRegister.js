//#region Variables globales
//#endregion

import { modalConfirm } from "./utils/modalConfirm";

//#region Constantes globales
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
//#endregion


//#region DOCUMENT READY
$(function () {
	setElements();
	setEvents();
	// Sincroniza la vista inicial con el hash (#registro | #login)
	initFromHash();
	showServerMessages();
});
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
		$('#reg_titulo, #reg_cedula').val('');
	} else {
		markField($('#reg_titulo'), ($('#reg_titulo')[0]?.files?.length || 0) > 0);
		markField($('#reg_cedula'), ($('#reg_cedula')[0]?.files?.length || 0) > 0);
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
		$('#reg_titulo').val('');
		$('#reg_cedula').val('');
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
	$('#login_form').off('submit').on('submit', function (e) {
		const email = $('#login_email').val()?.toString().trim();
		const password = $('#login_password').val()?.toString().trim();
		if (!email || !password) {
			e.preventDefault();
			modalNotification('Error', 'Por favor ingresa email y contraseña.');
		}
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
	$('#register_form').off('submit').on('submit', function (e) {
		e.preventDefault();
		const $form = $(this);
		const type = $('#reg_type').val();
		const name = $('#reg_name').val().toString().trim();
		const email = $('#reg_email').val().toString().trim();
		const pass = $('#reg_password').val().toString().trim();
		const pass2 = $('#reg_password_confirm').val().toString().trim();
		const tituloFiles = $('#reg_titulo')[0]?.files?.length || 0;
		const cedulaFiles = $('#reg_cedula')[0]?.files?.length || 0;

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
			const validTitulo = tituloFiles > 0; markField($('#reg_titulo'), validTitulo); if (!validTitulo) { isValid = false; modalNotification('Falta título', 'Por favor, sube tu título profesional (PDF/JPG/PNG).', { template: 'warning' }); }
			const validCedula = cedulaFiles > 0; markField($('#reg_cedula'), validCedula); if (!validCedula) { isValid = false; modalNotification('Falta cédula', 'Por favor, sube tu cédula escaneada (PDF/JPG/PNG).', { template: 'warning' }); }
		}

		if (!isValid) {
			modalNotification('Formulario incompleto', 'Corrige los campos marcados en rojo.');
			return;
		}

		const action = $form.attr('action') || '/register';
		const fd = new FormData($form[0]);
		window.axios.post(action, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
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
				if (res?.status === 422 && res?.data?.errors) {
					const list = Object.values(res.data.errors).flat();
					const html = '<ul class="mb-0 ps-3">' + list.map(e => `<li>${window.escapeHtml(e)}</li>`).join('') + '</ul>';
					modalNotification('Corrige el formulario', html, { template: 'warning' });
				} else {
					const concise = 'Se produjo un error en el servidor. Haz clic para ver detalles.';
					modalNotification('Error en registro', window.escapeHtml(concise), { template: 'danger' }, true, { xhr: res, fncErr: 'register', page: 'loginRegister' });
				}
			});
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