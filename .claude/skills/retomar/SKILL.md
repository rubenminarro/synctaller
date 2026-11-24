---
name: retomar
description: Informa dónde está el proyecto y propone el siguiente paso. Usar al empezar una sesión o cuando se pida retomar o continuar el trabajo. Solo lectura.
allowed-tools: Read Grep Glob Bash(git status *) Bash(git log *) Bash(git fetch *) Bash(git rev-parse *) Bash(git rev-list *) Bash(git diff --stat *) Bash(git branch --show-current)
disallowed-tools: Edit Write NotebookEdit
---

# Retomar el proyecto

Reunir el estado real y proponer el siguiente paso. **No modificar archivos, no correr la suite de tests y no crear ramas.**
`git fetch` es lo único que actualiza algo, y solo las referencias remotas.

## 1. Estado de Git

Desde la raíz del monorepo:

```bash
git fetch origin
git status -sb
git log --oneline -5
git rev-list --left-right --count HEAD...origin/main
```

## 2. Último commit y última verificación

Son dos datos distintos y hay que informar los dos:

| Dato | Qué es | De dónde sale |
|---|---|---|
| **Último commit** | Lo más reciente del repositorio. No implica que esté probado | Git |
| **Última verificación** | La última vez que se registró el resultado de las suites | `docs/roadmap/README.md`, sección **Última verificación** |

Leer esa sección y **Próximas tareas**. No copiar su contenido a esta Skill. Con la fecha de la verificación:

* Listar lo posterior con `git log --oneline --since=<fecha> origin/main`.
* Clasificarlo con `git diff --stat`: solo documentación, o también código (`backend/`, `frontend/`).

El último commit nunca se presenta como validado por el solo hecho de ser el último. Si hay código posterior a la verificación, ese
código **no está validado**: decirlo, sin suponer que los tests pasan.

## 3. Bloqueos

Revisar cada tarea de **Próximas tareas**. Comparar las fechas de bloqueo con la fecha actual. Si hace falta el detalle de una tarea,
consultar `docs/modules/README.md` o el historial de decisiones, según `docs/README.md`.

## 4. Informe

Responder con este formato:

```text
Estado de Git       Rama, cambios sin commitear y diferencia con origin/main
Último commit       Hash y mensaje
Última verificación Fecha y tests registrados
Desde entonces      Commits posteriores: solo documentación, o con código sin validar
Próximas tareas     Las del roadmap, con su bloqueo vigente
Siguiente paso      Una propuesta concreta, con su motivo
```

Avisar si algo no coincide: cambios sin commitear, ramas detrás de `origin/main` o un roadmap desactualizado. No corregirlo:
proponerlo.

Terminar con la propuesta y esperar la decisión del usuario. Ninguna entrega empieza sin su autorización.
