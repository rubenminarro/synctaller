// Entorno aislado para revisar en el navegador: base SQLite del taller, base central, taller registrado y storage propio,
// todo dentro de una carpeta temporal. Nunca toca la base `synctaller`, `backend/.env` ni `backend/storage`.
// Correr desde la raíz del monorepo:
//   node .claude/skills/revisar-navegador/scripts/entorno.mjs preparar <carpeta> [--sin-siembra]
//   node .claude/skills/revisar-navegador/scripts/entorno.mjs api <carpeta> [VAR=valor ...]   (en segundo plano)
//   node .claude/skills/revisar-navegador/scripts/entorno.mjs artisan <carpeta> [VAR=valor ...] -- <comando artisan>
//   node .claude/skills/revisar-navegador/scripts/entorno.mjs detener <carpeta>
//   node .claude/skills/revisar-navegador/scripts/entorno.mjs limpiar <carpeta>
// Las variables del proceso pisan las de backend/.env: así se activa una opción sin editar el .env del usuario.

import { spawn, spawnSync } from 'node:child_process'
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { isAbsolute, join, resolve } from 'node:path'

const PASSWORD = 'Revision.2026'
const MARCA = '.revisar-navegador'

const [accion, carpetaArg, ...resto] = process.argv.slice(2)

if (!existsSync('backend/artisan')) salir('Correr desde la raíz del repositorio.', 2)
if (!accion || !carpetaArg) salir('Uso: entorno.mjs preparar|api|artisan|detener|limpiar <carpeta> [opciones]', 2)

const carpeta = resolve(carpetaArg).replaceAll('\\', '/')
const backend = resolve('backend')

if (carpeta.startsWith(resolve('.').replaceAll('\\', '/'))) {
  salir('La carpeta debe estar fuera del repositorio (usar el scratchpad).', 2)
}

function salir(mensaje, codigo = 1) {
  console.error(mensaje)
  process.exit(codigo)
}

function variables(extra = {}) {
  return {
    ...process.env,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: `${carpeta}/taller.sqlite`,
    DB_CENTRAL_DATABASE: `${carpeta}/central.sqlite`,
    LARAVEL_STORAGE_PATH: `${carpeta}/storage`,
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    ADMIN_PASSWORD: PASSWORD,
    TEST_PASSWORD: PASSWORD,
    ...extra,
  }
}

function artisan(args, extra = {}) {
  const r = spawnSync('php', ['artisan', ...args], { cwd: backend, env: variables(extra), stdio: 'inherit' })
  if (r.status !== 0) salir(`Falló: php artisan ${args.join(' ')}`)
}

function extraDe(args) {
  return Object.fromEntries(args.filter((v) => /^[A-Z][A-Z0-9_]*=/.test(v)).map((v) => [v.slice(0, v.indexOf('=')), v.slice(v.indexOf('=') + 1)]))
}

// Detiene la API de este entorno (el `php -S`) con su árbol de procesos.
function detener() {
  const archivo = join(carpeta, 'api.pid')
  if (!existsSync(archivo)) return console.log('No hay API registrada en este entorno.')
  const pid = readFileSync(archivo, 'utf8').trim()
  const r = process.platform === 'win32'
    ? spawnSync('taskkill', ['/PID', pid, '/T', '/F'], { encoding: 'utf8' })
    : spawnSync('kill', ['--', `-${pid}`], { encoding: 'utf8' })
  console.log(r.status === 0 ? `API detenida (pid ${pid}).` : `La API (pid ${pid}) ya no corría.`)
  rmSync(archivo, { force: true })
}

function exigirMarca() {
  if (!existsSync(join(carpeta, MARCA))) salir(`${carpeta} no es un entorno de revisión (falta ${MARCA}).`)
}

async function puertoOcupado(url) {
  try {
    await fetch(url, { signal: AbortSignal.timeout(1500) })
    return true
  } catch {
    return false
  }
}

if (accion === 'preparar') {
  if (existsSync(carpeta)) salir(`${carpeta} ya existe: usar otra carpeta o limpiarla antes.`)

  for (const dir of ['framework/cache/data', 'framework/views', 'framework/sessions', 'logs', 'app/public', 'app/private']) {
    mkdirSync(`${carpeta}/storage/${dir}`, { recursive: true })
  }
  writeFileSync(`${carpeta}/taller.sqlite`, '')
  writeFileSync(`${carpeta}/central.sqlite`, '')
  writeFileSync(join(carpeta, MARCA), new Date().toISOString())

  artisan(['migrate', '--database=central', '--path=database/migrations/central', '--force'])
  artisan(['tenants:register', 'Revisión', '127.0.0.1', `${carpeta}/taller.sqlite`])
  // La siembra activa el taller registrado con esta base (historial 2.100): por eso va después del registro.
  artisan(resto.includes('--sin-siembra') ? ['migrate', '--force'] : ['migrate', '--seed', '--force'])

  console.log(`\nEntorno listo en ${carpeta}. Contraseña de todos los usuarios: ${PASSWORD}`)
} else if (accion === 'api') {
  exigirMarca()
  if (await puertoOcupado('http://127.0.0.1:8000')) {
    salir('El puerto 8000 está ocupado: puede ser la API de desarrollo contra `synctaller`. Detenerla antes de revisar.')
  }

  // El servidor de PHP directo, con el mismo router que `artisan serve`, en lugar de `artisan serve`: Laravel lee
  // LARAVEL_STORAGE_PATH solo de $_ENV o $_SERVER (Application::storagePath()), y el `php -S` que lanza `serve` no carga el
  // entorno en $_ENV (variables_order sin `E`), así que usaba backend/storage (deuda #25). Con `variables_order=EGPCS`
  // el storage, el log y los archivos quedan en la carpeta temporal. El router toma `public` del directorio actual.
  const router = join(backend, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')
  const api = spawn('php', ['-d', 'variables_order=EGPCS', '-S', '127.0.0.1:8000', router], {
    cwd: join(backend, 'public'),
    env: variables(extraDe(resto)),
    stdio: 'inherit',
  })
  writeFileSync(join(carpeta, 'api.pid'), String(api.pid))
  api.on('exit', (codigo) => process.exit(codigo ?? 0))
} else if (accion === 'artisan') {
  exigirMarca()
  const separador = resto.indexOf('--')
  if (separador === -1) salir('Uso: entorno.mjs artisan <carpeta> [VAR=valor ...] -- <comando>', 2)
  artisan(resto.slice(separador + 1), extraDe(resto.slice(0, separador)))
} else if (accion === 'detener') {
  exigirMarca()
  detener()
} else if (accion === 'limpiar') {
  exigirMarca()
  detener()
  if (!isAbsolute(carpeta) || carpeta.split('/').length < 4) salir(`Ruta sospechosa: ${carpeta}`)
  // Una API que sigue arriba sin pid registrado quedaría sirviendo una base borrada: detenerla primero.
  await new Promise((r) => setTimeout(r, 1000))
  if (await puertoOcupado('http://127.0.0.1:8000')) {
    salir('El puerto 8000 sigue respondiendo. Detener esa API (su tarea en segundo plano) y volver a limpiar; no se borró nada.')
  }
  console.log(`Entorno creado el ${readFileSync(join(carpeta, MARCA), 'utf8')}`)
  rmSync(carpeta, { recursive: true, force: true })
  console.log(`Borrado ${carpeta}`)
} else {
  salir(`Acción desconocida: ${accion}`, 2)
}
