# Despliegue

**Estado: PENDIENTE.** No hay entorno de producción ni procedimiento de despliegue definidos: hosting, servidor de base y red se
deciden antes de T6 (V9). Este documento reúne solo los requisitos ya identificados, para no perderlos.

## Requisitos conocidos

| Requisito | Origen |
|---|---|
| Un `queue:work` permanente y `schedule:run` cada minuto | [`setup.md`](setup.md) |
| Zona horaria de MySQL fijada en `-03:00`, también al restaurar un respaldo | Handoff 2.79 |
| `DB_DATABASE` vacío en producción, cuando exista la multitenancy | TD10 |
| `.env` sin datos de un taller: serían el valor por defecto de todos | Revisión del spike, historial 2.80 |
| `APP_DEBUG=false` y `MAIL_MAILER` real | Práctica de Laravel |
| Remitente autorizado en el servidor de correo | Handoff 2.78 |
| `INSURER_DEDUCTIBLE_NET_MODE` apagado hasta la confirmación escrita del contador | `reglas-de-negocio.md` 6.31 |
| Sin `config:cache` en desarrollo | `backend/CLAUDE.md` |

## Requisitos antes del primer taller comercial

* Tarea P8: fotos y firmas en almacenamiento privado (deuda #15).
* Decidir sobre el antivirus de archivos subidos (deuda #16).
* Límite de intentos en el login, hoy inexistente (historial 2.80).
* Revisar la licencia de ApexCharts (historial 2.77).
* T1 a T6 de la [línea SaaS](../roadmap/README.md).

## Sin definir

Respaldos, monitoreo y proceso de publicación. La CI solo valida (tests, lint y build): no despliega.
