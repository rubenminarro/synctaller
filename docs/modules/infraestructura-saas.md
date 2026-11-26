# Infraestructura SaaS

**Estado: EN DESARROLLO.** Operar varios talleres, con una base por taller. Lo hecho hasta ahora es la evaluación, la
configuración por taller y T2 completo: la base central, el paquete y el modelo `Tenant`, la identificación del taller por
el host y su preparación (`TenantRuntime::ensureReady()`: su base y su configuración). **Cada request a `api/*` usa la
base de su taller** (reglas de negocio 6.34); la consola, el worker y el scheduler siguen con una sola
base (`DB_CONNECTION`) hasta T3.

| Dato | Referencia |
|---|---|
| Diseño | [`especificacion-aseguradoras.html`](../especificacion-aseguradoras.html), §3 y §9 |
| Evaluación y medición | [`spike-t0/informe.md`](../spike-t0/informe.md) |
| Decisiones TD1–TD11 y plan | [historial](../decisions/historial.md), sección 2.80 |
| Paso 1 de T2 | [historial](../decisions/historial.md), sección 2.90 |
| Paso 2 de T2 | [historial](../decisions/historial.md), sección 2.93; [reglas de negocio](../reglas-de-negocio.md), 6.34 |
| Paso 3 de T2 | [historial](../decisions/historial.md), sección 2.95; [reglas de negocio](../reglas-de-negocio.md), 6.32 y 6.34 |
| Suspensión y baja (P9) | [historial](../decisions/historial.md), sección 2.94 |

## Estado por entrega

| Entrega | Objetivo | Estado | Observaciones |
|---|---|---|---|
| T0 | Evaluar el paquete | COMPLETADO | Viable. Paso 0 medido el 2026-11-01 y repetido con 4.2.3 y diez talleres el 2026-11-25 (informe, sección 9) |
| T1 | Configuración del taller en la base | COMPLETADO | `reglas-de-negocio.md` 6.32. Tests: `WorkshopSettings` 14 |
| T2 | Base central e identificación | COMPLETADO | Paso 1 hecho el 2026-11-25: paquete 4.2.3, conexiones `central` y `tenant`, tabla `tenants` y tareas al activar un taller. Paso 2 hecho el 2026-11-25: `IdentifyTenant`, errores `TENANT_*`, conexión por defecto `tenant` en HTTP y comando `tenants:register`. Paso 3 hecho el 2026-11-26: `TenantRuntime::ensureReady()` prepara el taller en el request, en cada job, en el scheduler y en el comando de recordatorios; se borró `ApplyWorkshopSettings`. Tests en `tests/Feature/Tenancy` (30) |
| T3 | Colas, cache, permisos y scheduler | PENDIENTE | Depende de T2 |
| T4 | Storage por taller | PENDIENTE | Depende de T3 |
| Tarea P8 | Fotos y firmas a privado | PENDIENTE | Después de T4; deuda #15 |
| T5 | Ciclo de vida | PENDIENTE | Incluye `tenants:migrate`, la suspensión (bloqueo total) y la baja con la base conservada 5 años y exportación asistida a pedido (historial 2.94) |
| T6 | Migrar la instalación actual | PENDIENTE | Requiere T1 a T5, P8 y decidir el hosting (V9) |

## Observaciones para continuar

* La línea T no bloquea a la línea de aseguradoras.
* La configuración de T1 se aplica en un solo punto, `TenantRuntime::ensureReady()` (historial 2.95). Hasta T3, fuera de HTTP
  no hay taller activo y la aplica sobre `DB_CONNECTION`; en T3 el middleware del job y el recorrido de talleres del
  scheduler la llaman con su taller.
* Las colas por taller siguen apagadas hasta T3, en dos lugares: los listeners de colas del paquete no se registran
  (`DeferQueueTenancyAction`, historial 2.93) y `queues_are_tenant_aware_by_default` es `false` (2.90). Sin los listeners, la
  opción no tiene efecto. En T3 primero se vuelven a registrar los listeners (la acción del paquete) y después se enciende la
  opción, cuando todo job lleve su taller: con los listeners y la opción encendida, un job sin taller se borra y falla.
* Para repetir la medición, las herramientas están en [`spike-t0/`](../spike-t0/README.md).
