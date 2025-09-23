# Psicoguia — Guía de onboarding para colaboradores

Bienvenido/a al repositorio de Psicoguia. Este documento explica paso a paso lo que debe hacer un nuevo colaborador que clona el proyecto para dejarlo funcionando en su entorno local (con Docker) y cómo solucionar problemas comunes.

Requisitos previos
- Git
- Docker y Docker Compose
- Composer (si vas a instalar dependencias fuera del contenedor)
- Node.js y npm/yarn (opcional si trabajas en assets front)

1) Clonar el repositorio

-
# Psicoguia — Guía de arranque y comandos (ordenados)

Esta guía concentra los pasos exactos y los comandos en el orden correcto para que un nuevo colaborador deje el proyecto funcionando localmente (con Docker). Está en español y contiene atajos y soluciones a problemas comunes.

Requisitos previos
- Git
- Docker y Docker Compose
- (Opcional) Composer y Node.js si prefieres no usar el contenedor para instalar dependencias

Resumen rápido (comandos mínimos en orden)

1. Clonar el repo

```bash
git clone <repo-url>
cd psicoguia
```

2. Copiar y revisar variables de entorno

```bash
cp .env.example .env
# Edita `.env` si necesitas (DB_HOST, DB_USER, APP_URL, etc.)
```

3. Levantar servicios Docker (construye imágenes si cambias Dockerfile)

```bash
docker-compose up -d --build
```

4. Instalar dependencias PHP (dentro del contenedor) y optimizar autoload

```bash
docker-compose exec app composer install --no-interaction --prefer-dist --optimize-autoloader
docker-compose exec app composer dump-autoload -o
```

5. (Opcional) Instalar dependencias JS y compilar assets

Si trabajas en frontend y tienes Node instalado localmente:
```bash
npm install
npm run dev
```

O dentro del contenedor (si está disponible):
```bash
docker-compose exec app npm install
docker-compose exec app npm run dev
```

Nota importante (assets & iconos)

- Este proyecto usa un generador local para crear la lista de iconos de Bootstrap Icons usada por los pickers del frontend.
- Antes de ejecutar `npm run dev`, si es la primera vez o si has actualizado dependencias, ejecuta:
```pwsh
npm run generate-icons
```
Esto crea `public/bootstrap-icons-list.json`. Si `npm run dev` falla con errores relacionados a iconos o CSS, ejecutar el comando anterior suele resolverlo.

Si `npm run dev` sigue fallando con Exit Code 1, intenta construir para producción y revisar la salida:
```pwsh
npm run generate-icons
npm run build
```
y revisa la salida de la terminal para el error concreto; copia el texto y pégalo en la issue o en la conversación para ayuda.

6. Generar clave de la aplicación (si aún no está)

```bash
docker-compose exec app php artisan key:generate
```

7. Ejecutar migraciones y seeders (desarrollo)

Si estás en desarrollo y puedes resetear la BD (recomendado para sincronizar esquema):
```bash
docker-compose exec app php artisan migrate:fresh --seed
```

Si NO quieres resetear la BD (aplicar migraciones pendientes):
```bash
docker-compose exec app php artisan migrate --seed
```

8. Limpiar y recargar cachés

```bash
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear
```

9. Ajustar permisos si ves errores de escritura (views / cache)

```bash
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache || true
docker-compose exec app chmod -R ug+rwx storage bootstrap/cache || true
```

10. Comprobar que `ADMIN_EMAILS` funciona y reasignar admin si hace falta

```bash
# Ver la config leída
docker-compose exec app php artisan tinker --execute="var_export(config('app.admin_emails'))"

# Re-seed si necesitas forzar asignaciones a admin
docker-compose exec app php artisan db:seed --class=DatabaseSeeder

# O con tinker asignar admin manualmente
docker-compose exec app php artisan tinker
>>> $u = \App\Models\User::where('email','test@admin.com')->first();
>>> $u->syncRoles(['admin']);
>>> $u->is_active = true; $u->save();
```

Comandos útiles frecuentes (atajos)

- Levantar todo (build si hace falta): `docker-compose up -d --build`
- Parar: `docker-compose down`
- Ejecutar artisan dentro del contenedor: `docker-compose exec app php artisan <command>`
- Abrir un shell en el contenedor: `docker-compose exec app sh` (o bash si está disponible)
- Ejecutar tests: `docker-compose exec app ./vendor/bin/pest --colors` (o `vendor/bin/phpunit` si aplica)

Diagnóstico y resolución de problemas comunes

- Redis
	- Si ves `Class "Redis" not found`: o bien instala la extensión phpredis en el PHP que ejecuta Artisan, o cambia a `predis` con `composer require predis/predis` y `REDIS_CLIENT=predis` en `.env`.
	- Si ves `php_network_getaddresses: getaddrinfo for redis failed`: estás ejecutando Artisan en el host y `REDIS_HOST=redis` sólo existe dentro de la red Docker. Ejecuta Artisan dentro del contenedor o mapea el puerto Redis en `docker-compose.yml`.

- Middleware `perm`
	- Si ves `Target class [perm] does not exist`, asegúrate de haber registrado el alias en `bootstrap/app.php` y ejecuta:
		```bash
		docker-compose exec app composer dump-autoload
		docker-compose exec app php artisan config:clear
		```

- Permisos de archivos
	- Si ves `Failed to open stream: Permission denied` al compilar vistas, ejecuta:
		```bash
		docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
		docker-compose exec app php artisan view:clear
		```

Información útil para colaboradores

- Evita editar migraciones históricas en repositorio compartido si ya han sido ejecutadas en otros entornos; en su lugar crea migraciones adicionales para cambios de esquema.
- Mantén `ADMIN_EMAILS` actualizado en `.env` para pruebas locales; el seeder y el controlador utilizan `config('app.admin_emails')`.

Preguntas frecuentes

- ¿Debo ejecutar `composer install` en mi host o dentro del contenedor? R: Lo más consistente es hacerlo dentro del contenedor con `docker-compose exec app composer install`.
- ¿Cómo reproduzco el entorno de producción localmente? R: Ajusta `APP_ENV` y `APP_DEBUG` en `.env`, y asegúrate de ejecutar migrations y seeders.

¿Quieres que añada una sección "Comandos útiles (atajos)" más detallada con alias de PowerShell o scripts de npm? Dime qué prefieres y lo agrego.

- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
