````markdown
# Utilidades frontend importantes

## `modalConfirm`

Archivo: `resources/js/utils/modalConfirm.js`

Resumen:
- Crea o reutiliza un modal Bootstrap y lo muestra.
- Acepta `bodyHtml` con campos: `title`, `subtitle`, `body`, `icon` (o `icona`), `iconColor`, `btnsType` (`ny` o `ac`), `onClickYes` (callback), `closeClick` (boolean), `modalId`.
- Ahora soporta explícitamente modales sin footer/botones mediante `bodyHtml.noFooter === true` o `bodyHtml.noButtons === true` o `bodyHtml.btnsType === 'none'`.
- Opciones globales en `modalConfirm.defaults` (por ejemplo `draggable`, `size`, `dialogClasses`).

Ejemplo (confirm con botones):
```javascript
modalConfirm({ title: 'Borrar', body: 'Confirmar borrado', icon: 'fa-trash', btnsType: 'ac', onClickYes: () => { /* ... */ } });
```

Ejemplo (solo lectura — no footer):
```javascript
modalConfirm({ modalId: 'userSessionsModal', title: 'Historial', body: html, noFooter: true }, 'dialog', { centered: true });
```

## `modalNotification`

Archivo: `resources/js/utils/modalNotification.js`

Notas:
- Ajustes visuales: `bgOpacity` reduce transparencia y hay un acento visual en el borde.
- Llamada rápida de prueba en consola:
```javascript
modalNotification('Título','Subtexto',{ variant:'info' });
```

## Sessions / sendBeacon

Para mejorar la detección de cierre de sesión cuando el usuario cierra la pestaña, el frontend ahora intenta notificar al servidor usando `navigator.sendBeacon` y `fetch(keepalive)` al `visibilitychange` / `beforeunload`. El endpoint del servidor es:

- POST `/sessions/end` — implementado en `LoginRegisterController@endSession`.

Esto es best-effort: algunos navegadores o extensiones pueden bloquear el envío. Por seguridad adicional el servidor también intenta marcar la sesión durante el flujo de logout explícito.

## Heartbeat (keepalive)

El frontend lanza un heartbeat periódico (por defecto 60s) que hace POST a `/profile/heartbeat`. Esto mantiene `last_seen_at` del usuario actualizado y permite labores de presencia. El heartbeat se activa automáticamente si `window.__isAuth` es truthy.

Puedes iniciar manualmente el heartbeat desde consola (para pruebas):
```js
startHeartbeat(10); // pings cada 10s
stopHeartbeat();
```

## Convención para módulos por página

Los módulos situados en `resources/js/pages/` deben exportar `init()` y opcionalmente `destroy()` para integrarse con el loader PJAX.

- `init()` se llamará después de inyectar la página.
- `destroy()` se llamará (si existe) antes de reemplazar la página actual — esto evita fugas de listeners y memoria.

Ejemplo mínimo de módulo por página:
```javascript
// resources/js/pages/example.page.js
export function init() {
    // inicializaciones: listeners, plugins, etc.
    document.querySelector('#btn')?.addEventListener('click', onClick);
}
export function destroy() {
    // limpiar listeners, timeouts, modales
    document.querySelector('#btn')?.removeEventListener('click', onClick);
}
```

````
