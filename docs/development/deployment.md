# Despliegue

**Estado: PENDIENTE.** No hay entorno de producción ni procedimiento de despliegue definidos: hosting, servidor de base y red se
deciden antes de T6 (V9). Este documento reúne solo los requisitos ya identificados, para no perderlos.

## Requisitos conocidos

| Requisito | Origen |
|---|---|
| Un `queue:work` permanente y `schedule:run` cada minuto | [`setup.md`](setup.md) |
| Zona horaria de MySQL fijada en `-03:00`, también al restaurar un respaldo | Handoff 2.79 |
| `DB_DATABASE` vacío en producción, cuando exista la multitenancy | TD10 |
| Base central creada y migrada (`DB_CENTRAL_DATABASE`, con `php artisan migrate --database=central --path=database/migrations/central`) antes de publicar el código | Historial 2.90 |
| Orden de cada despliegue: migrar la central, después las bases de taller con `php artisan tenants:migrate` y recién entonces publicar el código. Las migraciones son compatibles hacia atrás (primero agregar, quitar en un despliegue posterior), porque los talleres siguen atendiendo mientras se migran | Especificación §3.8, historial 2.101 |
| Usuario de MySQL de la aplicación con permiso `CREATE` (y `DROP` para `tenants:discard`) sobre las bases de taller: `tenants:create` crea la base por la conexión central. Si el hosting no lo da, el alta necesita un cambio (la base creada a mano y vacía ya se acepta) | Historial 2.103 |
| `max_connections` de MySQL con margen para la conexión persistente a la central: ocupa una conexión por proceso `php-cgi` | [Informe del spike](../spike-t0/informe.md), secciones 8 y 9 |
| `.env` sin datos de un taller: serían el valor por defecto de todos | Revisión del spike, historial 2.80 |
| `APP_DEBUG=false` y `MAIL_MAILER` real | Práctica de Laravel |
| Remitente autorizado en el servidor de correo | Handoff 2.78 |
| `INSURER_DEDUCTIBLE_NET_MODE` encendido (por defecto desde V16) salvo decisión contraria | `reglas-de-negocio.md` 6.31 |
| Sin `config:cache` en desarrollo | `backend/CLAUDE.md` |
| Nginx sirve `/storage/tenants/{id}/` solo en el host del taller `{id}` (el enlace `public/storage` expone los archivos públicos de todos los talleres) | Historial 2.98 |
| CORS, `VITE_API_URL` y proxies de confianza para varios talleres | Deuda técnica #22 |
| Antes de publicar T3, paso 4: vaciar la cola vieja con el código anterior (`queue:work --stop-when-empty`) y migrar la central, que suma la cola y la cache. Un job viejo, sin taller, el paquete lo borraría | Historial 2.96 |

## Runbook (procedimientos operativos)

