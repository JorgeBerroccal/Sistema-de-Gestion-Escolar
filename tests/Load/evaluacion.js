// Sesión única de carga del Módulo 5: login, registro de notas y consulta de boletas (Constitución 2.2.0, FR-016).
// Contrato y umbrales: specs/006-evaluacion-boletas/contracts/prueba-de-carga.md.
//   Rampa completa (1000 VUs, ~14 min):  k6 run /scripts/evaluacion.js
//   Prueba de humo (10 VUs, ~35 s):      k6 run -e SMOKE=1 /scripts/evaluacion.js
//   Otro servidor:                       k6 run -e BASE_URL=https://staging.ejemplo.pe /scripts/evaluacion.js
// Datos: `php artisan db:seed --class=EvaluacionCargaSeeder` (solo local, staging y testing).
//   Contraseña: `-e CARGA_PASSWORD=…` (el mismo valor con el que se sembró; en local, `password` por defecto).
//
// El guardado de notas es un formulario clásico (POST con CSRF), igual que el login: el script lee el token y los
// nombres de los campos de la página, así que no depende del protocolo interno de Livewire.
import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';

const BASE_URL = __ENV.BASE_URL || 'http://web';
const SMOKE = __ENV.SMOKE === '1';

// Contraseña de las cuentas `carga*`: no está en el repositorio. En GitHub Actions llega del secreto CARGA_PASSWORD;
// en local (BASE_URL por defecto, la pila de docker compose) se admite `password`, la de los seeders en local.
const PASSWORD = __ENV.CARGA_PASSWORD || (BASE_URL === 'http://web' ? 'password' : '');
if (!PASSWORD) {
  throw new Error('Falta CARGA_PASSWORD (-e CARGA_PASSWORD=…): la contraseña de las cuentas de carga no está en el repositorio.');
}

// Cuentas del seeder: cargadoc01…20 (docentes) y carga0001…1000 (familias). Cada VU elige su cuenta con
// `(id de VU - 1) % total`: no depende del orden en que k6 reparte los ids entre escenarios.
const DOCENTES = SMOKE ? 2 : 20;
const FAMILIAS = 1000;
// ~3 % de las iteraciones de familias descargan la boleta; el resto consulta notas (`-e PROPORCION_BOLETA=1` para ensayos).
const PROPORCION_BOLETA = Number(__ENV.PROPORCION_BOLETA || 0.03);

export const options = {
  // El login ocurre una sola vez por VU: sin esto k6 vacía las cookies entre iteraciones y las peticiones siguientes
  // llegarían sin sesión (se redirigirían a /login y devolverían 200 de la página de login).
  noCookiesReset: true,
  scenarios: {
    docentes: {
      executor: 'constant-vus',
      vus: DOCENTES,
      duration: SMOKE ? '35s' : '14m',
      gracefulStop: '30s',
      exec: 'docente',
    },
    familias: {
      executor: 'ramping-vus',
      startVUs: 0,
      gracefulRampDown: '30s',
      exec: 'familia',
      stages: SMOKE
        ? [
            { duration: '10s', target: 10 },
            { duration: '20s', target: 10 },
            { duration: '5s', target: 0 },
          ]
        : [
            { duration: '1m', target: 100 },
            { duration: '1m', target: 100 },
            { duration: '1m30s', target: 300 },
            { duration: '1m', target: 300 },
            { duration: '2m', target: 600 },
            { duration: '1m', target: 600 },
            { duration: '2m30s', target: 1000 },
            { duration: '3m', target: 1000 },
            { duration: '1m', target: 0 },
          ],
    },
  },
  // Humo = condiciones normales (SC-006: boleta p95 < 5 s). Rampa = pico de 1000 VUs en 2 vCPU (más holgado).
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{name:login}': ['p(95)<3500'],
    'http_req_duration{name:consulta}': [SMOKE ? 'p(95)<1500' : 'p(95)<2000'],
    'http_req_duration{name:notas}': [SMOKE ? 'p(95)<3000' : 'p(95)<4000'],
    'http_req_duration{name:boleta}': [SMOKE ? 'p(95)<5000' : 'p(95)<8000'],
  },
};

// Estado por VU: el login y el descubrimiento de ids ocurren solo en su primera iteración.
let sesionIniciada = false;
let datos = null;

function iniciarSesion(usuario) {
  const pagina = http.get(`${BASE_URL}/login`, { tags: { name: 'login_form' } });
  const token = pagina.html().find('input[name=_token]').first().attr('value');

  const res = http.post(
    `${BASE_URL}/login`,
    { _token: token, login: usuario, password: PASSWORD },
    { tags: { name: 'login' } },
  );

  sesionIniciada = check(res, { 'login termina en /inicio': (r) => r.url.endsWith('/inicio') });
}

// ---------------------------------------------------------------- familias: consulta de notas y boletas

// 200 de verdad: la página de notas, no la de login a la que redirige una sesión perdida.
function enMisNotas(r) {
  return r.status === 200 && !r.url.endsWith('/login') && r.body.includes('Mis notas');
}

