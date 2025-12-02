// Hybrid CTA module (exports init/destroy)
const NS = '.indexPage';
let waitInterval = null;

function isDesktop() { return window.matchMedia('(min-width: 768px)').matches; }

function handleCtaClick(e) {
	if (isDesktop() && modalConfirm) {
		e.preventDefault();
		const bodyHtml = {
			title: 'Iniciar sesión',
				body: `
				<form id="quickLoginFormLocal" action="/login" method="POST">
					<input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">
					<div class="mb-3">
						<label for="quick_email_local" class="form-label">Email</label>
						<input type="email" class="form-control" id="quick_email_local" name="email" autocomplete="email" placeholder="Email">
					</div>
					<div class="mb-3">
						<label for="quick_password_local" class="form-label">Contraseña</label>
						<input type="password" class="form-control" id="quick_password_local" name="password" autocomplete="current-password" placeholder="Contraseña">
					</div>
					<div class="d-flex justify-content-between align-items-center">
						<a class="small text-decoration-underline" href="/welcome#forgot">¿Olvidaste tu contraseña?</a>
						<a class="small text-decoration-underline" href="/welcome#registro">Crear cuenta</a>
					</div>
				</form>
			`,
			btnsType: 'ac',
			onClickYes: async () => {
				const $form = $('#quickLoginFormLocal');
				const email = $('#quick_email_local').val()?.toString().trim();
				const password = $('#quick_password_local').val()?.toString().trim();
				if (!email || !password) {
					modalNotification('Completa los campos', 'Ingresa email y contraseña.', { template: 'warning' });
					return;
				}
				const url = $form.attr('action');
				try {
						const fd = new FormData($form[0]); fd.set('email', email);
						try {
							const pubResp = await fetch('/auth/public-key');
							const pubJson = await pubResp.json();
							const pub = pubJson.public_key;
							if (pub) {
								const b64 = pub.replace(/-----BEGIN PUBLIC KEY-----/g,'').replace(/-----END PUBLIC KEY-----/g,'').replace(/\s+/g,'');
								const raw = Uint8Array.from(atob(b64), c=>c.charCodeAt(0));
								// Use SHA-1 for OAEP to match server-side OpenSSL default
								const key = await crypto.subtle.importKey('spki', raw.buffer, { name: 'RSA-OAEP', hash: 'SHA-1' }, false, ['encrypt']);
								const encBuf = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, key, new TextEncoder().encode(password));
								const encB64 = btoa(String.fromCharCode(...new Uint8Array(encBuf)));
								fd.set('password_enc', encB64);
							} else {
								fd.set('password', password);
							}
						} catch (_) { fd.set('password', password); }
					const res = await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
					if (res?.data && res.data.rejected && res.data.redirect) {
						try { if (res.data.notes) modalNotification('Solicitud rechazada', window.escapeHtml(String(res.data.notes)), { template: 'warning' }, false); } catch(_){}
						window.location.href = res.data.redirect; return;
					}
					if (res?.data && res.data.under_review && res.data.redirect) { window.location.href = res.data.redirect; return; }
					const target = res?.data?.redirect || '/'; window.location.href = target;
				} catch (err) {
					const res = err?.response; const status = res?.status; const isNetwork = !!err?.isAxiosError && !res; const isServer = typeof status === 'number' && status >= 500;
					let respData = res?.data;
					if (typeof respData === 'string') {
						try { respData = JSON.parse(respData); } catch(_) { }
					}
					if (respData?.under_review && respData?.redirect) { try { window.__isAuth = true; } catch(_){}; try { if (typeof updateHeaderCTA === 'function') updateHeaderCTA(); } catch(_){}; try { if (typeof startHeartbeat === 'function') startHeartbeat(60); } catch(_){}; window.location.href = respData.redirect; return; }
					if (respData?.rejected && respData?.redirect) {
						try { if (respData.notes) modalNotification('Solicitud rechazada', window.escapeHtml(String(respData.notes)), { template: 'warning' }, false); } catch(_){}
						try { window.__isAuth = true; } catch(_){ }
						try { if (typeof updateHeaderCTA === 'function') updateHeaderCTA(); } catch(_){ }
						try { if (typeof startHeartbeat === 'function') startHeartbeat(60); } catch(_){ }
						window.location.href = respData.redirect; return;
					}
					let message = 'Email o contraseña incorrectos.';
					if (respData && typeof respData === 'object') {
						if (respData.errors && typeof respData.errors === 'object') {
							const firstKey = Object.keys(respData.errors)[0];
							const firstMsg = Array.isArray(respData.errors[firstKey]) ? respData.errors[firstKey][0] : respData.errors[firstKey];
							message = firstMsg || respData.message || message;
						} else if (respData.message) message = respData.message; else try { message = JSON.stringify(respData); } catch (_) {}
					} else if (res && typeof res.data === 'string') message = res.data; else if (isNetwork && err?.message) message = err.message;
					const severe = isNetwork || isServer; const title = severe ? 'Error del servidor' : 'Error de acceso'; const template = severe ? 'danger' : 'warning';
					const detailCfg = { xhr: res, fncErr: 'quickLogin', page: 'index', body: 'Error al iniciar sesión' };
					modalNotification(title, window.escapeHtml(String(message)), { template, delayAutoClose: 6000 }, severe, detailCfg);
				}
			},
			closeClick: false
		};
		modalConfirm(bodyHtml, 'normal', { centered: true, scrollable: false, size: ''});
	setTimeout(() => document.getElementById('quick_email_local')?.focus(), 150);
	}
}

