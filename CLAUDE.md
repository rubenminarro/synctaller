# CLAUDE.md — SyncTaller

Instrucciones permanentes para trabajar en este monorepo sin romper lo existente. SyncTaller es el sistema de gestión de
un taller mecánico: una API Laravel (`backend/`) y una SPA React (`frontend/`), con la documentación del producto en `docs/`.

## Mapa

| Carpeta | Qué es | Reglas propias |
|---|---|---|
| `backend/` | API REST (PHP 8.3, Laravel 13, Sanctum, spatie/laravel-permission) | [`backend/CLAUDE.md`](backend/CLAUDE.md): convenciones, arquitectura, contrato y roles |
| `frontend/` | SPA (React 19, TypeScript, Vite, Tabler) | [`frontend/CLAUDE.md`](frontend/CLAUDE.md): interfaz, integración con la API, permisos |
| `docs/` | Documentación del producto | [`docs/README.md`](docs/README.md): dónde vive cada dato y qué actualizar |
| `.claude/skills/` | Skills del proyecto: `retomar`, `validar-docs`, `cerrar-entrega` | — |

Las dos apps están desacopladas: cada una tiene sus dependencias, su lock y su `.env`. La raíz solo orquesta
(`package.json`) y valida (`.github/workflows/ci.yml`).

| Para saber… | Leer |
|---|---|
| Reglas de negocio vigentes y sus `error_code` | [`docs/reglas-de-negocio.md`](docs/reglas-de-negocio.md) (numeración 6.N) |
| Qué sigue y qué está bloqueado | [`docs/roadmap/README.md`](docs/roadmap/README.md), "Próximas tareas" |
| Por qué algo es como es | [`docs/decisions/historial.md`](docs/decisions/historial.md) (secciones 2.N) |
| Deuda técnica y discrepancias | [`docs/roadmap/deuda-tecnica.md`](docs/roadmap/deuda-tecnica.md) |
| La "regla de oro" del diseño | [`docs/project/principios.md`](docs/project/principios.md) |

Si un documento contradice al código, **el código manda**: registrar la discrepancia en la deuda técnica y avisar, en lugar
de "corregir" por cuenta propia.

## Comandos

Desde la raíz:

```bash
npm run setup        # dependencias de las dos apps y sus .env
npm run dev          # API :8000 + cola + Vite :5173
npm test             # PHPUnit y Vitest (test:backend, test:frontend)
npm run lint         # Pint --test y ESLint (lint:backend, lint:frontend)
npm run build        # tsc -b y build del frontend
```

En `backend/`: `php artisan test [--filter=…]` (unos 6 minutos la suite completa; no correr dos a la vez: comparten
`database/testing.sqlite`) y `vendor/bin/pint` para corregir el estilo. En `frontend/`: `npm run lint`, `npm run build`
y `npm test`.

## Tests: baseline y uso diario

**Baseline inicial (2026-11-24, después de aplicar Pint):** corrida completa en verde. Backend: 488 tests y 8560 aserciones (6,8 min) y
Pint sin pendientes. Frontend: ESLint sin errores, Vitest 43 de 43 y build correcto. Es la referencia: todo lo que venga
después parte de un estado verde.

En el trabajo diario:

* Correr **solo los tests puntuales** de lo que se toca: `php artisan test --filter=…` o la carpeta del módulo en el
  backend, `npx vitest run <archivo>` en el frontend. Más `npm run lint` y, si cambió el frontend, `npm run build`.
* **No correr la suite completa** (`npm test`, `php artisan test` sin filtro) salvo que el usuario lo pida. La CI la
  corre en cada push y pull request.
* Si un test puntual falla por algo ajeno al cambio, informarlo; no ampliar la corrida por cuenta propia.

## Metodología

Antes de implementar cualquier cambio:

1. Inspeccionar el código relacionado y, si cruza las dos apps, los dos lados del contrato (ruta, Request, Resource y
   permiso en el backend; tipos y servicio en el frontend).
2. Identificar las reglas de negocio y los módulos afectados.
3. Proponer el cambio mínimo y explicar su motivo. **Las decisiones de negocio se consultan, no se suponen.**
4. Implementar solo el alcance autorizado. No modificar una app desde una tarea de la otra sin una necesidad identificada.
5. Correr los tests puntuales de lo que cambió y `npm run lint`; si cambió el frontend, también el build (ver
   "Tests: baseline y uso diario").
6. Verificar que no cambien contratos, permisos, `error_code` ni códigos HTTP existentes.
7. Verificar en el navegador las pantallas tocadas, con un usuario de cada rol involucrado.
8. Informar archivos modificados, comprobaciones ejecutadas y resultados. No declarar nada cerrado sin evidencia.

En fases nuevas: primero el diseño funcional y de datos, con las preguntas de negocio; después el código. Ninguna entrega
empieza sin autorización del usuario.

### Git

* Nunca trabajar ni commitear en `main`. Una rama por tarea desde `origin/main`: `feature/…`, `fix/…`, `docs/…`, `test/…`.
  Un cambio que toca backend y frontend va en una sola rama.
* Commit y push solo cuando el usuario lo pide. El merge a `main` lo hace el usuario (salvo pedido explícito).
* Mensajes de commit en español, describiendo el porqué.

### Documentación por cambio

En la misma rama que el código, según [`docs/README.md`](docs/README.md), "Qué actualizar en cada cambio": reglas de
negocio, una sección nueva del historial por decisión, guía de Postman si cambia la API, estado en módulos y roadmap.
Para cerrar una entrega, la Skill `cerrar-entrega`; para auditar la documentación, `validar-docs`.

## Entorno (Windows / Laragon)

* La API corre con `php artisan serve` en `127.0.0.1:8000`; Laragon no crea vhost para `backend/public`. Vite en
  `localhost:5173` con puerto fijo: es el único origen que admiten CORS y Sanctum (`FRONTEND_URL`,
  `SANCTUM_STATEFUL_DOMAINS` en `backend/.env`).
* No editar archivos con `Get-Content`/`Set-Content` de PowerShell 5.1: corrompen UTF-8 y agregan BOM. Usar las
  herramientas de edición. Los finales de línea son LF (`.gitattributes`).
* `npm ci`, nunca `npm install`, en `frontend/`. Sin `php artisan config:cache` en desarrollo: los tests correrían contra MySQL.
* No ejecutar `migrate:fresh`, `db:wipe` ni seeders de ejemplo sobre la base de desarrollo `synctaller` sin pedido
  explícito. Las revisiones que guardan datos, en una base temporal que se borra al terminar.
* Dependencias nuevas: versión exacta y con al menos dos semanas de publicada; no agregar ninguna sin evaluar necesidad y
  compatibilidad.

## Contrato entre backend y frontend

* Base `/api`, siempre JSON: `{ success, status, message, data, errors, meta, timestamp }`, más `error_code` en los errores.
  `message` en español para el usuario; el frontend decide por `error_code`.
* Auth con tokens Bearer de Sanctum (no expiran). La sesión se hidrata con `GET /api/me` (usuario, nombres de permisos,
  mecánico y preferencias).
* Los permisos se validan en el backend; ocultarlos en el frontend es solo UX. El cliente no duplica reglas ni cálculos de
  dinero: estados, totales, IVA y saldos los calcula la API.
* Textos de la interfaz y mensajes en español; código, campos y `error_code` en inglés.
