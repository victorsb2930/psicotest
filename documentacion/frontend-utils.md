## `modalConfirm`

Archivo: `resources/js/utils/modalConfirm.js`

Funcionalidad básica:
- Crea o reutiliza un modal Bootstrap y lo muestra.
- Acepta `bodyHtml` con campos: `title`, `subtitle`, `body`, `icon` (o `icona`), `iconColor`, `btnsType` (`ny` o `ac`), `onClickYes` (callback), `closeClick` (boolean), `modalId`.
- Opciones globales en `modalConfirm.defaults` (por ejemplo `draggable`, `size`, `dialogClasses`).

Ejemplo:
```javascript
modalConfirm({ title: 'Borrar', body: 'Confirmar borrado', icon: 'fa-trash', btnsType: 'ac', onClickYes: () => { /* ... */ } });
```

## `modalNotification`

Archivo: `resources/js/utils/modalNotification.js`

Notas:
- Ajustes visuales: `bgOpacity` reduce transparencia y hay un acento visible en el borde izquierdo para mejor contraste.
- Llamada rápida de prueba en consola:
```javascript
modalNotification('Título','Subtexto',{ variant:'info' });
```
