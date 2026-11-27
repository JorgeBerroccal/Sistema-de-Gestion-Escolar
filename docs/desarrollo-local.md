# Desarrollo local

Cómo levantar el proyecto con Docker: [README](../README.md#ejecutar-en-local). La aplicación queda en <http://localhost:8080/login>; entra con el administrador definido en `.env`.
En `APP_ENV=local` el seeder crea además usuarios de demostración (uno por rol) y una institución de ejemplo
con niveles, grados, secciones, cursos, tres docentes y algunas asignaciones (`AcademicoDemoSeeder`).

## Datos base y de ejemplo

| Qué | Cómo | Dónde corre |
|---|---|---|
| Roles, permisos, escalas de calificación, tipos de documento y estados de asistencia (datos base, idempotentes) | `php artisan sge:sincronizar-base` | Cada despliegue (lo ejecuta `deploy/deploy.sh` tras `migrate`); nunca crea usuarios |
| Administrador inicial | `DatabaseSeeder` (`AdministradorInicialSeeder`) | Primer despliegue |
| Institución y datos académicos de ejemplo | `php artisan db:seed --class=AcademicoDemoSeeder` | `local` (automático; incluye cuentas `docente.demo*` y asignaciones), `staging` (a mano; **solo datos académicos, sin cuentas**); **rechaza producción** |
| Alumnos y matrículas de ejemplo (Módulo 3, **ficticios**, sin cuentas de usuario) | `php artisan db:seed --class=MatriculaDemoSeeder` | `local` (automático), `staging` (a mano); **rechaza producción** |
| Asistencia de ejemplo (Módulo 4, **ficticia**, sin cuentas de usuario; solo alumnos del rango `9000xxxx`) | `php artisan db:seed --class=AsistenciaDemoSeeder` | `local` (automático), `staging` (a mano, tras `MatriculaDemoSeeder`); **rechaza producción** |
| Criterios y notas de ejemplo (Módulo 5, **ficticios**, sin cuentas de usuario; solo alumnos del rango `9000xxxx`) | `php artisan db:seed --class=EvaluacionDemoSeeder` | `local` (automático), `staging` (a mano, tras `MatriculaDemoSeeder`); **rechaza producción** |
| Datos de la sesión de carga k6 del Módulo 5 (alumnos ficticios `9001xxxx`, cuentas de familia `carga0001…` y `cargadoc01…20` con la contraseña de `CARGA_PASSWORD`, obligatoria fuera de local). `CARGA_TOTAL` fija las cuentas de familia: 1000 por defecto (1020 alumnos); con 2000, 2020 alumnos | `CARGA_PASSWORD=… php artisan db:seed --class=EvaluacionCargaSeeder` | `local`, `staging` (solo justo antes de la sesión; **desactivar las cuentas `carga*` al terminar**); **rechaza producción** |

**Regla operativa: en staging solo datos ficticios** (Ley 29733, Protección de Datos Personales). Los alumnos, apoderados y matrículas incluyen datos personales
de menores (Ley 29733): ni en staging ni en la demo se cargan nombres, documentos o correos reales, y ningún
seeder crea usuarios con contraseña conocida fuera de `local` (staging es público); `UsuariosDemoSeeder` y las cuentas
`docente.demo*` solo existen en `local`.

`sge:sincronizar-base` solo crea lo que falta y no revierte cambios del Administrador; los permisos que se
eliminen o renombren en una versión futura hay que limpiarlos a mano.

## Pruebas

```bash
docker compose exec app ./vendor/bin/pest          # suite completa
```

Las pruebas de navegador necesitan pasos aparte: [pruebas End to End](05-implementacion-y-validacion/pruebas-end-to-end.md).

## Prueba de carga (k6)

```bash
docker compose exec app php artisan db:seed --class=LoadTestSeeder              # 1000 usuarios (CARGA_TOTAL por defecto)
docker compose --profile load run --rm k6 run -e SMOKE=1 /scripts/carga.js      # humo: 10 VUs
docker compose --profile load run --rm k6 run -e BASE_URL=https://<fqdn-del-servidor> /scripts/carga.js  # rampa 1000 VUs
```

La rampa completa se ejecuta contra el servidor desplegado en pruebas, no en local: se lanza desde GitHub
Actions (workflow `carga`) tras seguir [`despliegue/prueba-de-carga.md`](despliegue/prueba-de-carga.md).
`LoadTestSeeder` rechaza producción y no forma parte de `DatabaseSeeder`.

Resultados reales: [sesión del 13/11](../specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-13.md) y [sesión del 25/11](../specs/006-evaluacion-boletas/evidencias/k6-sesion-2026-11-25.md).
