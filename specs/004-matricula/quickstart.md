# Quickstart: validar la Matrícula

**Feature**: `004-matricula`

Guía de validación; reglas en [data-model.md](data-model.md) y contratos en [contracts/](contracts/).

## Prerrequisitos

```bash
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan db:seed --class=AcademicoDemoSeeder
docker compose exec app php artisan db:seed --class=MatriculaDemoSeeder   # alumnos y matrículas FICTICIOS (vínculos solo con los usuarios demo de local)
```

## 1. Pruebas automáticas

```bash
docker compose exec app php artisan test --filter=Matricula
docker compose exec app php artisan test        # suite completa, incluye SinCondicionalesDeRolTest
```

Esperado: todo en verde; incluye concurrencia (una matrícula activa; documento único) y volumen (SC-005).

## 2. Prueba de humo manual en staging (~10 minutos, Constitución 2.2.0)

Solo el flujo principal, con el Administrador y un rol limitado. Lo demás lo cubren las pruebas automáticas.

| # | Como | Acción | Resultado esperado |
|---|---|---|---|
| 1 | Administrador | Ver menú | Aparecen Alumnos y Nóminas |
| 2 | Administrador | Crear un alumno **ficticio** con DNI de 7 dígitos | Rechazo por formato; con 8 dígitos se guarda |
| 3 | Administrador | Repetir el mismo DNI | Rechazo por documento repetido |
| 4 | Administrador | Matricular al alumno en una sección del año vigente; matricular 2 más en forma masiva | Nómina de la sección con los tres |
| 5 | Administrador | Retirar uno con motivo | Sale de la nómina activa; sigue en su historial |
| 6 | Administrador | Crear desde el panel una cuenta Padre de Familia (datos ficticios) y vincularla a un alumno | Vínculo visible en la ficha |
| 7 | Docente o Padre (rol limitado) | Abrir su área (Mis secciones o Mis alumnos); intentar abrir un alumno o sección ajenos por URL | Ve solo lo suyo; lo ajeno da 403 |

## 3. Despliegue a staging (FR-030)

1. Etiquetar `v0.4.0` y ejecutar `deploy.sh`: tras `migrate` corre `sge:sincronizar-base` (permisos nuevos y tipos de documento).
2. Cargar los alumnos y matrículas ficticios (sin cuentas de usuario): `docker compose … exec app php artisan db:seed --class=MatriculaDemoSeeder --force`. **En staging solo datos ficticios.**
3. Ejecutar la prueba de humo del §2 y registrar el resultado en `evidencias/staging.md`.
4. Repetir el despliegue: no duplica permisos ni tipos de documento ni revierte cambios del Administrador.