// --- Mental test wizard (page-scoped, uses modalConfirm but does NOT modify modalConfirm.js) ---
function _buildQuestionHtml(idx, question, choices) {
	const name = `mtq_${idx}`;
	const parts = choices.map((c, i) => {
		const id = `${name}_opt_${i}`;
		return `<div class="form-check text-start">
			<input class="form-check-input" type="radio" name="${name}" id="${id}" value="${i}">
			<label class="form-check-label" for="${id}">${window.escapeHtml ? window.escapeHtml(c) : c}</label>
		</div>`;
	}).join('');
	return `<div id="mtq_container_${idx}">
		<p class="mb-3">${window.escapeHtml ? window.escapeHtml(question) : question}</p>
		<div class="mb-2">${parts}</div>
	</div>`;
}

function _interpretScore(score, max) {
	const pct = (score / (max || 1)) * 100;
	if (pct < 20) return { label: 'Mínima', advice: 'Tus respuestas indican pocas dificultades actuales.' };
	if (pct < 50) return { label: 'Leve', advice: 'Podrías beneficiarte de seguimiento o autocuidado.' };
	if (pct < 75) return { label: 'Moderada', advice: 'Considera consultar con un profesional para evaluación.' };
	return { label: 'Severa', advice: 'Se recomienda solicitar atención profesional pronto.' };
}

