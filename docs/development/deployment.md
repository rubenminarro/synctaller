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
| Un aviso a cliente lleva más de 6 horas `pending` | La base de su taller no respondió durante todo el plazo de reintentos y el job quedó en `failed_jobs` de la central. Con la base otra vez disponible: `php artisan queue:failed` para ubicarlo y `php artisan queue:retry {uuid}` (o `all`). Mientras siga `pending`, el taller no lo puede reenviar (`CLIENT_NOTIFICATION_PENDING`) | Historial 2.96 |

## Requisitos antes del primer taller comercial

* Tarea P8: fotos y firmas en almacenamiento privado (deuda #15).
* Decidir sobre el antivirus de archivos subidos (deuda #16).
* Límite de intentos en el login, hoy inexistente (historial 2.80).
* Revisar la licencia de ApexCharts (historial 2.77).
* T1 a T6 de la [línea SaaS](../roadmap/README.md).

## Sin definir

Respaldos, monitoreo y proceso de publicación. La CI solo valida (tests, lint y build): no despliega.
