# Historial de decisiones

Por qué el sistema es como es. Las reglas **vigentes** están en [`reglas-de-negocio.md`](../reglas-de-negocio.md); este
documento guarda el motivo y el contexto de cada decisión. Si contradice al código o a las reglas vigentes, mandan ellos.

El repositorio arrancó como monorepo el 2026-11-24, sin el historial de Git de `synctaller-backend` y
`synctaller-frontend`. Las secciones conservan la numeración del antiguo `handoff.md` (`2.N`), porque el código y los demás
documentos la citan ("historial 2.79"). Los hashes de commit y los nombres de rama de los textos originales se quitaron:
ya no existen en este repositorio.

| Parte | Contenido |
|---|---|
| [1. Fundamentos](#1-fundamentos-secciones-1-a-253) | Visión, convenciones y módulos base (FASES 1 a 25), condensados |
| [2. Decisiones por entrega](#2-decisiones-por-entrega-secciones-254-en-adelante) | Desde el 2026-10-28: aseguradoras, T1, zona horaria, spike T0. Texto completo |
| [3. Frontend](#3-frontend) | Decisiones de la SPA |

---

## 1. Fundamentos (secciones 1 a 2.53)

Condensado del handoff original. Lo que sigue vigente de estas secciones ya está en `backend/CLAUDE.md` (convenciones) y en
las [reglas de negocio](../reglas-de-negocio.md).

### Visión y arquitectura (1 a 1.5)

* SyncTaller (antes **Presupuestador**, renombrado el 2026-10-21) es el sistema de gestión de un taller mecánico de
  Paraguay: de la recepción del vehículo a la entrega, con inventario, facturación, compras y reportes.
* API REST en Laravel con Sanctum (tokens Bearer) y una SPA separada en React. La API es la única fuente de reglas: el
  cliente no duplica cálculos.
* Flujo central: recepción → diagnóstico → presupuesto → orden de trabajo → cierre económico → factura → pago → entrega.

### Convenciones de diseño (3 y 4)

| Tema | Decisión |
|---|---|
| Capas | Controller (recibe, autoriza, responde) → Form Request → Service (reglas) → Model → Resource → `ApiResponse` |
| Transacciones | Toda operación sobre varios registros en `DB::transaction`, con `lockForUpdate` y un orden de bloqueo fijo |
| Estados | Siempre Enums, comparados contra Enums |
| Resources | `XResource` para listados y `ShowXResource` para el detalle |
| Rutas | Anidadas con `scopeBindings()`; actualizaciones parciales con `PATCH` |
| Permisos | Middleware `permission:<recurso>.<acción>` por método, con `HasMiddleware` |
| Errores | Contrato único (`success`, `status`, `message`, `data`, `errors`, `error_code`); los de negocio son excepciones de dominio con `error_code`, nunca 500 |
| Idioma | Mensajes al usuario en español; código, campos y `error_code` en inglés |
| Refactors | No refactorizar código estable por estilo ni al migrar |
| Secretos | `.env`, claves y respaldos nunca van al repositorio |

### Módulos base (2.1 a 2.23)

| Sección | Decisión |
|---|---|
| 2.2 Autenticación | Sanctum con tokens Bearer que no expiran; `POST /logout` revoca el actual |
| 2.3 Roles y permisos | `spatie/laravel-permission`; `administrador` pasa todo por `Gate::before`; roles del taller en `RolesAndPermissionsSeeder` |
| 2.4–2.5 Clientes y vehículos | El vehículo es una entidad propia (su dueño puede cambiar); placa obligatoria salvo `no_plate`, entonces chasis |
| 2.6–2.7 Recepción y checklist | El checklist se genera con los ítems de las categorías de servicio elegidas. Desde el 2026-10-19 el estado de la recepción se **deriva** del flujo y no se asigna a mano |
| 2.8–2.10 Diagnóstico, mecánicos y categorías | El diagnóstico referencia `mechanics`, no `users` |
| 2.11–2.12 Presupuestos | `draft → sent → approved`, solo el borrador es editable; ítems `labor`, `service` y `part`; precios sin IVA |
| 2.13–2.18 Orden de trabajo | Nace de un presupuesto aprobado, una por presupuesto; `WorkOrderItem` es ejecución, no inventario ni precios; una OT completada no vuelve atrás |
| 2.20–2.22 Respuestas y errores | Contrato JSON único y códigos HTTP estandarizados |
| 2.23 FASE 9 | Auditoría de excepciones y controllers: ningún error de negocio sale como 500 |

### FASES 11 a 25 (2.24 a 2.38, 2026-10-19/20)

| Sección | Decisión |
|---|---|
| 2.24 Inventario | Un depósito; `part_stocks` solo cambia por `InventoryService::applyMovement()`; movimientos inmutables; costo promedio ponderado; sin stock negativo |
| 2.25 Repuestos en la OT | `pending → reserved → consumed → returned`; costo del movimiento de consumo, precio del presupuesto |
| 2.26 Servicios y mano de obra | Catálogo de servicios; asignación de mecánicos por porcentaje; horas opcionales (sin horas el costo es desconocido, no cero) |
| 2.27 Cierre económico | Se congela al completar la OT (inmutable), sin IVA; margen con `is_cost_complete` |
| 2.28–2.29 Facturación y pagos | Guaraníes enteros con IVA incluido; numeración del timbrado con bloqueo; factura emitida inmutable; pagos parciales y recibo solo a crédito |
| 2.30 Entrega | Exige OT completadas y facturadas (pagadas si son al contado); comprobante `ENT-` |
| 2.31–2.32 Historial y mantenimiento | Historial sin tablas propias; planes por km y/o días con estado calculado |
| 2.33 Reportes | Solo lectura, un permiso por reporte; dashboard por bloques según permisos |
| 2.34–2.35 Compras y pagos a proveedores | Costos con IVA incluido, al inventario sin IVA; pagos parciales; cuentas por pagar |
| 2.36 Notificaciones | Emails al cliente por cola con `afterCommit`, deduplicados; avisos internos por rol |
| 2.37 Exportación | PDF con dompdf y marca de agua según estado; reportes en CSV (para Excel en español) y PDF |
| 2.38 Auditoría | Trait `Auditable`; `audit_logs` inmutable y sin depuración |

### Ajustes pedidos por el frontend (2.39 a 2.53, 2026-10-21/24)

| Sección | Decisión |
|---|---|
| 2.39 Datos maestros | Bajas del catálogo de vehículos en un Service con sus `error_code`; `GET /me` para hidratar la sesión de la SPA |
| 2.40 Repuestos | Los opcionales del catálogo se pueden vaciar con `null` al editar |
| 2.41–2.45 Contratos | Recepciones, diagnósticos, presupuestos, OT, cobros y entregas: campos que la SPA necesitaba, todos aditivos |
| 2.46 Recepción cerrada | Una recepción entregada o cancelada no admite cambios (`RECEPTION_CLOSED`); enviar un presupuesto exige los diagnósticos cerrados |
| 2.47 Mecánicos eliminados | Se cargan con `withTrashed()` en OT, historial y diagnóstico |
| 2.48 Datos maestros en uso | No se eliminan (los documentos viejos deben mostrarlos): se desactivan; todos `409` |
| 2.49 Repuestos extra | Nacen `awaiting_approval` y no se reservan sin la respuesta del cliente (resolvió la deuda #11) |
| 2.50 Preferencias y logo | Preferencias visuales por usuario en la base; logo con versión negra para PDF y emails |
| 2.51 Laravel 13 | Actualización de framework y dependencias |
| 2.52 Datos de prueba | Seeders por escenario (`ExampleScenarios`), idempotentes y siempre por los Services |
| 2.53 Catálogo de diagnóstico | El ítem del diagnóstico se elige siempre del catálogo (`DIA-`) y guarda su copia |

### Roadmap y cierre originales (5, 6, 8 a 12)

El roadmap original (FASES 10 a 25) se completó el 2026-10-20. Sus pendientes vigentes están en
[`roadmap/README.md`](../roadmap/README.md) y la "regla de oro" en [`project/principios.md`](../project/principios.md).

---

## 2. Decisiones por entrega (secciones 2.54 en adelante)

Texto completo. Las entregas `E`, `M`, `P` y `T` y las validaciones `G` y `V` son las de la
[especificación de aseguradoras](../especificacion-aseguradoras.html).

### 2.54 Recordatorios de factura al destinatario correcto (2026-10-28)

Entrega E2 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §9). Los recordatorios de
vencimiento iban siempre a `invoice.client`, el dueño de la recepción, aunque en el borrador se hubiera cambiado el RUC o
CI para facturar a otro (por ejemplo, a una aseguradora): el dueño recibía el aviso de una deuda que no era suya.

| Tema | Decisión |
|---|---|
| Regla | Si el `customer_tax_id` de la factura no coincide con el `document_number` del cliente, el recordatorio (`invoice_due_soon` / `invoice_overdue`) se registra `skipped` con el motivo "La factura está a nombre de otro destinatario." Es el mecanismo existente: no se envía email ni se crea otro canal, y la clave de deduplicación evita repetirlo |
| Comparación | Mayúsculas y sin espacios, sin el dígito verificador final (`-N`): CI `1234567` y RUC `1234567-8` cuentan como la misma persona (decisión del usuario del 2026-10-28). Sin documento del cliente no hay con qué comparar y el aviso sale como antes |
| Reenvío | `POST client-notifications/{id}/resend` sigue permitido sobre el aviso omitido y va al cliente (decisión del usuario del 2026-10-28): es una acción manual registrada. Sin `error_code` nuevo |
| Código | `NotificationReminderService::invoiceReminders` (`isAddressedToClient`) y un parámetro opcional `$skipReason` en `ClientNotificationService::notify` |
| Sin cambiar | Contrato de la API, permisos, migraciones, los demás avisos y los recordatorios de facturas a nombre del dueño |

### 2.55 Códigos internos con bloqueo (2026-10-28)

Entrega E3 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §9; política P4, §6.4).
Seis altas calculaban el código como `withTrashed()->max('id') + 1` sin bloqueo (`BUD-`, `OT-`, `COM-`, `REP-`, `SRV-`,
`DIA-`): dos altas simultáneas de la misma serie obtenían el mismo código y la segunda fallaba con 500 por el índice
único de `code`.

| Tema | Decisión |
|---|---|
| Contador | Tabla `document_sequences` (migración `000056`: `key` único, `next_value`). `DocumentSequenceService::create(DocumentSeries, closure)` bloquea la fila de la serie con `lockForUpdate` dentro de la transacción del alta, arma el código (prefijo + 6 dígitos, formato sin cambios) y lo pasa al closure que crea el documento. Si el alta falla, el rollback revierte el incremento. Solo se serializan las altas de la misma serie |
| Valor inicial | En el **primer uso** (decisión del usuario del 2026-10-28, en lugar de cargarlo en la migración): el servicio inserta la fila con `insertOrIgnore` y el máximo id + 1 de la tabla, incluidos los eliminados, que es el mismo código que daba el cálculo anterior. La migración es solo estructura. Las series futuras (`ASE-`, `SIN-`) solo se agregan al enum `DocumentSeries` |
| Códigos usados | Con la fila bloqueada, si el código ya existe (también eliminado) se salta al siguiente. Cubre códigos cargados sin pasar por la serie (por ejemplo, la migración `000055` con `DIA-`) |
| Duplicado | Si igual se viola la unicidad del código, la API responde `409 DOCUMENT_CODE_CONFLICT` (`AppException`), nunca 500 (decisión del usuario del 2026-10-28: solo para los códigos, sin mapeo global). Otras violaciones de unicidad no cambian |
| Orden de bloqueo | Documento → serie: la serie siempre al final, y cada serie la usa un solo servicio (sin ciclos). La OT retiene la fila de su serie mientras reserva repuestos en la misma transacción |
| Huecos | Se aceptan (P4): no son documentos fiscales. Facturas, `REC-`, `ENT-` y `PAG-` no cambian (ya usaban `lockForUpdate`) |
| Pruebas | `tests/Feature/Documents/DocumentSequenceTest` (formato, continuidad, salto, rollback, series independientes, 409). La concurrencia real solo se reproduce en MySQL: `tests/Manual/document_sequence_concurrency.php` (N procesos piden códigos a la vez; verifica que no haya repetidos ni errores y restaura el contador) |
| Sin cambiar | Rutas, Resources, permisos y formato de los códigos |

### 2.56 Importes del presupuesto en decimal(14,2) (2026-10-28)

Entrega E4 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §9). `budgets.subtotal|tax|total`
y `budget_items.unit_price|total` eran `decimal(10,2)`: tope de 99.999.999,99 Gs. Como los Requests no limitaban precio
ni cantidad y MySQL corre en modo estricto, un presupuesto mayor (por ejemplo, una chapería de 120 millones) fallaba
con 500.

| Tema | Decisión |
|---|---|
| Columnas | Migración `000057`: las cinco columnas pasan a `decimal(14,2)` con `->change()`, conservando default y nulabilidad. Es la misma precisión que las facturas. `quantity` queda en `(10,2)`. El `down()` se detiene con un mensaje si algún importe no entra en `(10,2)`, en lugar de truncar |
| Topes | Decisión del usuario del 2026-10-28: `unit_price` ≤ 999.999.999.999,99 y `quantity` ≤ 99.999.999,99 en los Requests (422 `VALIDATION_ERROR`). Si el total del ítem o el del presupuesto con IVA no entra en `(14,2)`: `BUDGET_AMOUNT_OUT_OF_RANGE` (422, `BudgetService::ensureAmountInRange`). Ningún importe llega a la base fuera de rango |
| Transacción | `BudgetItemController::store\|update` creaba o editaba el ítem y recalculaba sin transacción: si el recálculo fallaba, el ítem quedaba guardado. Ahora las dos operaciones van en `DB::transaction`. La lógica de ítems sigue en el controller (no se movió al Service: fuera de alcance) |
| Alcance | Solo presupuestos (decisión del usuario del 2026-10-28). **Observación sin cambios:** `parts.cost_price\|sale_price`, `part_supplier.last_cost` y `mechanics.hour_cost` siguen en `(10,2)`, con un máximo de 99,9 millones por unidad; `work_order_closings` está en `(12,2)` y alcanza |
| Pruebas | `tests/Feature/Budgets/BudgetAmountRangeTest`: presupuesto de 150.000.000, topes, total del ítem y del presupuesto fuera de rango (sin guardar nada) y edición fuera de rango. SQLite no aplica la precisión de los decimales: la ampliación se verificó en la base MySQL de desarrollo el 2026-10-28. Las cinco columnas quedaron en `decimal(14,2)` con sus defaults, y un presupuesto de 150.000.000 se guardó con total 165.000.000,00 (dentro de una transacción revertida). El `down()` se detuvo con importes grandes, y sin ellos volvió a `(10,2)` y se reaplicó |

### 2.57 Registro de la respuesta del cliente al presupuesto (2026-10-28)

Entrega E5 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.4 y §9). `approve` y
`reject` solo cambiaban el estado: no quedaba quién lo registró, por qué medio respondió el cliente ni por qué importe.
`reopen` borraba `approved_at`, así que se perdía el historial, y `Budget` no estaba auditado. Es la base del control
de aceptación del dueño en siniestros (decisión B, M7).

| Tema | Decisión |
|---|---|
| Tabla | `budget_client_acceptances` (migración `000058`): `budget_id`, `kind` (`ClientAcceptanceKind`: `approval\|rejection`), `decided_by` (usuario que lo registra), `decided_at`, `channel` (`ClientDecisionChannel`, nulo), `contact` (nulo), `notes` (nulo) y `owner_amount` (`decimal(14,2)`). Claves foráneas `restrictOnDelete`. Las columnas de aseguradora (`insurer_authorization_id`, `deductible_amount`) y el tipo `re_acceptance` llegan con M7 |
| Importe | `owner_amount` = subtotal sin IVA al responder (decisión del usuario del 2026-10-28: desde E5). En M7, con aseguradora, pasa a ser la parte del dueño |
| Body | Opcional (decisión del usuario del 2026-10-28): `channel`, `contact` (≤ 150) y `notes` (3 a 500), con las reglas de la respuesta a un extra (`DecideBudgetRequest`). Sin body el contrato es el de siempre y la fila se crea igual; el motivo del rechazo también es opcional |
| Inmutable | `BudgetClientAcceptance` no se edita ni se elimina (`BUDGET_CLIENT_ACCEPTANCE_IMMUTABLE`, 409). Reabrir no toca las filas: una respuesta nueva es otra fila |
| Histórico | Sin filas para los presupuestos anteriores (decisión del usuario del 2026-10-28): no hay de dónde sacar quién lo registró ni por qué medio. Conservan `approved_at` |
| Contrato | El detalle del presupuesto (`show` y las respuestas de las transiciones) agrega `client_acceptances`, en orden, con `kind`, `decided_by` (`id`, `name`), `channel` y `owner_amount` (aditivo) |
| Auditoría | `Budget` y `BudgetClientAcceptance` usan `Auditable`. Como cada ítem recalcula los totales del presupuesto, esos cambios también quedan registrados (son precios) |
| Seeders | `BudgetItemSeeder` aprueba (por WhatsApp) y rechaza (por teléfono, con motivo) con el recepcionista del escenario. `DatabaseSeederTest` verifica una aprobación por presupuesto aprobado y los rechazos, incluido el del reabierto |
| Pruebas | `tests/Feature/Budgets/BudgetClientAcceptanceTest`: sin body, con datos, canal y notas inválidos, historial al reabrir (importe de cada respuesta), inmutabilidad y auditoría. Los tests de presupuestos existentes no cambiaron |

### 2.58 Spike T0 de tenancy (2026-10-28/29, descartado)

Prueba técnica de `spatie/laravel-multitenancy` **4.2.1** (P2) en una rama descartable. No se mergeó código ni se
aprobó la dependencia. El informe completo, con los criterios S1–S13, las mediciones y lo que se armó para retomarlo
en T2, está en [`spike-t0/informe.md`](../spike-t0/informe.md).

| Tema | Resultado |
|---|---|
| Críticos (S1, S2, S4, S5, S7) | Cumplen. Los 393 tests en verde con el paquete y un taller activo; aislamiento de base, tokens, `exists`, bindings, SQL crudo, permisos y cache; un worker alternando talleres sin cruces |
| No cumplen | S8 (un job con la base del taller caída consume su único intento) y S10 (la falla de un taller corta las migraciones de los demás). Los dos se resuelven con código propio ya previsto (§3.5, §3.8) |
| Rendimiento (S13) | Con OPcache, unos 30–45 ms más por request, por encima de los umbrales de G. Hay mitigaciones para probar en T2 |
| Hallazgos de diseño | El paquete identifica el taller al arrancar, no en un middleware (se usó un middleware propio con errores JSON). La cache y `failed_jobs` tienen que ir en la base central. Spatie Permission necesita una tarea propia para olvidar los permisos al cambiar de taller |
| Entorno local | OPcache quedó activado para `php-cgi` (copia del `php.ini` original junto al archivo). Las bases del spike se eliminaron |

### 2.59 Cliente persona o empresa (2026-10-29)

Entrega M1 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.1 y §9; decisión P3). El
cliente era siempre una persona: nombre y apellido obligatorios y solo letras, así que una aseguradora o una flota no
se podían cargar. El nombre se armaba a mano en 15 lugares: los 7 que preveía la especificación y 8 Resources más.

| Tema | Decisión |
|---|---|
| Esquema | Migración `000059`: `clients.kind` (`person` por defecto, así los existentes quedan como personas), `business_name` (150) y `address` (255) nulos, e índice en `business_name`. `first_name`/`last_name` pasan a nulos |
| Validación | `StoreClientRequest`: sin `kind`, persona como siempre. Empresa: `business_name` obligatorio y nombre y apellido `prohibited`; persona, al revés. `UpdateClientRequest`: el tipo efectivo es el enviado o el actual. Al cambiar de tipo, los campos del nuevo son obligatorios y `ClientService` vacía los del anterior |
| Cambio de tipo | Bloqueado con recepciones o facturas: `CLIENT_KIND_NOT_EDITABLE` (422, `ClientException`). Ya muestran el nombre del cliente |
| Nombre | `Client::display_name` reemplazó las 15 concatenaciones (factura, PDF, reportes, mantenimiento, historial y los Resources de recepción, vehículo, entrega, diagnóstico y avisos), sin cambiar las claves. Para personas el texto es idéntico: los tests existentes de esas áreas pasaron sin tocarlos |
| Saludo | `greeting_name`: el nombre de pila de una persona, como antes ("Hola, Ana"; decisión del usuario del 2026-10-29), y la razón social de una empresa |
| Búsquedas | `search` en clientes, vehículos, presupuestos, OT, recepciones y diagnósticos (solo el bloque del cliente; el del mecánico no cambia) y los filtros `first_name` (clientes) y `client_first_name` (vehículos) también buscan por razón social (decisión del usuario del 2026-10-29) |
| Baja (D9) | Pasó de `ClientController` a `ClientService::delete`. Con vehículos vigentes, recepciones (incluidas las eliminadas) o facturas responde `409 CLIENT_IN_USE` (decisión del usuario del 2026-10-29). **Cambio de comportamiento:** antes esa baja funcionaba |
| Seeder | `ClientsSeeder` agrega una empresa (`ClientsSeeder::COMPANY`, "Transportes del Este S.A.") sin vehículos ni recepciones. `DatabaseSeederTest` verifica los dos tipos |
| Pruebas | `tests/Feature/Clients/ClientKindTest` (7): alta de persona como antes, alta de empresa, validaciones por tipo, cambio de tipo con y sin recepciones, baja en uso, empresa mostrada y encontrada por razón social, y saludo |
| Fuera de alcance | El dígito verificador del RUC (V10), la baja del cliente de una aseguradora (M2) y el email único en la base (D7, decisión del usuario del 2026-10-29: queda para después) |
| Frontend | **No crear empresas en producción hasta adaptar `frontend/`**: `first_name`/`last_name` como `string \| null`, `clientName()` con `display_name`, y el selector persona/empresa en el formulario. Para personas el contrato es el de antes, con campos agregados |

