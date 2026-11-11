// Prueba de carga del login y consultas (SC-006, research R-12).
//   Rampa completa (1000 VUs, ~15 min):  k6 run /scripts/carga.js
//   Prueba de humo (10 VUs, ~30 s):      k6 run -e SMOKE=1 /scripts/carga.js
//   Otro servidor:                       k6 run -e BASE_URL=https://staging.ejemplo.pe /scripts/carga.js
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://web';
const SMOKE = __ENV.SMOKE === '1';

export const options = {
  // El login ocurre una sola vez por VU: sin esto k6 vacía las cookies entre iteraciones, las peticiones siguientes
  // llegan sin sesión, Laravel las redirige a /login y devuelven 200 de la página de login (ver research.md del
  // Módulo 5, hallazgo A2: las consultas del Módulo 1 medían la página de login, no /inicio ni /admin/usuarios).
  noCookiesReset: true,
  scenarios: {
    carga: {
      executor: 'ramping-vus',
      startVUs: 0,
      gracefulRampDown: '30s',
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
  thresholds: {
    'http_req_duration{name:login}': ['p(95)<3500'],
    'http_req_duration{name:consulta}': ['p(95)<1500'],
    http_req_failed: ['rate<0.01'],
  },
};

let sesionIniciada = false; // estado por VU: el login solo ocurre en su primera iteración
let nombreUsuario = ''; // «Carga Usuario 0007»: lo que el encabezado muestra al usuario con sesión

function iniciarSesion() {
  const pagina = http.get(`${BASE_URL}/login`, { tags: { name: 'login_form' } });
  const token = pagina.html().find('input[name=_token]').first().attr('value');
  const usuario = `carga${String(__VU).padStart(4, '0')}`;

  const res = http.post(
    `${BASE_URL}/login`,
    { _token: token, login: usuario, password: 'password' },
    { tags: { name: 'login' } },
  );

  const ok = check(res, { 'login termina en /inicio': (r) => r.url.endsWith('/inicio') });
  sesionIniciada = ok;
  nombreUsuario = `Carga Usuario ${String(__VU).padStart(4, '0')}`;
}

export default function () {
  if (!sesionIniciada) {
    iniciarSesion();
  } else {
    const inicio = http.get(`${BASE_URL}/inicio`, { tags: { name: 'consulta' } });
    // 200 de verdad: la página con la sesión de este VU (su nombre en el encabezado), no la de login.
    check(inicio, {
      'inicio 200': (r) => r.status === 200 && !r.url.endsWith('/login') && r.body.includes(nombreUsuario),
    });

    const q = ['', 'Carga', 'Usuario 01', 'carga05'][Math.floor(Math.random() * 4)];
    const pagina = 1 + Math.floor(Math.random() * 10);
    const lista = http.get(`${BASE_URL}/admin/usuarios?q=${encodeURIComponent(q)}&page=${pagina}`, {
      tags: { name: 'consulta' },
    });
    check(lista, {
      'usuarios 200': (r) => r.status === 200 && !r.url.endsWith('/login') && r.body.includes('Buscar usuarios') && r.body.includes(nombreUsuario),
    });
  }

  sleep(SMOKE ? 1 + Math.random() * 2 : 10 + Math.random() * 20);
}
