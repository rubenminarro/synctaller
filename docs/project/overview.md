# Qué es SyncTaller

SyncTaller es un sistema de gestión para talleres mecánicos. Cubre la atención de un vehículo de punta a punta, con foco en la
trazabilidad de lo presupuestado, lo ejecutado, lo facturado y lo cobrado. Se llamaba **Presupuestador** hasta el 2026-10-21.

```text
Cliente → Vehículo → Recepción → Diagnóstico → Presupuesto → Aprobación → Orden de trabajo
       → Ejecución (servicios, mano de obra, repuestos) → Cierre económico → Factura → Pago → Entrega → Historial
```

Alrededor de ese flujo están el inventario y las compras, los proveedores, el mantenimiento preventivo, los reportes, los avisos a
clientes y la auditoría. La línea en curso agrega las reparaciones por siniestro, con aseguradoras, y prepara la operación como
SaaS, con varios talleres.

## Repositorio

Un monorepo ([`rubenminarro/synctaller`](https://github.com/rubenminarro/synctaller)) desde el 2026-11-24. Reemplaza a
`synctaller-backend` y `synctaller-frontend`, que quedan archivados.

| Carpeta | Contenido |
|---|---|
| `backend/` | API REST en Laravel. `resources/views` solo tiene las vistas de email y de PDF |
| `frontend/` | SPA en React que consume la API |
| `docs/` | Documentación del producto |

## Usuarios del sistema

Seis roles: `administrador`, `mechanic`, `recepcionista`, `jefe-taller`, `cajero` y `deposito`. Sus accesos están en `backend/CLAUDE.md`
(roles).

## Contexto de negocio

* País: Paraguay. Moneda: guaraníes, sin decimales en lo facturado.
* IVA del 10 %, 5 % o exento, con factura con timbrado. SIFEN no es obligatorio todavía (según la respuesta a G5, sin confirmar por
  un contador).
* Las reglas fiscales no se inventan: se validan con un contador. Las validaciones pendientes están en §10 de la
  [especificación](../especificacion-aseguradoras.html).

## Documentos relacionados

* [Principios del proyecto](principios.md)
* [Arquitectura](architecture.md)
* [Estado por módulo](../modules/README.md)