### 2.60 Aseguradoras (2026-10-29)

Entrega M2 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.2 y §9; decisión D). Reglas
vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.22.

| Tema | Decisión |
|---|---|
| Modelo | Migración `000060`: `insurers` (`client_id`, `code` `ASE-`, `status`, `payment_term_days`, `notes`, `created_by`, baja lógica) e `insurer_contacts` (`name`, `role`, `phone`, `email`, `is_primary`, `notes`). Los datos fiscales y de contacto de la aseguradora son los de su cliente empresa: no se duplican |
| Unicidad | Una aseguradora vigente por cliente, controlada en `InsurerService` y no con un índice único, para que una aseguradora dada de baja no retenga a su cliente (se puede volver a vincular) |
| Avisos (D) | Decisión del usuario del 2026-10-29: solo el cliente creado junto con la aseguradora nace con `notifications_enabled = false`. Un cliente existente conserva su configuración y la respuesta lo advierte en `meta.warnings`. Motivo: `notifications_enabled` es un interruptor único para los 6 avisos al cliente; desactivarlo a una empresa con vehículos propios le cortaría también esos avisos. Verificado en el código (`ClientNotificationService`): no afecta a los avisos internos |
| Alta | `client_id` o `client`, uno de los dos (`required_without` y `prohibits`). Los datos de `client` se validan con las reglas de `StoreClientRequest` con `kind = company`, sin duplicarlas; sus errores vuelven como `client.<campo>` |
| Código | `ASE-` es una serie nueva de `DocumentSequenceService` (E3): un caso más en `DocumentSeries`, sin migración propia |
| Fuera de alcance | `deductible_billing_mode` (decisión del usuario del 2026-10-29: se agrega en M10, en `insurers` y `claims`) y el bloqueo de la baja con siniestros (`INSURER_IN_USE`, M3) |
| Permisos | Los de la especificación (decisión del usuario del 2026-10-29): `insurers.index\|show` para recepcionista, jefe de taller y cajero; `insurers.store\|update\|destroy\|change-status` para recepcionista. Sin permisos propios para contactos |
| Cliente | `ClientService` bloquea la baja del cliente vinculado (`CLIENT_LINKED_TO_INSURER`, 409) y su cambio a persona (`CLIENT_KIND_NOT_EDITABLE`) |
| Seeder | `InsurerSeeder` (después de `ClientsSeeder`), por `InsurerService` con el recepcionista: "Seguros del Sur S.A." activa, con dos contactos, y "Aseguradora Guaraní S.A." inactiva. `DatabaseSeederTest` verifica los dos estados, el cliente empresa sin avisos y el contacto principal |
| Pruebas | `tests/Feature/Insurers/InsurerTest` (7): alta con cliente nuevo y existente (advertencia), validaciones, estados, contactos con un solo principal y binding anidado, protección del cliente y nuevo vínculo tras la baja, y permisos por rol |

### 2.61 Siniestros (2026-10-29)

Entrega M3 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.3, §5.2 y §9). Reglas
vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.23.

| Tema | Decisión |
|---|---|
| Modelo | Migración `000061`: `claims` con los campos de §4.3 (número y póliza, tipo, fecha y descripción, asegurado, perito, deducible, notas de cobertura, estado y datos de cierre y cancelación). `SIN-` es una serie más de `DocumentSequenceService` |
| Unicidad | Un siniestro vigente (no cancelado) por recepción, controlado en `ClaimService` (decisión del usuario del 2026-10-29): el cancelado queda en el historial y se puede abrir otro, por ejemplo con otra aseguradora. `Reception::currentClaim()` es el vigente |
| Asegurado | En uno propio, sin datos del asegurado se copian los del cliente de la recepción, con `insured_client_id` (decisión del usuario del 2026-10-29). En uno de tercero el nombre es obligatorio |
| Sincronización | `ClaimStatusService`, sin dependencias para evitar un ciclo (`ClaimService` usa `ReceptionStatusService`): `VehicleDeliveryService::deliver` lo cierra, `cancel` lo reabre y `ReceptionStatusService::cancel` lo cancela con el motivo de la recepción. Todo en la transacción del evento. Orden de bloqueo: recepción → siniestro |
| Edición | Solo `open` y con la recepción editable. Recepción, aseguradora y tipo son `prohibited`. El deducible se edita libremente hasta M9 (`CLAIM_DEDUCTIBLE_LOCKED` con facturas). La cancelación manual reutiliza `CancelReceptionRequest` (motivo obligatorio) |
| Aseguradora | La baja con siniestros, incluso cancelados, responde `INSURER_IN_USE` (409): completa lo que M2 dejó pendiente |
| Visibilidad | El detalle de la recepción agrega `claim` solo si el usuario tiene `claims.show` (decisión del usuario del 2026-10-29): el mecánico tiene `receptions.show`, pero no debe ver pólizas ni datos del asegurado (§8 de la especificación) |
| Permisos | `claims.index\|show` para recepcionista, jefe de taller y cajero; `claims.store\|update\|cancel` para recepcionista y jefe de taller. Mecánico y depósito sin acceso |
| Seeder | `ClaimSeeder` (después de `ReceptionSeeder`), con la aseguradora activa de `InsurerSeeder` y el recepcionista del escenario. Los tres nacen abiertos; el estado final sale de la sincronización: C07 abierto, H07 cerrado (se entrega) y X01 cancelado (se cancela la recepción) |
| Pruebas | `tests/Feature/Claims/ClaimTest` (8): asegurado copiado y visibilidad en la recepción, tercero, unicidades y nuevo siniestro tras cancelar, aseguradora inactiva y recepción cerrada, edición y cancelación, sincronización con entrega, anulación y cancelación de la recepción, aseguradora en uso y permisos |

### 2.62 Documentos privados de siniestros (2026-10-29)

Entrega M4 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.3, §8 y §9; decisión E).
Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.24.

| Tema | Decisión |
|---|---|
| Disco | `claim_documents` en `config/filesystems.php`: privado, `serve => false`, `throw => true`. `php artisan route:list --path=storage` sigue mostrando solo las 2 rutas del disco `local` (deuda #14): el disco nuevo no agrega ninguna |
| Tabla | Migración `000062`: `claim_documents` (`type`, `original_name`, `path` único, `mime`, `size`, `sha256`, `notes`, `uploaded_by`, `deleted_by`, `deletion_reason`, baja lógica). `insurer_authorization_id` se agrega en M6 (decisión del usuario del 2026-10-29) |
| Validación | `UploadClaimDocumentRequest`: `extensions` (nombre), `mimetypes` (contenido, por `finfo`) y una regla `after` que exige que la extensión coincida con el contenido (un PNG llamado `.pdf` se rechaza). 10 MB. Hallazgo: `UploadedFile::fake()` informa el tipo según la extensión del nombre, así que con fakes la validación por contenido pasa siempre; los tests usan archivos reales |
| Estado | Se suben documentos con el siniestro abierto o cerrado (decisión del usuario del 2026-10-29: el informe final del perito puede llegar después de la entrega); cancelado → `CLAIM_NOT_EDITABLE` |
| Descarga | `FileResponse::storedFileResponse` (nuevo): adjunto, tipo guardado y `nosniff`; el `fileResponse` de los PDF y CSV no cambió. Nombre saneado (sin comillas, barras ni caracteres de control). Evento de auditoría nuevo `AuditEvent::DOWNLOADED` (`downloaded`, "Descargado") con el siniestro, el tipo y el nombre. Archivo faltante en el disco → `CLAIM_DOCUMENT_FILE_MISSING` (404), sin registrar la descarga |
| Baja | Solo administrador (`claim-documents.destroy`), con motivo (`DestroyClaimDocumentRequest`). Lógica: el archivo queda en el disco hasta definir la retención (G10) |
| Auditoría | `ClaimDocument` es `Auditable` con `$auditExclude = ['path']`: la ruta interna no queda en `audit_logs` |
| Seeder | `ClaimDocumentSeeder` (después de `ClaimSeeder`): denuncia en PDF y foto PNG del siniestro abierto (C07), por el Service con el recepcionista. `DatabaseSeederTest` usa `Storage::fake('claim_documents')` y verifica los archivos |
| Pruebas | `tests/Feature/Claims/ClaimDocumentTest` (6): almacenamiento privado con UUID y sha256 (sin `path` en la API ni en la auditoría), rechazo de SVG, HTML, extensión que no coincide y más de 10 MB, descarga auditada como adjunto, baja (403 para el recepcionista, 404 después y archivo conservado), aislamiento por siniestro y estados, y permisos. Los helpers de los tests de siniestros pasaron a `ClaimTestCase` |

### 2.63 Responsable por línea del presupuesto (2026-10-29)

Entrega M5 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.4, §5.2 y §9). Reglas
vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.4.

| Tema | Decisión |
|---|---|
| Columna | Migración `000063`: `budget_items.responsibility` (`string(20)`, `owner` por defecto). Las líneas existentes quedaron a cargo del dueño sin migrar datos (en la base local, las 73) |
| Por defecto | `owner` aunque la recepción tenga siniestro (decisión del usuario del 2026-10-29): lo que paga la aseguradora se marca a propósito, y el frontend actual, que no manda el campo, no cambia nada |
| Regla | `insurer` exige un siniestro abierto en la recepción del presupuesto (`BUDGET_ITEM_INSURER_WITHOUT_CLAIM`, 422). Lo valida `BudgetService::ensureResponsibilityAllowed`, desde `BudgetItemController` (alta y edición) y desde el seeder. Solo en borrador, como el resto del ítem |
| Siniestro cancelado | `ClaimService::cancel` devuelve a `owner` las líneas `insurer` de los presupuestos **en borrador** de la recepción, uno por uno (queda en la auditoría de `BudgetItem`). Los enviados y aprobados no se tocan (decisión del usuario del 2026-10-29). La cancelación de la recepción ya cancela sus borradores |
| API | `BudgetItemResource` agrega `responsibility` (`value`/`label`). Aditivo |
| Seeder | `BudgetItemSeeder` carga a la aseguradora la línea del presupuesto del siniestro abierto (C07). `DatabaseSeederTest` verifica los dos valores |
| Pruebas | `tests/Feature/Budgets/BudgetItemResponsibilityTest` (4): dueño por defecto aun con siniestro, aseguradora solo con siniestro abierto (y valor inválido), solo en borrador, y vuelta al dueño al cancelar el siniestro (el enviado no cambia) |

### 2.64 Autorizaciones de la aseguradora (2026-10-29)

Entrega M6 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.5, P7 y decisión C).
Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.25.

| Tema | Decisión |
|---|---|
| Tablas | Migración `000064`: `insurer_authorizations` (única por `budget_id` + `version`), `insurer_authorization_items` y `claim_documents.insurer_authorization_id` (nulable, `restrictOnDelete`) |
| Unidad (P7) | Se guarda sin IVA, como el presupuesto. `amounts_include_tax` es obligatorio en cada registro (decisión del usuario del 2026-10-29): mientras V2 no tenga respuesta, quien carga dice en qué unidad vino el importe. Con IVA se convierte con la tasa del presupuesto (`invoice.default_tax_rate`) |
| Cobertura | Una línea por cada ítem `insurer`, ni más ni menos. Tope por importe y por cantidad (`INSURER_AUTHORIZATION_EXCEEDS_BUDGET`): lo que exceda va en un complementario (V1 sigue abierta) |
| Versiones | La nueva sustituye a la vigente. Si sube el importe de alguna línea, exige un documento del siniestro (decisión del usuario del 2026-10-29: evidencia obligatoria al subir); el documento queda vinculado y no se da de baja (`CLAIM_DOCUMENT_IN_USE`, 409) |
| Copia del ítem | Durante la implementación: `BudgetItemController::destroy` borra el ítem físicamente, y con `restrictOnDelete` borrar un ítem de un presupuesto reabierto habría dado 500. La línea guarda descripción, cantidad y total presupuestados, y la clave foránea pasa a `null` |
| Invalidación | `BudgetService::reopen` y `cancel` (ahora en una transacción) y `ReceptionStatusService::cancel` (por cada presupuesto que cancela). Cancelar el siniestro no la toca, igual que M5 con los presupuestos enviados |
| Sin dependencias | `InsurerAuthorizationService` no depende de otros Services: lo usan `BudgetService` y `ReceptionStatusService` |
| Detalle del presupuesto | `insurer_authorization` (vigente, resumida) solo con `insurer-authorizations.show`, como `claim` en la recepción. Aditivo |
| Seeder | C05 suma un siniestro abierto y sus líneas a la aseguradora: versión 1 parcial y versión 2 (sube la instalación, con el documento `authorization` como evidencia). C07: autorización total, invalidada al reabrir. `DatabaseSeederTest` verifica `current`, `superseded` e `invalidated` |
| Pruebas | `tests/Feature/Claims/InsurerAuthorizationTest` (7): registro por línea con conversión de IVA, coincidencia y topes, precondiciones, versiones y evidencia, invalidación (y borrado del ítem después de reabrir), inmutabilidad y permisos |

### 2.65 Bloqueo de la OT y excepciones internas (2026-10-29)

Entrega M7 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.4, §5.4, §5.5 y §9;
decisiones B, C y P5). Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.5 y 6.26.

| Tema | Decisión |
|---|---|
| Esquema | Migración `000065`: `budget_client_acceptances.insurer_authorization_id` (nulo, `restrictOnDelete`) y `deductible_amount` (`decimal(14,2)`, nulo, tal como se informa). Migración `000066`: `internal_exceptions` (`type`, `subject` morph, `amount`, `reason`, `granted_by`, `granted_at`, `consumed_at`, `consumed_by` morph). Alias `work_order` en el morph map (WorkOrder no se usaba en ninguna relación polimórfica) |
| Autorización exigida | Decisión del usuario del 2026-10-29 (corrige §5.5, que pedía cada ítem `authorized\|partial`): basta una autorización vigente del siniestro abierto. M6 ya obliga a decidir todas las líneas; lo no cubierto pasa al dueño y lo controla la aceptación |
| Sin autorización | Decisión del usuario del 2026-10-29: mientras no hay autorización, las líneas `insurer` no se suman a la parte del dueño. Si se sumaran, la aceptación quedaría siempre desactualizada y `start_without_authorization` no serviría |
| Siniestro cancelado | Las líneas `insurer` de un presupuesto enviado o aprobado no cambian (M5), pero sin siniestro abierto son del dueño: la OT no exige autorización y sí la re-aceptación por el total. Así una cancelación no deja pasar una OT con la aceptación vieja |
| Excepción al crear la OT | Decisión del usuario del 2026-10-29: `internal_exception_id` opcional en `POST work-orders`; se valida (tipo, presupuesto, sin usar) y se consume en la transacción del alta. Pasarla cuando no hace falta también es un error (`INTERNAL_EXCEPTION_NOT_APPLICABLE`): una excepción habilita una operación concreta |
| Absorción | Decisión del usuario del 2026-10-29: M7 solo la otorga (sobre la OT, con importe); el reparto la consume en M8 (R6) |
| Una sin usar | Una excepción sin usar por sujeto y tipo (`INTERNAL_EXCEPTION_ALREADY_GRANTED`, 409). Decisión de implementación para que M8 no tenga que elegir entre varias; se puede revisar en M8 |
| Body de la excepción | `type` + `subject_id`: el tipo define si el sujeto es un presupuesto o una OT (la tabla guarda el morph) |
| Re-aceptación | Decisión del usuario del 2026-10-29: con el presupuesto aprobado y la OT sin cerrar, sin exigir que el importe haya subido. `channel` obligatorio (decisión de implementación: es la evidencia de B; en `approve` sigue opcional por compatibilidad). Con la OT, el importe sale de la ejecución actual sin los extras, porque el tope suma los extras aprobados aparte (§5.4) |
| Ejecución actual | `OwnerShareService::workOrderOwnerAmount`: como el cierre (mano de obra y servicio por lo presupuestado, repuesto de catálogo por lo usado), pero las líneas sin resolver por lo previsto (cantidad × precio): una OT recién creada no muestra una parte menor que la aceptada. El refactor compartido con la factura (`WorkOrderSaleBreakdown`) sigue en M8 |
| Advertencia de B | Nombre `owner_amount_warning` (decisión del usuario del 2026-10-29; tarea interna de §10.3), solo con `claims.show`. Incluye el deducible que subió desde la aceptación, que en M8 bloquea el reparto (`CLAIM_DEDUCTIBLE_NOT_ACCEPTED`) |
| Advertencia de P5 | Decisión del usuario del 2026-10-29: `has_claim` en el detalle de la OT (sin permiso: el depósito agrega extras y no tiene `claims.show`) y el texto de la especificación en `meta.warnings` del alta de un extra |
| Permisos | Los de §8 (confirmados el 2026-10-29): `internal-exceptions.grant` para el jefe de taller; `index\|show` para jefe de taller y cajero. La re-aceptación usa `budgets.approve` y el historial `budgets.show` |
| Bloqueo | Presupuesto → autorización → excepción en el alta de la OT (el presupuesto solo se bloquea si tiene líneas `insurer`: el particular no cambia); OT → excepción en la absorción |
| Particular | Sin líneas `insurer` no cambia nada: mismo `owner_amount` (subtotal), mismas validaciones y errores. Claves nuevas en el detalle de la OT (`has_claim`, `owner_amount_warning` en `null`) y en las aceptaciones; contrato aditivo |
| Seeder | C05 pasa de enviado a aprobado: el jefe de taller otorga la excepción antes de la versión 1 (queda sin usar), el dueño aprueba con la versión 1 (paga la mitad de la instalación) y re-acepta con la 2 (no paga nada). `DatabaseSeederTest` ajusta los conteos de presupuestos (25 aprobados, 2 enviados) y verifica la aprobación, la re-aceptación y la excepción. La absorción no se siembra: llega con su uso en M8 |
| Pruebas | `tests/Feature/Claims/WorkOrderInsurerFlowTest` (8): autorización exigida, Ejemplo 3 (la excepción no reemplaza la aceptación) y re-aceptación, excepción otorgada y consumida (inmutable y auditada), siniestro cancelado, advertencias de B (Ejemplo 2 y deducible) y P5, absorción, particular sin cambios y permisos. Los tests existentes de OT, presupuestos y repuestos no cambiaron |
| Fuera de alcance | Reparto, R5 y consumo de la absorción (M8); el bloqueo de entrega y facturación por deducible (M9). Una excepción `start_without_authorization` sin usar sigue valiendo si el presupuesto se reabre y se vuelve a aprobar: si molesta, se decide en M8 |

### 2.66 Decisión P10: notas de crédito emitidas fuera del sistema (2026-10-29)

Decisión de diseño, **sin código todavía**. La primera respuesta a G5 (2026-10-28) indica que una factura emitida y
cuestionada se corrige con una nota de crédito, no anulándola, y que mientras SyncTaller no las emita se acepta otro
procedimiento autorizado. El usuario cerró P10 antes de diseñar M8, sin esperar V18–V19. Texto aprobado en
[especificación](../especificacion-aseguradoras.html) (§5.7 y §10.2).