| Situación | Qué hacer | Origen |
|---|---|---|
| Un taller no recibió hoy sus recordatorios | El scheduler los despacha en cuanto el taller está activo, sin mantenimiento y con su base disponible, después de su hora (`reminders_at`). Si sigue sin recibirlos, buscar en el log `La base del taller no responde.` o `No se pudieron despachar los recordatorios del taller.` con su `tenant_id`. Para forzarlos: `php artisan notifications:send-reminders --tenant={id}`, con `queue:work` corriendo. Correrlo de nuevo no repite avisos | Historial 2.97 |
| `tenants:migrate` terminó con código 1 | Cada taller que falló quedó en mantenimiento (`503 TENANT_MAINTENANCE`); los demás se migraron. El error está en `tenant_migration_runs` de la central (la última fila del taller, `status = failed`) y en el log (`Falló la migración del taller.` con su `tenant_id`). Corregida la causa, `php artisan tenants:migrate --failed` repite solo esos talleres y les quita el mantenimiento. Si un taller figura "ya se está migrando en otra corrida", esperar a que termine (el lock vence en una hora si el proceso se cortó). Un mantenimiento puesto a mano no lo quita el comando | Historial 2.101 |
| `tenants:create` terminó con código 1 | El taller quedó en `provisioning` (responde `404 TENANT_NOT_FOUND`) con el error en `tenants.provisioning_error` de la central y en el log (`Falló el alta del taller.` con su `tenant_id`). Corregida la causa, repetir el mismo `tenants:create` con el mismo dominio y la misma base: retoma desde donde quedó y el administrador ya creado conserva su contraseña (la mostrada en la primera corrida). Para abandonarlo, `php artisan tenants:discard {id}` borra la base y la fila. Si el comando informa que el alta "ya se está dando" en otra corrida, esperar (el lock vence en una hora) | Historial 2.103 |
| Suspender, reactivar o dar de baja un taller | `php artisan tenants:suspend {id}` bloquea todo el taller (`403 TENANT_SUSPENDED`, también el login) sin tocar datos ni sesiones; `php artisan tenants:reactivate {id}` lo vuelve a abrir con las mismas sesiones, y los avisos que quedaron `failed` con `Taller suspendido.` se reenvían desde la app. `php artisan tenants:cancel {id}` (pide confirmación; `--force` sin preguntar) da de baja un taller activo o suspendido: es final, responde `404 TENANT_NOT_FOUND`, guarda la fecha en `tenants.cancelled_at` y conserva la base 5 años desde ahí (ver el borrado a los 5 años y la exportación asistida, abajo). Un comando que responde "solo se … y este está …" no cambió nada | Historial 2.94 y 2.104 |
| Backup | Por taller, `mysqldump --single-transaction --routines` de cada base de `tenants.database` (activos, suspendidos y dados de baja sin `database_deleted_at`) y sus carpetas `storage/app/public/tenants/{id}` y `storage/app/tenants/{id}/private`; además, la base central. La frecuencia, la retención y el destino se fijan con el hosting (V9). Probar la restauración periódicamente con un taller de prueba | Especificación §3.9, historial 2.105 |
| Restaurar un taller | 1. Crear una base vacía con otro nombre (`[a-z0-9_]`, `utf8mb4_unicode_ci`) e importar el dump, con la zona horaria de MySQL en `-03:00`. 2. `php artisan tenants:restore {id} {base_nueva}`: verifica la base (también que tenga el RUC del taller, si la actual responde), muestra lo que cambia, le aplica las migraciones que falten y cambia `tenants.database`. Con el taller activo, lo guardado en la base actual después del backup se pierde: para evitar escrituras, suspenderlo antes y reactivarlo después. 3. Si se restauran los archivos, copiar las carpetas del taller del backup a las de su id. 4. Comprobar el login por el host del taller. 5. Borrar la base anterior cuando ya no haga falta: el comando no la toca. Si terminó con código 1, no cambió nada; el motivo está en la salida y, si falló al preparar la base, en el log (`Falló la restauración del taller.` con su `tenant_id`) | Historial 2.105 |
| Exportación asistida | A pedido de un taller suspendido, o dado de baja durante los 5 años desde `cancelled_at` (no si ya tiene `database_deleted_at`). Un CSV por tabla de su base y el dump SQL, más sus dos carpetas de storage, en un archivo comprimido que se entrega por un canal seguro. Sin `personal_access_tokens`, `password_reset_tokens`, `sessions`, `migrations` ni la columna `password` de `users` | Historial 2.94 y 2.105 |
| Borrado a los 5 años | En la central, los talleres con `status = 'cancelled'`, `cancelled_at` de hace más de 5 años y `database_deleted_at` vacío. Por cada uno, comprobar que no hay una exportación pendiente ni un motivo legal para conservarla (V14), borrar su base (`DROP DATABASE`) y sus dos carpetas de storage, y completar `tenants.database_deleted_at`. La fila no se borra: queda `cancelled`, con su dominio y su base reservados, y `tenants:restore` la rechaza. Las copias en los backups siguen su propia retención | Historial 2.94 y 2.105 |
| Corregir una baja hecha por error | Ningún comando revierte una baja. En la central: `UPDATE tenants SET status = 'active', cancelled_at = NULL WHERE id = {id} AND status = 'cancelled' AND database_deleted_at IS NULL`. Después, `php artisan tenants:migrate --tenant={id}`, porque mientras estuvo dado de baja no se migró. Los avisos que quedaron `failed` con `Taller dado de baja.` se reenvían desde la app | Historial 2.104 y 2.105 |
| Un aviso a cliente lleva más de 6 horas `pending` | La base de su taller no respondió durante todo el plazo de reintentos y el job quedó en `failed_jobs` de la central. Con la base otra vez disponible: `php artisan queue:failed` para ubicarlo y `php artisan queue:retry {uuid}` (o `all`). Mientras siga `pending`, el taller no lo puede reenviar (`CLIENT_NOTIFICATION_PENDING`) | Historial 2.96 |

## Requisitos antes del primer taller comercial

* Tarea P8: fotos y firmas en almacenamiento privado (deuda #15).
* Decidir sobre el antivirus de archivos subidos (deuda #16).
* Límite de intentos en el login, hoy inexistente (historial 2.80).
* Revisar la licencia de ApexCharts (historial 2.77).
* T1 a T6 de la [línea SaaS](../roadmap/README.md).

## Sin definir

Frecuencia, retención y destino de los respaldos, monitoreo y proceso de publicación. La CI solo valida (tests, lint y build): no despliega.
