# Aseguradoras y siniestros

**Estado: EN DESARROLLO.** Reparaciones pagadas por una aseguradora: siniestros, autorizaciones, reparto del costo y facturación por
responsable.

| Dato | Referencia |
|---|---|
| Diseño y validaciones | [`especificacion-aseguradoras.html`](../especificacion-aseguradoras.html), versión 4 |
| Reglas vigentes | `reglas-de-negocio.md` 6.22 a 6.31 y 6.33 |
| Decisiones | [historial](../decisions/historial.md), secciones 2.59 a 2.75, 2.83 y 2.84 |
| Depende de | Clientes, recepción, presupuestos, OT, facturación, pagos y entrega |

## Estado por entrega

| Entrega | Objetivo | Estado | Reglas | Tests |
|---|---|---|---|---|
| M1 | Tipo de cliente | COMPLETADO | 6.1 | `ClientKind` 9 |
| M2 | Aseguradoras y contactos | COMPLETADO | 6.22 | `Insurer` 7 |
| M3 | Siniestros | COMPLETADO | 6.23 | `Claim` 8 |
| M4 | Documentos privados | COMPLETADO | 6.24 | `ClaimDocument` 6 |
| M5 | Responsable por línea | COMPLETADO | 6.4 | `BudgetItemResponsibility` 4 |
| M6 | Autorizaciones | COMPLETADO | 6.25 | `InsurerAuthorization` 7 |
| M7 | Bloqueo de OT y excepciones | COMPLETADO | 6.26 | `WorkOrderInsurerFlow` 8 |
| M8 | Reparto económico | COMPLETADO | 6.27 | `WorkOrderAllocation` 9, `WorkOrderSaleBreakdown` 1 |
| P10 | Notas de crédito registradas | COMPLETADO | 6.28 | `CreditNote` 6 |
| M9 | Facturas por responsable | COMPLETADO | 6.29 | `AllocationInvoice` 6 |
| M10 | Deducible facturado, modalidad neta | COMPLETADO, **apagado** | 6.31 | `DeductibleNetMode` 5 |
| M11 | Reportes | COMPLETADO | 6.30 | `InsurerReports` 4 |
| M11 | Avisos a la aseguradora | PENDIENTE | D, V7 | Sin tests |
| M12 | Retenciones de IVA y renta | COMPLETADO | 6.33 | `InvoiceWithholding` 5 |

El frontend cubre estas entregas (FA0–FA4, M10 y M12): ver la parte 3 del [historial](../decisions/historial.md#3-frontend).

## Qué falta

Orden y decisiones en el [historial](../decisions/historial.md), sección 2.83 (respuestas de la contadora del 2026-11-24).

| Pendiente | Qué lo destraba |
|---|---|
| Datos del cliente en la factura (V10) | Nada: sin bloqueo, con aviso |
| Ajustes V18, V20 y V15 | Nada: timbrado obligatorio en la nota de crédito, tipos de documento nuevos, valor inicial del IVA del deducible |
| Anulación solo en el mes de emisión (V19) | Nada: decisión tomada |
| Encender M10 | Implementar V16 (deducible facturado, como máximo lo cubierto); V21 ya se cumplió |
| Avisos a la aseguradora | Decisión comercial (V7, cerrada por ahora como "sin recordatorios") |

## Observaciones para continuar

* Las retenciones (M12) las carga el cajero con el importe del comprobante; la renta no tiene sugerencia hasta conocer su porcentaje (pregunta C4 del cuestionario pendiente).
* La parte fiscal la respondió una contadora el 2026-11-24. Siguen sin respuesta V3, V5 y V13, que no bloquean.
* Un siniestro con deducible mayor a 0 no se factura ni se entrega con el interruptor apagado (decisión A).
* Hay que probar los permisos con cada rol: están repartidos entre recepcionista, jefe de taller y cajero (`backend/CLAUDE.md`, roles).
