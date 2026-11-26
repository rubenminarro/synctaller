# Instalación en una computadora nueva

El detalle de cada variable de entorno y los datos de prueba están en el [`README.md`](../../README.md) de la raíz y en `backend/.env.example`. Este documento
separa lo que exige el proyecto de lo que es propio de cada instalación.

## Requisitos del proyecto

Valen en cualquier equipo.

| Requisito | Detalle |
|---|---|
| PHP | 8.3 o superior |
| Extensiones de PHP | `pdo_mysql`, `pdo_sqlite` y `sqlite3` (tests), `bcmath`, `mbstring`, `gd` (imágenes en PDF), `zip` (Composer), `fileinfo`, `openssl`, `curl`, `dom` |
| Composer | 2 |
| MySQL | 8, con base en `utf8mb4` |
| Node.js y npm | 22 o superior: el frontend y los scripts de la raíz |
| Git | Acceso al repositorio |

Sin `zip` en el PHP de consola, Composer clona los paquetes uno por uno y la instalación tarda muchísimo.

## Instalación

```bash
git clone https://github.com/rubenminarro/synctaller.git
cd synctaller
npm ci            # concurrently, para los scripts de la raíz
npm run setup     # composer install, npm ci del frontend, copia de los .env y APP_KEY
```

Completar `backend/.env`: conexión a MySQL, `ADMIN_PASSWORD`, `TEST_PASSWORD` y las variables que indica el `README.md`.
**Las contraseñas no van en el repositorio ni en esta documentación.**

Crear la base y sembrarla:

```sql
CREATE DATABASE synctaller CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE synctaller_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
cd backend
php artisan migrate --seed     # solo sobre una base vacía; tarda unos 15 s
php artisan migrate --database=central --path=database/migrations/central   # base central (línea T)
php artisan tenants:register "Taller principal" 127.0.0.1 synctaller   # el taller del host de VITE_API_URL
php artisan storage:link       # fotos, firmas y logo
cd ..
npm test                       # backend y frontend en verde
npm run dev                    # API en :8000, cola y SPA en :5173
```

`frontend/.env` define `VITE_API_URL` (por defecto `http://127.0.0.1:8000/api`). CORS y Sanctum solo admiten el origen de
`FRONTEND_URL` y `SANCTUM_STATEFUL_DOMAINS` de `backend/.env` (por defecto `http://localhost:5173`).

## Actualizar un equipo ya instalado

```bash
git pull
cd backend && composer install && php artisan migrate
php artisan migrate --database=central --path=database/migrations/central   # crear antes synctaller_central si no existe
php artisan tenants:register "Taller principal" 127.0.0.1 synctaller   # una sola vez, desde T2 paso 2 (falla si ya existe)
php artisan db:seed --class=RolesAndPermissionsSeeder   # si la entrega agregó permisos
cd ../frontend && npm ci
```

**Al pasar a T3, paso 4** (historial 2.96), una sola vez: la cola y la cache pasan de `synctaller` a la central. **Antes**
del `git pull`, con el código anterior, vaciar la cola vieja (`php artisan queue:work --stop-when-empty`): el código
nuevo ya no la lee, y el paquete borraría sus jobs porque no traen taller. Después, el `migrate --database=central` de
arriba crea las tablas nuevas. El historial de `failed_jobs` queda en `synctaller`.

| No usar | Motivo |
|---|---|
| `npm install` dentro de `frontend/` | Reescribe `package-lock.json` y separa los equipos: usar `npm ci` |
| `php artisan config:cache` en desarrollo | Los tests correrían contra MySQL y podrían borrar la base |
| `migrate:fresh`, `migrate:refresh`, `db:wipe` | Borran todos los datos |

## Procesos para los avisos

Sin ellos, los avisos quedan `pending` y los recordatorios no se generan.

```bash
php artisan queue:work      # envía los avisos
php artisan schedule:run    # cada minuto
```

En desarrollo, `MAIL_MAILER=log` escribe los emails en `storage/logs/laravel.log`.

## Particularidades de una instalación local

**No son requisitos del proyecto.** Describen los equipos de desarrollo usados hasta ahora, y otro equipo puede ser distinto.

| Tema | Situación conocida |
|---|---|
| Sistema | Windows con Laragon |
| Servidor de la API | `php artisan serve` en `127.0.0.1:8000` (`npm run dev`). Laragon no crea un vhost para `backend/public`; si se usa uno manual, Nginx y no Apache: con Apache, requests concurrentes corrían sin el `.env` (deuda #12) |
| Ruta | `C:\laragon\www\synctaller` |
| OPcache | Activado en una de las notebooks y apagado en la otra. Acelera las respuestas y no afecta a los tests |
| Requests concurrentes | El servidor de `artisan serve` atiende uno por vez; `PHP_CLI_SERVER_WORKERS` en `backend/.env` permite más |
| `pdo_sqlite` deshabilitado | `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit` |
| Scheduler en Windows | Programador de tareas, cada 1 minuto |
| PowerShell 5.1 | No editar archivos con `Get-Content`/`Set-Content`: corrompen UTF-8 |

## Comprobar la instalación

| Comprobación | Resultado esperado |
|---|---|
| `php artisan test` | Todos en verde |
| `php artisan migrate:status` | Todas las migraciones en `Ran` |
| `php artisan migrate:status --database=central --path=database/migrations/central` | Las de `tenants` y de la cola y la cache en `Ran` |
| `GET /api/branding` | 200 (`404 TENANT_NOT_FOUND` si falta registrar el taller del host) |
| Login en el frontend | Entra con un usuario sembrado |