| Tema | Decisión del usuario (2026-10-29) |
|---|---|
| Mecanismo | Se registra en SyncTaller la nota de crédito emitida fuera del sistema (número, fecha, motivo y líneas). Emitirlas desde SyncTaller queda para cuando SIFEN sea obligatorio o se decida |
| Alcance | Cualquier factura emitida, también la del flujo particular: la regla es fiscal. Aditivo: las validaciones y errores actuales no cambian |
| Importe | Por línea y parcial: cada línea de la nota referencia una línea de la factura y no supera lo que esa línea tiene sin acreditar. Libera exactamente la porción del reparto (R8 es por porción) |
| Con pagos | El total de la nota no supera el saldo pendiente. Para acreditar lo cobrado se anulan antes los pagos (procedimiento vigente). Sin saldos a favor ni devoluciones en el MVP |
| Efectos | El saldo y el estado de cobro descuentan lo acreditado. El facturado de una porción es el neto (base facturada menos acreditada): P6 permite sustituir una versión del reparto cuando su facturado neto es 0, y `ALLOCATION_HAS_INVOICES` informa también lo acreditado |
| Anulación | La vigente (6.10) no cambia mientras V19 no diga lo contrario: una emitida sin pagos todavía puede anularse |
| Implementación | Entrega propia, después de M8 y antes de cerrar M9. Tablas, errores, anulación de un registro mal cargado y efectos en la entrega, los recordatorios y los reportes se diseñan al iniciarla |
| Pendiente externo | V18 (procedimiento autorizado y plazo de un año) y V19 (anulación de una factura que la aseguradora todavía no recibió). Aprobar P10 no las resuelve |

### 2.67 Cierres internos de validaciones (2026-10-29)

Antes de diseñar M8, el usuario cerró como decisiones de producto las preguntas que no necesitan un profesional. Detalle
en [especificación](../especificacion-aseguradoras.html), §10.1 ("Cierres internos").

| Tema | Decisión |
|---|---|
| V21 | Las respuestas a G1–G5 las dio **un dueño de taller que también lleva la contabilidad**: valen como base. Por recomendación (decisión del usuario), igual se busca la respuesta correcta y completa de un contador; su parte fiscal (IVA del deducible, retención del 30 %, nota de crédito y su plazo) sigue sin validar. M10 y M12 siguen exigiendo la confirmación escrita del contador. P10 se mantiene: no supone ninguna regla fiscal, solo registra lo emitido afuera |
| V9 | Muchos talleres a futuro, pocos al principio. Estándar de desarrollo y pruebas de la línea T: 10 talleres (al menos 3 dentro de la suite), con el volumen de los datos de ejemplo por taller. La infraestructura de producción se decide antes de T6 |
| Cerradas | G6, G7, G8, G9, V1, V2, V4, V6, V7, V8 y V12, con las reglas ya aprobadas o el alcance del MVP. Se revisan con el uso real |
| Pendientes con profesional | Contador: G1, V15, V16, V20, G3, V17, G4, V22, V18, V19, V11 y la dirección en la factura (V10). Asesor legal: V5, G10, V14 y la parte legal de V13. El dígito verificador del RUC se toma de la documentación oficial de la SET |
| Efecto | Ninguna pendiente bloquea M8, P10 ni M9 con deducible 0 |

### 2.68 Reparto económico (2026-10-29)

Entrega M8 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.5, §5.7, §6.2–6.3 y §9).
Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.27.

| Tema | Decisión |
|---|---|
| Refactor | `WorkOrderSaleBreakdown` sale de `InvoiceService::linesFromWorkOrder` (privado) sin cambiar el resultado. Primero el test de igualdad (`tests/Feature/Invoices/WorkOrderSaleBreakdownTest`, contra el código anterior); los tests de facturas, pagos y OT existentes pasaron sin tocarlos. Agrega a cada concepto su ítem del presupuesto y si es extra |
| Tablas | Migración `000067`: `work_order_allocations` (única por OT y versión) y `work_order_allocation_lines` (única por reparto, concepto y tipo). Alias `work_order_allocation` en el morph map (consume la excepción de absorción). En MySQL falló la primera vez: el nombre automático de la clave foránea a `insurer_authorization_items` tenía 66 caracteres (límite 64) y SQLite no lo detecta; se le dio un nombre propio y quedó la regla en `backend/CLAUDE.md` |
| Generación | Decisión del usuario del 2026-10-29: a mano (`POST`), la revisa el cajero. Con una confirmada, el borrador nuevo es su copia |
| Factura en M8 | Decisión del usuario del 2026-10-29: `WORK_ORDER_REQUIRES_ALLOCATION_INVOICING` ya en M8 (la especificación lo dejaba para M9), para que una OT de siniestro no se facture entera al dueño en el medio. Esas OT no se facturan hasta M9; no hay siniestros reales porque el frontend todavía no los usa |
| Deducible | Bajarlo por debajo de lo confirmado se rechaza (`CLAIM_DEDUCTIBLE_BELOW_ALLOCATED`, decisión del usuario del 2026-10-29, nueva). `deductible_fiscal_pending` es calculado (deducible > 0), no una columna (decisión del usuario) |
| Absorción en otra versión | La excepción consumida queda ligada a la versión que la usó: la versión nueva pide otra (decisión del usuario del 2026-10-29) |
| Sin autorización | El borrador se genera con todo al dueño y una advertencia (decisión del usuario del 2026-10-29); R5 exige su aceptación o la autorización |
| Autorización cambiada | Solo advertencia en el resumen (decisión del usuario del 2026-10-29); R3 se valida contra la vigente al confirmar |
| Vista previa | El borrador trae `checks` con cada regla (P6: resumen previo a confirmar) sin confirmar nada |
| Borrador descartado | Se elimina sin rastro y su número de versión se vuelve a usar (no es un documento) |
| Permisos | Los de §8, confirmados: gestiona el cajero; consultan recepcionista, jefe de taller y cajero; el administrador todo |
| Corrección de M7 | La re-aceptación (2.65) se bloqueaba con la OT completada, pero el reparto se arma con la OT completada y la salida (a) del Ejemplo 2 (decisión B) es que el dueño re-acepte. Ahora solo la bloquea la OT cancelada (o la recepción cerrada); con la OT completada toma lo ejecutado. El test de M7 se ajustó |
| Seeder | C05 suma una OT completada (`work_order.day`: corre la línea de tiempo de la OT dos días, para crearla después de la re-aceptación) y `AllocationSeeder` (después de `WorkOrderSeeder`, con el cajero) deja la versión 1 sustituida, la 2 confirmada y la 3 en borrador. `DatabaseSeederTest` ajusta los conteos de recepciones, OT y cierres y verifica las tres versiones |
| Pruebas | `tests/Feature/Claims/WorkOrderAllocationTest` (8): Ejemplo 1 propuesto y confirmado (con el bloqueo de la factura y del siniestro), Ejemplo 2 con absorción insuficiente (R6), absorción parcial y re-aceptación (R5), R1–R4 y R6 al confirmar e inmutabilidad, versiones y scope, deducible (desconocido, no aceptado, R7, no baja de lo asignado y Ejemplo 4 con dos OT, en secuencia), sin autorización, particular y OT sin completar, y permisos |
| Fuera de alcance | Facturas por responsable y `ALLOCATION_HAS_INVOICES` (M9, con el facturado neto de P10); la "venta facturable" en los reportes (§6.2); la concurrencia real del deducible compartido en MySQL (prueba aparte, §6.3) |

### 2.69 Notas de crédito emitidas fuera del sistema (2026-10-29)

Implementación de la decisión P10 (2.66). Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.28.

| Tema | Decisión |
|---|---|
| Tablas | Migración `000068`: `invoice_credit_notes` y `invoice_credit_note_items`. Probada en una base MySQL temporal (todas las migraciones y el rollback de esta), como pide la regla nueva de `backend/CLAUDE.md` |
| Base de la línea | Proporcional a la línea de la factura (`amount × base_total / total`), no por la tasa: así respeta el descuento de la línea. Si se acredita todo lo que le queda, es exactamente la base restante (sin diferencias de redondeo acumuladas) |
| Saldo | `Invoice::balance()` = total − pagos − notas vigentes. Todo lo que usa el saldo (pagos, entrega, recordatorios, reportes, historial, aviso) quedó correcto sin tocarlo; se agregó la precarga `credited_total` junto a `paid_total` (listado de facturas y recordatorios) y `creditNotes` en los reportes |
| Estado `credited` | Decisión del usuario del 2026-10-29: valor nuevo de `InvoicePaymentStatus` ("Acreditada") para la factura saldada solo con notas, sin pagos. **Cambio de contrato:** el frontend debe reconocerlo |
| Permisos | Decisión del usuario del 2026-10-29: registrar y anular con permisos nuevos `invoice-credit-notes.store` y `invoice-credit-notes.cancel` (cajero); consultar con `invoices.show` (recepcionista y cajero), sin permiso propio |
| Anulación | Un registro mal cargado se anula con motivo (reutiliza `CancelPaymentRequest`), como un pago. La factura con notas vigentes no se anula (`INVOICE_HAS_CREDIT_NOTES`) |
| Errores | En `InvoiceException` (módulo de facturación), sin una clase nueva |
| Número | Texto libre (≤ 30) y timbrado opcional, porque el formato del "otro procedimiento" (V18) no se conoce; único entre las vigentes |
| PDF y reportes | El PDF de la factura lista las notas y el saldo (decisión del usuario). El reporte de ventas agrega `credit_notes` y `net_total` (con sus etiquetas en `ReportExportService::LABELS`); `total` no cambió |
| Avisos | Sin aviso por email al cliente (decisión del usuario) |
| Seeder | `CreditNoteSeeder` (después de `PaymentSeeder`, con el cajero): H13 acreditada entera (estado `credited`) y H07 con una nota parcial y otra anulada. `DatabaseSeederTest` ajusta los estados de cobro y verifica las notas |
| Pruebas | `tests/Feature/Invoices/CreditNoteTest` (6): registro por línea con base proporcional y exacta, línea ajena y scope; tope del saldo, `paid` y `credited` (también en el listado); anulación de la nota y de la factura e inmutabilidad; validaciones y PDF; reporte de ventas y CSV; permisos. Los tests existentes de facturas, pagos, reportes, entregas y avisos pasaron sin cambios |
| Pendiente externo | V18 (procedimiento autorizado y plazo de un año) y V19 (anulación de una factura no recibida) |

### 2.70 Facturas por responsable (2026-10-29)

Entrega M9 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §4.6, §5.7, §7.1 y §9;
decisiones A y F). Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.29.

| Tema | Decisión |
|---|---|
| Tablas | Migración `000069`: `invoices.allocation_id` e `invoice_items.allocation_line_id`, nulos. Probada en una base MySQL temporal (todas las migraciones y el rollback de esta) |
| Facturado neto | `AllocationBillingService` (sin dependencias): base de las líneas en facturas vigentes menos la acreditada por notas (P10). Lo usan la generación, la emisión (R8), la corrección del reparto (P6) y la entrega |
| Condición | Decisión del usuario del 2026-10-29: aseguradora a crédito con vencimiento hoy + `payment_term_days` (30 si no tiene); dueño al contado. Editables en borrador |
| Líneas | Una por porción (decisión del usuario), aunque un concepto tenga dos porciones del mismo cliente. Concepto entero: su cantidad y precio; una parte: cantidad 1 y la cantidad real en la descripción (Ejemplo 5) |
| Una por cliente y versión | Decisión del usuario del 2026-10-29: lo acreditado se vuelve a facturar desde una versión nueva del reparto, no con otra factura en la misma versión |
| PDF | Decisión del usuario: la factura a la aseguradora trae el número de siniestro y la póliza |
| Entrega | Decisión del usuario: el control del deducible (A) se aplica a toda la recepción con siniestro abierto, también a las OT sin reparto. Las OT con reparto se controlan por porción y por factura; las particulares, como siempre |
| Cambio de comportamiento | Con un siniestro abierto de deducible desconocido (nulo) la recepción ya no se entrega. `ClaimTest` (M3) entregaba con deducible nulo: ahora usa deducible 0 |
| Excepciones | Los errores de entrega salen de `VehicleDeliveryException` (como los demás `DELIVERY_*`); `AllocationBillingService` solo responde si falta algo |
| Contrato (F) | `invoices[]` e `invoices_count` en el detalle de la OT con `invoices.show`; `invoice` queda como clave heredada. El historial (`vehicle-history.view`) agrega `invoices[]` sin permiso propio, como ya mostraba `invoice`, y `summary.by_recipient` |
| Bloqueo | Generar: siniestro → OT → reparto → factura. Emitir bloquea la factura y después el reparto (la generación no bloquea facturas existentes, así que no se cruzan) |
| Seeder | La OT de C05 suma un extra aprobado (a cargo del dueño) y `AllocationInvoiceSeeder` (después de `InvoiceSeeder`, con el cajero) emite las dos facturas: la de la aseguradora a crédito, impaga, y la del dueño al contado, cobrada. Es el escenario obligatorio de §7.1; el opcional (deducible mayor a 0) no se sembró. `DatabaseSeederTest` ajusta los conteos de facturación y verifica las dos facturas |
| Pruebas | `tests/Feature/Claims/AllocationInvoiceTest` (5): Ejemplo 1 facturado a cada responsable (importes de la especificación: 1.980.000 y 1.067.000 con IVA), duplicado y cliente sin porciones, `invoices[]` y su ausencia para el mecánico; entrega por factura e historial por destinatario; deducible en la generación, la emisión y la entrega, y `CLAIM_DEDUCTIBLE_LOCKED`; corrección con `ALLOCATION_HAS_INVOICES` resuelta con una nota de crédito; permisos. Los helpers del Ejemplo 1 pasaron a `ClaimTestCase` |
| Fuera de alcance | M10 (deducible mayor a 0, espera G1), M11 (avisos a aseguradoras) y M12 (retenciones, espera V17) |

### 2.71 Reportes del módulo de aseguradoras (2026-10-30)

Entrega M11 de la especificación de aseguradoras ([especificación](../especificacion-aseguradoras.html), §9; decisión D y V7).
Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.30.

| Tema | Decisión |
|---|---|
| Alcance | Decisión del usuario del 2026-10-30: M11 son los reportes. Los avisos a la aseguradora (plantillas, contacto de facturación, preferencias) esperan V7; mientras tanto rige D (cliente de la aseguradora sin avisos) |
| Cuentas por cobrar | Decisión del usuario: reporte nuevo `reports/receivables` con permiso `reports.receivables` (cajero), en lugar de ampliar el reporte de clientes. Misma regla de vencimiento que las cuentas por pagar. Agrega antigüedad por días de atraso |
| Tipo de destinatario | Enum `InvoiceRecipientType` (`insurer|customer`). Una aseguradora dada de baja sigue clasificando como aseguradora |
| Resumen del siniestro | Decisión del usuario: por siniestro (`claims/{claim}/summary`) y además un reporte (`reports/claims`, permiso `reports.claims`, cajero; período por fecha del siniestro) |
| Visibilidad | Decisión del usuario: por bloque según permiso. Presupuestado, autorizado y aceptado con `claims.show`; el reparto con `work-order-allocations.show`; facturado y cobrado con `invoices.show` (el jefe de taller no ve facturas, como hoy). Las alertas siguen la misma visibilidad |
| Unidades | Presupuesto, autorización, aceptación y reparto sin IVA; facturación con IVA. Van en bloques separados y no se suman entre sí |
| Totales | Presupuestos: solo los aprobados (los que pasan a OT); se listan todos los no cancelados. Facturas: solo las emitidas; los borradores se cuentan aparte |
| Contrato | Aditivo: `recipient_type` y `recipient_label` en `reports/clients` (`receivables.clients`). Tres rutas nuevas; sin migración |
| Verificación en MySQL | Resúmenes, cuentas por cobrar, reporte de siniestros y CSV corridos de solo lectura sobre la base local sembrada (4 siniestros) |
| Pruebas | `tests/Feature/Claims/InsurerReportsTest` (4): cuentas por cobrar por destinatario con antigüedad, pagos y filtros (aseguradora dada de baja incluida) y exportación; resumen del siniestro de lo presupuestado a lo cobrado con complementario en borrador y alertas; bloques según permiso y permisos de los reportes; reporte de siniestros con filtros, período y exportación. Los reportes existentes no cambiaron sus tests |
| Fuera de alcance | Avisos a la aseguradora (V7), M10 (deducible mayor a 0, espera G1) y M12 (retenciones, espera V17) |

### 2.72 Filtro por tipo de cliente (2026-10-30)

Pedido por la fase FA1 del frontend (aseguradoras y siniestros): el alta de una aseguradora puede vincular un cliente
empresa existente, y el buscador necesita listar solo empresas.

| Tema | Decisión |
|---|---|
| Filtro | `GET clients?kind=person|company` (decisión del usuario del 2026-10-30: en el backend, no filtrando en el frontend, porque el listado es paginado). Aditivo; un valor fuera del enum responde `422 VALIDATION_ERROR` en `kind` |
| Empresas en producción | El frontend admite clientes empresa desde FA0 (2026-10-30): se levanta la advertencia de M1 |
| Pruebas | `ClientKindTest::test_clients_are_filtered_by_kind` |

### 2.73 Conceptos vendidos en el reparto (2026-10-30)

Pedido por la fase FA3 del frontend (reparto y facturación): el editor del borrador muestra una fila por concepto y
necesita saber cuánto debe sumar cada uno aunque el borrador guardado esté incompleto.

| Tema | Decisión |
|---|---|
| Contrato | `WorkOrderAllocationResource.concepts` (decisión del usuario del 2026-10-30: en el backend, no deduciéndolo de las porciones en el frontend). Aditivo y de solo lectura (`AllocationService::concepts`, sobre `WorkOrderSaleBreakdown`): `base_total` (R1), `coverable` (R4: ni extra ni línea del dueño) y `authorized_total` del ítem en la autorización vigente (R3, por ítem). Sin migración ni cambios de reglas |
| Pruebas | `WorkOrderAllocationTest::test_the_allocation_lists_the_sold_concepts` (suma igual a la venta, `coverable`, autorizado y extra) |

### 2.74 Discrepancias D3, D7 y D8 (2026-10-30)

Decisiones del usuario del 2026-10-30 sobre las discrepancias que quedaban abiertas ([deuda técnica](../roadmap/deuda-tecnica.md)). Migración `000070`, probada
en MySQL (también su `down`).

| Tema | Decisión |
|---|---|
| D3 | Se elimina `receptions.approved_by` (columna con su clave foránea, `fillable`, relación, búsqueda del listado y clave del detalle). Nunca se asignaba: siempre era `null`. Cambio de contrato: `ShowReceptionResource` ya no trae `approved_by`; el frontend solo la tenía en el tipo |
| D7 | Índice único en `clients.email`. Los dados de baja ya liberan el email con el sufijo `//deleted_` |
| D8 | `clients.document_number` y `clients.email`, `NOT NULL` (la API ya los exigía) |
| Datos existentes | La migración no inventa datos: si hay clientes sin documento o sin email, o emails repetidos, se detiene con el conteo. En la base local había uno sin email (el cliente de ejemplo del escenario C15) y se completó con el email que ahora le da el seeder |
| Ejemplos | `ClientsSeeder::WITHOUT_EMAIL` pasa a `C15_OWNER`, con email; C15 queda como un presupuesto enviado con su aviso. Los avisos omitidos de ejemplo bajan de 2 a 1 (C16, dado de baja) |
| Avisos sin email | El control de `ClientNotificationService` (omitido al registrar, `CLIENT_WITHOUT_EMAIL` al reenviar) queda como defensa, sin cambios: solo se alcanza con un email vacío, y su test usa eso |
| Pruebas | `ClientKindTest::test_the_database_requires_a_unique_email_and_the_document`, `ReceptionTest` (sin `approved_by`), `DatabaseSeederTest` y los helpers de tests que creaban clientes sin documento o sin email. Dos ajustes por el cambio: `ClaimTestCase` repetía `ana@example.com` entre los clientes de un mismo test (ahora solo el primero), y un test de recordatorios que esperaba avisos omitidos por falta de email reinicia el registro de emails antes de verificar |

### 2.75 Deducible facturado: modalidad neta (M10, 2026-10-30)

Entrega M10 de la especificación (§6.6, Ejemplo 7), con la modalidad candidata que salió de la respuesta a G1. Reglas
vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.31.

