# CLAUDE.md — Backend (API Laravel)

Reglas para trabajar en `backend/`. Las generales del monorepo (metodología, Git, documentación) están en el
[`CLAUDE.md`](../CLAUDE.md) de la raíz.

| Documento | Para qué |
|---|---|
| [`docs/reglas-de-negocio.md`](../docs/reglas-de-negocio.md) | **Reglas de negocio vigentes** con sus `error_code` (numeración 6.N) |
| [`docs/roadmap/deuda-tecnica.md`](../docs/roadmap/deuda-tecnica.md) | Deuda técnica conocida y discrepancias |
| [`docs/testing/guia-postman.md`](../docs/testing/guia-postman.md) | Pruebas manuales de la API, encadenadas por fase |
| [`docs/decisions/historial.md`](../docs/decisions/historial.md) | Por qué se decidió cada cosa |

Si una regla contradice al código, **el código manda**: registrar la discrepancia en la deuda técnica y avisar, en lugar de
"corregir" por cuenta propia. La deuda técnica no se corrige sin decisión previa del usuario.

---

## 1. Convenciones obligatorias

* La lógica de negocio va en **Services**; los Controllers solo reciben, autorizan, llaman al Service y devuelven
  un Resource con `ApiResponse`.
* Reutilizar Requests, Resources, Enums y excepciones existentes antes de crear nuevos.
* Mantener el **contrato público de la API** (formato de respuesta, nombres de campos, `error_code`, códigos HTTP).
* Estados y tipos formales siempre con **Enum** (`BudgetStatus::APPROVED`, nunca `'approved'`). Ojo: comparar enums
  con enums (`in_array($x, [Enum::A], true)`), nunca contra strings.
* Errores de negocio: excepción de dominio con `error_code` y `422` (o `409` en conflictos). Nunca `500` ni trazas.
* No cambiar rutas, permisos ni contratos sin justificación funcional.
* No refactorizar código estable por estilo. No modificar módulos terminados sin necesidad demostrable.
* No agregar dependencias sin evaluar necesidad y compatibilidad.
* No cambiar el esquema ni crear migraciones por iniciativa propia: proponerlas.
* **Las migraciones no llaman a código de la aplicación** (Services/Models): solo estructura.
* **Nombres de índices y claves foráneas: hasta 64 caracteres** (límite de MySQL). Los tests usan SQLite, que no tiene
  ese límite: si el nombre automático (`tabla_columna_foreign`) es largo, darle uno propio (`indexName:`). Probar la
  migración nueva en MySQL antes de darla por terminada.
* Dinero y cantidades con **BCMath** sobre strings (`App\Support\Decimal::round`), nunca `float`.
* Operaciones sobre varios registros en `DB::transaction`; concurrencia con `lockForUpdate`.
  Orden de bloqueo: OT → línea → `part_stocks`; OT → recepción (recálculo de estado); factura → pago;
  recepción → entregas; compra → `part_stocks`; compra → pago a proveedor; recepción → siniestro; presupuesto → autorización de la
  aseguradora → documentos del siniestro; presupuesto u OT → excepción interna; siniestro → OT → reparto → excepción; siniestro → OT → reparto → factura; factura → nota de crédito; factura → retención;
  documento → serie de códigos (siempre última).
* **Códigos internos** (`BUD-`, `OT-`, `COM-`, `REP-`, `SRV-`, `DIA-`, `ASE-`, `SIN-`): salen de `DocumentSequenceService::create()`
  (serie en `App\Enums\DocumentSeries`, contador en `document_sequences`), nunca de `max(id) + 1`. El contador de la
  serie se bloquea dentro de la transacción del alta (un alta fallida no consume el número), se saltan los códigos ya
  usados y un repetido igual responde `409 DOCUMENT_CODE_CONFLICT`. Puede haber huecos: no son documentos fiscales.
  La fila de cada serie se crea en el primer uso con el máximo id + 1. Una serie nueva se agrega al enum.
* Documentos emitidos (cierre económico, factura emitida, pago, entrega, movimiento de inventario, compra recibida,
  pago a proveedor) son
  **inmutables a nivel de modelo**: solo se anulan o se corrigen con otro registro.
