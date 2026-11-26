---
name: registrar-ci
description: Registra en el roadmap la corrida de la CI sobre main después del merge de una entrega, con las cifras sacadas del log de GitHub Actions, y destraba las próximas tareas que esperaban esa entrega en main.
disable-model-invocation: true
argument-hint: "[entrega o run-id]"
allowed-tools: Read Edit Grep Glob Bash(node .claude/skills/registrar-ci/scripts/*) Bash(gh run *) Bash(gh pr view *) Bash(gh pr list *) Bash(git fetch *) Bash(git status *) Bash(git log *) Bash(git switch *) Bash(git diff *) Bash(git branch --show-current) Bash(php .claude/skills/validar-docs/scripts/check-docs.php*)
---

# Registrar la corrida de la CI

Entrega o corrida: $ARGUMENTS

Después del merge de una entrega a `main`, la CI corre las suites completas. Esta skill registra ese resultado en
`docs/roadmap/README.md`. **Solo toca ese archivo. No hace commit, push ni pull request salvo que el usuario lo pida en la misma
invocación**, y nunca hace merge.

## 1. Obtener la corrida

```bash
git fetch origin
node .claude/skills/registrar-ci/scripts/ci-resumen.mjs [run-id]
```

Sin `run-id`, toma la última corrida sobre `main` que corrió el backend o el frontend: las de un cambio solo de documentación omiten los
dos jobs y no verifican nada. El script saca del log los tests, las aserciones, Pint, Vitest y el build.

**Detenerse e informar** si:

* La corrida no terminó en verde, o el script no encontró una cifra (código de salida 1). Una CI en rojo sobre `main` es un hallazgo para el
  usuario: no se registra como verificación.
* La corrida no corresponde a la entrega indicada, o la entrega ya tiene su fila de CI en **Última verificación**.
* Hay cambios sin commitear en la rama actual.

## 2. Identificar la entrega

Con el título de la corrida (`Merge pull request #N …`) y `gh pr view N`, ubicar la entrega y su sección del historial. Sale de la fila de
la corrida local de esa entrega en **Última verificación**, que escribió `cerrar-entrega`. Si no se puede determinar, preguntar.

## 3. Rama

```bash
git switch -c docs/verificacion-ci-<entrega> origin/main
```

`<entrega>` en minúsculas y con guiones, como las anteriores (`t4`, `t3-paso5`, `deuda-19`).

## 4. Actualizar el roadmap

**Última verificación:** una fila nueva, arriba de todo, con este formato. No agregar más detalle: el detalle de la entrega está en el
historial y en su fila local.

```text
| <fecha de la corrida> | <N> tests en verde, <A> aserciones (antes de <próxima tarea>); Pint sin pendientes | <frontend> | <Entrega> (historial 2.N). Corrida de la CI sobre `main` (`<commit>`), no local[. <Fase> completa] |
```

La última frase va solo si la corrida cierra una fase o una línea (por ejemplo «T4 completo»).

* `<N>` es lo que informa PHPUnit (cuenta cada caso de un data provider). Si hubo omitidos, decirlo.
* `<frontend>`: `Vitest en verde (<n>), ESLint y build`, o `Sin corrida: el cambio no tocó el frontend (la CI lo omite por rutas)`.
* Lo mismo para el backend si su job se omitió.
* Las cifras son las del script. Nunca de memoria ni de otra fila.

**Próximas tareas:** solo los bloqueos que esperaban "esta entrega en `main` con la CI en verde". Pasan a "Sin bloqueo", citando el
commit. No agregar, quitar ni reordenar tareas.

Ningún otro documento: el estado de los módulos y el historial ya los actualizó `cerrar-entrega` en la rama de la entrega.

## 5. Verificar

```bash
php .claude/skills/validar-docs/scripts/check-docs.php
```

Si el script encuentra cifras que no coinciden con el código, es esperable que la causa sea otra entrega: informarlo sin corregirlo.

## 6. Informe

Mostrar el diff, el enlace de la corrida y un mensaje de commit propuesto en español, con el porqué:

```text
Registrar en el roadmap la corrida de la CI de <entrega>

La CI sobre main (<commit>) pasó con <N> tests y <A> aserciones; <qué queda destrabado>.
```

Si el usuario pidió commit, push y pull request: hacerlos en esta rama (título igual al del commit) y dejar el merge al usuario.
