# Infraestructura SaaS

**Estado: EN DESARROLLO.** Operar varios talleres, con una base por taller. Lo hecho hasta ahora es la evaluación, la
configuración por taller, T2 completo (la base central, el paquete y el modelo `Tenant`, la identificación del taller por
el host y su preparación, `TenantRuntime::ensureReady()`) y T3 completo: **cada request a `api/*` y cada job usan la
base de su taller** (reglas de negocio 6.34), con la cola y la cache en la central, y el scheduler despacha un job de
recordatorios por taller, a la hora de cada uno. Sin taller activo, nada consulta la conexión por defecto (fallo cerrado,
TD10). T4 completo: los archivos de cada taller van a su propia carpeta y sin taller activo los discos no se resuelven.
Falta P8 y T5 en adelante.

| Dato | Referencia |
|---|---|
| Diseño | [`especificacion-aseguradoras.html`](../especificacion-aseguradoras.html), §3 y §9 |
| Evaluación y medición | [`spike-t0/informe.md`](../spike-t0/informe.md) |
| Decisiones TD1–TD11 y plan | [historial](../decisions/historial.md), sección 2.80 |
| Paso 1 de T2 | [historial](../decisions/historial.md), sección 2.90 |
| Paso 2 de T2 | [historial](../decisions/historial.md), sección 2.93; [reglas de negocio](../reglas-de-negocio.md), 6.34 |
| Paso 3 de T2 | [historial](../decisions/historial.md), sección 2.95; [reglas de negocio](../reglas-de-negocio.md), 6.32 y 6.34 |
| Suspensión y baja (P9) | [historial](../decisions/historial.md), sección 2.94 |
| Paso 4 de T3 | [historial](../decisions/historial.md), sección 2.96; [reglas de negocio](../reglas-de-negocio.md), 6.17, 6.32 y 6.34 |
| Paso 5 de T3 | [historial](../decisions/historial.md), sección 2.97; [reglas de negocio](../reglas-de-negocio.md), 6.17, 6.32 y 6.34 |
| T4 | [historial](../decisions/historial.md), sección 2.98; [reglas de negocio](../reglas-de-negocio.md), 6.24 y 6.34 |

## Estado por entrega

| Entrega | Objetivo | Estado | Observaciones |
|---|---|---|---|
| T0 | Evaluar el paquete | COMPLETADO | Viable. Paso 0 medido el 2026-11-01 y repetido con 4.2.3 y diez talleres el 2026-11-25 (informe, sección 9) |
| T1 | Configuración del taller en la base | COMPLETADO | `reglas-de-negocio.md` 6.32. Tests: `WorkshopSettings` 14 |
| T2 | Base central e identificación | COMPLETADO | Paso 1 hecho el 2026-11-25: paquete 4.2.3, conexiones `central` y `tenant`, tabla `tenants` y tareas al activar un taller. Paso 2 hecho el 2026-11-25: `IdentifyTenant`, errores `TENANT_*`, conexión por defecto `tenant` en HTTP y comando `tenants:register`. Paso 3 hecho el 2026-11-26: `TenantRuntime::ensureReady()` prepara el taller en el request, en cada job, en el scheduler y en el comando de recordatorios; se borró `ApplyWorkshopSettings`. Tests en `tests/Feature/Tenancy` (30) |
| T3 | Colas, cache, permisos y scheduler | COMPLETADO | Diseño aprobado el 2026-11-26, en dos pasos. Paso 4 hecho el 2026-11-26 (2.96): cola, fallidos y cache en la central; todo job con su taller (`PrepareTenant`: libera con espera, descarta si el taller está suspendido); permisos y configuración limpios al cambiar de taller; recordatorios por taller. Paso 5 hecho el 2026-11-26 (2.97): un job de recordatorios por taller (`SendTenantReminders`), el scheduler con la hora de cada taller una vez por día (se recupera la hora perdida) y el fallo cerrado de TD10 en el código (`ensureReady()` sin taller falla). Tests en `tests/Feature/Tenancy` (45) |
| T4 | Storage por taller | COMPLETADO | Hecho el 2026-11-26 (2.98): driver `tenant_local` (`TenantStorage`) con raíces `tenants/{id}` en el disco público y en el privado (`tenant_private`, antes `claim_documents`), `SwitchTenantStorageTask`, fallo cerrado sin taller y URL pública con el host del taller. No conserva los archivos actuales. Tests en `tests/Feature/Tenancy` (49; 51 corridas en PHPUnit) |
| Tarea P8 | Fotos y firmas a privado | PENDIENTE | Después de T4; deuda #15 |
| T5 | Ciclo de vida | PENDIENTE | Incluye `tenants:migrate`, la suspensión (bloqueo total) y la baja con la base conservada 5 años y exportación asistida a pedido (historial 2.94) |
| T6 | Migrar la instalación actual | PENDIENTE | Requiere T1 a T5, P8 y decidir el hosting (V9) |

## Observaciones para continuar

* La línea T no bloquea a la línea de aseguradoras.
* La configuración de T1 se aplica en un solo punto, `TenantRuntime::ensureReady()` (historial 2.95): en el request, en el
  middleware de cada job y en el comando de recordatorios con `--due`, siempre con su taller. Sin taller activo lanza una
  excepción (fallo cerrado, TD10; historial 2.97): un proceso nuevo debe activar el taller antes de prepararlo.
* Todo job es del taller (2.96). Un job nuevo usa el trait `RunsInTenant`, para liberarse si el taller no está disponible y
  descartarse si está suspendido; si deja registro en el taller, implementa `DiscardsForSuspendedTenant`. Un job que no
  sea de ningún taller implementa `NotTenantAware`.
* Las tablas `jobs`, `failed_jobs`, `job_batches`, `cache` y `cache_locks` de las bases de taller quedaron sin uso (deuda
  técnica #20).
* Para repetir la medición, las herramientas están en [`spike-t0/`](../spike-t0/README.md).
