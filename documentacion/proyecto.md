# Documentación del proyecto

Propósito: Psicoguia es una aplicación Laravel que sirve como guía y panel administrativo para profesionales y usuarios. Este archivo documenta los comandos esenciales y convenciónes que un nuevo colaborador necesita conocer para poner el proyecto en marcha y entender la estructura general.

Comandos esenciales

- Clonar y configurar:
```bash
git clone <repo-url>
cd psicoguia
cp .env.example .env
```

- Levantar con Docker (recomendado):
```bash
docker-compose up -d --build
```

- Instalar dependencias PHP dentro del contenedor:
```bash
docker-compose exec app composer install --no-interaction --prefer-dist --optimize-autoloader
```

- Frontend (si trabajas en assets localmente):
```pwsh
npm install
npm run generate-icons   # crea public/bootstrap-icons-list.json
npm run dev
```

Estructura relevante

- `app/` — modelos, controladores, providers.
- `resources/views/` — plantillas Blade.
- `resources/js/` — scripts JS por página y utilidades (`utils/`).
- `public/` — assets públicos y el JSON generado `bootstrap-icons-list.json`.
- `documentacion/` — documentación técnica ligera y guías internas.

Convenciones

- Scripts por página: `resources/js/pages/<page>.js` y la página debe exponer `data-page="<namespace>"` en el `<body>` para que el loader pueda importarlo.
- Modales y utilidades globales se encuentran en `resources/js/utils/`.

Problemas comunes

- Si el picker de iconos no muestra resultados, comprueba que `public/bootstrap-icons-list.json` exista y sea accesible.
- Si `npm run dev` falla, ejecuta `npm run generate-icons` y vuelve a intentarlo. Si el error persiste, ejecutar `npm run build` y revisar la salida de la terminal.

Si necesitas ampliar esta sección con diagramas, flujos o una guía de despliegue paso a paso, dime qué formato prefieres (Markdown extendido, PlantUML, PDF).