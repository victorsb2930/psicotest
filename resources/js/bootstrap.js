//#region JQuery
import $ from 'jquery';
window.$ = window.jQuery = $;
//#endregion

//#region Bootstrap JS 5.3.1
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
//#endregion

//#region Axios
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// CSRF header from meta tag (for Laravel)
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrf) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}
//#endregion

// Provide a small global helper to get an authoritative "now" using server time
// if the server injected a `meta[name="server-time-ms"]` tag (epoch ms).
try {
	const serverMsRaw = document.querySelector('meta[name="server-time-ms"]')?.getAttribute('content');
 	const serverMs = serverMsRaw ? parseInt(serverMsRaw, 10) : NaN;
 	let serverTimeOffsetMs = 0;
 	if (!Number.isNaN(serverMs)) {
 		serverTimeOffsetMs = serverMs - Date.now();
 	}
 	window.serverTimeOffsetMs = serverTimeOffsetMs;
 	window.getServerNow = function() {
 		return new Date(Date.now() + (window.serverTimeOffsetMs || 0));
 	};
} catch (e) {
 	window.serverTimeOffsetMs = 0;
 	window.getServerNow = function() { return new Date(); };
}
//#endregion

//#region Tom Select
import TomSelect from 'tom-select';
window.TomSelect = TomSelect;
//#endregion

//#region UUID
import { v7 as uuidv7 } from 'uuid';
window.uuidv7 = uuidv7;
//#endregion

//#endregion Globals functions
// Utilidad global para escape de HTML reutilizable en toda la app
window.escapeHtml = function (str) {
	return String(str ?? '')
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#039;');
};
//#endregion

//#region Utils
import { modalConfirm as _modalConfirm } from './utils/modalConfirm';
import { modalNotification as _modalNotification } from './utils/modalNotification';
window.modalConfirm = _modalConfirm;
window.modalNotification = _modalNotification;
window.NotificationDefaults = _modalNotification.defaults;
window.ModalDefaults = _modalConfirm.defaults;
//#endregion
