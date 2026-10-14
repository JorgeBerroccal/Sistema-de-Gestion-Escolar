# Sistema de Gestión Escolar (SGE)

**Curso:** Arquitectura de Software (IS-488)
**Universidad:** Universidad Nacional de San Cristóbal de Huamanga
**Escuela Profesional:** Ingeniería de Sistemas

El **SGE** es una aplicación web para gestionar una institución escolar: usuarios, roles y permisos
de acceso, y —en los módulos siguientes— los datos académicos que dependen de ellos.

Este repositorio contiene el **Módulo 1: Autenticación y Roles (RBAC)** — inicio de sesión con
correo o usuario, gestión de usuarios, roles y permisos configurables desde la interfaz, menú por
permisos y un mecanismo de filtros de pertenencia (*fail-closed*) para los módulos 2–6.

## Stack

- PHP 8.3, Laravel 13, Livewire 4, Blade y Tailwind CSS (Vite)
- MySQL 8.0 (datos) y Redis 7 (sesiones, caché y limitador de intentos)
- nginx + PHP-FPM, todo orquestado con Docker Compose
- Pest 4 (pruebas unitarias y de funcionalidad) y k6 (prueba de carga)

## Metodología: Spec-Driven Development con Spec Kit

El desarrollo sigue **Spec-Driven Development** con [GitHub Spec Kit](https://github.com/github/spec-kit):
primero se escribe la especificación y luego el plan, las tareas y el código. Los artefactos de cada
módulo viven en `specs/<módulo>/` (`spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`,
`quickstart.md`, `tasks.md`) y los principios del proyecto en `.specify/memory/constitution.md`.
Módulo 1: [`specs/001-auth-roles-rbac/`](specs/001-auth-roles-rbac/).

## Levantar el proyecto con Docker

Requisitos: Docker con Docker Compose v2.

```bash
cp .env.example .env        # definir SGE_ADMIN_USERNAME, SGE_ADMIN_EMAIL y SGE_ADMIN_PASSWORD
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
docker compose --profile dev run --rm node sh -c "npm install && npm run build"
```

La aplicación queda en <http://localhost:8080/login>. Entra con el administrador definido en `.env`.
En `APP_ENV=local` el seeder crea además usuarios de demostración.

## Pruebas

```bash
docker compose exec app ./vendor/bin/pest          # suite completa
```

**Prueba de carga (k6)** — detalle en [`quickstart.md`](specs/001-auth-roles-rbac/quickstart.md#4-prueba-de-carga-sc-006):

```bash
docker compose exec app php artisan db:seed --class=LoadTestSeeder              # 1000 usuarios
docker compose --profile load run --rm k6 run -e SMOKE=1 /scripts/carga.js      # humo: 10 VUs
docker compose --profile load run --rm k6 run -e BASE_URL=https://staging.ejemplo.pe /scripts/carga.js  # rampa 1000 VUs
```

La rampa completa se ejecuta contra el servidor desplegado en pruebas, no en local.

## Regla de autorización

**Los permisos se verifican por identificador (p. ej. `usuarios.ver`), nunca por nombre de rol.**
Los roles son datos editables desde la interfaz; el código solo conoce permisos.

## Despliegue en producción

- nginx + PHP-FPM, Redis para sesiones y caché; sin Octane.
- `OPCACHE_VALIDATE_TIMESTAMPS=0` y `php artisan optimize` en cada despliegue.
- `APP_ENV=production`, `APP_DEBUG=false`.
- **`LoadTestSeeder` nunca se ejecuta en producción** (lanza excepción) ni forma parte de `DatabaseSeeder`.
