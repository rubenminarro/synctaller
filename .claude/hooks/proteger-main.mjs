// Hook PreToolUse (Bash y PowerShell): bloquea `git commit` y `git push` en `main`, y el push que apunte a `main` desde otra rama.
// El merge a main lo hace el usuario (CLAUDE.md, "Git"). Lee el JSON del hook por stdin; sin salida, deja seguir.

import { execFileSync } from 'node:child_process'

let entrada = ''
for await (const parte of process.stdin) entrada += parte

let datos
try {
  datos = JSON.parse(entrada)
} catch {
  process.exit(0)
}

const comando = String(datos.tool_input?.command ?? '')
const git = /\bgit\b[^|;&\n]*?\b(commit|push)\b([^|;&\n]*)/g

const usos = [...comando.matchAll(git)]
if (usos.length === 0) process.exit(0)

let rama = ''
try {
  rama = execFileSync('git', ['branch', '--show-current'], {
    cwd: datos.cwd || process.env.CLAUDE_PROJECT_DIR || process.cwd(),
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'ignore'],
  }).trim()
} catch {
  process.exit(0)
}

const motivo = (() => {
  for (const [, verbo, resto] of usos) {
    if (rama === 'main') return `git ${verbo} en la rama main`
    // Push explícito a main desde otra rama: `git push origin main`, `HEAD:main`, `+main`, `refs/heads/main`.
    if (verbo === 'push' && /(^|[\s:+/])main(\s|$)/.test(resto)) return 'git push hacia main'
  }
  return ''
})()

if (!motivo) process.exit(0)

console.log(JSON.stringify({
  hookSpecificOutput: {
    hookEventName: 'PreToolUse',
    permissionDecision: 'deny',
    permissionDecisionReason: `Bloqueado: ${motivo}. Nunca trabajar ni commitear en main; el merge lo hace el usuario (CLAUDE.md, "Git"). Crear una rama desde origin/main.`,
  },
}))
