# SyncTaller

Sistema de gestión para talleres mecánicos de Paraguay: atiende un vehículo de punta a punta, con foco en la trazabilidad
de lo presupuestado, lo ejecutado, lo facturado y lo cobrado.

```text
Cliente → Vehículo → Recepción → Diagnóstico → Presupuesto → Aprobación → Orden de trabajo
       → Ejecución (servicios, mano de obra, repuestos) → Cierre económico → Factura → Pago → Entrega → Historial
```

Alrededor del flujo: inventario y compras, proveedores, mantenimiento preventivo, reportes, avisos a clientes, auditoría y
reparaciones por siniestro con aseguradoras. **El sistema no está listo para producción**: el estado por fase está en
[`docs/roadmap/README.md`](docs/roadmap/README.md).

## Estructura

```text
synctaller/
├── backend/     API REST en Laravel (PHP). Solo API: sus vistas son las de los PDF y los emails
├── frontend/    SPA en React + TypeScript (Vite), consume la API
├── docs/        Documentación del producto: reglas de negocio, módulos, roadmap, decisiones
├── .github/     CI
└── package.json Scripts de la raíz (no es un workspace: cada app tiene sus dependencias y su lock)
```

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3, Laravel 13, Sanctum (tokens Bearer), spatie/laravel-permission, dompdf |
| Frontend | React 19, TypeScript, Vite, Tabler UI (Bootstrap 5), React Router, TanStack Query, Axios, ApexCharts |
| Base de datos | MySQL 8 (utf8mb4); SQLite en archivo para los tests del backend |
| Tests | PHPUnit 12 (backend), Vitest + Testing Library (frontend) |
| Calidad | Pint (backend), ESLint (frontend) |

Versiones y dependencias retenidas a propósito: [`docs/project/technology-stack.md`](docs/project/technology-stack.md).

## Requisitos

* PHP 8.3 o superior con `pdo_mysql`, `pdo_sqlite` y `sqlite3` (tests), `bcmath`, `mbstring`, `gd` (imágenes en PDF),
  `zip` (Composer), `fileinfo`, `openssl`, `curl` y `dom`. Sin `zip`, Composer clona los paquetes uno por uno y tarda muchísimo.
* Composer 2.
* Node.js 22 o superior, con npm.
* MySQL 8. En Windows, Laragon trae PHP y MySQL.

## Instalación

```bash
git clone https://github.com/rubenminarro/synctaller.git
cd synctaller
npm ci            # concurrently, para los scripts de la raíz
npm run setup     # composer install, npm ci del frontend, copia de los .env y APP_KEY
```

