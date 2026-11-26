---
name: revisar-navegador
description: Revisa pantallas del frontend en Chrome headless con un usuario de cada rol, sobre un entorno aislado (base SQLite temporal, taller registrado y storage propio) que se borra al terminar. Usar para el paso 7 de la metodología (verificar en el navegador las pantallas tocadas) o cuando se pida ver una pantalla.
argument-hint: "[pantallas o entrega a revisar]"
allowed-tools: Read Grep Glob Write Bash(node .claude/skills/revisar-navegador/scripts/*) Bash(curl -s *) Bash(git diff *) Bash(git status *)
---

# Revisar en el navegador

Qué revisar: $ARGUMENTS

Todo pasa en una carpeta temporal del **scratchpad**: la base del taller, la central, los archivos subidos y los logs. **Nunca se usa la
base `synctaller`, `backend/.env` ni `backend/storage`**, así que la revisión puede guardar datos sin pedir permiso. Ni la base ni las
capturas se commitean.

## 1. Planificar

* Pantallas: las rutas del frontend que tocó la entrega (`frontend/src/app/router.tsx`). Sin argumentos, deducirlas de
  `git diff origin/main...HEAD`.
* Roles: los involucrados según los permisos de cada ruta (`guarded(path, permiso, …)`) y la tabla de roles de `backend/CLAUDE.md`, **no
  solo el administrador**. Incluir un rol **sin** el permiso, si lo hay, para comprobar el 403.
* Usuarios (siembra, `AdminUserSeeder::USERS`): `admin@mail.com.py`, `recepcion1@…`, `jefetaller1@…`, `caja1@…`, `deposito1@…` y
  `jperez@…` (mecánico), todos `@mail.com.py`.
* Opciones de configuración que necesite el caso (por ejemplo `INSURER_DEDUCTIBLE_NET_MODE=true`): se pasan como variables, sin editar el
  `.env`.

## 2. Levantar el entorno

Desde la raíz del monorepo, con `<dir>` dentro del scratchpad y que todavía no exista:

```bash
node .claude/skills/revisar-navegador/scripts/entorno.mjs preparar <dir>        # migra, registra el taller y siembra (~35 s)
node .claude/skills/revisar-navegador/scripts/entorno.mjs api <dir> [VAR=valor]  # en segundo plano (run_in_background)
```

* `api` se niega si el puerto 8000 ya responde: puede ser la API de desarrollo contra `synctaller`. Avisar al usuario y no detener
  procesos que no se levantaron en esta sesión.
* Vite: si `curl -s http://localhost:5173` no responde, levantarlo en segundo plano con `npm run dev` en `frontend/`. Usa
  `frontend/.env` (`VITE_API_URL=http://127.0.0.1:8000/api`). El taller se identifica por el host `127.0.0.1` (reglas 6.34): con
  `localhost` en la URL de la API todo da `404 TENANT_NOT_FOUND`.
* Para armar un caso que la siembra no trae: por la interfaz (pasos de abajo), o con
  `entorno.mjs artisan <dir> [VAR=valor] -- tinker --execute="…"`. Nunca con un `php artisan` suelto: leería `backend/.env`.

## 3. Recorrer

```bash
node .claude/skills/revisar-navegador/scripts/capturar.mjs --salida <dir>/capturas \
  --usuarios caja1@mail.com.py,jperez@mail.com.py --rutas inicio,claims,claims/1
```

Las rutas van **sin la barra inicial** (`inicio` es `/`): Git Bash convierte `/claims` en una ruta de Windows. Para un flujo con acciones,
escribir un JSON de pasos y pasarlo con `--pasos <archivo>` (se ejecutan para cada usuario):

```json
[
  { "ruta": "claims/1", "captura": false },
  { "clickTexto": "Subir documento", "nombre": "modal-subir" },
  { "escribir": ["input[placeholder^='Buscar']", "QAB105"], "esperar": 1000 },
  { "click": "button[type=submit]", "nombre": "guardado" },
  { "ruta": "claims", "espera": 403 }
]
```

| Campo | Qué hace |
|---|---|
| `ruta` | Navega y espera a que la red quede quieta |
| `clickTexto` / `click` | Hace clic en el botón, enlace, pestaña o `label` con ese texto exacto, o en el selector CSS |
| `escribir` | `[selector, texto]` en un `input`, `textarea` o `select`, con los eventos que espera React |
| `esperar` | Milisegundos extra |
| `nombre` | Nombre de la captura; `captura: false` no la guarda |
| `espera` | Página de error esperada (`403`, `404`): no cuenta como problema |

La salida marca cada paso con ✓ o ✗. Son problemas: un paso que falló, una redirección (por ejemplo al login), una página 403 o 404 no
esperada, respuestas de la API con status 400 o más, errores de red, excepciones de JavaScript y errores o avisos de la consola. El detalle
queda en `<dir>/capturas/resumen.json`, con el menú lateral que vio cada usuario.

**Mirar las capturas con Read.** Que no haya errores no prueba que la pantalla esté bien: comprobar textos, montos, estados y botones
visibles u ocultos según el rol. Errores de la API: `<dir>/storage/logs/laravel.log`.

## 4. Cerrar

Siempre, también si la revisión falló:

```bash
node .claude/skills/revisar-navegador/scripts/entorno.mjs limpiar <dir>
```

Detiene la API del entorno y borra la carpeta. Si avisa que el puerto 8000 sigue respondiendo, detener esa tarea en segundo plano y repetir.
Detener también el Vite que se haya levantado en esta sesión. Detener su tarea en segundo plano puede dejar vivo el proceso `node … vite.js`:
comprobar con `curl -s http://localhost:5173` que el puerto quedó libre y, si no, detener solo ese proceso (el que tiene
`synctaller\frontend` y `vite` en la línea de comandos). Copiar antes las capturas que el usuario quiera conservar.

## 5. Informe

Por cada usuario y pantalla: qué se vio, lo que coincide con lo esperado y lo que no, las capturas que lo muestran y el resumen de
problemas. Indicar lo que no se pudo revisar (por ejemplo, flujos que dependen de un archivo para subir) y que la base fue SQLite.
`frontend/CLAUDE.md` pide una base MySQL temporal: si el cambio depende del motor (migraciones nuevas, índices, consultas propias de
MySQL), avisarlo para revisarlo aparte.