| Tema | Decisión |
|---|---|
| Habilitación | Decisión del usuario del 2026-10-30: implementada y probada completa, detrás de `insurer.deductible_net_mode` (`INSURER_DEDUCTIBLE_NET_MODE`), **apagado por defecto**. La respuesta a G1 la dio un dueño de taller que lleva la contabilidad; la confirmación escrita de un contador (V21) es el requisito para encenderlo en producción |
| IVA del deducible (V15) | Decisión del usuario: se indica en cada siniestro (`deductible_includes_tax`), sin valor por defecto, como la unidad de los importes de una autorización (M6) |
| Deducible mayor que lo cubierto (V16) | Decisión del usuario: se rechaza la confirmación (`ALLOCATION_DEDUCTIBLE_EXCEEDS_COVERED`) hasta tener la respuesta |
| Factura del deducible | Decisión del usuario: en la misma factura del dueño (sigue "una vigente por cliente y versión", M9) |
| Modalidad | En el siniestro, copiada de la aseguradora al abrirse (§4.3). Apagado el interruptor, no se elige (422 de validación) y lo cargado no tiene efecto |
| Porción del deducible | `owner_deductible` sobre los mismos conceptos, descontada de lo cubierto en orden de concepto (determinista); R1/R2 sin cambios. La propuesta la arma y el editor la muestra como una columna más |
| R11 | Nombre nuevo (R10 ya era "la OT con reparto no se factura entera"): las porciones del deducible suman la base del deducible asignado a la OT (0 sin la modalidad). Se revalida al generar y al emitir las facturas: si el deducible, su IVA o la modalidad cambian después de confirmar, se genera otra versión |
| R5 | No cuenta el deducible: lo cubren el control del deducible aceptado, R7 y R11 |
| Cambios en el siniestro | Modalidad e IVA del deducible, bloqueados con una factura vigente a la aseguradora (`CLAIM_DEDUCTIBLE_LOCKED`), igual que el importe |
| Datos de ejemplo | Sin escenario: el interruptor viene apagado y `DatabaseSeederTest` no cambia |
| Migración | `000071` (columnas nulas), probada en MySQL con su `down` |
| Pruebas | `tests/Feature/Claims/DeductibleNetModeTest` (5): Ejemplo 7 (propuesta, reglas, facturas de 1.760.000 y 1.287.000 con la línea del deducible, bloqueo del siniestro y entrega), interruptor apagado (validación, regla A en factura y entrega), IVA sin indicar y sin IVA, R11 al confirmar y al facturar, deducible mayor que lo cubierto y modalidad copiada de la aseguradora. Los tests de M8 y M9 no cambiaron |
| Corrección | La revisión del frontend (2026-10-30) mostró que `deductible_fiscal_pending` seguía en `true` con la modalidad neta (el reparto y el resumen decían "no se factura"). Ahora es deducible mayor a 0 **que no se factura** (`AllocationBillingService::deductibleInvoiced`: modalidad neta en vigor y su IVA indicado). Con su verificación en el test del Ejemplo 7 |
| Frontend | Hecho el 2026-10-30: modalidad e IVA en el siniestro y la aseguradora, columna del deducible y reglas nuevas en el editor del reparto |
| Pendiente | V16 (respuesta), V20 (documento de la modalidad) y V21 (encender) |

### 2.76 Emails de ejemplo en mail.com.py (2026-10-30)

Los emails de ejemplo de clientes, aseguradoras (contactos), proveedores y siniestros usaban `example.com` (y `.test`), que publican un "null MX": no pasan `email:rfc,dns` con `EMAIL_DNS_CHECK=true`, y no se podía editar un siniestro de ejemplo (el email del asegurado se copia del cliente).

| Tema | Decisión |
|---|---|
| Dominio | Decisión del usuario del 2026-10-30: `mail.com.py`, como los usuarios de ejemplo. `nombre@example.com` → `nombre@mail.com.py`; `nombre@empresa.example.com` y `nombre@empresa.test` → `nombre.empresa@mail.com.py` |
| Riesgo | Es un dominio real con correo: con un SMTP configurado, un aviso reenviado de un cliente de ejemplo sale de verdad (hoy `MAIL_MAILER=log`). `NotificationSeeder` sigue usando el mailer `array` |
| Base local | Los seeders no corrigen lo que ya existe: los datos de ejemplo de la base local se actualizaron con el mismo reemplazo (clientes, asegurados de siniestros, contactos de aseguradoras y proveedores) |
| Pruebas | `DatabaseSeederTest` (email del cliente C15) |

### 2.77 Actualización de dependencias (2026-11-01)

Pedido del usuario: las versiones más actuales y compatibles en los dos repositorios, para dejar iguales sus dos equipos.

| Tema | Decisión |
|---|---|
| Backend | `composer update` dentro de los rangos de `composer.json`, sin cambiarlos: Laravel 13.39, PHPUnit 12.5.42, Symfony 7.4.22 y otros parches. `composer outdated` queda vacío y `composer audit` sin avisos |
| Frontend | `npm update` dentro de los rangos, más ApexCharts (abajo). Siguen por compatibilidad `typescript` 6.0.3 (`typescript-eslint` exige `<6.1.0`) y `@types/node` 24 (el runtime es Node 22) |
| Licencia de los gráficos | Decisión del usuario, informado del cambio: `apexcharts` 4.7.0 → 8.7.0 y `react-apexcharts` 1.7.0 → 2.2.0, en versión exacta. Ya no son MIT: licencia dual, gratis por debajo de USD 2 millones de ingresos anuales; la OEM (paga) puede aplicar a un SaaS con gráficos interactivos. **Revisar antes del primer taller comercial** |
| Efecto en el frontend | `react-apexcharts` 2 es ESM: se quitó el envoltorio de `default` en `Chart.tsx`. El paquete lazy de gráficos pasa a unos 280 kB gzip; `react-apexcharts/core` permitiría reducirlo |
| Vulnerabilidades | Producción, sin avisos en los dos repositorios. Quedan 12 moderadas en el frontend, solo de desarrollo (`minimatch`, por ESLint), sin arreglo compatible |
| Pruebas | Backend: 472 tests. Frontend: 38 tests, build y lint después de `npm ci`. Ningún test cubre los gráficos: se renderizaron los tipos en uso (barras, barras horizontales y donut, con las opciones de los reportes) en Chrome sin interfaz, sin errores, y se revisó la captura |
| Reinstalar | `composer install` y `npm ci`, nunca `npm install`: la diferencia entre los equipos era un `package-lock.json` reescrito sin cambios en `package.json` |

### 2.78 Configuración del taller en la base (T1, 2026-11-01)

Primera entrega de la línea T, la única sin validaciones pendientes. Sirve también sin SaaS. Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.32.

| Tema | Decisión |
|---|---|
| Alcance | `company.*`, tasa de IVA por defecto, remitente y horarios de avisos, en `system_settings` (sin migración). Quedan afuera `invoice.decimals`, `maintenance.*`, `insurer.*` y los destinatarios internos: la especificación no los incluye |
| Valores | Solo se guarda lo cambiado; `null` vuelve al `.env`. El frontend manda solo los campos cambiados, para no fijar en la base los valores del `.env` |
| Aplicación | `config()->set`, como prevé la especificación (§3.6): el resto del código no cambia. Por request (una consulta), por job y en la tarea de recordatorios, no al arrancar: la base puede no estar disponible (`composer install`, `migrate`) |
| Worker | `Queue::before` reaplica antes de cada job, y el remitente va en el `Envelope` porque el mailer guarda el global al crearse |
| Tasa de IVA | Decisión del usuario: editable por el administrador (0, 5 o 10), con aviso previo en la pantalla y `meta.warnings`. Rige desde que se guarda, también en documentos en curso |
| Hora de los recordatorios | Decisión del usuario: en hora de Paraguay. **Cambio de comportamiento:** con el valor por defecto salían a las 08:00 UTC (05:00 de Paraguay) y ahora a las 08:00 de Paraguay. La tarea pasa a `everyMinute()` con `when()`: no se puede leer la base al cargar `routes/console.php` |
| Permiso | Se reutiliza `system-settings.update`, también para leer: los datos solo se usan en esa pantalla |
| Hallazgo | La aplicación corre en UTC: deuda #17, resuelta después (2.79) |
| Tasa de un documento ya calculado | La prueba en el navegador mostró que el PDF de un presupuesto aprobado al 10 % decía "IVA 5%" junto a su importe del 10 %: la etiqueta salía de la configuración, que hasta T1 era fija. Ahora sale de los importes guardados (`Budget::taxRate`), sin migración. El diálogo "Facturar OT" tampoco afirma ya "IVA 10%": era un texto fijo |
| Pruebas | `tests/Feature/Settings/WorkshopSettingsTest` (14): valores por defecto, reemplazo, vuelta al `.env`, tasa 0, tasa nueva en la factura y su aviso, tasa del PDF del presupuesto, totales iguales por defecto, validaciones, permisos por rol, auditoría, remitente, job, comando y hora local. Frontend: `WorkshopSettingsCard.test.tsx` (4). Revisión en Chrome (2026-11-01) sobre una copia temporal de la base (`synctaller_t1`, borrada después): error de validación, aviso de la tasa, guardado, recarga, vaciar un campo, PDF, factura al 5 % y 403 de la recepcionista, sin errores de consola |

### 2.79 Zona horaria: hora de Paraguay (deuda #17, 2026-11-01)

| Tema | Decisión |
|---|---|
| Problema | La aplicación corría en UTC: desde las 21:00 de Paraguay `now()` ya era el día siguiente. Una factura emitida a la noche salía con fecha de mañana, y el pago con la fecha real se rechazaba por "anterior a la emisión". También se movían filtros y reportes |
| Opción elegida | Decisión del usuario, entre tres (toda la aplicación en hora local; guardar UTC y calcular el día local; parche mínimo): `app.timezone` = `APP_TIMEZONE`, por defecto `America/Asuncion`. Un solo cambio corrige `whereDate`, validaciones (`today`), rangos y fechas de los Resources, sin tocar cada uso |
| Por qué no UTC | Es el estándar, pero exigía revisar unos 49 filtros, 77 formatos de fecha y 22 rangos, y cada omisión sería un error silencioso. Paraguay no tiene horario de verano (UTC-3 fijo), así que la hora local no es ambigua, y la base por taller (T2) permite que cada taller tenga su zona |
| Límite | En un país con horario de verano habría una hora ambigua por año, y un informe entre talleres de distintas zonas necesitaría convertir horas |
| Datos | Decisión del usuario: la base local solo tiene datos de ejemplo, sin conversión. Sus horas ya estaban pensadas como locales (una OT a las 17:00 se veía a las 14:00 y ahora a las 17:00) |
| API | Las fechas con hora siguen viajando en UTC (`Z`); el frontend las muestra según el navegador. Sin cambios de contrato |
| T1 | Se quitó `notifications.timezone`: ya no hace falta |
| Frontend | `AuthorizationFormModal` calculaba hoy con `toISOString()` (UTC): usa `todayInput()` |
| **Antes de T6** | Laravel escribe la hora local y las columnas `timestamp` de MySQL la convierten según la zona de la sesión. Aquí es la del sistema (UTC-3). En producción, fijar `database.connections.mysql.timezone` o `default-time-zone` en `-03:00`, y usar la misma al restaurar un respaldo |
| Pruebas | `tests/Feature/Settings/TimezoneTest` (3), con instantes absolutos en UTC: fallan con `APP_TIMEZONE=UTC`. Frontend: fecha propuesta a la noche. Revisión en Chrome sobre una copia temporal (`synctaller_tz`, borrada después): OT con sus horas correctas y factura emitida con la fecha y la hora del reloj, también en MySQL |

### 2.80 Revisión del spike T0 y decisiones para T2 (2026-11-01)

Auditoría y medición antes de T2 ([`spike-t0/informe.md`](../spike-t0/informe.md), sección 8). No se cambió código del producto ni se aprobó todavía
instalar la dependencia en `main`. Se llaman **TD** porque `D1–D10` ya son las discrepancias de la [deuda técnica](../roadmap/deuda-tecnica.md).

| # | Decisión del usuario |
|---|---|
| TD1 | Paso 0 hecho en una rama descartable. Lo necesario para repetirlo está en [`spike-t0/`](../spike-t0/README.md) |
| TD2 | Paquete **4.2.3 desde el 2026-11-09**, cuando cumple dos semanas. 4.3.0 no aporta lo suficiente con un `tenants:migrate` propio. **T2 no empieza antes** y repite la medición con esa versión |
| TD3 | `tenants.domain`, sin `tenant_domains` |
| TD4 | Id entero |
| TD5 | `active` y `suspended` en T2; `provisioning` y `cancelled` en T5; el mantenimiento es un dato aparte. Sin `deleted` ni `provisioning_failed`: la fila del taller no se borra |
| TD6 | Hora de recordatorios por taller, como en T1, recorriendo los activos cada minuto |
| TD7 | Reintentos de jobs hasta 6 horas |
| TD8 | Sin `X-Tenant`: hosts reales también en local y tests |
| TD9 | Los umbrales de G no cambian; conexión persistente solo en la central |
| TD10 | `DB_DATABASE` vacío en producción: sin taller activo, una consulta falla |
| TD11 | Taller cancelado: `404 TENANT_NOT_FOUND` |

| Hallazgo | Efecto en T2 y T3 |
|---|---|
| El registro de Spatie se instancia al arrancar, antes de identificar el taller | La tarea `initializeCache()` es obligatoria también en HTTP: sin ella, los permisos se comparten entre talleres. Comprobado |
| `forgetCachedPermissions()` borra la cache | No usarla al activar un taller (corrige §3.6): suma unos 140 ms por request |
| El paquete borra el job si no encuentra el taller, antes de lanzar la excepción | Por lectura del código, no llegaría a `failed_jobs`: validar al despachar y no borrar filas de `tenants`. Verificar con un test |
| Las tareas de cambio no abren conexión, y la activación corre en `JobProcessing`, antes del middleware del job | Una tarea que consulte la base, como aplicar T1, haría fallar el job antes del middleware. Se separa cambiar (sin E/S) de preparar (`TenantRuntime::ensureReady()`) |
| `ApplyWorkshopSettings` corre en tres lugares | Se unifica en T2 |
| `$tries = 1` es incompatible con liberar el job | `retryUntil` con `maxExceptions = 1`: un error dentro de `handle` no se reintenta, porque podría duplicar el email |
| `failed_jobs` y `job_batches` usan `DB_CONNECTION` | Fijarlos en la central. También CORS para los dominios de taller |
| Sin límite de intentos en el login | Fuera de multitenancy. Resolver antes del primer taller comercial y probar el prefijo del `RateLimiter` |

Plan: pasos 1 a 3 en T2 (base central, identificación, unificar T1), 4 y 5 en T3 (jobs, scheduler) y 6 y 7 en T5
(`tenants:migrate`, suspensión). Los tests permanentes van en `tests/Feature/Tenancy` y cada uno debe fallar al quitar su
protección.

### 2.81 Skills del proyecto (2026-11-01)

Primeras Skills de Claude Code, versionadas en `.claude/skills/`. Las reglas siguen en `docs/README.md` y los `CLAUDE.md`: las Skills
indican qué leer, no las repiten.

| Tema | Decisión |
|---|---|
| `retomar` | Solo lectura: informa por separado el último commit y el último checkpoint validado, y propone el siguiente paso. No corre los tests |
| `cerrar-entrega` | Solo la invoca el usuario. Se detiene si la entrega no está validada, pide confirmación si no puede determinar qué documentar y no hace commit |
| `validar-docs` | Solo lectura. Enlaces, documentos huérfanos, rutas citadas y cifras contra el código |
| Cifras | Toda cifra se compara con el código, salvo una marca explícita en su misma cláusula. Las excluidas se cuentan y `--detalle` las lista. Motivo: un filtro para evitar falsos positivos había ocultado el error de `README.md`, 45 services en vez de 47 |
| Validador en PHP | PHP es requisito del proyecto; Node es opcional en el backend. Pruebas: `.claude/skills/validar-docs/scripts/check-docs-test.php` |
| Local | `.claude/settings.local.json` queda fuera de Git |
| `medir-tenancy` | Sin crear: se decide al arrancar T2, con un caso real que medir |

### 2.82 Monorepo, estilo con Pint y baseline de tests (2026-11-24)

| Tema | Decisión |
|---|---|
| Monorepo | Decisión del usuario: `synctaller-backend` y `synctaller-frontend` se unen en `rubenminarro/synctaller` sin su historial de Git. Cada app conserva sus dependencias, lock y `.env`; la raíz orquesta (`package.json`) y valida (CI con filtro por carpeta) |
| Estilo | Decisión del usuario: se aplicó `vendor/bin/pint` con su configuración por defecto (preset de Laravel) a todo el backend, en un cambio solo de formato (389 archivos), aprovechando el arranque del monorepo. Resuelve la deuda #18. Desde entonces `pint --test` corre en `npm run lint` y en la CI |
| Baseline | La corrida completa posterior (tests del backend, ESLint, Vitest y build) quedó en verde y es la línea base, registrada en `CLAUDE.md` y en la última verificación del roadmap |
| Tests en el día a día | Decisión del usuario: solo tests puntuales de lo que se toca; la suite completa, a pedido del usuario. La CI la corre en cada push y pull request |

### 2.83 Respuestas de la contadora y plan que sigue (2026-11-24)

El cuestionario del 2026-10-29 volvió respondido por **una contadora** (confirmado por el usuario; el documento no trae los
datos de quien responde). Transcripción y efectos en la [especificación](../especificacion-aseguradoras.html), §10.1
("Respuestas de la contadora"). Ninguna regla vigente cambia hasta implementar cada entrega.

| Tema | Decisión |
|---|---|
| V21 | Cumplida: es la confirmación escrita de un contador que pedían M10 y M12. Lo que valía como base (2.67) queda confirmado con los ajustes de abajo |
| V16 | Respuesta: el dueño paga el valor real, nunca el deducible completo, y la aseguradora recibe factura. Decisión del usuario («lo más conveniente»): el deducible facturado en una OT es **como máximo lo cubierto** por la aseguradora en ella; lo que sobra no se cobra; la aseguradora se factura por lo que le quede y, si no le queda nada, no hay factura en cero. Reemplaza el rechazo de 2.75 (`ALLOCATION_DEDUCTIBLE_EXCEEDS_COVERED`) |
| M10 | El interruptor se enciende después de implementar V16. Hasta entonces sigue apagado por defecto |
| V17 y M12 | Rediseño (§9, versión 4): cualquier cliente puede retener; IVA o renta; número y timbrado del comprobante; un comprobante para varias facturas; solo facturas a crédito. Decisión del usuario: el **importe lo carga el cajero** tal como figura en el comprobante y, para el IVA, la API sugiere el 30 % del IVA. El monto mínimo para retener no se valida. Falta aprobar el diseño |
| V19 | Decisión del usuario (la práctica general con cualquier empresa): se anula solo una factura **emitida en el mes en curso** (todavía no declarada), con el motivo de siempre; después corresponde una nota de crédito. Cambia la anulación vigente (6.10) al implementarse |
| V10 | Decisión del usuario: los datos del cliente (dirección, teléfono y correo) **no bloquean** la emisión. La factura los guarda y los muestra, y la API informa los que faltan para que el frontend avise |
| V11 | `REC-` ya cumple la numeración correlativa. El recibo agrega la condición de la factura y el siniestro con su póliza |
| V18 | El timbrado de la nota de crédito registrada pasa a ser obligatorio. «Antes del cierre del ejercicio» es una recomendación: no se valida |
| V15 y V20 | V15 confirma el diseño (el deducible no incluye IVA salvo que la póliza lo diga; 10 %). V20 suma dos tipos de documento al siniestro: contrato y cédula del declarante |
| V22 | Anticipos sigue fuera del MVP; la factura a crédito con recibos ya cubre una de las dos formas |
| V14 y G10 | Conservación: 5 años. Exportación antes de borrar y suspensión por etapas (aviso, gracia, solo lectura): revisan P9 en la línea T, decisión comercial pendiente |
| Sin respuesta | V3 y V13 (talleres), V5 y la parte legal de V13 (asesor legal). No bloquean. Van en el [cuestionario pendiente](https://claude.ai/artifact/2APciv4PaFoKoPsDfAtJv1), junto con los términos comerciales de la suspensión (V14) y confirmaciones opcionales de la contadora sobre V16, V19, V10 y M12 |
| Orden | 1) recibo completo (V11); 2) M12, si se aprueba el diseño; 3) datos del cliente en la factura (V10); 4) ajustes chicos (V18, V20, V15); 5) anulación (V19); 6) V16 y encender M10. Cada una en su rama |

### 2.84 Retenciones de IVA y renta (M12, 2026-11-24)

Entrega M12 con el diseño de la versión 4 de la [especificación](../especificacion-aseguradoras.html) (§9), aprobado por
el usuario al pedir que empiece. Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.33.