* Rutas anidadas con `scopeBindings()` (grupo global en `routes/api.php`); si el parámetro hijo **no** es una relación
  del padre, usar `->withoutScopedBindings()` en esa ruta.
* Actualizaciones parciales con `PATCH`.
* Resources: `XResource` para listados, `ShowXResource` para detalle. `$this->when()` no se resuelve dentro de
  colecciones anidadas (`->map()`): agregar claves condicionales a mano.
* Mensajes al usuario en español; código, clases, campos y `error_code` en inglés.

### Permisos

* Cada Controller implementa `HasMiddleware` con `permission:<recurso>.<acción>` por método.
* El nombre del permiso del middleware debe existir **exactamente** en `RolesAndPermissionsSeeder`. El rol
  `administrador` pasa todo por `Gate::before` (`AppServiceProvider`), así que un nombre mal escrito no se nota con el
  admin: probar con un rol propio (ver `tests/Feature/Access`).
* Roles del taller en `RolesAndPermissionsSeeder::workshopRoles()`; `WorkshopRolesTest` verifica que todos sus
  permisos existan.

### Entorno (Windows / Laragon)

* Tests: `php artisan test` o `vendor/bin/phpunit` (SQLite en archivo, `database/testing.sqlite`: migra y siembra roles y
  permisos una vez por ejecución, cada test en una transacción; no sembrarlos en `setUp`). Los tests de la base central
  usan `Tests\Concerns\UsesCentralDatabase` (otro archivo, `database/testing-central.sqlite`). Si `pdo_sqlite` está deshabilitado:
  `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit`. Los tests no necesitan red:
  `phpunit.xml` fija `EMAIL_DNS_CHECK=false` (sin consulta DNS de emails) y `uncompromised()` se simula con `Http::fake`.
  También desactiva la sincronización a disco de SQLite (`DB_SQLITE_SYNCHRONOUS`, `DB_SQLITE_JOURNAL_MODE`, conexión
  `sqlite` de `config/database.php`).
* No editar archivos con `Get-Content`/`Set-Content` de PowerShell 5.1: corrompen UTF-8 y agregan BOM. Usar las
  herramientas de edición.
