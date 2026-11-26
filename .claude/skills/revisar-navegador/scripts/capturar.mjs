// Recorre pantallas del frontend con Chrome headless (DevTools Protocol, sin dependencias) y deja capturas y un resumen.
// Correr desde la raíz del monorepo, con la API del entorno de revisión y Vite levantados:
//   node .claude/skills/revisar-navegador/scripts/capturar.mjs --salida <carpeta> --usuarios a@x,b@y --rutas /,/invoices
//        [--pasos pasos.json] [--password ...] [--ancho 1366] [--alto 900]
// Cada usuario entra con su token (login por la API) y recorre las rutas; --pasos agrega acciones (ver SKILL.md).
// Resultado: <salida>/<usuario>-<n>-<nombre>.png y <salida>/resumen.json. Sale con 1 si hubo errores.

import { spawn } from 'node:child_process'
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { join, resolve } from 'node:path'

const opciones = leerOpciones(process.argv.slice(2))
const API = opciones.api ?? 'http://127.0.0.1:8000/api'
const APP = opciones.app ?? 'http://localhost:5173'
const CHROME = opciones.chrome ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const PASSWORD = opciones.password ?? 'Revision.2026'
const TOKEN_KEY = 'synctaller.token' // frontend/src/lib/tokenStorage.ts

if (!opciones.salida || !opciones.usuarios) {
  console.error('Uso: capturar.mjs --salida <carpeta> --usuarios a@x,b@y [--rutas /,/x] [--pasos pasos.json]')
  process.exit(2)
}

const salida = resolve(opciones.salida)
mkdirSync(salida, { recursive: true })
const usuarios = opciones.usuarios.split(',').map((u) => u.trim()).filter(Boolean)
const pasos = opciones.pasos
  ? JSON.parse(readFileSync(opciones.pasos, 'utf8'))
  : (opciones.rutas ?? 'inicio').split(',').map((ruta) => ({ ruta: ruta.trim() }))

// Las rutas pueden ir sin la barra inicial (`inicio` es `/`): Git Bash convierte un argumento `/x` en `C:/…/Git/x`.
for (const paso of pasos) {
  if (paso.ruta === undefined) continue
  if (/^[A-Za-z]:[\\/]/.test(paso.ruta)) {
    console.error(`Ruta convertida por Git Bash: ${paso.ruta}. Escribirla sin la barra inicial o con MSYS_NO_PATHCONV=1.`)
    process.exit(2)
  }
  paso.ruta = paso.ruta === 'inicio' ? '/' : '/' + paso.ruta.replace(/^\/+/, '')
}

function leerOpciones(args) {
  const o = {}
  for (let i = 0; i < args.length; i++) {
    if (args[i].startsWith('--')) o[args[i].slice(2)] = args[i + 1]?.startsWith('--') ? true : args[++i]
  }
  return o
}

const esperar = (ms) => new Promise((r) => setTimeout(r, ms))