function showMentalTestWizard() {
	try {
		const questions = [
			'En las últimas dos semanas, ¿con qué frecuencia has sentido poco interés o placer en hacer cosas?',
			'En las últimas dos semanas, ¿con qué frecuencia te has sentido decaído, deprimido o sin esperanzas?',
			'¿Con qué frecuencia has tenido dificultades para conciliar el sueño o te has despertado muy temprano?',
			'¿Con qué frecuencia te has sentido cansado o con poca energía?',
			'¿Con qué frecuencia has tenido dificultad para concentrarte en cosas, como leer el periódico o ver la televisión?'
		];
		const choices = ['Nunca', 'A veces', 'Frecuentemente', 'Siempre'];
		const answers = new Array(questions.length).fill(null);
		let currentIndex = 0;
		const modalId = `mentalTestWizard-${Date.now()}`;

		function showInlineError($modal, msg) {
			try {
				const $body = $modal.find(`#modalBody${modalId}`);
				$body.find('.mt-error').remove();
				const safe = (window.escapeHtml ? window.escapeHtml(msg) : msg);
				$body.find(`#mtq_container_${currentIndex}`).append(`<div class="mt-error text-danger small mt-2">${safe}</div>`);
			} catch (_) { }
		}

		function renderStep($modal, i) {
			currentIndex = i;
			const total = questions.length;
			// update title and body
			$modal.find(`#modalTitle${modalId}`).text(`Pregunta ${i + 1} de ${total}`);
			$modal.find(`#modalBody${modalId}`).html(_buildQuestionHtml(i, questions[i], choices));
			// remove any previous error
			$modal.find('.mt-error').remove();
			// pre-select previous answer if exists
			if (answers[i] !== null && typeof answers[i] !== 'undefined') {
				const sel = $modal.find(`#modalBody${modalId} input[name=mtq_${i}][value='${answers[i]}']`);
				if (sel && sel.length) sel.prop('checked', true);
			}
			// update buttons state
			const $back = $modal.find(`#modalBtn_${modalId}_1`);
			const $next = $modal.find(`#modalBtn_${modalId}_2`);
			if ($back && $back.length) {
				if (i === 0) { $back.prop('disabled', true).addClass('disabled'); } else { $back.prop('disabled', false).removeClass('disabled'); }
			}
			if ($next && $next.length) {
				if (i === total - 1) { $next.text('Terminar'); } else { $next.text('Siguiente'); }
			}
			// focus first input
			setTimeout(() => { try { $modal.find(`#modalBody${modalId} input[type=radio]`).first().trigger('focus'); } catch(_){} }, 60);
		}

		const buttons = [
			{ text: 'Cancelar', className: 'btn-outline-secondary', dismiss: true },
			{ text: 'Atrás', className: 'btn-secondary', closeOnClick: false, onClick: function ($modal) { try { if (currentIndex > 0) renderStep($modal, currentIndex - 1); } catch (_) {} } },
			{ text: 'Siguiente', className: 'btn-primary', closeOnClick: false, onClick: function ($modal) {
				try {
					const sel = $modal.find(`#modalBody${modalId} input[name=mtq_${currentIndex}]:checked`);
					if (!sel || sel.length === 0) {
						showInlineError($modal, 'Por favor seleccioná una opción para continuar.');
						return;
					}
					answers[currentIndex] = Number(sel.val() || 0);
					// if last, show results inside the same modal
					if (currentIndex === questions.length - 1) {
						// More dynamic, domain-based interpretation and several suggestion templates
						const vals = answers.map(v => Number(v || 0));
						// Domains: mood (q0,q1), sleep (q2), energy (q3), cognition (q4)
						const domain = {
							mood: (vals[0] || 0) + (vals[1] || 0), // max 6
							sleep: (vals[2] || 0), // max 3
							energy: (vals[3] || 0), // max 3
							cognition: (vals[4] || 0) // max 3
						};

						const domainMax = { mood: 6, sleep: 3, energy: 3, cognition: 3 };

						function pctKey(v, max) {
							const p = (v / (max || 1)) * 100;
							if (p >= 75) return 'marked';
							if (p >= 50) return 'moderate';
							if (p >= 25) return 'mild';
							return 'none';
						}

						const domainSeverity = {};
						Object.keys(domain).forEach(k => { domainSeverity[k] = pctKey(domain[k], domainMax[k]); });

						// Determine dominant domain (by percent of max)
						let dominant = Object.keys(domain).map(k => ({ k, pct: (domain[k] / domainMax[k]) })).sort((a,b) => b.pct - a.pct)[0].k;

						// Templates per domain + severity (arrays to vary phrasing)
						const templates = {
							mood: {
								marked: [
									'Es posible que estés experimentando síntomas significativos relacionados con el estado de ánimo o ansiedad. Recomendamos solicitar una evaluación profesional para una orientación precisa.'
								],
								moderate: [
									'Se observan signos de preocupación emocional o estrés sostenido. Considerá técnicas de autocuidado y, si persisten, consultá con un profesional.'
								],
								mild: [
									'Hay indicios leves de malestar emocional. Actividades de autocuidado y seguimiento suelen ser útiles.'
								],
								none: [
									'No se detectan señales claras de malestar emocional en este breve screening.'
								]
							},
							sleep: {
								marked: [
									'Existen síntomas relevantes de alteración del sueño que podrían estar afectando tu bienestar. La higiene del sueño y, si no mejora, la consulta con un especialista son recomendables.'
								],
								moderate: [
									'Estás mostrando dificultades para dormir de forma recurrente. Prueba rutinas regulares y técnicas de relajación antes de dormir.'
								],
								mild: [
									'Algunas molestias en el sueño están presentes; mejorar hábitos nocturnos suele ayudar.'
								],
								none: [
									'No hay signos significativos de problemas de sueño en este cuestionario.'
								]
							},
							energy: {
								marked: [
									'Fatiga persistente y baja energía pueden indicar sobrecarga o condiciones médicas subyacentes. Considerá evaluación médica y apoyo profesional.'
								],
								moderate: [
									'Poca energía o cansancio recurrente: actividad física moderada y pausas regulares pueden mejorar tu vitalidad.'
								],
								mild: [
									'Alguna reducción de energía observada; pequeños cambios en actividad y sueño pueden ser útiles.'
								],
								none: [
									'No se observan signos relevantes de fatiga en este screening.'
								]
							},
							cognition: {
								marked: [
									'Dificultades notables de concentración pueden impactar el rendimiento diario; técnicas de organización y evaluación profesional pueden ser necesarias.'
								],
								moderate: [
									'Dificultad para concentrarte con cierta frecuencia: fragmentar tareas y reducir distracciones es recomendable.'
								],
								mild: [
									'Leves dificultades de atención; estructura y descansos breves suelen ayudar.'
								],
								none: [
									'No se detectan dificultades de concentración importantes en este screening.'
								]
							}
						};

						// Suggestions pool per domain and severity (multiple strings to vary)
						const suggestionsPool = {
							mood: {
								marked: [
									'Contactate con un profesional de salud mental para evaluación y seguimiento.',
									'Evita el aislamiento: mantener contactos de apoyo puede ser un primer paso importante.'
								],
								moderate: [
									'Lleva un diario de estado de ánimo para identificar patrones.',
									'Practica técnicas de respiración y pausas programadas en tu día.'
								],
								mild: [
									'Hablar con alguien de confianza sobre cómo te sientes puede aliviar.',
									'Dedica 10-15 minutos diarios a actividades que disfrutes.'
								],
								none: [
									'Mantener hábitos emocionales saludables: sueño, actividad física y relaciones sociales.'
								]
							},
							sleep: {
								marked: [
									'Establece horarios fijos de sueño y evita cafeína por la tarde.',
									'Crea una rutina relajante (lectura ligera, ducha tibia) antes de acostarte.'
								],
								moderate: [
									'Reduce el uso de pantallas 60 minutos antes de dormir.',
									'Mantén una temperatura agradable y ambiente oscuro en el dormitorio.'
								],
								mild: [
									'Prueba técnicas de relajación breve antes de dormir (respiración 4-4-8).'
								],
								none: [
									'Sigue manteniendo buenas prácticas de sueño.'
								]
							},
							energy: {
								marked: [
									'Evaluá posibles causas médicas con tu médico si la fatiga es marcada.',
									'Integrá actividad física regular y revisá tu alimentación.'
								],
								moderate: [
									'Incorpora pausas activas y caminatas cortas durante el día.',
									'Revisa la calidad del sueño y la hidratación como factores clave.'
								],
								mild: [
									'Pequeños cambios en rutina y descanso pueden mejorar la energía.'
								],
								none: [
									'Buen nivel de energía aparente; mantiene hábitos saludables.'
								]
							},
							cognition: {
								marked: [
									'Considera estrategias de compensación (listas, recordatorios) y valoración profesional si interfiere con el trabajo.'
								],
								moderate: [
									'Divide tareas en pasos pequeños y usa la técnica Pomodoro (25/5).'
								],
								mild: [
									'Mejora ambiente de trabajo reduciendo ruidos y distracciones.'
								],
								none: [
									'Atención en rango esperado; continua con buenas prácticas de organización.'
								]
							}
						};

						// Helper to pick up to N suggestions from pool with randomness
						function pickSuggestions(dom, sev, n) {
							const pool = (suggestionsPool[dom] && suggestionsPool[dom][sev]) || [];
							const general = ['Practica respiración 3–5 minutos al día.', 'Realizá pausas activas y mantené contacto social.', 'Si las dificultades aumentan, consultá con un profesional.'];
							const chosen = [];
							// shuffle pool copy
							const copy = pool.slice();
							while (copy.length && chosen.length < n) {
								const idx = Math.floor(Math.random() * copy.length);
								chosen.push(copy.splice(idx,1)[0]);
							}
							// complement with general tips if not enough
							let gcopy = general.slice();
							while (chosen.length < n && gcopy.length) {
								const idx = Math.floor(Math.random() * gcopy.length);
								chosen.push(gcopy.splice(idx,1)[0]);
							}
							return chosen;
						}

						const sev = domainSeverity[dominant];
						const mainArr = (templates[dominant] && templates[dominant][sev]) || ['Observaciones relevantes identificadas.'];
						const mainMessage = mainArr[Math.floor(Math.random() * mainArr.length)];

						// Pick up to 3 suggestions focused on dominant domain plus one cross-domain tip
						const tailored = pickSuggestions(dominant, sev, 3);

						const safeMain = window.escapeHtml ? window.escapeHtml(mainMessage) : mainMessage;
						const sugHtml = tailored.map(s => `<li>${window.escapeHtml ? window.escapeHtml(s) : s}</li>`).join('');

						$modal.find(`#modalTitle${modalId}`).text('Interpretación y recomendaciones');
						$modal.find(`#modalBody${modalId}`).html(`<div class="p-2">
							<p class="mb-2">${safeMain}</p>
							<h6 class="mt-3">Sugerencias prácticas</h6>
							<ul class="small mb-0">${sugHtml}</ul>
							<p class="mt-3 text-muted small">Este cuestionario es orientativo y no reemplaza una evaluación profesional.</p>
						</div>`);

						// replace footer with single Close button
						const wrapper = `<div class="px-4 pb-4 d-flex justify-content-end"><div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button></div></div>`;
						$modal.find(`#modalBody${modalId}`).nextAll().remove();
						$modal.find('.modal-content').append(wrapper);
						return;
					}
					// otherwise advance
					renderStep($modal, currentIndex + 1);
				} catch (e) { /* ignore and do nothing */ }
			} }
		];

		// create modal once
		modalConfirm({ modalId, title: `Pregunta 1 de ${questions.length}`, body: _buildQuestionHtml(0, questions[0], choices), buttons }, 'normal', { centered: true, scrollable: false });
		// after created, render initial state and ensure Back is disabled
		setTimeout(() => {
			const $m = $(`#${modalId}`);
			if ($m && $m.length) renderStep($m, 0);
		}, 80);

	} catch (e) { /* fail silently */ }
}