* Servidor local: `php artisan serve` en `127.0.0.1:8000` (lo levanta `npm run dev` desde la raíz). Si se usa un vhost,
  **Nginx**, no Apache: con Apache (PHP como módulo multihilo) algunos requests concurrentes corrían sin el `.env` y
  fallaban con `APP_ENV=production` (el frontend los veía como CORS; deuda técnica #12). No usar `config:cache` en
  desarrollo: los tests correrían contra MySQL.
* CORS y Sanctum solo admiten el origen de `FRONTEND_URL` y `SANCTUM_STATEFUL_DOMAINS` (por defecto `localhost:5173`).
* **Zona horaria:** la aplicación corre en hora de Paraguay (`APP_TIMEZONE`, por defecto `America/Asuncion`; historial 2.79) y
  la base guarda esa hora. La API sigue enviando las fechas con hora en UTC (`Z`). En un test de horario, simular un
  instante absoluto en UTC: en la zona de la aplicación, "las 22:00" pasaría también con la zona equivocada.
* No ejecutar `migrate:fresh`, `db:wipe` ni seeders de datos de ejemplo sobre la base de desarrollo sin pedido
  explícito (ver el `README.md` de la raíz, "Base de datos").
* **Seeders de ejemplo:** un seeder por módulo, registrados en `DatabaseSeeder`. El flujo del taller sale de
  `database/seeders/ExampleScenarios.php` (un escenario por recepción y su estado final); cada seeder recorre los
  escenarios y hace su parte **por los Services** (nunca `create()` directo de documentos), con el usuario del rol que
  corresponde y el reloj en la fecha del evento (`ExampleScenarios::travel`). Reglas:
  * Idempotentes: cada seeder omite lo que ya existe (`DatabaseSeederTest` siembra dos veces).
  * El kardex guarda el saldo acumulado y se lista por orden de carga: los movimientos de stock se siembran en orden
    cronológico (compras antes de los escenarios, ajustes al final).
  * Un estado nuevo en un enum necesita su escenario y su verificación en `DatabaseSeederTest`.
  * Los usuarios nuevos usan `TEST_PASSWORD`; el administrador principal, `ADMIN_PASSWORD`.
  * No envían emails reales: `NotificationSeeder` usa el mailer `array`.
* Notificaciones: los emails salen por cola (`QUEUE_CONNECTION=database`) y los recordatorios por el scheduler; sin
  `php artisan queue:work` y `php artisan schedule:run` (cada minuto) los avisos quedan `pending` o no se generan.
  En los tests la cola es `sync` y el mail `array` (`Mail::fake()` para verificar envíos).

---

## 2. Arquitectura

```text
Controller (HasMiddleware) → Form Request → Service → Model/Eloquent → API Resource → ApiResponse
```

| Carpeta | Contenido |
|---|---|
| `app/Http/Controllers/Api` | 59 controllers |
| `app/Http/Requests` | Validación (mensajes en español) |
| `app/Services` | Reglas de negocio (48 services, incluido `AuditLogger`) |
| `app/Jobs`, `app/Mail`, `app/Notifications`, `app/Console/Commands` | Envío de avisos (FASE 23) |
| `app/Models` | Modelos Eloquent (muchos con `SoftDeletes`) |
| `app/Http/Resources` | Contrato JSON |
| `app/Enums` | Estados y tipos |
| `app/Exceptions` | `AppException` y una subclase por módulo |
| `app/Support/Decimal.php` | Redondeo BCMath |
| `app/Multitenancy/Tasks` | Tareas propias al activar un taller (línea T, historial 2.90). La config está en `config/multitenancy.php` y la base central, en `database/migrations/central` |
| `app/Traits/ApiResponse.php` | `successResponse` / `errorResponse` |
| `app/Traits/FileResponse.php` | Descarga de archivos generados (PDF, CSV) y de documentos subidos (`storedFileResponse`: adjunto y `nosniff`) |
| `app/Traits/Auditable.php` | Registro automático de cambios en `audit_logs` |
| `bootstrap/app.php` | Mapeo de excepciones a JSON |
| `config/invoice.php`, `maintenance.php`, `notifications.php`, `company.php`, `insurer.php` | Parámetros de negocio (`invoice.default_tax_rate` es el IVA de presupuestos y facturas; `invoice.vat_withholding_percentage`, el porcentaje del IVA que se sugiere como retención, 6.33; `company` son los datos del taller para los PDF; `insurer.deductible_net_mode` habilita la modalidad neta del deducible, 6.31) |

### Contrato de respuesta

```json
{ "success": true, "status": 200, "message": "...", "data": {}, "errors": null, "meta": null, "timestamp": "..." }
{ "success": false, "status": 422, "message": "...", "data": null, "errors": null, "error_code": "..." }
```

* Listados: `meta.pagination` (`total`, `perPage`, `currentPage`, `lastPage`).
* `bootstrap/app.php` mapea: 400 `BAD_REQUEST`, 401 `UNAUTHENTICATED`, 403 `FORBIDDEN`, 404 `RESOURCE_NOT_FOUND`,
  405 `METHOD_NOT_ALLOWED`, 406 `NOT_ACCEPTABLE`, 415 `UNSUPPORTED_MEDIA_TYPE`, 422 `VALIDATION_ERROR`,
  429 `TOO_MANY_REQUESTS`, 500 `INTERNAL_SERVER_ERROR`, 503 `SERVICE_UNAVAILABLE`, y cualquier `AppException` con su
  propio `status` y `error_code`. Solo si el request espera JSON: el middleware global `EnsureJsonResponse` fija
  `Accept: application/json` en toda ruta `api/*` (también 404/405), así que la API nunca responde HTML.
* Excepciones de dominio: `BudgetException`, `WorkOrderException`, `WorkOrderPartException`, `PartException`,
  `SupplierException`, `InventoryException`, `ServiceException`, `InvoiceException`, `PaymentException`,
  `VehicleDeliveryException`, `MaintenanceException`, `ReceptionException`, `PurchaseException`,
  `SupplierPaymentException`, `NotificationException`, `AuditException`, `VehicleCatalogException`, `ClientException`, `InsurerException`, `ClaimException`, `InsurerAuthorizationException`, `InternalExceptionException`, `AllocationException` (todas extienden `AppException(message, status, errorCode, errors)`).

### Autenticación

Sanctum con tokens Bearer: `POST /api/login` (público) devuelve `data.token`; todo lo demás está bajo
`auth:sanctum`. `POST /api/logout` revoca el token actual. Los tokens no expiran (`sanctum.expiration = null`).
`GET /api/me` (sin permiso específico) devuelve `data.user` (`UserResource`, como el login) y `data.permissions` (nombres
de los permisos, ordenados; el administrador recibe todos los del guard `api` por el `Gate::before`), más
`data.mechanic` (`id`, `employee_code`, `full_name` de la ficha de mecánico vinculada, o `null`) y `data.preferences`
(preferencias visuales completas, reglas 6.21). Lo usa el frontend para hidratar la sesión y adaptar menús y botones.
`GET /api/branding` (logo del sistema) es público, como el login.

---

## 3. Roles

| Rol | Función | Accesos clave |
|---|---|---|
| `administrador` | Todo (`Gate::before`) | Exclusivo: usuarios/roles/permisos, auditoría (`audit-logs.*`), logo y datos del taller (`system-settings.update`), costos y margen (`work-orders.view-economics`), liquidar comisiones, anular entregas, timbrados, ABM de planes de mantenimiento, reporte de rentabilidad |
| `mechanic` | Ejecuta la OT | Diagnóstico (consulta y usa el catálogo de ítems), iniciar/pausar/completar OT e ítems, consumir/devolver repuestos, sus horas y comisiones, historial, estado de mantenimiento, dashboard (bloque taller) |
| `recepcionista` | Atiende al cliente | Clientes, vehículos, recepciones, checklist, presupuestos completos, registrar entregas, alertas de mantenimiento, reporte de presupuestos, avisos a clientes (consultar y reenviar), ver repuestos de la OT y registrar la respuesta del cliente a los extras, aseguradoras completas (alta, edición, estado, baja y contactos), siniestros (alta, edición y cancelación, con sus documentos: subir, listar y descargar), autorizaciones de la aseguradora (registrar y consultar) y re-aceptaciones del dueño y consulta del reparto de la OT |
| `jefe-taller` | Organiza el trabajo | OT completas, ítems, repuestos en OT (incluida la respuesta del cliente a los extras), asignación y horas de todos, diagnóstico y su catálogo de ítems, intervalos de mantenimiento por vehículo, reporte de productividad, consulta de compras, pagos a proveedores y cuentas por pagar, consulta de aseguradoras y siniestros (alta, edición y cancelación, con sus documentos: subir, listar y descargar), autorizaciones de la aseguradora (registrar y consultar) y excepciones internas (otorgar y consultar) y consulta del reparto de la OT |
| `cajero` | Cobra y paga | Facturas, pagos, resumen de caja, reportes de ventas, clientes, cuentas por cobrar y siniestros, consulta de compras, pagos a proveedores completos (registrar, anular, resumen de egresos), cuentas por pagar, avisos a clientes (consultar y reenviar) y consulta de aseguradoras, siniestros (con la descarga de sus documentos), autorizaciones de la aseguradora y excepciones internas, y el reparto de la OT completo (generar, editar, confirmar y descartar el borrador), notas de crédito externas y retenciones de IVA y renta (registrar y anular) |
| `deposito` | Stock | Repuestos, proveedores, inventario, compras completas, repuestos en OT, reporte de inventario, consulta de pagos a proveedores y cuentas por pagar |

El rol del mecánico se llama `mechanic` (no `mecanico`). El seeder crea usuarios de cada rol (`README.md` de la raíz, "Datos de prueba").
La bandeja interna (`me/notifications`) y las preferencias (`me/preferences`) no usan permisos: cada usuario ve solo
lo suyo.