async function login(email) {
  const r = await fetch(`${API}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password: PASSWORD }),
  })
  const cuerpo = await r.json().catch(() => ({}))
  if (!r.ok || !cuerpo.data?.token) throw new Error(`Login de ${email}: ${r.status} ${cuerpo.error_code ?? ''} ${cuerpo.message ?? ''}`)
  return cuerpo.data.token
}

async function abrirChrome() {
  const perfil = join(salida, '.perfil-chrome')
  rmSync(perfil, { recursive: true, force: true })
  const chrome = spawn(CHROME, [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0',
    `--user-data-dir=${perfil}`, `--window-size=${opciones.ancho ?? 1366},${opciones.alto ?? 900}`, 'about:blank',
  ], { stdio: 'ignore' })

  // Chrome escribe el archivo con el puerto al abrir; hasta terminar de escribirlo puede estar bloqueado o vacío.
  const archivo = join(perfil, 'DevToolsActivePort')
  let puerto = ''
  for (let i = 0; i < 150 && !puerto; i++) {
    await esperar(100)
    try {
      if (existsSync(archivo)) puerto = readFileSync(archivo, 'utf8').split('\n')[0].trim()
    } catch {
      // Todavía bloqueado: reintentar.
    }
  }
  if (!puerto) {
    chrome.kill()
    throw new Error('Chrome no abrió el puerto de depuración')
  }

  const destinos = await (await fetch(`http://127.0.0.1:${puerto}/json`)).json()
  const pagina = destinos.find((d) => d.type === 'page')
  return { chrome, perfil, ws: pagina.webSocketDebuggerUrl }
}

function conectar(url) {
  const ws = new WebSocket(url)
  let id = 0
  const pendientes = new Map()
  const oyentes = []
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data)
    if (m.id && pendientes.has(m.id)) {
      const { ok, mal } = pendientes.get(m.id)
      pendientes.delete(m.id)
      m.error ? mal(new Error(m.error.message)) : ok(m.result)
    } else if (m.method) {
      oyentes.forEach((f) => f(m.method, m.params))
    }
  }
  const enviar = (method, params = {}) => new Promise((ok, mal) => {
    pendientes.set(++id, { ok, mal })
    ws.send(JSON.stringify({ id, method, params }))
  })
  return new Promise((ok) => { ws.onopen = () => ok({ enviar, oir: (f) => oyentes.push(f), cerrar: () => ws.close() }) })
}

async function evaluar(cdp, expresion) {
  const r = await cdp.enviar('Runtime.evaluate', { expression: expresion, awaitPromise: true, returnByValue: true })
  if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description ?? r.exceptionDetails.text)
  return r.result.value
}

// Espera a que no queden requests en curso durante 700 ms (máximo 15 s).
async function esperarRed(estado) {
  const limite = Date.now() + 15000
  await esperar(300)
  let quieto = 0
  while (Date.now() < limite && quieto < 700) {
    await esperar(100)
    quieto = estado.enCurso.size === 0 ? quieto + 100 : 0
  }
}

const nombreArchivo = (texto) => texto.replace(/^\/+/, '').replace(/[^a-zA-Z0-9-]+/g, '_').slice(0, 40) || 'inicio'

