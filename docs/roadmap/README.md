# Roadmap

Fuente de verdad del estado por fase y entrega. Verificado el 2026-11-26 contra el código (auditoría `validar-docs`). Las fases conservan su numeración
original y no se renumeran. El detalle por módulo está en [`modules/README.md`](../modules/README.md).

## Última verificación

| Fecha | Backend | Frontend | Notas |
|---|---|---|---|
| 2026-11-26 | Tests puntuales en verde: `tests/Feature/Tenancy` (30, 98 aserciones; los 5 nuevos de `TenantRuntimeTest` comprobados contra su protección quitada) y las carpetas `Settings`, `Notifications`, `Http`, `Auth`, `Access` y `Seeders` (84, 925 aserciones); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend | T2, paso 3 (historial 2.95) y decisión sobre P9 (2.94). Corrida local en la rama `feature/t2-paso3-preparar-taller`, sin la suite completa; `Tenancy` y Pint se repitieron después del último cambio de código (un import sin uso y comentarios). Probado con `php artisan serve` sobre la base de desarrollo: `GET /api/branding` 200 en `127.0.0.1`, `404 TENANT_NOT_FOUND` en `localhost` y el login con credenciales inválidas `401`. Sin revisión en navegador: las pantallas no cambian |
| 2026-11-25 | 523 tests en verde, 9070 aserciones (antes de T2, paso 3); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T2, paso 2 (historial 2.93). Corrida de la CI sobre `main` (`3932a32`), no local. Primera corrida completa con el taller de prueba |
| 2026-11-25 | Tests puntuales en verde (240, 4559 aserciones): `tests/Feature/Tenancy` (25, cada test comprobado contra su protección quitada) y las carpetas `Http`, `Auth`, `Access`, `Seeders`, `Settings`, `Notifications`, `Documents`, `Invoices`, `Claims` y `Receptions`; Pint sin pendientes | ESLint (sin cambios en el frontend) | T2, paso 2 (historial 2.93). Corrida local, sin la suite completa. Taller #1 registrado en `synctaller_central` (`127.0.0.1` → `synctaller`) y probado con `php artisan serve`: `GET /api/branding` 200 en `127.0.0.1` y `404 TENANT_NOT_FOUND` en `localhost` (también el login), `/up` fuera de la identificación y CORS desde el SPA sin cambios. Sin revisión en navegador: las pantallas no cambian |
| 2026-11-25 | 508 tests en verde, 9007 aserciones (antes de T2, paso 2; con Laravel y Symfony ya actualizados); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | Deuda #19 (historial 2.92). Corrida de la CI sobre `main` (`11cd48d`), no local |
| 2026-11-25 | Tests puntuales en verde (131, 2087 aserciones): carpetas `Http`, `Auth`, `Access`, `Tenancy`, `Notifications`, `Documents`, `Invoices` y `Settings`; Pint sin pendientes; `composer audit` sin avisos | Sin corrida: el cambio no tocó el frontend | Deuda #19, actualización de dependencias (historial 2.92). Corrida local, sin la suite completa |
| 2026-11-25 | 508 tests en verde, 9007 aserciones (antes de la deuda #19); Pint sin pendientes | Vitest en verde (51), ESLint y build | T2, paso 1 y guía de Postman quitada (historial 2.90 y 2.91). Corrida de la CI sobre `main` (`6b0ef8b`), no local |
| 2026-11-25 | Tests puntuales en verde: `tests/Feature/Tenancy` (10, 20 aserciones, cada test comprobado contra su protección quitada) y las carpetas `Notifications`, `Auth`, `Access`, `Settings` y `Seeders` (78, 902 aserciones); Pint sin pendientes; migración central probada en MySQL (`synctaller_central`) | ESLint (sin cambios en el frontend) | T2, paso 1 (historial 2.90). Corrida local, sin la suite completa. Sin revisión en navegador: la API no cambia |
| 2026-11-25 | 498 tests en verde, 8987 aserciones (antes de T2); Pint sin pendientes | Vitest en verde (51), ESLint y build | V16 y M10 (historial 2.89). Corrida de la CI sobre `main` (`3137016`), no local |
| 2026-11-25 | Tests puntuales en verde (52, 1736 aserciones): `DeductibleNetModeTest`, `AllocationInvoiceTest`, `ClaimTest`, `WorkOrderAllocationTest`, `WorkOrderInsurerFlowTest`, `InsurerReportsTest`, `InsurerTest`, `InsurerAuthorizationTest`, `BudgetItemResponsibilityTest` y `DatabaseSeederTest`; Pint sin pendientes | Vitest en verde (`features/allocations` y `features/claims`, 17), ESLint y build | V16 y encendido de M10 (historial 2.89). Corrida local, sin la suite completa. Revisión en navegador con cajero y recepcionista, en una base temporal |
| 2026-11-25 | 498 tests en verde, 8969 aserciones (antes de V16); Pint sin pendientes | Vitest en verde (51), ESLint y build | Anulación en el mes de emisión, V19 (historial 2.88). Corrida de la CI sobre `main` (`bab2f8a`), no local |
| 2026-11-25 | Tests puntuales en verde (62, 2349 aserciones): carpetas `Invoices` y `Payments`, `DocumentPdfTest`, `AllocationInvoiceTest`, `ClaimTest`, `InsurerReportsTest` y `DatabaseSeederTest`; Pint sin pendientes | Vitest en verde (`features/invoices`, 8), ESLint y build | Anulación solo en el mes de emisión, V19 (historial 2.88). Corrida local, sin la suite completa. Revisión en navegador con cajero y administrador, en una base temporal |
| 2026-11-25 | 496 tests en verde, 8898 aserciones (antes de V19); Pint sin pendientes | Vitest en verde (49), ESLint y build | Ajustes V18, V20 y V15 (historial 2.87). Corrida de la CI sobre `main` (`7081c94`), no local |
| 2026-11-25 | Tests puntuales en verde (19, 604 aserciones): `CreditNoteTest`, `ClaimDocumentTest` y `AllocationInvoiceTest`; Pint sin pendientes | Vitest en verde (`claims/api.test.ts`, 7), ESLint y build | Ajustes V18, V20 y V15 (historial 2.87). Corrida local, sin la suite completa. Revisión en navegador con cajero, recepcionista y jefe de taller, en una base temporal |
| 2026-11-24 | 495 tests en verde, 8888 aserciones (antes de V18, V20 y V15); Pint sin pendientes | Vitest en verde (47), ESLint y build | Datos del cliente en la factura, V10 (historial 2.86). Corrida de la CI sobre `main` (`e2aa4a8`), no local |
| 2026-11-24 | 494 tests en verde, 8848 aserciones (antes de V10); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | Recibo completo, V11 (historial 2.85). Corrida de la CI sobre `main` (`02dbd33`), no local |
| 2026-11-24 | 493 tests en verde, 8805 aserciones (antes de V11); Pint sin pendientes | Vitest en verde (46), ESLint y build | M12 (historial 2.84). Corrida de la CI sobre `main` (`d51fddf`), no local |
| 2026-11-24 | 488 tests en verde (línea base), 8560 aserciones; Pint sin pendientes | Vitest en verde (43), ESLint y build | **Baseline** (historial 2.82): después de aplicar Pint a todo el backend. Desde acá, tests puntuales salvo pedido del usuario |
| 2026-11-24 | 488 tests en verde, 8560 aserciones (antes de Pint) | Vitest en verde (43), lint y build | Commit inicial del monorepo |

## Próximas tareas

Ninguna entrega empieza sin autorización del usuario. **El sistema no está listo para producción.**

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | **T3:** colas, cache, permisos y scheduler por taller (pasos 4 y 5 de la revisión del spike) | Tras T2 (historial 2.95). Empieza con el diseño, según el plan de la sección 2.80 del [historial](../decisions/historial.md) |
| 2 | T4 y T5 | Según el plan de la sección 2.80. T5 incluye la suspensión y la baja decididas el 2026-11-26 (historial 2.94): bloqueo total, base conservada 5 años y exportación asistida a pedido |
| 3 | Tarea P8 (fotos y firmas a privado) | Después de T4; requisito antes del primer taller comercial |

Las entregas de las respuestas de la contadora (sección 2.83 del historial) quedaron completas el 2026-11-25 con V16 y el
encendido de M10 (2.89).

Las validaciones externas pendientes están en §10 de la [especificación](../especificacion-aseguradoras.html).

## Módulos base y FASES 9 a 25

| Fase | Contenido | Estado |
|---|---|---|
| Hasta FASE 9 | Autenticación, usuarios, clientes, vehículos, recepción, diagnóstico, presupuestos, OT, respuestas y excepciones | COMPLETADO |
| FASE 9.14 | Auditoría de excepciones y controllers | COMPLETADO |
| FASE 10 | Catálogo de repuestos, categorías y proveedores | COMPLETADO |
| FASE 11 | Inventario | COMPLETADO |
| FASE 12 | Repuestos en la OT | COMPLETADO |
| FASE 13 | Servicios y mano de obra | COMPLETADO |
| FASE 14 | Cierre económico | COMPLETADO |
| FASE 15 | Facturación | COMPLETADO |
| FASE 16 | Pagos | COMPLETADO |
| FASE 17 | Entrega del vehículo | COMPLETADO |
| FASE 18 | Historial del vehículo | COMPLETADO |
| FASE 19 | Mantenimiento preventivo | COMPLETADO |
| FASE 20 | Reportes y dashboard | COMPLETADO |
| FASE 21 | Compras | COMPLETADO |
| FASE 22 | Pagos a proveedores | COMPLETADO |
| FASE 23 | Notificaciones por email y bandeja interna | COMPLETADO |
| FASE 24 | Exportación y comprobantes | COMPLETADO |
| FASE 25 | Auditoría | COMPLETADO |

Entre fases se hicieron además preferencias y logo, datos de ejemplo por escenarios, catálogo de ítems de diagnóstico y actualizaciones
de Laravel y dependencias (historial 2.39 a 2.53, 2.77 y 2.79).

El diseño original de estas fases se conserva en
[historial](../decisions/historial.md) como historia.

## Módulo de aseguradoras

Plan de §9 de la [especificación](../especificacion-aseguradoras.html).

| Línea | Entrega | Estado |
|---|---|---|
| E | E0 a E5: correcciones previas | COMPLETADO |
| M | M1 a M9 y P10 | COMPLETADO |
| M | M10: deducible facturado | COMPLETADO, encendido desde el 2026-11-25 con V16 (2.89) |
| M | M11: reportes | COMPLETADO |
| M | M11: avisos a la aseguradora | PENDIENTE (V7) |
| M | M12: retenciones de IVA y renta | COMPLETADO |

Frontend: FA0 a FA4, M10 y M12 completos ([detalle](../modules/aseguradoras.md)).

## Línea T: infraestructura SaaS

| Entrega | Estado |
|---|---|
| T0: spike | COMPLETADO |
| T1: configuración en la base | COMPLETADO |
| T2: central e identificación | COMPLETADO: pasos 1 (central, paquete y modelo `Tenant`) y 2 (identificación por host) el 2026-11-25; paso 3 (preparar el taller, `TenantRuntime`) el 2026-11-26 |
| T3: colas, cache, permisos y scheduler | PENDIENTE |
| T4: storage | PENDIENTE |
| Tarea P8: fotos y firmas a privado | PENDIENTE |
| T5: ciclo de vida | PENDIENTE |
| T6: migrar la instalación actual | PENDIENTE |

Los pasos 0 a 7 de la revisión del spike son internos de T2, T3 y T5, no fases nuevas (historial 2.80). El paso 0 está hecho y se repitió con 4.2.3 el 2026-11-25; los pasos 1, 2 y 3 también (2.90, 2.93 y 2.95).

## Pendiente sin fase

| Tema | Estado |
|---|---|
| Notificaciones por WhatsApp | PENDIENTE, sin diseño |
| Validaciones externas (contador, legal, talleres) | §10 de la especificación |
| Deuda técnica | [`deuda-tecnica.md`](deuda-tecnica.md) |
| Despliegue | [Sin definir](../development/deployment.md) |
