# Roadmap

Fuente de verdad del estado por fase y entrega. Verificado el 2026-11-26 contra el código (auditoría `validar-docs`). Las fases conservan su numeración
original y no se renumeran. El detalle por módulo está en [`modules/README.md`](../modules/README.md).

## Última verificación

| Fecha | Backend | Frontend | Notas |
|---|---|---|---|
| 2026-11-26 | 613 tests en verde, 9770 aserciones (antes de la tarea P8, paso 3); Pint sin pendientes | Vitest en verde (64), ESLint y build | Tarea P8, paso 2 (historial 2.106). Corrida de la CI sobre `main` (`a1542e9`), no local |
| 2026-11-26 | Tests puntuales en verde (86, 1317 aserciones): `tests/Feature/Receptions` (con 2 nuevos en `ReceptionTest` y 2 en `DiagnosticTest`, cada protección comprobada quitándola), `tests/Feature/Deliveries`, `tests/Feature/Seeders`, `tests/Feature/Access` y `TenantStorageTest`; Pint sin pendientes | Vitest (`PhotoGallery.test.tsx`, 3 nuevos, y `PrivateImage.test.tsx`), ESLint y build | Tarea P8, paso 2 (historial 2.106). Corrida local en la rama `feature/p8-paso2-fotos`, sin la suite completa. Revisión en navegador con administrador, recepcionista, jefe de taller, mecánico y cajero (403), en el entorno aislado (SQLite), ya con el storage aislado (deuda #25 resuelta), repetida al cerrar con los mismos resultados. Deudas #26 y #27 registradas, anteriores a P8 |
| 2026-11-26 | 610 tests en verde, 9688 aserciones (antes de la tarea P8, paso 2); Pint sin pendientes | Vitest en verde (61), ESLint y build | Tarea P8, paso 1 (historial 2.106). Corrida de la CI sobre `main` (`4fa99a5`), no local |
| 2026-11-26 | Tests puntuales en verde (24, 611 aserciones): `tests/Feature/Deliveries` (con 3 nuevos en `VehicleDeliveryTest`, cada protección comprobada quitándola) y `tests/Feature/Documents`; `tests/Feature/Access` (19); Pint sin pendientes | Vitest (`src/components/ui/PrivateImage.test.tsx`, 4 nuevos), ESLint y build | Tarea P8, paso 1 (historial 2.106). Corrida local en la rama `feature/p8-paso1-firma`, sin la suite completa. Revisión en navegador con administrador, recepcionista, cajero y mecánico (403), en el entorno aislado (SQLite) |
| 2026-11-26 | 607 tests en verde, 9573 aserciones (antes de la tarea P8); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T5, paso 5 (historial 2.105). Corrida de la CI sobre `main` (`4543403`), no local. T5 completo |
| 2026-11-26 | Tests puntuales en verde: `tests/Feature/Tenancy` (104, 571 aserciones, con `RestoreTenantCommandTest`, 14 nuevos, cada protección comprobada quitándola); Pint sin pendientes | ESLint (sin cambios en el frontend) | T5, paso 5 (historial 2.105). Corrida local en la rama `feature/t5-paso5-restauracion`, sin la suite completa. Migración central `000005` aplicada en MySQL (`synctaller_central`); restauración probada con un taller temporal y un `mysqldump` importado en otra base (rechazos con código 1, restauración con la `000074` pendiente con código 0, `GET /api/branding` 200 por su host), ya borrados. Sin revisión en navegador: las pantallas no cambian |
| 2026-11-26 | 593 tests en verde, 9499 aserciones (antes de T5, paso 5); Pint sin pendientes | Vitest en verde (57), ESLint y build | T5, paso 4 (historial 2.104). Corrida de la CI sobre `main` (`f7025fb`), no local |
| 2026-11-26 | Tests puntuales en verde (107, 784 aserciones): `tests/Feature/Tenancy` (90, con `TenantStatusCommandsTest`, 9 nuevos, y uno nuevo en `IdentifyTenantTest` y en `TenantQueueTest`, cada protección comprobada quitándola) y `tests/Feature/Notifications` (17); Pint sin pendientes | Vitest en verde (`src/services/api.test.ts` y `src/pages/TenantSuspendedPage.test.tsx`, 6 nuevos, comprobados quitando su protección), ESLint y build | T5, paso 4 (historial 2.104). Corrida local en la rama `feature/t5-paso4-suspension`, sin la suite completa. Migración central `000004` aplicada en MySQL (`synctaller_central`); comandos y respuestas `403`/`404` probados con una fila temporal, ya borrada. Revisión en navegador con administrador y cajero, en el entorno aislado (SQLite): pantalla de taller suspendido con sesión y en el login, y la sesión vuelve al reactivar |
| 2026-11-26 | 582 tests en verde, 9417 aserciones (antes de T5, paso 4); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T5, paso 3 (historial 2.103). Corrida de la CI sobre `main` (`ea62bca`), no local |
| 2026-11-26 | Tests puntuales en verde (94, 570 aserciones): `tests/Feature/Tenancy` (79, con `CreateTenantCommandTest`, 10 nuevos, `DiscardTenantCommandTest`, 6 nuevos, y uno nuevo en `IdentifyTenantTest`, cada protección comprobada quitándola), `tests/Feature/Seeders` (6) y `tests/Feature/Auth` (9); Pint sin pendientes | ESLint (sin cambios en el frontend) | T5, paso 3 (historial 2.103). Corrida local en la rama `feature/t5-paso3-alta`, sin la suite completa. Alta y descarte probados en MySQL con un taller temporal, ya borrado (2.103). El entorno local estaba en un estado anterior a T2 y el taller #1 de desarrollo no está registrado (2.103, "Entorno local"). Sin revisión en navegador: las pantallas no cambian |
| 2026-11-26 | 565 tests en verde, 9298 aserciones (antes de T5, paso 3); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T5, paso 2 (historial 2.102). Corrida de la CI sobre `main` (`ff454fe`), no local |
| 2026-11-26 | Tests puntuales en verde (85, 700 aserciones): `tests/Feature/Tenancy` (con `TenantQueueTablesTest`, 3 nuevos, cada protección comprobada quitándola, y `SwitchTenantTest` con la cache en la central), `tests/Feature/Seeders` y `tests/Feature/Notifications`; Pint sin pendientes | ESLint (sin cambios en el frontend) | T5, paso 2 (historial 2.102). Corrida local en la rama `feature/t5-paso2-tablas-sin-uso`, sin la suite completa. En MySQL: `tenants:migrate` con el taller #1 aplicó la `000074` (código 0); las cinco tablas quedaron solo en `synctaller_central` (estaban vacías en `synctaller`) y la segunda corrida no tiene pendientes. La migración rechaza una conexión con la base central. Sin revisión en navegador: las pantallas no cambian |
| 2026-11-26 | 562 tests en verde, 9267 aserciones (antes de T5, paso 2); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T5, paso 1 (historial 2.101). Corrida de la CI sobre `main` (`afacc66`), no local |
| 2026-11-26 | Tests puntuales en verde (65, 382 aserciones): `tests/Feature/Tenancy` (con `TenantMigrateCommandTest`, 8 nuevos, cada protección comprobada quitándola) y `tests/Feature/Seeders`; Pint sin pendientes | ESLint (sin cambios en el frontend) | T5, paso 1 (historial 2.101). Corrida local en la rama `feature/t5-paso1-migrate`, sin la suite completa. Migración central aplicada en MySQL (`synctaller_central`); `tenants:migrate` con el taller #1: sin pendientes (código 0), `--failed` sin fallidos (código 0) y `--tenant=99` (código 1). Sin revisión en navegador: las pantallas no cambian |
| 2026-11-26 | 554 tests en verde, 9201 aserciones (antes de T5); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | Deuda #23 (historial 2.100). Corrida de la CI sobre `main` (`90f2ec8`), no local |
| 2026-11-26 | Tests puntuales en verde (57 corridas de PHPUnit, 316 aserciones): `tests/Feature/Seeders` (con `SeedingTenantTest`, 5 nuevos, cada uno comprobado contra su protección quitada) y `tests/Feature/Tenancy`; Pint sin pendientes | ESLint (sin cambios en el frontend) | Deuda #23 (historial 2.100). Corrida local en la rama `fix/deuda-23-siembra`, sin la suite completa. Guía nueva probada sobre SQLite temporal: sin taller no siembra y lo indica; con el taller, siembra completa con los archivos en su carpeta, y repetida sin errores. Sin revisión en navegador: las pantallas no cambian |
| 2026-11-26 | 549 corridas de PHPUnit en verde (547 métodos de test), 9188 aserciones (antes de T5); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T4 (historial 2.98). Corrida de la CI sobre `main` (`245f274`), no local. T4 completo |
| 2026-11-26 | Tests puntuales en verde (249 corridas de PHPUnit, 3927 aserciones): `tests/Feature/Tenancy` (49 métodos, 51 corridas; los 4 nuevos de `TenantStorageTest`, 6 corridas por su data provider, comprobados contra su protección quitada) y las carpetas `Claims`, `Receptions`, `Deliveries`, `Documents`, `Settings`, `Seeders`, `Http`, `Auth`, `Access` y `Notifications`; Pint sin pendientes | ESLint (sin cambios en el frontend) | T4 (historial 2.98). Corrida local en la rama `feature/t4-storage`, sin la suite completa. Probado con `php artisan serve` sobre la base de desarrollo, solo lectura: `GET /api/branding` 200 en `127.0.0.1` y `404 TENANT_NOT_FOUND` en `localhost`; con el taller #1, la URL pública es `http://127.0.0.1:8000/storage/tenants/1/…`, las raíces `storage/app/public/tenants/1` y `storage/app/tenants/1/private`, y sin taller el disco falla. `route:list --path=storage` sigue mostrando solo las dos rutas `storage/{path}` del disco `local`. Sin revisión en navegador: las pantallas no cambian; el usuario dio la entrega por validada así. Los archivos ya subidos en desarrollo no se conservan y sus carpetas viejas se borraron (2.98) |
| 2026-11-26 | 543 tests en verde, 9173 aserciones (antes de T4); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T3, paso 5 (historial 2.97). Corrida de la CI sobre `main` (`8a45213`), no local. T3 completo |
| 2026-11-26 | Tests puntuales en verde (191, 1827 aserciones): `tests/Feature/Tenancy` (45; las protecciones nuevas de `ReminderCommandTest`, comprobadas quitándolas) y las carpetas `Notifications`, `Settings`, `Http`, `Auth`, `Access`, `Seeders`, `Budgets` y `Receptions`; Pint sin pendientes | ESLint (sin cambios en el frontend) | T3, paso 5 (historial 2.97). Corrida local en la rama `feature/t3-paso5-scheduler`, sin la suite completa; repetida con el mismo resultado después de las correcciones de `validar-docs` (solo documentación y un comentario de código). Contra MySQL: `schedule:list` muestra `notifications:send-reminders --due` cada minuto y `--tenant=999` responde el error esperado (código 1). No se despacharon recordatorios en `synctaller`, para no escribir avisos en la base de desarrollo. Sin revisión en navegador: las pantallas no cambian |
| 2026-11-26 | 538 tests en verde, 9135 aserciones (antes de T3, paso 5); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T3, paso 4 (historial 2.96). Corrida de la CI sobre `main` (`5541141`), no local |
| 2026-11-26 | Tests puntuales en verde (186, 1789 aserciones): `tests/Feature/Tenancy` (40; los 10 nuevos y el cambiado de `SwitchTenantTest`, comprobados contra su protección quitada) y las carpetas `Notifications`, `Settings`, `Http`, `Auth`, `Access`, `Seeders`, `Budgets` y `Receptions`; Pint sin pendientes | ESLint (sin cambios en el frontend) | T3, paso 4 (historial 2.96). Corrida local en la rama `feature/t3-paso4-colas`, sin la suite completa. Cola vieja de `synctaller` vaciada con el código de `main` (59 jobs, ninguno fallido) y migración central nueva aplicada en MySQL (`synctaller_central`). Probado con `php artisan serve`: `GET /api/branding` 200 en `127.0.0.1`, `404 TENANT_NOT_FOUND` en `localhost` y login inválido `401`; `queue:work` lee la cola de la central. Sin revisión en navegador: las pantallas no cambian |
| 2026-11-26 | 528 tests en verde, 9085 aserciones (antes de T3); Pint sin pendientes | Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas) | T2, paso 3 y decisión sobre P9 (historial 2.94 y 2.95). Corrida de la CI sobre `main` (`87fea4e`), no local. T2 completo |
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
| 1 | Tarea P8 (fotos y firmas a privado) | En curso (2.106): pasos 1 (firma) y 2 (fotos) hechos; sigue el 3 (comando para los archivos existentes). Requisito antes del primer taller comercial |
| 2 | T6 (migrar la instalación actual) | Bloqueada: requiere P8 y decidir el hosting (V9) |

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
| T3: colas, cache, permisos y scheduler | COMPLETADO: diseño aprobado el 2026-11-26; paso 4 (colas por taller, cola y cache en la central) el 2026-11-26 (2.96); paso 5 (scheduler por taller y fallo cerrado) el 2026-11-26 (2.97) |
| T4: storage | COMPLETADO el 2026-11-26 (2.98): diseño e implementación |
| Tarea P8: fotos y firmas a privado | EN DESARROLLO: diseño en 3 pasos aprobado el 2026-11-26 (2.106); paso 1 (firma privada) el 2026-11-26, en `main` con la CI en verde; paso 2 (fotos de recepción y de diagnóstico privadas) el 2026-11-26, en `main` con la CI en verde |
| T5: ciclo de vida | COMPLETADO: plan en 5 pasos aprobado el 2026-11-26 (2.101); paso 1 (`tenants:migrate`) el 2026-11-26, en `main` con la CI en verde; paso 2 (sin tablas de cola y cache en las bases de taller, deuda #20) el 2026-11-26 (2.102), en `main` con la CI en verde; paso 3 (alta completa, `tenants:create` y `tenants:discard`) el 2026-11-26 (2.103), en `main` con la CI en verde; paso 4 (suspensión, reactivación y baja, con la pantalla del frontend) el 2026-11-26 (2.104), en `main` con la CI en verde; paso 5 (restauración y runbooks) el 2026-11-26 (2.105), en `main` con la CI en verde |
| T6: migrar la instalación actual | PENDIENTE |

Los pasos 0 a 7 de la revisión del spike son internos de T2, T3 y T5, no fases nuevas (historial 2.80). El paso 0 está hecho y se repitió con 4.2.3 el 2026-11-25; los pasos 1 a 5 también (2.90, 2.93, 2.95, 2.96 y 2.97).

## Pendiente sin fase

| Tema | Estado |
|---|---|
| Notificaciones por WhatsApp | PENDIENTE, sin diseño |
| Validaciones externas (contador, legal, talleres) | §10 de la especificación |
| Deuda técnica | [`deuda-tecnica.md`](deuda-tecnica.md) |
| Despliegue | [Sin definir](../development/deployment.md) |
