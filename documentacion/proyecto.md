# Documentación del proyecto

Propósito: PsicoTest es una aplicación Laravel que sirve como guía y panel administrativo para profesionales y usuarios. Este archivo documenta los comandos esenciales y convenciones que un nuevo colaborador necesita conocer para poner el proyecto en marcha y entender la estructura general.

Comandos esenciales (arranque rápido)

1) Clonar y configurar:
```bash
git clone <repo-url>
cd PsicoTest
cp .env.example .env
# editar .env según sea necesario (DB, APP_URL, etc.)
```

2) Levantar servicios Docker (recomendado):
```bash
docker-compose up -d --build
```

3) Instalar dependencias PHP dentro del contenedor:
```bash
docker-compose exec app composer install --no-interaction --prefer-dist --optimize-autoloader
docker-compose exec app composer dump-autoload -o
```

4) Frontend (si trabajas en assets localmente):
```pwsh
npm install
npm run generate-icons   # crea public/bootstrap-icons-list.json
npm run dev
```

5) Migraciones / seeders (desarrollo)
Si estás en desarrollo y puedes resetear la BD:
```bash
docker-compose exec app php artisan migrate:fresh --seed
```
Si NO quieres resetear la BD (aplicar migraciones pendientes):
```bash
docker-compose exec app php artisan migrate --seed
```

6) Limpieza de cachés (si es necesario):
```bash
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear
```

Estructura relevante

- `app/` — modelos, controladores, providers.
- `resources/views/` — plantillas Blade.
- `resources/js/` — scripts JS por página y utilidades (`utils/`).
- `public/` — assets públicos y el JSON generado `bootstrap-icons-list.json`.
- `documentacion/` — documentación técnica ligera y guías internas.

Convenciones

- Scripts por página: `resources/js/pages/<page>.js` y la página debe exponer `@section('page', '<namespace-name>')` para que el loader pueda importarlo.
- Modales y utilidades globales se encuentran en `resources/js/utils/`.

Sesiones y mantenimiento

- Endpoint para notificar cierre de sesión desde el cliente: `POST /sessions/end` (implementado en `LoginRegisterController@endSession`). El frontend intenta enviar un beacon cuando la pestaña se cierra o se oculta.
- Heartbeat: el cliente hace `POST /profile/heartbeat` periódicamente (por defecto cada 60s) para mantener `last_seen_at` actualizado.
- Job/Comando para cerrar sesiones inactivas: `php artisan sessions:close-stale --hours=24` (por defecto 24h). Se agregó `app/Console/Commands/CloseStaleSessions.php` y la tarea está programada en `app/Console/Kernel.php` para ejecutarse diariamente.

Problemas comunes

- Si el picker de iconos no muestra resultados, comprueba que `public/bootstrap-icons-list.json` exista y sea accesible.
- Si `npm run dev` falla, ejecuta `npm run generate-icons` y vuelve a intentarlo. Si el error persiste, ejecutar `npm run build` y revisar la salida de la terminal.

Notas finales

Si ya has ejecutado los pasos anteriores (Docker up, composer install, generar iconos, npm run dev y las migraciones), en principio la aplicación debe arrancar. Recomendación mínima para un nuevo colaborador en desarrollo:

1. Levantar Docker: `docker-compose up -d --build`
2. Composer: `docker-compose exec app composer install --no-interaction --prefer-dist --optimize-autoloader`
3. Generar iconos y compilar assets: `npm run generate-icons && npm run dev`
4. Migraciones/seeders: `docker-compose exec app php artisan migrate:fresh --seed`

Si sigues estos pasos en un entorno limpio, la aplicación debería funcionar. Si hay problemas con colas, Redis u otros servicios, revisa las secciones de diagnóstico en el `README.md`.

Si quieres, puedo añadir un checklist en el README con exactamente los comandos a ejecutar en orden.
