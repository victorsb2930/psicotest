# Documentación: PJAX ligero y carga por página

Este documento explica cómo funciona el sistema de navegación parcial (PJAX ligero) implementado en `resources/js/app.js` y qué deben cumplir las vistas para integrarse correctamente.

Cómo funciona

- Se interceptan clicks en enlaces same-origin (GET) que no tengan `data-no-pjax="true"`.
- Se realiza un `fetch` de la URL objetivo y se parsea el HTML recibido.
- Se reemplaza el contenido del contenedor `#app-content` con el HTML nuevo.
- Se actualiza `document.body.dataset.page` al valor encontrado en el HTML nuevo (si existe en el fragmento) y se ejecuta `initPage()` para cargar el script por página.
- Se hace `history.pushState` para mantener navegación atrás/adelante.

Requisitos para vistas del servidor

- Las respuestas parciales deben incluir el contenedor principal con id `#app-content` para que la sustitución funcione.
- El HTML retornado debe exponer un `data-page` para que el loader pueda importar el script por página. Puede estar en el `<body>` o dentro del fragmento (por ejemplo en el propio `#app-content`). El loader intenta leer `data-page` con `getAttribute('data-page')` de varios lugares para máxima compatibilidad.

Ejemplo mínimo de respuesta HTML (fragmento):

```html
<div id="app-content" data-page="admin.roles">
  <!-- HTML de la página -->
</div>
```

Notas de debugging

- Si el script de la página no se ejecuta después de la navegación PJAX:
  - Abre DevTools → Network → revisa la respuesta HTML de la petición PJAX y verifica que contenga `#app-content` o un elemento con `data-page`.
  - Verifica que `data-page` se haya actualizado en el `body` o en el `#app-content` del HTML retornado.
  - Revisa la consola de JS en busca de errores al importar dinámicamente `resources/js/pages/<page>.js`.

- Si la petición `fetch` falla por CORS u otra razón, asegúrate de que la URL sea same-origin y que no haya cabeceras bloqueantes. Para enlaces que deben forzar recarga completa, añade `data-no-pjax="true"` al `a`.

Recomendaciones

- Mantener scripts por página pequeños y deterministas. Evitar dependencias implícitas que se carguen fuera del `initPage()`.
- Exponer una función `destroy()` en los módulos por página para limpiar listeners y tiempo de ejecución cuando la página sea reemplazada. `resources/js/app.js` llama a `destroy()` si el módulo lo exporta.

Observaciones sobre compatibilidad

- El loader usa `getAttribute('data-page')` en `document.body`, en `#app-content`, y en el primer elemento con `data-page` dentro del fragmento recibido. Esto permite flexibilidad en cómo colocas la directiva `@section('page', '...')` en tus vistas Blade.
