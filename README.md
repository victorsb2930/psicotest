# Psicoguia — Guía de onboarding para colaboradores

Bienvenido/a al repositorio de Psicoguia. Este documento explica paso a paso lo que debe hacer un nuevo colaborador que clona el proyecto para dejarlo funcionando en su entorno local (con Docker) y cómo solucionar problemas comunes.

Requisitos previos
- Git
- Docker y Docker Compose
- Composer (si vas a instalar dependencias fuera del contenedor)
- Node.js y npm/yarn (opcional si trabajas en assets front)

1) Clonar el repositorio

```bash
git clone <repo-url>
cd psicoguia
```

2) Copiar el entorno de variables

```bash
cp .env.example .env
# Edita .env según sea necesario (puedes usar el .env del proyecto si te lo proporcionaron)
```

3) Levantar servicios con Docker (recomendado)

```bash
docker-compose up -d --build
```

Esto crea los contenedores `app`, `db` (Postgres) y `redis` (si están definidos en `docker-compose.yml`).

4) Ejecutar migraciones y seeders

Dentro del contenedor `app` ejecuta:

```bash
docker-compose exec app php artisan migrate --seed
# o, para reiniciar y seedear limpio:
docker-compose exec app php artisan migrate:fresh --seed
```

5) Generar autoload y limpiar cachés

```bash
docker-compose exec app composer install --no-interaction --prefer-dist --optimize-autoloader
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear
```

6) Compilar assets (opcional en desarrollo)

Si trabajas con JS/CSS y quieres compilar localmente:

```bash
# desde el host (si tienes node instalado)
npm install
npm run dev

# o dentro del contenedor si está configurado
docker-compose exec app npm install
docker-compose exec app npm run dev
```

7) Variables .env importantes

- `APP_URL` — URL base.
- `DB_*` — conexión a la base de datos Postgres.
- `REDIS_CLIENT` y `REDIS_HOST` — configuración de Redis. Si trabajas dentro del contenedor deja `REDIS_HOST=redis`.
- `ADMIN_EMAILS` — lista CSV de emails que el sistema considera administradores (ej.: `test@admin.com,admin2@local`).

8) Comportamiento esperado al registrar usuarios

- Si `ADMIN_EMAILS` contiene el email usado en el registro, el usuario será marcado activo y se le asignará el rol `admin` automáticamente (esto lo gestiona el seeder y el controlador de registro).
- Si registras el usuario manualmente después de ejecutar seeders, puedes reasignar roles ejecutando el seeder otra vez o usando Tinker.

9) Problemas comunes y soluciones rápidas

- Error "Class 'Redis' not found": asegúrate de usar `phpredis` con la extensión instalada o `predis` en composer y que `REDIS_CLIENT` sea coherente con la extensión/paquete.
- Error "php_network_getaddresses: getaddrinfo for redis failed": significa que ejecutaste Artisan en el host; ejecuta Artisan dentro del contenedor (`docker-compose exec app php artisan ...`) o mapea el puerto Redis para que el host lo vea.
- Error "Target class [perm] does not exist": registrar el alias de middleware `perm` está en `bootstrap/app.php`. Si se produce, ejecutar `composer dump-autoload` y `php artisan config:clear` dentro del contenedor.
- Permisos de `storage`/`bootstrap/cache`: si ves "Failed to open stream: Permission denied", ajusta permisos dentro del contenedor:

```bash
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app php artisan view:clear
```

10) Cómo reasignar admin a un usuario existente

```bash
docker-compose exec app php artisan db:seed --class=DatabaseSeeder
# o, con tinker
docker-compose exec app php artisan tinker
>>> $u = \App\Models\User::where('email','test@admin.com')->first();
>>> $u->syncRoles(['admin']);
>>> $u->is_active = true; $u->save();
```

11) Cómo ejecutar pruebas

Si hay tests incluidos:

```bash
docker-compose exec app ./vendor/bin/pest --colors
```

12) Contacto y estilo de commits

- Usa mensajes de commit claros: `feature: add X`, `fix: correct Y`.
- Abre Pull Requests contra `main` y asigna un reviewer.

---

Si quieres, puedo añadir badges, instrucciones para desarrollo sin Docker, o ejemplos de comandos frecuentes. ¿Quieres que lo deje con un apartado "Comandos útiles" con atajos para desarrollo? 
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
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