// --- end mental test wizard ---

function attachHandlers() {
	// Avoid attaching local quick-login handler if a global handler is present
	if (window.__quickLoginGlobalAttached) {
		// still bind other handlers but skip quick-login click
		const $cta = $(".site-header .btn-cta[href='/welcome']");
		$cta.off('click' + NS).on('click' + NS, function(e){ /* delegated to global quickLogin */ });
	} else {
		const $cta = $(".site-header .btn-cta[href='/welcome']");
		$cta.off('click' + NS).on('click' + NS, handleCtaClick);
	}
	$(document).off('click' + NS).on('click' + NS, 'a[href*="/welcome#registro"]', function(){});

	// Bind mental test button in the hero (if present)
	try {
		$('#btn-mental-test').off('click' + NS).on('click' + NS, function(e){
			e.preventDefault();
			try { showMentalTestWizard(); } catch (_) { }
		});
	} catch (_) {}
	$(document).off('submit' + NS).on('submit' + NS, '#quickLoginFormLocal', function(e){ e.preventDefault(); });
}

function detachHandlers() {
	try { $(".site-header .btn-cta[href='/welcome']").off('click' + NS); } catch (_) {}
	try { $(document).off(NS); } catch (_) {}
	if (waitInterval) { clearInterval(waitInterval); waitInterval = null; }
	// Remove quickLogin modal if present
	try {
	const $m = $('.modal').has('#quickLoginFormLocal');
		$m.each(function(){ const inst = bootstrap.Modal.getInstance(this); inst?.hide(); $(this).remove(); });
	} catch (_) {}

	// Unbind mental test button
	try { $('#btn-mental-test').off('click' + NS); } catch (_) {}
}

export function init() { attachHandlers(); }
export function destroy() { detachHandlers(); }
