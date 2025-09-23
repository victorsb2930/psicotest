// Globals: jQuery, Bootstrap, Axios, tom-select, uuid, global functions, utils
import './bootstrap';
import './partials/quickLogin';

/* 
* Agrego aqui tambien la configuracion global CSRF => CSRF token mismatch.
*/

$.ajaxSetup({
	headers: {
		'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
	}
});

// Carga condicional de assets por página (opcional)
const page = document.body.dataset.page || 'default';
switch (page) {
    case 'index':
        import('../css/index.css');
        import('./index.js');
        break;
	case 'loginRegister':
		import('../css/loginRegister.css');
		import('./loginRegister.js');
		break;
	case 'contact':
		import('../css/contact.css');
		import('./contact.js');
		break;
	// Agrega más casos según sea necesario
	case 'admin.roles':
		import('./pages/admin.roles.js');
		break;
	case 'admin-professional-apps':
		import('./pages/admin.profapps.js');
		break;
	default:
		break;
}