| Tema | Decisión |
|---|---|
| Alcance | Cualquier cliente puede retener, no solo las aseguradoras (V17). Solo facturas emitidas a crédito |
| Importe | Decisión del usuario: el del comprobante, cargado por el cajero. La API sugiere el 30 % del IVA (`invoice.vat_withholding_percentage`), menos lo ya retenido de IVA y sin pasar el saldo; para la renta no hay sugerencia porque no se conoce su porcentaje (pregunta C4 del cuestionario pendiente) |
| Comprobante | Número y timbrado obligatorios. Un comprobante puede cubrir varias facturas: una fila por factura. Duplicado = mismo comprobante e impuesto vigentes en la misma factura (409) |
| Monto mínimo | No se valida (V17: solo se retiene por encima de un monto): se registra lo que dice el comprobante |
| Rutas | `invoices/{invoice}/withholdings`, como las notas de crédito (P10): registrar, anular con motivo, listar y ver. Una por factura, sin endpoint para varias a la vez |
| Estado de cobro | Una retención cuenta como un pago: con saldo, `partial`; sin saldo, `paid`. `credited` queda para la factura saldada solo con notas de crédito. Por eso la factura de la aseguradora de C05 de los datos de ejemplo pasó de `unpaid` a `partial` |
| Anulación de la factura | Bloqueada con retenciones vigentes (`INVOICE_HAS_WITHHOLDINGS`), como con pagos y notas de crédito |
| Caja y reportes | Sin movimiento de caja: `payments/summary` y lo cobrado del reporte de ventas no cambian; el reporte de ventas trae `withholdings` aparte, por la fecha de la retención |
| Saldo | `Invoice::balance()` resta las retenciones vigentes. Todo lugar que precargaba `creditNotes` o `credited_total` precarga también `withholdings` o `withheld_total`, para no hacer una consulta por factura |
| Migración | `000072` (tabla `invoice_withholdings`), probada en MySQL con su `down` |
| Datos de ejemplo | `WithholdingSeeder`: la retención de IVA sugerida y una de renta anulada en la factura de la aseguradora de C05. `DatabaseSeederTest` verifica los dos estados y los dos impuestos |
| Pruebas | `tests/Feature/Invoices/InvoiceWithholdingTest` (5): sugerencia, saldo, estado, caja y ventas; condición, tope, fechas, validación y duplicados; anulación y bloqueo de la factura; cuentas por cobrar y PDF; permisos por rol |
| Verificación | En el navegador, sobre una base temporal sembrada: cajero (alta con la sugerencia, error de tope, anulación, reporte de ventas y resumen del siniestro) y recepcionista (solo lectura) |

### 2.85 Recibo completo (V11, 2026-11-24)

Primera entrega del orden de la sección 2.83. La contadora respondió (V11) que el recibo, sin timbrado pero correlativo,
declara la factura que cancela, la forma de pago (contado o crédito), la fecha y los detalles del siniestro. Reglas
vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.18.

| Tema | Decisión |
|---|---|
| Numeración | `REC-` ya era correlativo y sin huecos (los pagos se anulan, no se borran): sin cambios |
| Forma de pago | Se muestra la **condición de la factura** (siempre crédito: solo esas tienen recibo), además del medio de pago que ya estaba |
| Siniestro | Número (con el código `SIN-`), póliza y aseguradora cuando la factura sale de un reparto (M9), sea la de la aseguradora o la del dueño. La factura en PDF sigue mostrándolo solo en la de la aseguradora |
| Pruebas | `AllocationInvoiceTest` (recibo de la factura a la aseguradora con el siniestro) y `DocumentPdfTest` (factura particular, sin siniestro), sobre el HTML que recibe la vista |

### 2.86 Datos del cliente en la factura (V10, 2026-11-24)

Tercera entrega del orden de la sección 2.83. La contadora respondió (V10) que toda factura consigna dirección, teléfono
y correo del cliente. Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.10.

| Tema | Decisión |
|---|---|
| Bloqueo | Decisión del usuario (2.83): dirección, teléfono y correo **no bloquean** la emisión. Solo el RUC/CI sigue bloqueando |
| Copia | El borrador copia del cliente dirección, teléfono y correo; editables en borrador como el resto de la cabecera |
| Factura particular | No copiaba la dirección (solo la del reparto lo hacía). Decisión del usuario: se corrige en esta entrega |
| Facturas anteriores | Decisión del usuario: quedan sin teléfono ni correo. Una factura emitida conserva lo que se imprimió; no se completan con los datos actuales del cliente |
| Contrato | Solo se agregan claves: `customer.phone`, `customer.email` y `customer.missing` (`address`, `phone`, `email`) en el detalle. El listado no cambia |
| Validación | Teléfono y correo con el formato del alta de clientes; el correo hasta 255 caracteres (el tamaño de la columna) |
| Migración | `000073` (`customer_phone` y `customer_email` en `invoices`), probada en MySQL con su `down` |
| Frontend | Teléfono y correo en el detalle y en "Editar datos"; aviso en el borrador con los datos que faltan |
| Pruebas | `InvoiceDraftTest` (copia, edición, validación, `missing` y emisión sin bloqueo), `AllocationInvoiceTest` (copia en el reparto), `DocumentPdfTest` (PDF) y `EditInvoiceModal.test.tsx` |
| Pendiente | El dígito verificador del RUC (la otra parte de V10) sigue esperando la documentación de la SET |

### 2.87 Ajustes V18, V20 y V15 (2026-11-25)

Cuarta entrega del orden de la sección 2.83: los ajustes chicos que dejaron las respuestas de la contadora. Reglas vigentes
en [reglas de negocio](../reglas-de-negocio.md), 6.28 (notas de crédito), 6.24 (documentos del siniestro) y 6.31 (IVA del
deducible).

| Tema | Decisión |
|---|---|
| V18, timbrado | El timbrado de la nota de crédito registrada pasa a ser **obligatorio** (`stamp_number` `required`; `VALIDATION_ERROR`, 422). Decisión del usuario: solo para los registros nuevos. Las notas ya registradas sin timbrado quedan como están (la columna sigue admitiendo nulo) y no se agrega cómo completarlas |
| V18, plazo | «Antes del cierre del ejercicio» es una recomendación: decisión del usuario, no se valida ni se avisa |
| V20 | Tipos nuevos en `ClaimDocumentType`: `contract` ("Contrato") y `declarant_id` ("Cédula del declarante"). La columna `type` es texto: sin migración |
| V15 | Decisión del usuario: «no incluye IVA» es el valor inicial **solo en el formulario de alta del frontend**. La API no cambia (nulo sigue siendo posible y sigue bloqueando con la modalidad neta) y los siniestros existentes no se tocan. Sin deducible el campo no se muestra y el alta envía `null` |
| Contrato | Cambia una validación (timbrado obligatorio) y se suman dos valores al enum de tipos de documento. Sin rutas, permisos ni `error_code` nuevos |
| Datos de ejemplo | `CreditNoteSeeder` carga el timbrado en sus notas |
| Pruebas | `CreditNoteTest` (sin timbrado → 422), `ClaimDocumentTest` (los dos tipos nuevos), `AllocationInvoiceTest` (el body con timbrado) y `claims/api.test.ts` (valor inicial del alta) |

### 2.88 Anulación solo en el mes de emisión (V19, 2026-11-25)

Quinta entrega del orden de la sección 2.83. Regla vigente en [reglas de negocio](../reglas-de-negocio.md), 6.10.

| Tema | Decisión |
|---|---|
| Regla | Una factura emitida se anula solo si `issue_date` cae en el mes en curso: todavía no se declaró. Después corresponde una nota de crédito registrada (6.28). `issue_date` es siempre el día de la emisión, así que es una referencia confiable |
| Mes | El de la zona de la aplicación (`APP_TIMEZONE`, `America/Asuncion`). El último minuto del mes todavía admite anular |
| Error | `INVOICE_CANCELLATION_PERIOD_CLOSED`, 422, nuevo. Se valida después de `INVOICE_NOT_CANCELLABLE` y antes de pagos, notas de crédito y retenciones |
| Excepciones | Decisión del usuario: ninguna, ni para el administrador. Sin permiso nuevo |
| Alcance | Decisión del usuario: todas las facturas, también las del reparto (6.29) |
| Contrato | Campo nuevo `is_cancellable` en el detalle (`ShowInvoiceResource`): emitida y dentro del mes. Así el frontend no repite la regla. Sin rutas, permisos ni códigos HTTP nuevos |
| Frontend | Decisión del usuario: fuera de plazo el botón "Anular" no se muestra y un aviso remite a la nota de crédito (solo a quien tiene `invoices.cancel`) |
| Datos existentes | Las facturas emitidas en meses anteriores dejan de poder anularse desde la entrega. Los datos de ejemplo anulan el mismo día de la emisión: no cambian |
| Pruebas | `InvoiceIssueTest` (límite del mes con instantes en UTC y borrador no anulable) e `InvoiceDetailPage.test.tsx` (botón y aviso) |

### 2.89 Deducible mayor que lo cubierto y encendido de M10 (V16, 2026-11-25)

Sexta y última entrega del orden de la sección 2.83. Regla vigente en [reglas de negocio](../reglas-de-negocio.md), 6.31.
Reemplaza el rechazo provisorio de la sección 2.75.

| Tema | Decisión |
|---|---|
| Tope | El deducible que se factura en una OT (porciones `owner_deductible`) es la base del deducible asignado, como máximo lo que la aseguradora cubre en ella: sus porciones más las del deducible. Un solo cálculo, `AllocationBillingService::expectedOwnerDeductible`, para la propuesta, R11 y el control al facturar (`ownerDeductibleBase` conserva la base sin tope para la propuesta) |
| Lo que sobra | Decisión del usuario (recomendación): no se cobra ni pasa a otra OT del mismo siniestro. R7 y la sugerencia del deducible de la OT siguiente siguen contando el deducible asignado entero; para cobrarlo en otra OT, el cajero baja a mano el de la primera |
| Regla retirada | `deductible_covered` y su `ALLOCATION_DEDUCTIBLE_EXCEEDS_COVERED` dejan de existir: un deducible mayor que lo cubierto se confirma |
| Factura en cero | No hace falta código: sin porciones, la aseguradora no figura entre los responsables y generarle la factura da `ALLOCATION_CLIENT_NOT_RESPONSIBLE` (422, existente). La entrega no cambia |
| Revalidación | Con el tope alcanzado, si el deducible baja o sube sin quedar por debajo de lo cubierto (por ejemplo, al indicar que incluye el IVA), R11 sigue cumpliéndose y no se pide otra versión del reparto: el dueño ya paga el máximo |
| Contrato | Campo nuevo `summary.deductible_uncollected` en el reparto (base sin IVA que no se cobra, `0.00` si no hay). Sin rutas, permisos ni códigos HTTP nuevos |
| Cancelación del siniestro | Decisión del usuario (recomendación): las porciones `owner_deductible` también la bloquean, con el mismo `CLAIM_HAS_INSURER_ALLOCATIONS`. Sin eso, con todo lo cubierto pasado al deducible, el siniestro se cancelaba con la factura del deducible vigente. Cambia el mensaje, no el código |
| Encendido de M10 | Decisión del usuario (recomendación): `INSURER_DEDUCTIBLE_NET_MODE` pasa a `true` por defecto (`config/insurer.php` y `.env.example`) y el interruptor se conserva para apagarlo sin tocar código. La modalidad sigue eligiéndose por aseguradora y por siniestro: sin ella rige la regla A. Cada `.env` existente conserva su valor |
| Datos de ejemplo | Decisión del usuario (recomendación): sin escenario nuevo; los siniestros de ejemplo no tienen modalidad y `DatabaseSeederTest` no cambia |
| Frontend | El editor del reparto pierde la etiqueta de `deductible_covered` y muestra un aviso con el deducible no cobrado, en el borrador y en el confirmado, con el importe de la API |
| Pruebas | `DeductibleNetModeTest`: el caso del deducible mayor que lo cubierto pasa a confirmarse (sin porciones de la aseguradora, `deductible_uncollected`, cancelación bloqueada, cambio del IVA sin otra versión, factura de la aseguradora rechazada, la del dueño de 3.047.000 y la entrega); el del interruptor apagado lo fija explícitamente |

### 2.90 T2, paso 1: base central, paquete y modelo `Tenant` (2026-11-25)

Primer paso de T2 según el plan de la sección 2.80. Solo infraestructura: **la API no cambia**, porque todavía nada activa
un taller. La identificación por host es el paso 2.

| Tema | Decisión |
|---|---|
| Medición | Se repitió el paso 0 con 4.2.3 y diez talleres ([`spike-t0/informe.md`](../spike-t0/informe.md), sección 9). Con la conexión persistente a la central, todos los endpoints quedan dentro de los umbrales de G (+11,2 ms de p95 como máximo, umbral 15 ms) |
| Paquete | Decisión del usuario: se aprueba `spatie/laravel-multitenancy` **4.2.3**, fija (TD2). La 4.3.1 (2026-11-04) ya cumple dos semanas, pero sus cambios son de colas: se revisa al llegar a T3 |
| Conexiones | `central` (registro de talleres, `DB_CENTRAL_DATABASE`, por defecto `synctaller_central`, con `PDO::ATTR_PERSISTENT`) y `tenant` (plantilla sin base, sin conexión persistente), como en §3.3. Usan el motor de `DB_CONNECTION`: MySQL en desarrollo, SQLite en los tests. `DB_CONNECTION` no cambia hasta el paso 2 |
| Tabla `tenants` | En `database/migrations/central`, que el `migrate` de siempre no ve: `id`, `name`, `domain` y `database` únicos, `status` (`App\Enums\TenantStatus`: `active` y `suspended`, TD5) y `maintenance` (booleano). Se migra con `php artisan migrate --database=central --path=database/migrations/central` |
| Modelo | `App\Models\Tenant` hereda del modelo del paquete, que fija la conexión central. Sin escenario en los seeders de ejemplo: no es un dato del taller |
| Tareas al activar | La base del paquete, más dos propias en `app/Multitenancy/Tasks`: `PrefixTenantCacheTask` (prefijo `tenant:{id}:`) y `SwitchTenantPermissionsCacheTask` (`initializeCache()`, sin `forgetCachedPermissions()`, 2.80) |
| Prefijo de cache | Hallazgo de la medición: el del paquete (`tenant_id_{id}`, sin separador) hace que la clave `0abc` del taller 1 y la `abc` del taller 10 coincidan. Se usa el de §3.6, con separador |
| Colas | `queues_are_tenant_aware_by_default = false` hasta T3. El paquete revisa todos los jobs: uno que considere del taller y no traiga taller lo borra y falla, y en este paso ningún job lleva taller |
| Base local | Decisión del usuario: se crea `synctaller_central` en el MySQL de desarrollo y se migra. `synctaller` no se toca |
| Pruebas | `tests/Feature/Tenancy` (10 tests), con la central en otro archivo SQLite (`database/testing-central.sqlite`) migrado una vez por ejecución (`Tests\Concerns\UsesCentralDatabase`). Se comprobó que cada test de las tareas falla al quitar su protección: la tarea de base, el prefijo propio, la tarea de permisos y las colas apagadas |

### 2.91 Se quita la guía de Postman (2026-11-25)

| Tema | Decisión |
|---|---|
| Guía | Decisión del usuario: se borra `docs/testing/guia-postman.md` con la carpeta `docs/testing/` y todas sus referencias (los `CLAUDE.md`, el `README.md`, el índice de `docs/`, `development/testing.md`, `modules/README.md` y la especificación). Ya figuraba como no ejecutada contra la API actual |
| Contrato | Sin documento manual: el contrato es el código (`routes/api.php`, Form Requests y Resources), con el formato común de `backend/CLAUDE.md`, y lo cubren los tests de cada módulo |
| Por cambio | "Cambiar la API" deja de pedir actualizar la guía en `docs/README.md` y en el `CLAUDE.md` de la raíz |

### 2.92 Avisos de seguridad en dependencias (deuda #19, 2026-11-25)

| Tema | Decisión |
|---|---|
| Alcance | Decisión del usuario: resolver la deuda #19 antes del paso 2 de T2. `composer audit` informaba 8 avisos en 5 paquetes; tres tocan el host o los proxies de confianza, que importan para identificar el taller por el host |
| Versiones | Las primeras que corrigen todos los avisos y tienen dos semanas publicadas: `laravel/framework` 13.42.0 (2026-11-10; la corrección empieza en 13.40.0) y `symfony/http-foundation`, `http-kernel`, `routing` y `yaml` 7.4.23 (2026-11-04). Las 7.4.24 y Laravel 13.43 y 13.44 son de la última semana y quedan fuera |
| Actualización mínima | Solo esos cinco paquetes (`composer update` con la versión de cada uno, sin `--with-dependencies`): con dependencias, Composer subía otros 25 paquetes a versiones de esta semana. El `composer.json` no cambia: las restricciones ya admitían esas versiones |
| Proxies | La aplicación no configura proxies de confianza (`bootstrap/app.php`), así que el aviso de `private_ranges` no la alcanzaba como está. Se corrige igual: el paso 2 puede necesitarlos detrás de un balanceador |
| Revisión | Entre 13.39 y 13.42 no hay cambios que rompan lo que usa la aplicación: correcciones de base de datos, colas y colecciones, y `preventLazyLoading` con `cursor()`, que la aplicación no usa |

### 2.93 T2, paso 2: identificación del taller por el host (2026-11-25)

Segundo paso de T2 según el plan de la sección 2.80. Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md),
6.34. La API no cambia mientras haya un solo taller: lo nuevo son los cuatro `error_code` `TENANT_*`.

| Tema | Decisión |
|---|---|
| Middleware | `IdentifyTenant`, en el grupo `api` antes de `ApplyWorkshopSettings` y de `auth:sanctum`, con el orden de §3.2. No olvida el taller al terminar: cada request es un proceso. `getHost()` ya da el host en minúsculas y sin puerto |
| Errores | `TenantException` (hereda de `AppException`) con los cuatro códigos de §3.2. Mensajes aprobados por el usuario. Si la base no responde, el taller no queda activo y el detalle va al log |
| Conexión por defecto | Decisión del usuario: `tenant` **solo en HTTP**. Una tarea nueva, `SwitchDefaultConnectionTask`, la cambia al activar un taller y devuelve la anterior al olvidarlo. La consola, el worker y el scheduler siguen con `DB_CONNECTION` hasta T3, donde se completa el fallo cerrado de TD10. Motivo: la cola, la cache y los recordatorios todavía no saben de talleres, y con `tenant` sin base dejarían de funcionar |
| Colas | Hallazgo: con los listeners de colas del paquete, un job que no es "del taller" olvida el taller y al terminar lo vuelve a activar, lo que reconecta `tenant`. Un job `sync` (en los tests, o un `dispatchSync`) dejaba el resto del request fuera de su conexión. Hasta T3 no se registran (`DeferQueueTenancyAction`): los jobs corren donde se procesan, como antes de la línea T. T3 los hace llevar su taller (§3.5) |
| Registro | Decisión del usuario: comando `tenants:register {name} {domain} {database}`, que solo crea la fila (el alta completa es T5). El dominio es solo el host, en minúsculas |
| Desarrollo | Decisión del usuario: el taller #1 se registra con dominio `127.0.0.1` y base `synctaller`, el host al que ya llama el frontend (`VITE_API_URL`). Sin cambios en el frontend ni en el `.env` |
| Frontend | Decisión del usuario: sin cambios. El SPA ya muestra el `message` de la API; una pantalla propia para los `TENANT_*` se decide con varios talleres reales. CORS para varios orígenes y `VITE_API_URL` por taller quedan para T4 |
| Proxies | Sin proxies de confianza: se usa el `Host` del request. Se configuran con la infraestructura (T4) |
| Tests | Todo test corre en un taller de prueba (`TestCase`, id 1000) registrado con el host de `APP_URL` y la base de siempre, activado antes de RefreshDatabase para que su transacción quede en `tenant`. La central se migra una vez por ejecución para toda la suite (reemplaza `UsesCentralDatabase`). Los tests con varios talleres usan bases extra (`UsesTenantDatabases`, `database/testing-tenant-{nombre}.sqlite`) y la transacción en la conexión fija `sqlite`, porque cambiar de taller reconecta `tenant`. `tests/Feature/Tenancy` pasa de 10 a 25 tests; cada uno se comprobó contra su protección quitada |

### 2.94 Suspensión y baja de un taller: se mantiene P9 (2026-11-26)

Decisión comercial que quedó pendiente con las respuestas de la contadora a V14 y G10 (2.83): conservar 5 años, garantizar
acceso, exportación e integridad antes de borrar, y sugerencia de suspender por etapas (aviso, gracia, solo lectura). Se
suma al plan de T5; no hay código antes. Detalle en la [especificación](../especificacion-aseguradoras.html), §3.9 y §10.

| Tema | Decisión del usuario |
|---|---|
| Suspensión | Se mantiene el **bloqueo total y reversible** de P9: todas las rutas del taller, incluidos el login y el branding, responden `403 TENANT_SUSPENDED`. Sin etapas de aviso, gracia ni solo lectura, que salen de las alternativas futuras |
| Acceso | Un taller suspendido o dado de baja no ingresa |
| Motivo | La suspensión es la misma cualquiera sea el motivo |
| Baja | Se entrega la exportación completa y la plataforma **conserva la base 5 años** antes de borrarla; la fila de `tenants` queda `cancelled` (TD5) |
| Exportación | A pedido, mientras el taller está suspendido y durante los 5 años posteriores a la baja. La hace la plataforma con un procedimiento operativo (runbook de T5), no es una funcionalidad de la app. Así se cumple lo que pide V14: acceso, exportación e integridad antes de borrar |
| Pendiente | La parte legal de V14 (datos personales durante la suspensión), con un asesor legal |