Completar `backend/.env` (ver [Variables de entorno](#variables-de-entorno)) y crear la base:

```sql
CREATE DATABASE synctaller CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
cd backend
php artisan migrate --seed     # solo sobre una base vacía; tarda unos 15 s
php artisan storage:link       # fotos, firmas y logo
cd ..
npm run dev
```

| Servicio | URL |
|---|---|
| SPA | http://localhost:5173 |
| API | http://127.0.0.1:8000/api |

El detalle, con lo que cambia entre equipos: [`docs/development/setup.md`](docs/development/setup.md).

## Comandos

Desde la raíz:

| Comando | Qué hace |
|---|---|
| `npm run setup` | Instala las dependencias de las dos apps y crea sus `.env` si faltan |
| `npm run dev` | Levanta la API (`artisan serve` en :8000), la cola (`queue:listen`) y Vite (:5173) |
| `npm test` | Tests del backend (PHPUnit) y del frontend (Vitest) |
| `npm run lint` | Pint en modo verificación (backend) y ESLint (frontend) |
| `npm run build` | Comprobación de tipos y build de producción del frontend en `frontend/dist/` |

Cada parte tiene su variante (`dev:api`, `dev:queue`, `dev:web`, `test:backend`, `test:frontend`, `lint:backend` y
`lint:frontend`). Para corregir el estilo del backend: `cd backend && vendor/bin/pint`.
Dentro de cada carpeta siguen valiendo sus comandos propios: `php artisan …` y `composer test` en
`backend/`; `npm run dev|build|lint|test` en `frontend/`.

**No usar `npm install` dentro de `frontend/`** (reescribe el lock: usar `npm ci`) **ni `php artisan config:cache` en
desarrollo** (los tests correrían contra MySQL).

## Variables de entorno

Cada app tiene su `.env.example`; los `.env` no se versionan.

**`backend/.env`**, lo que hay que revisar:

| Variable | Para qué |
|---|---|
| `APP_URL` | URL de la API (`http://127.0.0.1:8000`). Las fotos y el logo se sirven desde ahí |
| `APP_TIMEZONE` | Zona horaria del taller (`America/Asuncion`). La base guarda esa hora; la API envía UTC |
| `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` | Origen de la SPA, el único que admiten CORS y Sanctum (`http://localhost:5173`) |
| `DB_*` | Conexión a MySQL (base `synctaller`) |
| `ADMIN_PASSWORD`, `TEST_PASSWORD` | Contraseñas que los seeders dan al administrador principal y al resto de los usuarios de prueba |
| `EMAIL_DNS_CHECK` | Valida que exista el dominio de los emails; `false` solo sin red |
| `MAIL_*`, `QUEUE_CONNECTION` | Avisos a clientes. Con `MAIL_MAILER=log` los emails van a `storage/logs/laravel.log` |
| `INSURER_DEDUCTIBLE_NET_MODE` | Modalidad neta del deducible de siniestros: **apagada** hasta la confirmación escrita del contador |
| `COMPANY_*` | Datos del taller en los PDF. Valores por defecto: el administrador los cambia en Configuración del sistema |

**`frontend/.env`**: `VITE_API_URL`, la URL base de la API con el prefijo `/api` (`http://127.0.0.1:8000/api`).

El puerto 5173 es fijo (`strictPort`). Para cambiarlo hay que ajustar también `FRONTEND_URL` y `SANCTUM_STATEFUL_DOMAINS`.

## Base de datos

* Después de traer cambios: `php artisan migrate` (solo las migraciones nuevas) y, si la entrega agregó permisos,
  `php artisan db:seed --class=RolesAndPermissionsSeeder`.
* `migrate:fresh`, `migrate:refresh` y `db:wipe` **borran todos los datos**. No usarlos sobre una base con datos que interesen.
* Los seeders son **idempotentes**: sobre una base ya sembrada no duplican ni fallan, pero agregan los datos de ejemplo que
  falten. Sobre una base con datos reales, correr solo los necesarios.
* Si un cambio del `.env` no se refleja: `php artisan config:clear`.

## Cola y tareas programadas

Los emails a clientes salen por cola y los recordatorios diarios (facturas, mantenimientos, cuentas por pagar) los genera el
scheduler. Sin estos procesos, los avisos quedan `pending` y los recordatorios no se generan.

```bash
php artisan queue:work      # en desarrollo lo reemplaza `npm run dev` (queue:listen)
php artisan schedule:run    # cada minuto; en Windows, con el Programador de tareas
```

Los recordatorios se prueban a mano con `php artisan notifications:send-reminders`.

## Datos de prueba

`php artisan migrate --seed` crea los datos de ejemplo **por los Services**, como si los cargaran los usuarios de cada rol,
con fechas repartidas en los últimos tres meses. Ningún seeder envía emails.

**Usuarios** (`@mail.com.py`). `admin` usa `ADMIN_PASSWORD`; el resto, `TEST_PASSWORD`. Qué puede hacer cada rol: `backend/CLAUDE.md`.

| Rol | Usuarios |
|---|---|
| administrador | `admin`, `admin2` |
| recepcionista | `recepcion1`, `recepcion2`, `recepcion3` |
| jefe-taller | `jefetaller1`, `jefetaller2` |
| cajero | `caja1`, `caja2` |
| deposito | `deposito1`, `deposito2` |
| mechanic | `jperez` (MEC-001), `cgomez` (MEC-002), `mecanico3`–`mecanico4` (activos), `mecanico5` (inactivo), `mecanico6` (de licencia) |

**Escenarios** (`backend/database/seeders/ExampleScenarios.php`): una recepción por escenario, llevada hasta su estado final.

| Escenarios | Estado final |
|---|---|
| H01–H13 | OT completada, facturada y entregada: contado y crédito, pagadas, parciales, vencidas (H06, H07), una factura anulada y vuelta a emitir (H04), notas de crédito (H13 acreditada entera; H07 con una parcial y otra anulada), un cobro anulado (H05), una entrega anulada y vuelta a registrar (H03) |
| H14–H17 | OT completada sin entregar: pagada (H14), al contado sin cobrar (H15), factura en borrador (H16), sin facturar (H17) |
| C01–C03 | Recepción pendiente: sin diagnóstico, con diagnóstico pendiente, con diagnóstico en curso |
| C04, C06–C08, C15–C16 | Presupuesto en borrador, rechazado, rechazado y reabierto, cancelado; enviado a clientes que no reciben avisos (omitidos) |
| C05, C07 | Siniestro abierto con líneas a cargo de la aseguradora. C05: autorización en dos versiones, presupuesto aprobado y re-aceptado, OT completada con su reparto en tres versiones y una factura por responsable. C07: autorización invalidada al reabrir el presupuesto |
| C09, C14 | OT pendiente (C14 con repuestos reservados) |
| C10–C13 | OT en curso (C12 con un extra pendiente de la respuesta del cliente, C13 esperando un repuesto sin stock) o pausada (C11) |
| X01–X02 | Recepción cancelada (X02 con la OT cancelada) |

Además: dos aseguradoras (una inactiva), siniestros abiertos, cerrado y cancelado con sus documentos, compras en cada
estado con cuentas por pagar, ajustes de inventario, mantenimientos al día, por vencer y vencidos, comisiones liquidadas y
avisos a clientes en cada estado.

## API

* Todas las rutas, salvo `POST /api/login` y `GET /api/branding`, exigen `Authorization: Bearer {token}`. Los tokens no
  expiran; `POST /api/logout` revoca el actual. `GET /api/me` trae el usuario, los nombres de sus permisos, su ficha de
  mecánico y sus preferencias.
* Todo `/api` responde JSON con el mismo formato; los errores de negocio traen un `error_code` estable (`422`, o `409` en
  conflictos) y nunca un `500`.
* Precios internos (presupuesto, OT, cierre) **sin IVA**; facturas y compras **con IVA incluido**, en guaraníes enteros.

Contrato y reglas: `backend/CLAUDE.md` y [`docs/reglas-de-negocio.md`](docs/reglas-de-negocio.md). Pruebas manuales con
Postman: [`docs/testing/guia-postman.md`](docs/testing/guia-postman.md).

## Tests

```bash
npm test                                         # todo
cd backend && php artisan test --filter=nombre   # un test del backend
```

Los tests del backend usan SQLite (`backend/database/testing.sqlite`) y no tocan la base de desarrollo ni necesitan red; la
suite completa tarda unos 6 minutos. La CI (`.github/workflows/ci.yml`) corre tests, lint y build de cada app cuando cambia
su carpeta. Más en [`docs/development/testing.md`](docs/development/testing.md).

## Flujo de trabajo

* No se trabaja en `main`: una rama por tarea (`feature/…`, `fix/…`, `docs/…`, `test/…`), integrada con merge.
* Antes de dar un cambio por terminado: los tests puntuales de lo que se tocó, `npm run lint` y, si tocó el frontend,
  `npm run build`. La suite completa la corre la CI (ver `CLAUDE.md`, "Tests: baseline y uso diario").
* Commits en español, explicando el porqué.
* Cada cambio actualiza la documentación que le corresponde: [`docs/README.md`](docs/README.md), "Qué actualizar en cada cambio".

## Documentación

| Documento | Para qué |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | Reglas para trabajar en el monorepo (también para Claude Code) |
| `backend/CLAUDE.md`, `frontend/CLAUDE.md` | Convenciones de cada app |
| [`docs/README.md`](docs/README.md) | Índice de la documentación del producto |
