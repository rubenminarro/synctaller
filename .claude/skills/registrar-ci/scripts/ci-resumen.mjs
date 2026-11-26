// Resume una corrida de la CI con las cifras sacadas del log (nunca de memoria). Solo lectura; necesita `gh` autenticado.
// Correr desde la raíz del monorepo:
//   node .claude/skills/registrar-ci/scripts/ci-resumen.mjs            última corrida sobre main que corrió backend o frontend
//   node .claude/skills/registrar-ci/scripts/ci-resumen.mjs <run-id>   una corrida concreta (por ejemplo la de un pull request)
// Imprime JSON. Sale con 1 si la corrida no terminó en verde o si falta una cifra.

import { execFileSync } from 'node:child_process'

const gh = (...args) => execFileSync('gh', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })
const sinColor = (texto) => texto.replace(/\x1b\[[0-9;]*m|\^\[\[[0-9;]*m/g, '')

function corrida(id) {
  return JSON.parse(gh('run', 'view', String(id), '--json', 'databaseId,headSha,headBranch,event,status,conclusion,displayTitle,createdAt,url,jobs'))
}

function elegir() {
  if (process.argv[2]) return corrida(process.argv[2])

  const lista = JSON.parse(gh('run', 'list', '--branch', 'main', '--workflow', 'CI', '--event', 'push', '--limit', '30', '--json', 'databaseId'))
  for (const { databaseId } of lista) {
    const c = corrida(databaseId)
    // Las corridas de un cambio solo de documentación omiten los dos jobs: no verifican nada.
    if (c.jobs.some((j) => ['backend', 'frontend'].includes(j.name) && j.conclusion !== 'skipped')) return c
  }
  throw new Error('No hay corridas sobre main con backend o frontend en las últimas 30.')
}

function job(c, nombre) {
  const j = c.jobs.find((x) => x.name === nombre)
  if (!j) return { estado: 'sin job' }
  if (j.conclusion === 'skipped') return { estado: 'omitido (la CI lo saltea por rutas: el cambio no tocó esta app)' }

  const resultado = { estado: j.conclusion, pasos: Object.fromEntries(j.steps.filter((s) => !/^(Set up|Complete|Post |Run actions\/)/.test(s.name)).map((s) => [s.name, s.conclusion])) }
  if (j.conclusion !== 'success') return resultado

  const log = sinColor(gh('run', 'view', String(c.databaseId), '--log', '--job', String(j.databaseId)))
  if (nombre === 'backend') {
    const tests = log.match(/Tests:\s+(\d+) passed(?:, (\d+) skipped)? \((\d+) assertions\)/)
    const duracion = log.match(/Duration:\s+([\d.]+)s/)
    const pint = log.match(/PASS\s+\.+\s+(\d+) files/)
    resultado.phpunit = tests ? { pasaron: +tests[1], omitidos: +(tests[2] ?? 0), aserciones: +tests[3], segundos: duracion ? +duracion[1] : null } : null
    resultado.pint = pint ? { archivos: +pint[1], pendientes: 0 } : null
  } else {
    const archivos = log.match(/Test Files\s+(\d+) passed \((\d+)\)/)
    const tests = log.match(/Tests\s+(\d+) passed \((\d+)\)/)
    resultado.vitest = tests ? { pasaron: +tests[1], total: +tests[2], archivos: archivos ? +archivos[1] : null } : null
    resultado.build = /✓ built in/.test(log)
  }
  return resultado
}

try {
  const c = elegir()
  const resumen = {
    run: c.databaseId,
    url: c.url,
    rama: c.headBranch,
    evento: c.event,
    commit: c.headSha.slice(0, 7),
    titulo: c.displayTitle,
    fecha: c.createdAt,
    estado: c.status === 'completed' ? c.conclusion : c.status,
    backend: job(c, 'backend'),
    frontend: job(c, 'frontend'),
  }
  console.log(JSON.stringify(resumen, null, 2))

  const faltan = (resumen.backend.estado === 'success' && !resumen.backend.phpunit) || (resumen.frontend.estado === 'success' && !resumen.frontend.vitest)
  if (resumen.estado !== 'success') console.error(`La corrida no está en verde: ${resumen.estado}.`)
  if (faltan) console.error('No se encontró una cifra en el log: revisarlo a mano con gh run view --log.')
  process.exit(resumen.estado === 'success' && !faltan ? 0 : 1)
} catch (error) {
  console.error(error.message)
  process.exit(1)
}