### 2.95 T2, paso 3: preparar el taller (2026-11-26)

Tercer y último paso de T2 según el plan de la sección 2.80. Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md),
6.32 y 6.34. La API no cambia: ninguna ruta, permiso ni `error_code` nuevo.

| Tema | Decisión |
|---|---|
| Punto de entrada | `App\Multitenancy\TenantRuntime::ensureReady()`, la fase "preparar" de §3.3: con un taller activo comprueba que su base responde y aplica `system_settings` (T1). Cambiar de taller sigue sin E/S |
| Dónde corre | Decisión del usuario: en los **cuatro** caminos desde ahora, no solo en HTTP: `IdentifyTenant` (después de `makeCurrent()`), `Queue::before`, el filtro `when()` del scheduler y el comando `notifications:send-reminders`. Eran cuatro, no tres como decía 2.80. Sin taller activo (consola y worker hasta T3) aplica la configuración sobre la conexión por defecto, como antes, y un error sigue su curso. En T3 el middleware del job y el recorrido de talleres del scheduler usan el mismo punto de entrada |
| `ApplyWorkshopSettings` | Se borra: lo reemplaza `ensureReady()` dentro de `IdentifyTenant`. La comprobación de la base sale de `IdentifyTenant` y pasa a `TenantRuntime`. `remindersDue()` ya no aplica la configuración: la espera aplicada |
| Configuración ilegible | Decisión del usuario: si la base conecta pero no se puede leer `system_settings` (por ejemplo, sin migrar), responde `503 TENANT_UNAVAILABLE` en vez de un 500, con el detalle en el log (`No se pudo aplicar la configuración del taller.`) y el taller sin activar. Mismo código y mensaje que una base que no responde |
| Entre talleres | `WorkshopSettingService` sigue siendo un singleton: los valores originales que recuerda son los del `.env`, así que valen para cualquier taller y un valor guardado en uno no llega a otro que no lo tiene |
| Tests | `tests/Feature/Tenancy` pasa de 25 a 30 (`TenantRuntimeTest`); cada uno se comprobó contra su protección quitada |

### 2.96 T3, paso 4: colas por taller (2026-11-26)

Primer paso de T3 según el plan de la sección 2.80 (pasos 4 y 5 de la revisión del spike). Diseño aprobado por el usuario
el 2026-11-26, en dos entregas: este paso (la infraestructura en la central y los jobs con su taller) y el paso 5 (el
scheduler por taller y el fallo cerrado de TD10). Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.17,
6.32 y 6.34. La API no cambia: ninguna ruta, permiso, `error_code` ni código HTTP nuevo.

| Tema | Decisión |
|---|---|
| Hallazgo: la cola en la base del taller | La cola, la cache, `failed_jobs` y `job_batches` no nombraban conexión: en HTTP iban a la conexión por defecto, `tenant`, y el worker leía `DB_CONNECTION`. Con un solo taller son la misma base; con dos, los avisos del segundo no se enviarían nunca. §3.1 ya las ubicaba en la central |
| Infraestructura en la central | Migración nueva en `database/migrations/central` con `jobs`, `job_batches`, `failed_jobs`, `cache` y `cache_locks`, y las cinco conexiones fijadas en `central` (`config/queue.php` y `config/cache.php`), sin variable de entorno. Las tablas iguales de las bases de taller no se borran (§3.8: primero se agrega, después se quita): quedan sin uso hasta `tenants:migrate` (T5), deuda técnica #20 |
| Jobs con su taller | Vuelve la acción de colas del paquete (se borra `DeferQueueTenancyAction`) y `queues_are_tenant_aware_by_default = true`: todo job lleva el id del taller activo al despacharlo (en el Context de Laravel) y el worker lo activa antes de correrlo |
| Despacho sin taller | `RequireTenantForQueuedJobs` (`Queue::createPayloadUsing`): despachar un job del taller sin taller activo lanza una excepción en ese momento. Sin esto, el paquete lo borraría al procesarlo sin dejarlo en `failed_jobs` (hallazgo de 2.80). Un job que implementa `NotTenantAware` se despacha igual |
| Middleware del job | `PrepareTenant` reemplaza a `Queue::before`, que corría en `JobProcessing` y no podía liberar el job: con el taller en mantenimiento, o si `ensureReady()` falla, libera el job con espera (30 s, 2, 5 y 15 min, §3.11). Liberarlo no cuenta como excepción |
| Taller suspendido | Decisión del usuario: se descarta ya en T3, como dice §3.5, y no en T5. El middleware borra el job; si es un aviso (`DiscardsForSuspendedTenant`), antes lo deja `failed` con el motivo `Taller suspendido.`, sin sumar intento, y se puede reenviar después de reactivar el taller. Si el taller además está en mantenimiento, primero espera |
| `SendClientNotification` | Trait `RunsInTenant`: el middleware, `retryUntil` de 6 horas desde el despacho (TD7) y `maxExceptions = 1` en vez de `$tries = 1`. Una excepción dentro de `handle` sigue sin reintentarse, porque podría duplicar el email |
| Vence `retryUntil` con la base caída | Decisión del usuario: se acepta. El job queda en `failed_jobs` de la central y el aviso, `pending` en el taller (no se puede escribir en una base caída): no se puede reenviar (`CLIENT_NOTIFICATION_PENDING`) hasta que la plataforma corra `queue:retry`. Va en el runbook ([despliegue](../development/deployment.md)); la regla de reenvío no cambia |
| Permisos en memoria | Hallazgo: `initializeCache()` de Spatie no vacía los permisos ya cargados. En un worker, el taller B usaba la colección del A. `SwitchTenantPermissionsCacheTask` llama también a `clearPermissionsCollection()`, que no consulta la base ni la cache |
| Configuración al olvidar el taller | Hallazgo: `WorkshopSettingService` no la restauraba, y un worker se quedaba con el remitente y los datos de empresa del último taller. Tarea nueva `RestoreWorkshopSettingsTask` (`restore()`, sin consultas) |
| Comando de recordatorios | Ajuste de alcance necesario: con el despacho sin taller rechazado, el comando dejaría de funcionar en la consola hasta el paso 5. Se adelanta del paso 5: `notifications:send-reminders` recorre los talleres activos y sin mantenimiento (o los de `--tenant`), activa y prepara cada uno, y un taller que falla queda en el log y no frena a los demás (código 1). Al terminar deja el taller que estaba activo. El filtro de hora del scheduler sigue sin taller hasta el paso 5 |
| Paquete | Decisión del usuario: sigue 4.2.3. La 4.3.1 (2026-11-04) solo evita deserializar jobs para decidir si son del taller. La 4.3.2 (2026-11-26) mantiene el prefijo de cache en los stores ya resueltos (como el `RateLimiter`), que nuestra tarea de prefijo tampoco actualiza: se revisa desde el 2026-12-10, antes del límite de intentos del login (2.80) |
| Desarrollo | Antes de usar esta versión: vaciar la cola vieja de `synctaller` con el código anterior y migrar la central. El historial de `failed_jobs` queda en `synctaller` ([setup](../development/setup.md)) |
| Tests | `tests/Feature/Tenancy` pasa de 30 a 40: `TenantQueueTest` (7, con la cola `database` de la central y un worker en el mismo proceso), `ReminderCommandTest` (3) y uno nuevo en `SwitchTenantTest` (los permisos en memoria, en lugar del job sin taller, que ya no aplica). Cada uno se comprobó contra su protección quitada. Los tests que simulaban `Queue::before` con un `JobProcessing` falso pasan por el middleware real |

### 2.97 T3, paso 5: scheduler por taller y fallo cerrado (2026-11-26)

Segundo y último paso de T3, con el diseño aprobado el 2026-11-26 (2.96). Con este paso T3 queda completo. Reglas
vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.17, 6.32 y 6.34. La API no cambia: ninguna ruta, permiso,
`error_code` ni código HTTP nuevo. Sin migraciones ni cambios en el frontend.

| Tema | Decisión |
|---|---|
| Un job por taller | `SendTenantReminders` (`RunsInTenant`) corre `NotificationReminderService::run()` en su taller y deja los conteos en el log. `notifications:send-reminders` ya no registra los recordatorios en su proceso: despacha un job por taller activo y sin mantenimiento, o por los de `--tenant` (§3.5) |
| Hora de cada taller (TD6) | El scheduler corre `notifications:send-reminders --due` cada minuto, sin filtro `when()`. Con `--due`, el comando activa y prepara cada taller, lee su `reminders_at` y despacha si ya llegó la hora |
| Hora perdida | Decisión del usuario: **se recupera el día**. No hace falta el minuto exacto (`remindersTimeReached()`, antes `remindersDue()`): basta con que ya haya pasado la hora y que hoy no se haya despachado. Un taller que a su hora estaba en mantenimiento, suspendido o con la base caída, o un servidor apagado, recibe los recordatorios en cuanto se puede ese día. Si se adelanta la hora a una que ya pasó, salen en ese momento. Las claves de deduplicación evitan avisos repetidos |
| Día despachado | Se guarda en la cache de la central (`notifications:reminders-dispatched:{id}`, 48 horas), después de despachar. Un taller que no se pudo preparar no se marca y se vuelve a intentar al minuto siguiente: deja un error en el log en cada minuto mientras siga caído. Si se borra la cache, puede volver a despachar el mismo día, y la deduplicación evita los avisos repetidos. Una vez despachado, los reintentos y la suspensión son del job (2.96) |
| Comando manual | Decisión del usuario: sin `--due`, despacha a todos o a los de `--tenant` sin mirar la hora y sin marcar el día. Muestra a qué talleres despachó; los conteos van al log del job, que procesa `queue:work` |
| Fallo cerrado (TD10) | Decisión del usuario: **en el código**, sin cambiar el `.env` de desarrollo. `TenantRuntime::ensureReady()` sin taller activo lanza una excepción en vez de aplicar la configuración de `DB_CONNECTION`. Era el último camino que consultaba la conexión por defecto sin taller (el filtro del scheduler). En desarrollo, `migrate`, `db:seed` y `tinker` siguen con su base. La alternativa (conexión por defecto `tenant` también en la consola) se descartó porque exigía `--database` en esos comandos hasta `tenants:migrate` (T5) |
| Sesiones | Revisado: `SESSION_DRIVER=database` usa la conexión por defecto, pero `routes/web.php` no tiene rutas y la API no abre sesión (Bearer). Sin cambios |
| Tests | `tests/Feature/Tenancy` pasa de 40 a 45: `ReminderCommandTest` pasa de 3 a 8 (job por taller en su taller, hora de cada taller una vez por día, recuperación después del mantenimiento, taller caído sin marcar, `--tenant`, taller activo al terminar, entrada del scheduler y fallo cerrado con una conexión por defecto sin base). Usa la cola y la cache `database` de la central: la `array` de los tests se vacía al cambiar de taller. En `TenantRuntimeTest`, el test sin taller pasa a comprobar el fallo cerrado. Cada protección nueva se comprobó quitándola. En `WorkshopSettingsTest` y `NotificationReminderTest` cambian el test del filtro del scheduler y el de la salida del comando |

### 2.98 T4: storage por taller (2026-11-26)

Diseño aprobado por el usuario el 2026-11-26, según §3.7 de la [especificación](../especificacion-aseguradoras.html) y la
fila T4 de §9, e implementado el mismo día. Reglas vigentes en [reglas de negocio](../reglas-de-negocio.md), 6.24 y 6.34.
Sin migraciones ni cambios en el frontend; ninguna ruta, permiso, `error_code` ni código HTTP nuevo. Hoy todos los talleres comparten las raíces de los discos: `public`
(`storage/app/public`: branding, fotos de recepción y de diagnóstico, firmas de entrega) y `claim_documents`
(`storage/app/claim-documents`). Las rutas guardadas en la base son relativas al disco, así que T4 cambia solo la raíz.

