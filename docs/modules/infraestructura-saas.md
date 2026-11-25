# Infraestructura SaaS

**Estado: EN DESARROLLO.** Operar varios talleres, con una base por taller. Lo hecho hasta ahora es la evaluación, la
configuración por taller y el paso 1 de T2: la base central, el paquete y el modelo `Tenant`. **Todavía nada activa un taller**:
la API sigue usando una sola base.

| Dato | Referencia |
|---|---|
| Diseño | [`especificacion-aseguradoras.html`](../especificacion-aseguradoras.html), §3 y §9 |
| Evaluación y medición | [`spike-t0/informe.md`](../spike-t0/informe.md) |
| Decisiones TD1–TD11 y plan | [historial](../decisions/historial.md), sección 2.80 |
| Paso 1 de T2 | [historial](../decisions/historial.md), sección 2.90 |

## Estado por entrega

| Entrega | Objetivo | Estado | Observaciones |
|---|---|---|---|
| T0 | Evaluar el paquete | COMPLETADO | Viable. Paso 0 medido el 2026-11-01 y repetido con 4.2.3 y diez talleres el 2026-11-25 (informe, sección 9) |
| T1 | Configuración del taller en la base | COMPLETADO | `reglas-de-negocio.md` 6.32. Tests: `WorkshopSettings` 14 |
| T2 | Base central e identificación | EN DESARROLLO | Paso 1 hecho el 2026-11-25: paquete 4.2.3, conexiones `central` y `tenant`, tabla `tenants` y tareas al activar un taller. Tests en `tests/Feature/Tenancy`. Faltan el paso 2 (identificación por host) y el 3 (unificar T1) |
| T3 | Colas, cache, permisos y scheduler | PENDIENTE | Depende de T2 |
| T4 | Storage por taller | PENDIENTE | Depende de T3 |
| Tarea P8 | Fotos y firmas a privado | PENDIENTE | Después de T4; deuda #15 |
| T5 | Ciclo de vida | PENDIENTE | Incluye `tenants:migrate` y suspensión |
| T6 | Migrar la instalación actual | PENDIENTE | Requiere T1 a T5, P8 y decidir el hosting (V9) |

## Observaciones para continuar

* La línea T no bloquea a la línea de aseguradoras.
* `ApplyWorkshopSettings`, `Queue::before` y el comando de recordatorios aplican hoy la configuración de T1 en tres lugares: se
  unifican en T2.
* Las colas por taller siguen apagadas (`queues_are_tenant_aware_by_default = false`) hasta T3: encenderlas antes perdería los
  jobs que no traen taller (historial 2.90).
* Para repetir la medición, las herramientas están en [`spike-t0/`](../spike-t0/README.md).
