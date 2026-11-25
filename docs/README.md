# Documentación de SyncTaller

Documentación del producto, compartida por `backend/` y `frontend/`. Para instalar y levantar el proyecto, empezar por el
[`README.md`](../README.md) de la raíz.

## Dónde está cada cosa

| Quiero saber… | Documento |
|---|---|
| Qué es el proyecto | [`project/overview.md`](project/overview.md) |
| Cómo está construido | [`project/architecture.md`](project/architecture.md) |
| Qué tecnologías y versiones usa | [`project/technology-stack.md`](project/technology-stack.md) |
| Qué principios guían el diseño | [`project/principios.md`](project/principios.md) |
| Qué reglas de negocio rigen, con sus `error_code` | [`reglas-de-negocio.md`](reglas-de-negocio.md) |
| Qué módulos están terminados y cuáles no | [`modules/README.md`](modules/README.md) |
| Qué se hizo, qué falta y qué sigue | [`roadmap/README.md`](roadmap/README.md) |
| Qué deudas y discrepancias siguen abiertas | [`roadmap/deuda-tecnica.md`](roadmap/deuda-tecnica.md) |
| Qué se decidió y por qué | [`decisions/README.md`](decisions/README.md) y el [historial](decisions/historial.md) |
| Cómo instalar en una computadora nueva | [`development/setup.md`](development/setup.md) |
| Cómo correr los tests | [`development/testing.md`](development/testing.md) |
| Qué se sabe del despliegue | [`development/deployment.md`](development/deployment.md) |
| Diseño del módulo de aseguradoras y de la línea SaaS | [`especificacion-aseguradoras.html`](especificacion-aseguradoras.html) |
| Evaluación del paquete de multitenancy | [`spike-t0/informe.md`](spike-t0/informe.md) |

## Fuente de verdad de cada tema

Cada dato vive en un solo lugar. Los demás documentos enlazan, no copian.

| Tema | Fuente de verdad |
|---|---|
| Comportamiento real | El código y los tests. Si un documento lo contradice, manda el código: registrar la discrepancia en [`roadmap/deuda-tecnica.md`](roadmap/deuda-tecnica.md) |
| Reglas de negocio vigentes | [`reglas-de-negocio.md`](reglas-de-negocio.md) |
| Convenciones del backend y del frontend | `backend/CLAUDE.md` y `frontend/CLAUDE.md` |
| Instalación, variables de entorno y datos de prueba | [`README.md`](../README.md) de la raíz y [`development/setup.md`](development/setup.md) |
| Estado de cada módulo | [`modules/README.md`](modules/README.md) |
| Estado por fase y próximas tareas | [`roadmap/README.md`](roadmap/README.md) |
| Deuda técnica | [`roadmap/deuda-tecnica.md`](roadmap/deuda-tecnica.md) |
| Decisiones, con su motivo | [`decisions/historial.md`](decisions/historial.md) |
| Diseño pendiente de implementar y validaciones externas | [`especificacion-aseguradoras.html`](especificacion-aseguradoras.html), §9 y §10 |
| Contrato de la API | El código: `backend/routes/api.php`, los Form Requests y los Resources, más el formato común de `backend/CLAUDE.md` |

## Estructura

```text
docs/
├── README.md                         Este índice
├── reglas-de-negocio.md              Reglas vigentes de la API (numeración 6.N)
├── especificacion-aseguradoras.html  Diseño vigente de aseguradoras y SaaS (versión 3)
├── project/                          Qué es y cómo está construido
├── modules/                          Estado por módulo
├── roadmap/                          Estado por fase, próximas tareas y deuda técnica
├── decisions/                        Índice e historial de decisiones
├── development/                      Instalación, tests y despliegue
└── spike-t0/                         Evaluación de multitenancy y herramientas para repetir la medición en T2
```

## Qué actualizar en cada cambio

| Cambio | Documentos |
|---|---|
| Terminar una entrega | [`modules/README.md`](modules/README.md) y [`roadmap/README.md`](roadmap/README.md) |
| Tomar una decisión | Una sección nueva `2.N` en la parte 2 del [historial](decisions/historial.md) (frontend: parte 3) |
| Cambiar una regla de negocio | [`reglas-de-negocio.md`](reglas-de-negocio.md) |
| Cambiar la instalación o el entorno | [`README.md`](../README.md) de la raíz y [`development/setup.md`](development/setup.md) |

El historial de decisiones es registro: lo que dice era cierto cuando se escribió y no se corrige para reflejar el estado
actual. Solo crece.
