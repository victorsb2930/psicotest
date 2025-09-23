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