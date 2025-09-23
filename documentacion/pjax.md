# Documentación: PJAX ligero y carga por página

Este documento explica cómo funciona el sistema de navegación parcial (PJAX ligero) implementado en `resources/js/app.js` y qué deben cumplir las vistas para integrarse correctamente.

Cómo funciona

- Se interceptan clicks en enlaces same-origin (GET) que no tengan `data-no-pjax="true"`.
- Se realiza un `fetch` de la URL objetivo y se parsea el HTML recibido.
- Se reemplaza el contenido del contenedor `#app-content` con el HTML nuevo.
- Se actualiza `document.body.dataset.page` al valor encontrado en el HTML nuevo (si existe) y se ejecuta `initPage()` para cargar el script por página.
- Se hace `history.pushState` para mantener navegación atrás/adelante.

Requisitos para vistas del servidor

- Las respuestas parciales deben incluir el contenedor principal con id `#app-content` para que la sustitución funcione.
- El `<body>` del HTML devuelto (o la página completa) debe exponer `data-page="namespace.name"` para que el loader pueda importar el script por página.

Ejemplo mínimo de respuesta HTML (fragmento):

```html
<body data-page="admin.roles">
  <div id="app-content">
    <!-- HTML de la página -->
  </div>
</body>
```

Notas de debugging

- Si el script de la página no se ejecuta después de la navegación PJAX:
  - Abre DevTools → Network → revisa la respuesta HTML de la petición PJAX y verifica que contenga `#app-content`.
  - Verifica que `data-page` se haya actualizado en el `body` del HTML retornado.
  - Revisa la consola de JS en busca de errores al importar dinámicamente `resources/js/pages/<page>.js`.

- Si la petición `fetch` falla por CORS u otra razón, asegúrate de que la URL sea same-origin y que no haya cabeceras bloqueantes. Para enlaces que deben forzar recarga completa, añade `data-no-pjax="true"` al `a`.

Recomendaciones

- Mantener scripts por página pequeños y deterministas. Evitar dependencias implícitas que se carguen fuera del `initPage()`.
- Si un script por página necesita inicializar muchas cosas, exponer una función `destroy()` opcional en el módulo para limpiar listeners cuando la página sea reemplazada (por ahora `initPage()` re-importa y re-ejecuta, pero no llama a destroy automáticamente).

Si quieres añadir una convención para módulos por página (por ejemplo exportar `{ init, destroy }`), puedo adaptar `resources/js/app.js` para llamar a `destroy()` antes de reemplazar contenido y `init()` después de inyectarlo.