async function main() {
  const { chrome, perfil, ws } = await abrirChrome()
  const cdp = await conectar(ws)
  const estado = { enCurso: new Set(), eventos: [] }

  cdp.oir((metodo, p) => {
    if (metodo === 'Network.requestWillBeSent') estado.enCurso.add(p.requestId)
    if (metodo === 'Network.loadingFinished' || metodo === 'Network.loadingFailed') estado.enCurso.delete(p.requestId)
    if (metodo === 'Network.responseReceived' && p.response.url.startsWith(API) && p.response.status >= 400) {
      estado.eventos.push({ tipo: 'api', detalle: `${p.response.status} ${p.response.url.slice(API.length)}` })
    }
    if (metodo === 'Network.loadingFailed' && !p.canceled) estado.eventos.push({ tipo: 'red', detalle: p.errorText })
    if (metodo === 'Runtime.exceptionThrown') estado.eventos.push({ tipo: 'excepción', detalle: p.exceptionDetails.exception?.description ?? p.exceptionDetails.text })
    if (metodo === 'Runtime.consoleAPICalled' && (p.type === 'error' || p.type === 'warning')) {
      estado.eventos.push({ tipo: `consola.${p.type}`, detalle: p.args.map((a) => a.value ?? a.description ?? '').join(' ').slice(0, 300) })
    }
  })
  await cdp.enviar('Page.enable')
  await cdp.enviar('Network.enable')
  await cdp.enviar('Runtime.enable')

  const navegar = async (ruta) => {
    await cdp.enviar('Page.navigate', { url: APP + ruta })
    await esperarRed(estado)
  }

  const resumen = []
  try {
    for (const usuario of usuarios) {
      const token = await login(usuario)
      await navegar('/login')
      await evaluar(cdp, `localStorage.clear(); localStorage.setItem(${JSON.stringify(TOKEN_KEY)}, ${JSON.stringify(token)})`)

      let n = 0
      for (const paso of pasos) {
        estado.eventos = []
        const registro = { usuario, paso }
        try {
          if (paso.ruta !== undefined) await navegar(paso.ruta)
          if (paso.click) {
            await evaluar(cdp, `(() => { const e = document.querySelector(${JSON.stringify(paso.click)}); if (!e) throw new Error('No existe ${paso.click.replaceAll("'", '')}'); e.click() })()`)
            await esperarRed(estado)
          }
          if (paso.clickTexto) {
            await evaluar(cdp, `(() => { const t = ${JSON.stringify(paso.clickTexto)}; const e = [...document.querySelectorAll('button, a, [role=button], [role=tab], label')].find((x) => x.textContent.trim() === t); if (!e) throw new Error('No hay un botón o enlace "' + t + '"'); e.click() })()`)
            await esperarRed(estado)
          }
          if (paso.escribir) {
            const [selector, texto] = paso.escribir
            await evaluar(cdp, `(() => { const e = document.querySelector(${JSON.stringify(selector)}); if (!e) throw new Error('No existe ${selector.replaceAll("'", '')}'); e.focus(); const set = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(e), 'value').set; set.call(e, ${JSON.stringify(texto)}); e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })) })()`)
          }
          if (paso.esperar) await esperar(paso.esperar)

          registro.url = (await evaluar(cdp, 'location.pathname + location.search'))
          registro.titulo = await evaluar(cdp, `(document.querySelector('.page-title, h1, h2')?.textContent ?? '').trim().slice(0, 120)`)
          // Páginas de error del frontend (src/pages/ErrorPages.tsx): 403 sin permiso, 404 ruta inexistente.
          registro.error = await evaluar(cdp, `(document.querySelector('.empty-header')?.textContent ?? '').trim()`) || undefined
          registro.menu = await evaluar(cdp, `[...document.querySelectorAll('aside a, .navbar a.nav-link')].map((a) => a.textContent.trim()).filter(Boolean)`)
          if (paso.ruta !== undefined && !registro.url.startsWith(paso.ruta.split('?')[0])) registro.redirigido = true

          if (paso.captura !== false) {
            const { data } = await cdp.enviar('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true })
            const nombre = `${nombreArchivo(usuario.split('@')[0])}-${String(++n).padStart(2, '0')}-${nombreArchivo(paso.nombre ?? paso.ruta ?? paso.clickTexto ?? paso.click ?? 'paso')}.png`
            writeFileSync(join(salida, nombre), Buffer.from(data, 'base64'))
            registro.captura = nombre
          }
        } catch (error) {
          registro.fallo = error.message.split('\n')[0]
        }
        registro.eventos = estado.eventos
        resumen.push(registro)
      }
      await evaluar(cdp, 'localStorage.clear()')
    }
  } finally {
    cdp.cerrar()
    chrome.kill()
    await esperar(500)
    rmSync(perfil, { recursive: true, force: true, maxRetries: 5, retryDelay: 300 })
  }

  writeFileSync(join(salida, 'resumen.json'), JSON.stringify(resumen, null, 2))
  let problemas = 0
  for (const r of resumen) {
    const marcas = [
      r.fallo && `FALLO: ${r.fallo}`,
      r.redirigido && `redirigido a ${r.url}`,
      r.error && r.error !== String(r.paso.espera ?? '') && `página ${r.error}`,
      ...r.eventos.map((e) => `${e.tipo}: ${e.detalle}`)].filter(Boolean)
    problemas += marcas.length
    console.log(`${marcas.length ? '✗' : '✓'} ${r.usuario} ${r.paso.nombre ?? r.paso.ruta ?? JSON.stringify(r.paso)} → ${r.url ?? '?'} «${r.titulo ?? ''}» ${r.captura ?? ''}`)
    marcas.forEach((m) => console.log(`    ${m}`))
  }
  console.log(`\n${resumen.length} pasos, ${problemas} problemas. Detalle en ${join(salida, 'resumen.json')}`)
  process.exit(problemas ? 1 : 0)
}

main().catch((error) => {
  console.error(error.message)
  process.exit(1)
})