| Tema | Decisión |
|---|---|
| Raíces | Público: `storage/app/public/tenants/{id}/…`. Privado: `storage/app/tenants/{id}/private/…`. Las rutas guardadas (`branding/…`, `receptions/…`, `diagnostic-items/{id}/…`, `deliveries/…`, `claims/{id}/…`) no cambian |
| Mecanismo | Los discos `public` y privado toman la raíz del taller activo al resolverse: driver `tenant_local` (`App\Multitenancy\TenantStorage`, registrado en `AppServiceProvider`), un disco `local` cuyo `{tenant}` en `root` y `tenant_url` se reemplaza por el id del taller. Una tarea nueva, `SwitchTenantStorageTask` (después de la de cache), olvida las instancias de esos discos al activar y al olvidar un taller, como las tareas de cache y permisos (2.96). Vale igual en el request, el job y el scheduler. Las carpetas se crean al primer uso (`lazy_root_creation`) |
| Fallo cerrado | Sin taller activo, usar uno de esos discos lanza una excepción, como `ensureReady()` (TD10, 2.97). No hay raíz por defecto donde un archivo quede mezclado |
| Disco privado | `claim_documents` pasa a llamarse `tenant_private` (nombre propuesto en §3.7), sin `serve` y sin URL firmada ni `temporaryUrl`. `ClaimDocumentService::pathFor` no cambia. P8 usará este disco |
| Disco `local` | Sin cambios (deuda #14, decisión E) |
| URL pública | Decisión del usuario: se arma con el `domain` del taller, con el esquema y el puerto de `APP_URL` (por ejemplo `http://127.0.0.1:8000/storage/tenants/1/…`), para que un taller nunca reciba enlaces con el host de otro. `ReceptionPhotoResource` deja `asset('storage/'.…)` y usa `Storage::disk('public')->url()`, como el resto |
| Contrato | Sin cambios de forma: `url`, `logo_url`, `logo_black_url`, `favicon_url` y `signature_url` siguen iguales; cambia su valor. Sin cambios en el frontend. Los PDF incrustan las imágenes en base64 |
| Archivos existentes | Decisión del usuario: el proyecto está en desarrollo y los archivos y datos actuales no se conservan. T4 no incluye un comando para moverlos ni compatibilidad con las raíces viejas. Si T6 necesita moverlos, se decide ahí. A pedido del usuario se borraron las carpetas viejas de desarrollo, ninguna en git: `storage/app/claim-documents` (vacía) y `storage/app/public/diagnostic-items` (38 archivos) |
| Validación | Decisión del usuario: T4 queda validada con los tests y la prueba con `php artisan serve`, **sin revisión en navegador** (las pantallas no cambian; cambian los valores de las URL) |
| Acceso entre talleres | El criterio de §9 (archivos de A no accesibles por rutas de B) lo cumple la API: cada taller resuelve sus discos con su raíz. Los archivos públicos los sirve el servidor web por el enlace `public/storage`, sin pasar por la API: cerrarlos por host es una regla de Nginx, que queda como requisito del [despliegue](../development/deployment.md). Hasta P8 son públicos por diseño (deuda #15) |
| Alcance | Decisión del usuario: CORS con varios orígenes, `VITE_API_URL` por taller y proxies de confianza, que 2.93 dejaba para "T4", salen de T4. Dependen del hosting (V9) y quedan como deuda #22 |
| Tests | Los que usan `Storage::fake` no cambian, salvo el nombre del disco privado (`ClaimDocumentTest`, `ClaimTestCase`, `DatabaseSeederTest`). `tests/Feature/Tenancy` pasa de 45 a 49 tests (51 corridas en PHPUnit, por el data provider) con `TenantStorageTest` (discos reales sobre una carpeta temporal): lo guardado con A no se ve desde B en los dos discos, fallo cerrado sin taller en los dos (también con el disco ya resuelto), URL con el host del taller y URL de las fotos de recepción. Cada test se comprobó quitándole su protección: la tarea, el fallo cerrado, el host del taller y el `url()` del disco en `ReceptionPhotoResource` |

### 2.99 Skills de proceso y protecciones de Claude Code (2026-11-26)

A pedido del usuario, después de analizar las skills del proyecto. No cambia código de la aplicación, rutas, permisos ni contratos. Todo
vive en `.claude/`.

| Tema | Decisión |
|---|---|
| Flujo de una entrega | Implementar → tests puntuales → `revisar-navegador` → `validar-docs` → `cerrar-entrega` → commit y PR → CI → merge → `registrar-ci` |
| `registrar-ci` (nueva) | Registra en el roadmap la corrida de la CI sobre `main` después del merge, con las cifras sacadas del log por `gh` (script `ci-resumen.mjs`), y destraba las próximas tareas. Se mantiene un PR aparte por corrida, como hasta ahora: la fila habla de `main`, no de la rama. Se ejecuta solo a pedido del usuario |
| `revisar-navegador` (nueva) | Reemplaza el procedimiento que se rearmaba en cada sesión. Usa un entorno aislado en el scratchpad: base SQLite del taller y central, taller `127.0.0.1` registrado, siembra por `tenants:artisan` (deuda #23) y `LARAVEL_STORAGE_PATH` propio, así que los archivos subidos y los logs no tocan `backend/storage`. La API se sirve con `serve --no-reload`, para que reciba las variables del proceso. Chrome headless por DevTools Protocol, sin dependencias, con un usuario por rol. Detecta páginas 403 y 404, respuestas de la API con status 400 o más, excepciones y errores de consola. `frontend/CLAUDE.md` pide una base MySQL temporal: la skill usa SQLite y avisa cuando el cambio depende del motor. Queda para decisión del usuario si se ajusta esa regla |
| `cerrar-entrega` | Hace `git fetch` antes del diff, deduce la entrega de la rama y acepta los resultados de los tests como argumento. Define un formato breve para la fila local de **Última verificación** (el detalle va al historial) y termina proponiendo el mensaje de commit |
| `.claude/settings.json` (nuevo) | Reglas `deny` para `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `config:cache` y `npm install`, en Bash y PowerShell. Un hook `PreToolUse` (`.claude/hooks/proteger-main.mjs`) bloquea `git commit` y `git push` en `main`, y un push hacia `main` desde otra rama. Las reglas de `CLAUDE.md` pasan a ser obligatorias para Claude Code. Para el usuario no cambia nada |

### 2.100 Deuda #23: la siembra activa su taller (2026-11-26)

Decisión del usuario: la siembra activa el taller por su cuenta, en lugar de corregir solo la guía. Regla vigente en
[reglas de negocio](../reglas-de-negocio.md), 6.34 (siembra de ejemplo). Sin migraciones ni cambios en el frontend;
ninguna ruta, permiso, `error_code` ni código HTTP nuevo.

| Tema | Decisión |
|---|---|
| Problema | Desde T4 (2.98) los seeders escriben en los discos del taller, que sin taller activo fallan (fallo cerrado). La guía sembraba con `migrate --seed` antes de migrar la central y registrar el taller, así que una instalación nueva quedaba sin datos de ejemplo. Los tests no lo veían porque corren dentro de un taller |
| Mecanismo | `Database\Seeders\SeedingTenant::run()` envuelve la siembra de `DatabaseSeeder`. Con un taller activo (tests, `tenants:artisan`) usa ese y no lo olvida. Sin taller activo, busca en la central el taller cuya `database` es la de la conexión por defecto (en SQLite, la ruta resuelta: relativa o absoluta da igual) y lo activa con `makeCurrent()`, como `tenants:artisan`, sin `ensureReady()`. Al terminar lo olvida, también si la siembra falla |
| Sin taller | Sin taller registrado para esa base, o sin la central migrada, no siembra nada. El mensaje indica el comando que falta (`tenants:register` o el `migrate` de la central). En producción `DB_DATABASE` va vacío, así que la siembra de ejemplo no encuentra taller y no corre |
| Guía | `README.md` y `development/setup.md`: primero la central y el registro del taller, después `migrate --seed`. Ese orden evita además otro fallo: desde T3, paso 4 (2.96), las migraciones limpian la cache de permisos, que vive en la central, y sin la central migrada `migrate` falla antes de sembrar |
| Alcance | Solo `DatabaseSeeder`. `db:seed --class=RolesAndPermissionsSeeder` (actualizar un equipo) sigue sin taller, como antes: no usa los discos. La skill `revisar-navegador` siembra ahora con `migrate --seed` |
| Tests | `tests/Feature/Seeders/SeedingTenantTest` (5): sin taller activo siembra en el de la base y lo olvida después, lo encuentra con la ruta escrita de otra forma, lo olvida también si la siembra falla, sin taller para la base no siembra nada, y con taller activo siembra en ese y lo mantiene. Cada test se comprobó quitándole su protección |

### 2.101 T5: plan en pasos y paso 1, `tenants:migrate` (2026-11-26)

Diseño aprobado por el usuario el 2026-11-26, según §3.8 y §3.9 de la [especificación](../especificacion-aseguradoras.html)
y la fila T5 de §9. Es el paso 6 del plan de 2.80. Regla vigente en [reglas de negocio](../reglas-de-negocio.md), 6.34.
Agrega una migración central y no cambia el frontend. No agrega rutas, permisos, `error_code` ni códigos HTTP.

**Plan de T5** (decisiones del usuario):

| Paso | Contenido |
|---|---|
| 1 | `tenants:migrate` (esta sección) |
| 2 | Quitar las tablas de cola y cache sin uso de las bases de taller (deuda #20), en un despliegue posterior al paso 1 (regla de §3.8: primero agregar, después quitar) |
| 3 | Alta completa: `provisioning` → base → migraciones → roles → administrador inicial → `system_settings` → `active`. El administrador inicial se crea con nombre y email, y una contraseña aleatoria que se muestra una sola vez en la consola. No depende del correo |
| 4 | Suspender, reactivar y dar de baja (`cancelled` → `404 TENANT_NOT_FOUND`, TD11), más la pantalla de taller suspendido del frontend (§7.3, P9) |
| 5 | Restauración con un comando (cambio verificado de `tenants.database`) y fecha de baja guardada en la central. El backup (`mysqldump` y storage), el borrado de la base a los 5 años y la exportación asistida quedan como runbook, porque dependen del hosting (V9) |

**Paso 1:**

| Tema | Decisión |
|---|---|
| Alcance | Decisión del usuario: sin `--tenant`, migra los talleres `active` y `suspended`, con o sin mantenimiento. Un suspendido conserva el esquema al día para que funcione al reactivarlo y para la exportación asistida. Los estados de los pasos 3 y 4 (`provisioning` y `cancelled`) no se migran |
| Bloqueo | Decisión del usuario: el taller se bloquea solo si la migración falla (§3.8). Mientras migra sigue atendiendo, porque las migraciones son compatibles hacia atrás: se migra y después se publica el código |
| Mecanismo | `App\Console\Commands\MigrateTenants`. Por taller: toma un lock y activa el taller con `makeCurrent()`, sin `ensureReady()`, porque con el esquema viejo leer la configuración podría fallar. Después aplica las migraciones pendientes de `database/migrations` en la conexión `tenant` y olvida el taller |
| `Migrator` y no `migrate` | El comando `migrate --force` crea la base si no existe (en SQLite el archivo y en MySQL un `CREATE DATABASE`). Un taller que apuntara a una base caída o mal escrita recibiría una vacía con todas las migraciones. El comando usa el `Migrator` y lee primero las migraciones ya aplicadas, lo que conecta con la base: una base que falta falla ahí y no se crea |
| Lock | `tenants:migrate:{id}` en la cache de la central, por una hora. Se toma sin taller activo, porque con uno la clave llevaría su prefijo (`tenant:{id}:`) y otra corrida no la vería. El comando olvida el taller activo al empezar y después de cada taller, y al terminar deja el que estaba. Un taller con el lock tomado se informa como en curso, no se toca y el comando termina con código 1 |
| Registro | Tabla central nueva `tenant_migration_runs` (migración `2026_11_26_000002`, modelo `TenantMigrationRun`, enum `TenantMigrationRunStatus`): una fila por taller y corrida con el estado (`running`, `succeeded`, `failed`), las migraciones aplicadas (también las que llegaron a aplicarse antes de una falla), el error, si la corrida puso el mantenimiento y las fechas. El detalle de la falla queda ahí y en el log (`Falló la migración del taller.`, con su `tenant_id`), nunca en una respuesta de la API |
| Falla | El taller pasa a `maintenance = true` (`503 TENANT_MAINTENANCE`), los demás siguen y el comando termina con código 1 |
| `--failed` | Repite los talleres cuya última corrida terminó `failed`. Una corrida que quedó `running` porque el proceso se cortó no cuenta: la corrida normal siguiente la cubre. Cuando una corrida sale bien después de una fallida que puso el mantenimiento, lo quita. Un mantenimiento puesto a mano (el taller ya lo tenía al fallar) no se quita nunca |
| Tests | `tests/Feature/Tenancy/TenantMigrateCommandTest` (8), con bases SQLite vacías que el test crea y borra. Comprueba que migra activos y suspendidos y registra cada corrida, que el taller que falla queda en mantenimiento sin crear su base y los demás se migran, que una falla a mitad registra lo aplicado, que `--failed` repite solo los fallidos y les quita el mantenimiento, que no quita un mantenimiento manual, que el lock se respeta con un taller activo al empezar y después de migrar otro, que un id desconocido se informa y que se conserva el taller activo. Cada protección se comprobó quitándola: el mantenimiento al fallar, quitarlo solo si lo puso el comando, quitarlo al salir bien, el lock, olvidar el taller antes del lock y después de cada taller, el filtro de `--failed`, los suspendidos y el `migrate --force` sin la lectura previa. Una comprobación previa con `getPdo()` resultó redundante y se quitó |
| MySQL | Migración central aplicada en `synctaller_central`. Con el taller #1, `tenants:migrate` responde "sin migraciones pendientes" (código 0), `--failed` "No hay talleres con la última corrida fallida." y `--tenant=99` código 1 |

### 2.102 T5, paso 2: sin tablas de cola y cache en las bases de taller (2026-11-26)

Diseño aprobado por el usuario el 2026-11-26. Resuelve la deuda #20. Es el paso 2 del plan de 2.101 y cumple la regla de
§3.8 de la [especificación](../especificacion-aseguradoras.html): las tablas se agregaron en la central en T3, paso 4
(2.96), y se quitan del taller ahora, con `tenants:migrate` (paso 1) ya en `main`. Agrega una migración de taller y no
cambia el frontend, rutas, permisos, `error_code` ni códigos HTTP. Regla vigente en [reglas de negocio](../reglas-de-negocio.md), 6.34.

| Tema | Decisión |
|---|---|
| Migración | Migración de taller nueva `0001_01_01_000074_drop_queue_and_cache_tables`: `up()` borra `jobs`, `job_batches`, `failed_jobs`, `cache` y `cache_locks` si existen, y `down()` las vuelve a crear con el esquema de las originales |
| Migraciones originales | Decisión del usuario: `0001_01_01_000001` y `0001_01_01_000002` no se editan, porque ya están aplicadas. Un taller nuevo (paso 3) crea las cinco tablas y las borra en la misma corrida |
| Protección | Decisión del usuario: la migración lanza una excepción si la conexión es la central, por su nombre o porque apunta a la misma base (en SQLite el mismo archivo; en los demás motores, el mismo servidor, puerto y base). Evita que un `migrate` con `DB_DATABASE` mal puesto borre la cola y la cache reales. Solo lee la configuración: no llama a código de la aplicación |
| Datos | Las cinco tablas de `synctaller` estaban vacías al aplicarla (también `failed_jobs`, al contrario de lo que decía la deuda #20), así que no se perdió nada |
| Tests | `tests/Feature/Tenancy/TenantQueueTablesTest` (3): la base del taller no tiene las cinco tablas y la central sí; en una base SQLite vacía, revertir la migración las vuelve a crear y aplicarla otra vez las quita; y no corre sobre una conexión con la base central. Cada protección se comprobó quitándola: el borrado, el `down()`, la comprobación de la central y la comparación por base (no solo por nombre) |
| `SwitchTenantTest` | Guardaba la cache de sus tests en la conexión `sqlite`, la base de taller de los tests, y sin la tabla `cache` fallaba. Ahora usa la cache `database` de la central, como en producción. Sigue fallando si se quita la tarea de permisos (`SwitchTenantPermissionsCacheTask`) |
| MySQL | `tenants:migrate` con el taller #1: "1 migración aplicada" (código 0), con la corrida `succeeded` en `tenant_migration_runs`; las cinco tablas quedaron solo en `synctaller_central` y la segunda corrida no tiene pendientes. Llamada directa a `up()` sobre una copia de la conexión central: rechazada, sin borrar nada. La cache de la central sigue respondiendo |

### 2.103 T5, paso 3: alta completa de un taller (2026-11-26)

Diseño aprobado por el usuario el 2026-11-26, según §3.9 de la [especificación](../especificacion-aseguradoras.html) y el
paso 3 del plan de 2.101. Agrega dos comandos, el estado `provisioning` y una migración central; no cambia el frontend,
rutas, permisos, `error_code` ni códigos HTTP. Regla vigente en [reglas de negocio](../reglas-de-negocio.md), 6.34.

| Tema | Decisión |
|---|---|
| Comando | `App\Console\Commands\CreateTenant`: `tenants:create {name} {domain} {database}` con los datos del administrador y de la empresa como opciones, para correrlo sin preguntas. `tenants:register` no cambia: sigue siendo el camino de una instalación que ya tiene su base (§3.10, T6) |
| Secuencia | Fila en `provisioning` → crear la base → migraciones → `RolesAndPermissionsSeeder` → administrador inicial → `system_settings` → comprobar el taller con `TenantRuntime::ensureReady()` (lo mismo que hará cada request) → `active` |
| Administrador | Decisión del usuario: el alta pide los cuatro datos que exige `users` (`--admin-email`, `--admin-username`, `--admin-first-name`, `--admin-last-name`), todos obligatorios y con las reglas del alta de usuarios, sin inventar ninguno. La contraseña es aleatoria (`Str::password(20)`) y se muestra una sola vez en la consola; no se guarda ni va al log. No depende del correo. Si el administrador ya existe (un alta retomada), conserva su contraseña |
| `system_settings` | Decisión del usuario: con la tabla vacía, los PDF y los correos tomarían los datos de la empresa del `.env`, que en producción son de la plataforma. El alta pide nombre comercial, razón social y RUC (`--company-name`, `--company-legal-name`, `--company-tax-id`, obligatorios) y guarda vacíos los demás datos de la empresa (actividad, dirección, ciudad, teléfono y email). El IVA, el remitente y los recordatorios siguen saliendo del `.env` hasta que el administrador los cambie en Configuración |
| Base | Decisión del usuario: la crea el comando (`App\Multitenancy\TenantDatabase`). En MySQL, `CREATE DATABASE IF NOT EXISTS` con el charset y la collation de la conexión `tenant`, por la conexión central, cuyo usuario necesita el permiso `CREATE`; en SQLite, el archivo. El nombre solo admite `[a-z0-9_]` (hasta 64) en MySQL, porque va dentro de la sentencia, y en SQLite una ruta `.sqlite` en una carpeta que existe |
| Base en uso | El alta no se hace sobre una base que ya tiene tablas, salvo que sea la del mismo taller en alta que se retoma: así no cae sobre la de otro taller ni sobre una con datos. Una base existente y vacía se acepta |
| Migraciones | `App\Multitenancy\TenantMigrator`, extraído de `tenants:migrate` sin cambiar su comportamiento: usa el `Migrator`, que no crea la base. Crearla es el paso explícito anterior. El alta no registra en `tenant_migration_runs`: su error queda en el taller |
| Falla | Decisión del usuario: el taller queda en `provisioning` con el error en la columna central nueva `tenants.provisioning_error` (migración `2026_11_26_000003`) y en el log (`Falló el alta del taller.`, con su `tenant_id`). Se retoma corriendo `tenants:create` otra vez con el mismo dominio y la misma base: cada paso se puede repetir. Al terminar, el error se borra |
| Descarte | Decisión del usuario: `tenants:discard {id} [--force]` (`DiscardTenant`) borra la base y la fila, solo de un taller en `provisioning`, que nunca atendió ni tuvo jobs: es la única excepción a que la fila no se borra (§3.1). Pide confirmación salvo con `--force`, nunca borra la base central ni la de la conexión por defecto (en SQLite compara las rutas resueltas) y relee el estado con el lock tomado |
| En alta | `IdentifyTenant` responde `404 TENANT_NOT_FOUND` a un taller en `provisioning`, como si no existiera. `tenants:migrate` (activos y suspendidos) y el scheduler (activos) ya lo excluían |
| Lock | `tenants:provision:{dominio}` en la cache de la central, por una hora, tomado sin taller activo como en 2.101. Usa el dominio porque antes del alta el taller no tiene id; `tenants:discard` toma el mismo. Los dos comandos dejan al terminar el taller que estaba activo |
| Retomar | Solo se retoma un taller en `provisioning` con el mismo dominio y la misma base. Un taller activo o suspendido con esos datos no se retoma: responde que el dominio y la base ya están en uso. Si hay uno en alta con otro dominio o con otra base, el comando lo indica con su id |
| Mensajes | La aplicación no tiene traducciones (`APP_LOCALE=en`, sin `lang/`): los mensajes de validación en español van en el comando, como en los Form Requests |
| Tests | `tests/Feature/Tenancy/CreateTenantCommandTest` (10), `DiscardTenantCommandTest` (6) y uno nuevo en `IdentifyTenantTest`, con bases SQLite que el test borra. El alta completa se comprueba entrando con el administrador por el host del taller nuevo. Cada protección se comprobó quitándola: el 404 en alta, la base con tablas, el lock de los dos comandos, conservar la contraseña al retomar, los datos de la empresa vacíos, dejar el taller que estaba activo, retomar solo un taller en alta, el estado y la confirmación del descarte, la base central protegida y la comparación de rutas resueltas |
| MySQL | Migración central aplicada en `synctaller_central`. Con `EMAIL_DNS_CHECK=false` (los dominios `.com.py` de prueba no tienen DNS): rechazos de `synctaller` (base con tablas) y de un nombre con comillas invertidas, los dos con código 1; alta de `prueba-alta.localhost` → `synctaller_prueba_alta` (utf8mb4, `utf8mb4_unicode_ci`) con código 0; repetirla responde que el dominio y la base ya están en uso. Con `php artisan serve`, login del administrador por ese host y `GET system-settings/workshop` con los tres datos de la empresa y los demás vacíos. `tenants:discard` rechazó el taller activo; vuelto a `provisioning` a mano para limpiar, lo descartó y borró la base |
| Entorno local | Al empezar, `vendor` no tenía `spatie/laravel-multitenancy` (se instaló desde el lock con `composer install`, sin cambiar versiones), `synctaller_central` no tenía tablas y `synctaller` estaba en la migración `000071`, con `jobs` y `cache`: un estado anterior a T2, distinto del que registran 2.93 a 2.102. No se corrigió: el taller #1 de desarrollo queda sin registrar hasta que el usuario lo decida |

### 2.104 T5, paso 4: suspensión, reactivación y baja de un taller (2026-11-26)

Diseño aprobado por el usuario el 2026-11-26, según §3.9 y §7.3 de la [especificación](../especificacion-aseguradoras.html),
P9, TD11 y el paso 4 del plan de 2.101, con las decisiones de 2.94. Agrega tres comandos, el estado `cancelled`, una
migración central y la pantalla de taller suspendido del frontend. No agrega rutas, permisos, `error_code` ni códigos
HTTP: `403 TENANT_SUSPENDED` y `404 TENANT_NOT_FOUND` ya existían. Regla vigente en [reglas de negocio](../reglas-de-negocio.md), 6.17 y 6.34.

| Tema | Decisión |
|---|---|
| Comandos | `tenants:suspend {id}` (`active` → `suspended`), `tenants:reactivate {id}` (`suspended` → `active`) y `tenants:cancel {id} [--force]` (`active` o `suspended` → `cancelled`). Buscan el taller por id, como `tenants:discard`. La transición está en `App\Multitenancy\TenantStatusTransition`: relee la fila con `lockForUpdate` dentro de una transacción de la central, así dos comandos a la vez no pasan los dos desde el mismo estado. Desde otro estado, o con un id desconocido, terminan con código 1 sin cambiar nada. El cambio queda en el log (`Cambió el estado del taller.` con `tenant_id` y `status`) |
| Qué no tocan | Ni el mantenimiento, ni los tokens, ni los datos, ni la base. No activan el taller. Al reactivar, las sesiones vuelven a funcionar (§3.9) y los avisos que quedaron `failed` por la suspensión se pueden reenviar |
| Baja desde | Decisión del usuario: desde `active` o `suspended`, con confirmación salvo `--force` |
| Baja final | Decisión del usuario: ningún comando revierte una baja; `tenants:reactivate` solo acepta suspendidos. Una corrección se haría a mano, con el runbook del paso 5 |
| Fecha de baja | Decisión del usuario: se adelanta al paso 4, que es el que da de baja (el plan de 2.101 la ponía en el paso 5). Columna central `tenants.cancelled_at` (migración `2026_11_26_000004`), que completa `tenants:cancel`; desde ahí corren los 5 años de conservación de la base (2.94). El paso 5 la usa para el runbook del borrado |
| `cancelled` | `TenantStatus::CANCELLED` ("Dado de baja"). La columna `status` es `string(20)`: no necesita migración. `IdentifyTenant` lo responde como `404 TENANT_NOT_FOUND` (TD11), aun con un token vigente. `tenants:migrate` (activos y suspendidos) y el scheduler (activos) ya lo excluían |
| Jobs | Hueco cubierto: `PrepareTenant` solo descartaba los jobs de un taller `suspended`, y con la base conservada el job de un taller dado de baja se habría ejecutado (un aviso se habría enviado). Ahora descarta los dos estados; `DiscardsForSuspendedTenant::discardForSuspendedTenant()` recibe el estado y el aviso queda `failed` con `Taller suspendido.` o `Taller dado de baja.` (`ClientNotificationService::CANCELLED_TENANT_ERROR`), sin sumar intento. La interfaz conserva su nombre |
| Frontend | Decisión del usuario: solo `TENANT_SUSPENDED` (§7.3, P9); mantenimiento, base caída y taller no encontrado siguen como un error común. El interceptor de `src/services/api.ts` marca el taller suspendido (`src/lib/tenantStatus.ts`) ante ese `error_code` en cualquier request, también el branding y el login; `TenantSuspendedGate` (`src/pages/TenantSuspendedPage.tsx`) reemplaza al router por la pantalla "Taller suspendido", con un texto genérico y "Reintentar", que recarga la app. No borra el token: si el taller se reactivó, la sesión sigue. Las mutaciones no muestran toast con ese `error_code`. La pantalla va fuera del router porque el taller no atiende ninguna ruta |
| Tests | Backend: `tests/Feature/Tenancy/TenantStatusCommandsTest` (9, nuevo), uno nuevo en `IdentifyTenantTest` (dado de baja con token → 404) y en `TenantQueueTest` (aviso de un taller dado de baja → `failed` con su motivo), y un taller dado de baja en los tests existentes de `tenants:migrate` y de recordatorios. Cada protección se comprobó quitándola: el 404 de la baja, el descarte de jobs y su motivo, la relectura con la fila bloqueada, la fecha de baja, la confirmación, los estados de origen de cada comando y la exclusión en `tenants:migrate` y en el scheduler. Frontend: `src/services/api.test.ts` (3) y `src/pages/TenantSuspendedPage.test.tsx` (3), cada uno comprobado quitando la marca del interceptor o el gate |
| MySQL | Migración central aplicada en `synctaller_central`. Con una fila temporal (ya borrada) y `php artisan serve`: suspender (código 0), repetir (código 1), `GET /api/branding` con ese host → `403 TENANT_SUSPENDED`, reactivar, dar de baja con `--force` (código 0, con la fecha), `GET /api/branding` → `404 TENANT_NOT_FOUND`, reactivar el dado de baja (código 1) y un id desconocido (código 1) |
| Navegador | `/revisar-navegador` con administrador y cajero, sobre el entorno aislado (SQLite): con sesión abierta en Facturas, se suspendió el taller y la app y el login mostraron la pantalla (`403` en `/branding` y `/me`, sin excepciones ni errores de consola); al reactivarlo, "Reintentar" volvió a la app con la misma sesión y Facturas cargó normal |
| Dominio | Un taller dado de baja conserva su dominio y su base, que son únicos: no se reutilizan para otro taller mientras exista la fila |

### 2.105 T5, paso 5: restauración de un taller y runbooks (2026-11-26)

Diseño aprobado por el usuario el 2026-11-26, según §3.9 de la [especificación](../especificacion-aseguradoras.html) y el
paso 5 del plan de 2.101, con las decisiones de 2.94. La fecha de baja ya estaba hecha (paso 4, 2.104). Agrega un
comando y una migración central, y los procedimientos operativos en [`deployment.md`](../development/deployment.md). No
cambia el frontend, rutas, permisos, `error_code` ni códigos HTTP. Regla vigente en [reglas de negocio](../reglas-de-negocio.md), 6.34.
Con este paso, T5 queda completo.

| Tema | Decisión |
|---|---|
| Comando | `App\Console\Commands\RestoreTenant`: `tenants:restore {id} {database} [--force]`. Cambia `tenants.database` a una base restaurada con otro nombre, después de verificarla (§3.9: cambio atómico de puntero). No importa el backup, que depende del hosting (V9), ni toca la base anterior: se borra a mano según el runbook |
| Estados | Decisión del usuario: activo, suspendido y dado de baja (la base conservada sirve para la exportación asistida). No cambia el estado. Rechaza un taller en `provisioning` (se retoma o se descarta, 2.103) y uno con la base borrada |
| Taller activo | Decisión del usuario: se permite, con un aviso en la confirmación de que lo guardado en la base actual después del backup se pierde. Con `--force` no pregunta. El caso típico es una base caída, que ya no recibe escrituras |
| Migraciones | Decisión del usuario: si a la base restaurada le faltan migraciones (un backup viejo), se le aplican con `TenantMigrator` antes del cambio. `TenantMigrator::pending()` es nuevo. Si una falla, el puntero no cambia y el taller sigue con su base de antes |
| Base borrada | Decisión del usuario: columna central `tenants.database_deleted_at` (migración `2026_11_26_000005`), que completa a mano el runbook del borrado a los 5 años. La fila queda `cancelled`; así se distingue una base conservada de una borrada, y `tenants:restore` la rechaza |
| Verificación | Sin conectar con el taller: nombre válido, distinto del actual (en SQLite, por la ruta resuelta), ni la central ni la de la conexión por defecto, ni la de otro taller, y que exista y tenga tablas. Con la base nueva activa solo en memoria (la fila no cambia): que responda y qué migraciones le faltan. Después de confirmar: migrarla, `TenantRuntime::ensureReady()` (lo que hará cada request) y al menos un usuario `administrador` |
| Mismo taller | Si la base actual responde y tiene RUC (`company.tax_id`), la restaurada tiene que tener el mismo: así no se apunta al dump de otro taller. Si no responde (el caso típico), la confirmación muestra el nombre comercial y el RUC de la base restaurada y advierte que no se pudo comprobar |
| Confirmación | Antes de migrar, porque migrar ya modifica la base restaurada. Muestra la base actual y la nueva, el estado, la identidad, las migraciones pendientes y el aviso del taller activo |
| Cambio | Toma el lock de `tenants:migrate` (`tenants:migrate:{id}`), para no cruzarse con una migración. Relee la fila con `lockForUpdate` dentro de una transacción de la central y solo cambia si sigue con la misma base, en un estado restaurable y sin la base borrada. Después borra la cache de permisos del taller (la clave lleva su id y tenía los roles de la base anterior). La configuración del taller no se cachea. Queda en el log `Cambió la base del taller.` con `tenant_id`, `from` y `to`; una falla al conectar o preparar, `Falló la restauración del taller.` |
| Workers | No hace falta reiniciarlos: cada job relee el taller en la central y reconecta al activarlo |
| Reutilización | Las protecciones de base de `tenants:discard` (la central, la conexión por defecto y la comparación de rutas resueltas) pasan a `TenantDatabase::isProtected()` y `same()`, sin cambiar su comportamiento |
| Runbooks | En `deployment.md`: backup (`mysqldump` por taller, la central y el storage del taller), restauración, exportación asistida (un CSV por tabla, el dump y los archivos, sin `personal_access_tokens`, `password_reset_tokens`, `sessions`, `migrations` ni la columna `password` de `users`), borrado a los 5 años (base y storage, con `database_deleted_at`) y corrección manual de una baja (pendiente de 2.104) |
| Tests | `tests/Feature/Tenancy/RestoreTenantCommandTest` (14), con copias de una base de taller migrada que el test borra. Cada protección se comprobó quitándola: el RUC, la confirmación, la migración, el administrador, la cache de permisos, el lock, la misma base, la central y la conexión por defecto, la base de otro taller, la base vacía, el estado, la base borrada y el taller activo releído al terminar. La relectura con la fila bloqueada no tiene test: no se puede intercalar otro cambio dentro de un comando de consola |
| MySQL | Migración central aplicada en `synctaller_central`. Con un taller temporal (`tenants:create`, `synctaller_prueba_restore`): `mysqldump` importado en `synctaller_prueba_restore_r` sin el registro de la `000074`; rechazos de la misma base, `synctaller`, `synctaller_central`, una base que no existe y un nombre con comilla invertida (código 1); la restauración aplicó la `000074` y cambió el puntero (código 0), y con `php artisan serve`, `GET /api/branding` por el host del taller respondió 200. La fila y las dos bases se borraron al terminar |

### 2.106 Tarea P8: fotos y firmas a privado, diseño, paso 1 (firma), paso 2 (fotos) y paso 3 (comando) (2026-11-26)

Diseño aprobado por el usuario el 2026-11-26. Resuelve la deuda #15 según P8 (§3.7 de la
[especificación](../especificacion-aseguradoras.html)): después de T4, primero la firma y después las fotos. Hasta
ahora, la firma de entrega, las fotos de recepción y las de diagnóstico se guardaban en el disco `public`, con URL
permanente y sin permiso ni auditoría. Regla vigente en [reglas de negocio](../reglas-de-negocio.md), 6.12.

**Diseño (los tres pasos):**

| Tema | Decisión |
|---|---|
| Disco | Firma, fotos de recepción y fotos de diagnóstico pasan a `tenant_private` (T4, 2.98) con las mismas rutas relativas (`deliveries/…`, `receptions/…`, `diagnostic-items/{id}/…`). Sin migración de base: `path` y `signature_path` no cambian. En `public` queda solo el branding |
| Rutas | Una ruta de la API por tipo: `GET vehicle-deliveries/{delivery}/signature` (paso 1), `GET receptions/{reception}/photos/{photo}/file` y `GET diagnostic-items/{item}/photos/{photo}/file` (paso 2). Responden la imagen en línea, con `nosniff` y `Cache-Control: private, no-store`. Sin archivo, `404` con un `error_code` propio |
| Permisos | Decisión del usuario: se reusan los de la pantalla (`vehicle-deliveries.show`, `receptions-photos.index`, `diagnostic-item-photos.index`). La matriz de roles no cambia |
| Auditoría | Decisión del usuario: solo la firma. Cada vista registra `downloaded` sobre la entrega (`vehicle_delivery`). Las fotos no, porque cada miniatura de una galería dejaría un registro. El PDF de entrega (que lleva la firma) tampoco: sigue como estaba |
| Contrato | Decisión del usuario: `signature_url` y `url` conservan el nombre, pero traen la URL absoluta de la ruta de la API (con el host del taller), que exige el token: ya no sirve para un `<img src>` directo. El `path` interno que exponía `DiagnosticItemPhotoResource` se quita (paso 2) |
| Frontend | La imagen se baja con el cliente de la API (con el token) y se muestra como URL `blob:`: `getBlob` (`services/api.ts`), `usePrivateImage` (`lib/`) y `PrivateImage` (`components/ui/`). La consulta no se repite al volver a la pantalla (`staleTime: Infinity`, cada vista de la firma se audita) y `queryClient` libera la URL `blob:` cuando la caché descarta la consulta, también al cerrar sesión. Así no hace falta `setState` en un efecto |
| Existentes | Decisión del usuario: un comando idempotente (`tenants:privatize-files --tenant=`) mueve los archivos de `public` a `tenant_private` y saltea los que ya están (paso 3). Lo usará T6. En desarrollo no hay archivos que mover (2.98) |
| Pasos | 1: firma (disco, ruta, auditoría, PDF, `signature_url` y pantalla de entrega). 2: fotos (controladores, recursos, borrado en cascada del diagnóstico, seeder y `PhotoGallery`). 3: el comando. Cada paso en su rama, con backend y frontend juntos |
| Fuera de alcance | Borrar una foto sigue eliminando el archivo (en los siniestros la baja es lógica). No cambian roles ni reglas de edición |

**Paso 1 (firma):**

| Tema | Decisión |
|---|---|
| Backend | `VehicleDeliveryService::SIGNATURE_DISK` (`tenant_private`) para guardar, borrar si el alta falla y leer en el PDF (`DocumentPdfService`). `VehicleDeliveryService::signature()` audita y devuelve el contenido; sin firma o sin archivo, `404 DELIVERY_SIGNATURE_NOT_FOUND` (`VehicleDeliveryException`). `FileResponse::privateImageResponse()` arma la respuesta en línea. `VehicleDelivery::signatureUrl()` devuelve `route('vehicle-deliveries.signature')` |
| Frontend | `DeliveryDetailPage` muestra la firma con `PrivateImage` ("Cargando imagen…" mientras baja y "No se pudo cargar la imagen." si falla) |
| Tests | `VehicleDeliveryTest` (3 nuevos y uno ajustado): la firma queda solo en el disco privado, la sirve la ruta con sus headers y deja un registro con el usuario (el PDF no), el mecánico recibe `403` y sin sesión `401`, y sin firma o sin archivo `404` sin registro. `DocumentPdfTest` simula el disco privado. Cada protección se comprobó quitándola: el permiso, el disco, la auditoría y `no-store`. Frontend: `PrivateImage.test.tsx` (4): baja con el cliente de la API, no repite la descarga al volver a montar, libera la URL al vaciar la caché y avisa del error sin reintentar |
| Revisión | En el navegador, en el entorno aislado (SQLite), con una firma de prueba en la entrega `ENT-000001`: administrador, recepcionista y cajero la ven; una entrega sin firma muestra "No se cargó la firma."; el mecánico recibe la pantalla 403. Por curl: `signature_url` apunta a la API, la ruta responde `image/png` en línea con `nosniff` y `no-store`, y sin token `401`. La auditoría quedó con un `downloaded` por vista y usuario. El entorno no aísla el storage de la API (deuda #25): con autorización del usuario, la firma se copió a `backend/storage` durante la revisión y se borró al terminar |

**Paso 2 (fotos), 2026-11-26:**

| Tema | Decisión |
|---|---|
| Backend | `PhotoFileService` (`DISK = tenant_private`) para subir, reemplazar y borrar las fotos de recepción y de diagnóstico, en el borrado en cascada del ítem (`DiagnosticItemController::destroy`) y en `DiagnosticItemPhotoSeeder`. `PhotoFileService::image()` devuelve el contenido o lanza el 404 del módulo: `RECEPTION_PHOTO_FILE_NOT_FOUND` (`ReceptionException`) y `DIAGNOSTIC_ITEM_PHOTO_FILE_NOT_FOUND` (`DiagnosticException`). Las rutas `receptions.photos.file` y `diagnostic-items.photos.file` usan el permiso `index` de cada controlador y `privateImageResponse()`, sin auditoría. `ReceptionPhoto::fileUrl()` y `DiagnosticItemPhoto::fileUrl()` arman `url`; `DiagnosticItemPhotoResource` ya no trae `path` |
| Frontend | `PhotoGallery` muestra cada miniatura con `usePrivateImage` ("Cargando imagen…" y "No se pudo cargar la imagen." dentro del recuadro, sin afectar a las demás); el enlace abre la misma URL `blob:` en otra pestaña. El tipo `Photo` no cambia |
| Tests | `ReceptionTest` y `DiagnosticTest` (2 nuevos cada uno, y los de fotos en el disco privado): la foto queda solo en `tenant_private`, `url` apunta a la ruta (también en el detalle del ítem), la ruta responde la imagen con sus headers y sin registro de auditoría, la foto de otra recepción o ítem da 404, el cajero recibe `403` y sin sesión `401`, y sin archivo el 404 propio. `DatabaseSeederTest` verifica las fotos sembradas en el disco privado. Cada protección se comprobó quitándola: el permiso, el disco (también en el seeder y en el borrado del ítem), `no-store` y el `path` del recurso. Se quitó de `TenantStorageTest` el test de la URL pública de las fotos, que ya no existe. Frontend: `PhotoGallery.test.tsx` (3) |
| Entorno de revisión | Con autorización del usuario, se corrigió la deuda #25 en esta entrega: `entorno.mjs` lanza la API con `php -d variables_order=EGPCS -S` y el router de Laravel (desde `backend/public`) en lugar de `artisan serve`, así `LARAVEL_STORAGE_PATH` llega a `$_ENV` y el storage, el log y los archivos quedan en la carpeta temporal |
| Revisión | En el navegador, en el entorno aislado (SQLite) con el storage ya aislado: administrador, recepcionista, jefe de taller y mecánico ven la foto de recepción subida y las sembradas del diagnóstico (el recepcionista, con subir, editar y eliminar en una recepción pendiente); el cajero recibe la pantalla 403 en las dos. Por curl: la ruta responde `image/jpeg` en línea con `nosniff` y `no-store` a esos roles, `403` al cajero, `401` sin token y `404` con la foto de otra recepción |
| Revisión al cerrar | Repetida el 2026-11-26 en otra sesión, en un entorno aislado nuevo (SQLite): una foto subida por la API como recepcionista a la recepción #33 y la galería del ítem "Amortiguador delantero" del diagnóstico #10 (2 fotos sembradas). Mismos resultados por rol en la pantalla y por curl; el archivo quedó en `tenant_private` de la carpeta temporal y `backend/storage` no cambió |
| Hallazgos | Dos problemas anteriores a P8, registrados sin corregir: el alta de fotos de recepción puede dejar una foto sin archivo (deuda #26) y un texto que no es UTF-8 se guarda y después rompe las respuestas (deuda #27) |

**Paso 3 (comando), 2026-11-27:**

| Tema | Decisión |
|---|---|
| Comando | `php artisan tenants:privatize-files --tenant={id}` (`PrivatizeTenantFiles`). Activa el taller, recorre las rutas de su base (`vehicle_deliveries.signature_path`, `reception_photos.path` y `diagnostic_item_photos.path`) y mueve cada archivo de `public` a `tenant_private` con la misma ruta: primero lo escribe en privado y después lo borra del público, así un corte deja dos copias y no ninguna. Lo que no está en la base (el branding) no se toca. Acepta un taller en cualquier estado. Al terminar deja el taller que estaba activo |
| Ya en privado | Decisión del usuario (2026-11-27): se saltea sin sobrescribirlo y, si sigue en público, esa copia se deja y se informa. La existencia se mira con `fileExists` (no `exists`, que también es verdadero para una carpeta) |
| Faltantes | Decisión del usuario (2026-11-27): un archivo que no está en ningún disco solo se informa; no cambia el código de salida |
| Errores | Un archivo que no se puede mover se informa (el detalle va al log, `No se pudo pasar el archivo al disco privado.`), conserva su copia pública y los demás siguen. El comando termina con código 1 si hubo alguno, si falta `--tenant` o si el taller no existe. La salida cierra con el resumen: movidos, ya en privado (con copia en público), faltantes y con error |
| Tests | `PrivatizeTenantFilesCommandTest` (6, en `tests/Feature/Tenancy`, con los discos reales en una carpeta temporal): mueve los tres tipos y deja el branding, la segunda corrida no cambia nada, no sobrescribe lo ya privado y deja su copia pública, informa los faltantes con código 0, un archivo que no se puede mover conserva su copia y los demás siguen (código 1), y rechaza la falta de `--tenant` o un taller inexistente. Cada protección se comprobó quitándola: el borrado de la copia pública, el salteo de lo ya privado, los faltantes sin error, el `catch` por archivo, el reinicio de los contadores en cada corrida y `fileExists` |
| Prueba en MySQL | Con el taller #1 de desarrollo, sin archivos en ningún disco (2.98): 38 faltantes informados, nada movido y código 0; `--tenant=99` y sin `--tenant`, código 1. No escribió nada |

---

## 3. Frontend

Decisiones de la SPA (`frontend/`). El plan por fases (F0 a F14 y FA0 a FA4, del 2026-10-21 al 2026-11-01) se completó y
se retiró; queda lo que explica el código actual.

### Base (2026-10-21)

| Tema | Decisión | Motivo |
|---|---|---|
| Permisos en el cliente | Endpoint `GET /api/me` con el usuario, sus roles y los **nombres** de sus permisos | `POST /login` solo trae IDs y no había forma de revalidar el token al recargar. Evita duplicar el seeder |
| Token | `localStorage` | La sesión sobrevive a recargas; los tokens no expiran y `POST /logout` los revoca |
| Datos remotos | TanStack Query | Caché, invalidación tras mutaciones y estados uniformes en unas 40 pantallas |
| Formularios | Estado de React + `FormField` con errores `422` | Sin librería extra hasta que haga falta |
| Gráficos | ApexCharts por `components/ui/Chart.tsx`, con carga diferida | Es la de las demos de Tabler; los colores salen de sus variables CSS. Chunk aparte |
| Tests | Vitest + Testing Library (2026-10-30, FA0) | Probar sobre todo los formularios delicados y el armado de los requests |

### Preferencias visuales (2026-10-24)

* Se aplican como atributos `data-bs-*` en `<html>` (`features/preferences/theme.ts`; `theme_primary` →
  `data-bs-theme-primary`). No se usan `tabler-theme.js` ni `sidebar.ts`: guardan en `localStorage` y tocan el DOM por su cuenta.
* La fuente es el backend (`GET /me` trae `preferences`). `localStorage` (`synctaller.preferences`) guarda una copia solo
  para el primer pintado: un script en `index.html` la aplica antes de React, sin parpadeo.
* Los predeterminados reproducen el aspecto original (barra lateral oscura), así que la app escribe siempre todos los atributos.

### Catálogo de ítems de diagnóstico (2026-10-24)

| Tema | Decisión |
|---|---|
| Campos | Título único, categoría opcional, descripción, severidad sugerida, requiere reparación o reemplazo, tiempo y recomendación. Sin costo: el precio va en el presupuesto |
| Quién lo administra | Administrador y jefe de taller; el mecánico lo consulta y lo usa |
| Ítem usado y borrado | Baja lógica: el diagnóstico guarda su copia y el vínculo (`template.deleted`) |

### Módulo de aseguradoras (FA0 a FA4, 2026-10-30)

| Tema | Decisión |
|---|---|
| Orden | FA0 (compatibilidad: clientes empresa, respuesta al presupuesto, `credited`, `invoices[]`) primero, porque habilita crear empresas en producción |
| Siniestros | Ítem propio en el menú y tarjeta en el detalle de la recepción; el alta se hace desde la recepción |
| Aseguradora | El alta ofrece cliente nuevo o existente; para buscar solo empresas, `GET clients?kind=company` (historial 2.72) |
| Autorización | La unidad de los importes (con o sin IVA) no tiene valor inicial; la evidencia se elige o se sube desde el formulario |
| Excepciones internas | Se otorgan en contexto y tienen una pantalla de consulta; la OT usa la excepción con una casilla explícita |
| Reparto | Propuesta del backend editada por concepto en una tabla (una columna por tipo de porción), con los `checks` a la vista; lo vendido por concepto lo informa el backend (historial 2.73) |
| Errores de la entrega | Se muestran con el mensaje del backend, sin repetir la regla |
| Reportes | El reporte de siniestros agrupa sus importes por etapa; las facturas de un cliente de cuentas por cobrar se ven filtrando el reporte |
| Deducible neto (M10) | La modalidad se elige solo al editar; el IVA del deducible es opcional, con aviso; la columna del deducible aparece solo cuando se usa |

### Retenciones (M12, 2026-11-24)

| Tema | Decisión |
|---|---|
| Ubicación | Tarjeta "Retenciones" en el detalle de la factura, debajo de las notas de crédito, y la fila "Retenido" en el cobro y en el resumen del siniestro |
| Formulario | Arranca en IVA con la sugerencia del backend como importe; el texto de ayuda no repite el porcentaje (es configuración del backend) |
| Reporte de ventas | Tarjeta "Retenciones" aparte de lo cobrado, solo si hubo retenciones en el período |

### Datos del cliente en la factura (V10, 2026-11-24)

| Tema | Decisión |
|---|---|
| Aviso | Alerta amarilla arriba del detalle, solo en el borrador, con los datos que trae `customer.missing`; no bloquea el botón "Emitir" (la regla la decide el backend, 2.86) |
| Edición | Teléfono y correo en el modal "Editar datos", junto a la dirección; vacíos se mandan como `null` |
| Detalle | Filas "Teléfono" y "Correo" en la tarjeta de la factura, con "—" cuando faltan (también en las facturas anteriores a V10) |

### Ajustes V18, V20 y V15 (2026-11-25)

| Tema | Decisión |
|---|---|
| Nota de crédito | "Timbrado" pasa a ser obligatorio en el formulario; el error por campo lo da el backend (2.87) |
| Documentos del siniestro | "Contrato" y "Cédula del declarante" en el selector de tipo, después de "Póliza" |
| IVA del deducible | El alta arranca en "No incluye IVA"; la edición muestra lo guardado (un siniestro sin indicar sigue sin indicar). La ayuda recuerda que, por lo general, la póliza no lo incluye salvo que diga «IVA incluido» |
