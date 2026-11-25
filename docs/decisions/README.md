# Decisiones

Índice de las decisiones técnicas y de negocio. No repite su contenido: cada fila apunta a donde está el detalle y el motivo.

## Series de decisiones

Las siglas se conservan tal como se usaron en el proyecto. Ojo: `D` (decisión de avisos) y `D1–D10` (discrepancias) son cosas distintas.

| Serie | Tema | Dónde está |
|---|---|---|
| A a G | Revisión de la especificación: deducible, aceptación del dueño, excepciones, avisos, documentos, facturas de la OT, umbrales | [Especificación](../especificacion-aseguradoras.html), §10.2 |
| P1 a P9 | Propuestas de la especificación: tenancy, empresas, correlativos, extras, repartos, topes, archivos, suspensión | Especificación, §10.2 |
| P10 | Notas de crédito emitidas fuera del sistema | Especificación, §10.2; historial 2.66 |
| G1 a G10 | Preguntas externas | Especificación, §10.1 |
| V1 a V22 | Validaciones y precisiones externas, con sus cierres internos | Especificación, §10.1; historial 2.67 |
| R1 a R11 | Invariantes del reparto y la facturación | Especificación, §6.2; [Reglas de negocio](../reglas-de-negocio.md), 6.27 y 6.31 |
| S1 a S13 | Criterios del spike | Especificación, §3.13; [informe](../spike-t0/informe.md) |
| TD1 a TD11 | Decisiones para T2 | Especificación, §10.2; historial 2.80 |
| D1 a D10 | Discrepancias del estudio, no decisiones | [Deuda técnica](../roadmap/deuda-tecnica.md), 14.1 |
| #1 a #18 | Deudas técnicas | [Deuda técnica](../roadmap/deuda-tecnica.md) |

## Decisiones por tema

| Tema | Sección del historial |
|---|---|
| Convenciones de diseño | 1 (Fundamentos) |
| Repuestos extra con aprobación del cliente | 2.49 |
| Migración a Laravel 13 | 2.51 |
| Datos de ejemplo por escenarios | 2.52 |
| Códigos internos con bloqueo | 2.55 |
| Importes en `decimal(14,2)` | 2.56 |
| Spike T0 | 2.58, 2.80 |
| Modalidad neta del deducible | 2.75 |
| Discrepancias D3, D7 y D8 | 2.74 |
| Actualización de dependencias y licencia de ApexCharts | 2.77 |
| Configuración del taller | 2.78 |
| Hora de Paraguay | 2.79 |
| Monorepo, Pint y baseline de tests | 2.82 |

Las secciones están en el [historial de decisiones](historial.md), que se completa con cada entrega nueva (la siguiente es la 2.83).

## Decisiones técnicas que hay que tener presentes

| Tema | Decisión |
|---|---|
| Multitenancy | Base central más una base por taller, con `spatie/laravel-multitenancy` 4.2.3. Aislada en infraestructura: modelos, controllers y services no la conocen |
| Permisos por taller | La tarea de cambio usa `initializeCache()`, también en HTTP. **Nunca `forgetCachedPermissions()`** |
| Fallo cerrado | `DB_DATABASE` vacío en producción: sin taller activo, una consulta falla |
| Conexión a la central | Persistente solo en la central; elimina la sobrecarga medida |
| Zona horaria | `APP_TIMEZONE`, por defecto `America/Asuncion`. La base guarda hora local y la API envía UTC |
| Deducible mayor a 0 | Implementado detrás de `INSURER_DEDUCTIBLE_NET_MODE`. No se enciende sin la confirmación escrita del contador |
| Notas de crédito | SyncTaller las registra, no las emite (P10) |
| Documentos de siniestros | Disco privado sin URL; toda descarga pasa por la API y se audita |
| Monorepo | Desde el 2026-11-24, `backend/` y `frontend/` en un solo repositorio, sin el historial de Git anterior. Cada app conserva sus dependencias y su configuración; la raíz solo orquesta (historial 2.82) |