export function familia() {
  if (!sesionIniciada) {
    iniciarSesion(`carga${String(((exec.vu.idInTest - 1) % FAMILIAS) + 1).padStart(4, '0')}`);
    sleep(1);
    return;
  }

  if (datos === null) {
    // «Mis notas» enlaza a la boleta del alumno y del periodo publicado: de ahí salen los ids.
    const pagina = http.get(`${BASE_URL}/notas/mias`, { tags: { name: 'consulta' } });
    const enlace = pagina.body.match(/\/boletas\/(\d+)\/(\d+)/);
    check(pagina, { 'mis notas 200': enMisNotas });
    datos = enlace ? { alumno: enlace[1], periodo: enlace[2] } : false;
  } else if (datos && Math.random() < PROPORCION_BOLETA) {
    const boleta = http.get(`${BASE_URL}/boletas/${datos.alumno}/${datos.periodo}`, { tags: { name: 'boleta' } });
    check(boleta, {
      'boleta 200': (r) => r.status === 200,
      'boleta es PDF': (r) => (r.headers['Content-Type'] || '').includes('application/pdf'),
    });
  } else {
    const consulta = http.get(`${BASE_URL}/notas/mias`, { tags: { name: 'consulta' } });
    check(consulta, { 'mis notas 200': enMisNotas });
  }

  sleep(SMOKE ? 1 + Math.random() * 2 : 10 + Math.random() * 20);
}

// ---------------------------------------------------------------- docentes: registro de notas (POST clásico)

// Valores válidos según la escala que la pantalla declara: «de 0 a 20» (número) o una lista de letras.
function generadorDeValores(html) {
  // Livewire intercala comentarios HTML en el texto: se quitan comentarios y etiquetas antes de leer la escala.
  const texto = html.replace(/<!--[\s\S]*?-->/g, '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ');
  const escala = texto.match(/Escala [^:]+: (.+?) · Celda vacía/);
  const definicion = escala ? escala[1].trim() : 'de 0 a 20';
  const rango = definicion.match(/^de (\d+) a (\d+)$/);

  if (rango) {
    const minimo = Math.min(Number(rango[2]), Number(rango[1]) + 8);
    const maximo = Number(rango[2]);
    return () => String(minimo + Math.floor(Math.random() * (maximo - minimo + 1)));
  }

  const letras = definicion.split(',').map((l) => l.trim()).filter(Boolean);
  return () => letras[Math.floor(Math.random() * letras.length)];
}

function descubrirPar() {
  const lista = http.get(`${BASE_URL}/notas/registro`, { tags: { name: 'notas_lista' } });
  const par = lista.body.match(/\/notas\/registro\/(\d+)\/(\d+)/);
  if (!par) return false;

  const base = `${BASE_URL}/notas/registro/${par[1]}/${par[2]}`;
  const inicial = http.get(base, { tags: { name: 'notas_form' } });
  const select = inicial.body.match(/<select id="periodo"[\s\S]*?<\/select>/);
  const periodos = select ? [...select[0].matchAll(/<option value="(\d+)"/g)].map((m) => m[1]) : [];

  // El periodo abierto es el que muestra la tabla de notas editable (sin «Periodo cerrado»).
  for (const periodo of periodos) {
    const pagina = http.get(`${base}?periodo=${periodo}`, { tags: { name: 'notas_form' } });
    if (pagina.body.includes('name="notas[') && !pagina.body.includes('Periodo cerrado')) {
      return { base, periodo };
    }
  }

  return false;
}

export function docente() {
  if (!sesionIniciada) {
    iniciarSesion(`cargadoc${String(((exec.vu.idInTest - 1) % DOCENTES) + 1).padStart(2, '0')}`);
    sleep(1);
    return;
  }

  if (datos === null) {
    datos = descubrirPar();
    check(datos, { 'hay un periodo abierto con criterios': (d) => d !== false });
    if (datos === false) {
      datos = null; // se reintenta en la siguiente iteración
    }
  }

  if (datos) {
    const pagina = http.get(`${datos.base}?periodo=${datos.periodo}`, { tags: { name: 'notas_form' } });
    const token = pagina.body.match(/name="_token" value="([^"]+)"/);
    const campos = [...pagina.body.matchAll(/name="(notas\[\d+\]\[\d+\])"/g)].map((m) => m[1]);

    if (check(pagina, { 'tabla de notas con campos': () => token !== null && campos.length > 0 })) {
      const valor = generadorDeValores(pagina.body);
      const cuerpo = { _token: token[1], periodo: datos.periodo };
      campos.forEach((campo) => {
        cuerpo[campo] = valor();
      });

      // Formulario clásico con CSRF: 302 de vuelta a la pantalla, que muestra «Notas guardadas».
      const guardado = http.post(datos.base, cuerpo, { tags: { name: 'notas' } });
      check(guardado, {
        'notas guardadas': (r) => r.status === 200 && r.body.includes('Notas guardadas'),
        'sin errores de validación': (r) => !/Fila \d+, columna \d+/.test(r.body),
      });
    }
  }

  sleep(SMOKE ? 3 : 55 + Math.random() * 10);
}
