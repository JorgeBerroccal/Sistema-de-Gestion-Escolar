# Quickstart: validar la Matrícula

**Feature**: `004-matricula`

Guía de validación; reglas en [data-model.md](data-model.md) y contratos en [contracts/](contracts/).

## Prerrequisitos

```bash
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
# En local, DatabaseSeeder ya carga AcademicoDemoSeeder y MatriculaDemoSeeder (alumnos y matrículas FICTICIOS;
# vínculos solo con los usuarios demo de local). Para cargarlos a mano, por ejemplo en staging:
docker compose exec app php artisan db:seed --class=AcademicoDemoSeeder
docker compose exec app php artisan db:seed --class=MatriculaDemoSeeder
```

## 1. Pruebas automáticas

```bash
docker compose exec app php artisan test --filter=Matricula
docker compose exec app php artisan test        # suite completa, incluye SinCondicionalesDeRolTest
```

Esperado: todo en verde; incluye concurrencia (una matrícula activa; documento único) y volumen (SC-005).

## 2. Prueba de humo manual en staging (~10 minutos, Constitución 2.2.0)

Solo el flujo principal, con el Administrador y un rol limitado (el Padre de Familia del paso 6). Lo demás lo cubren las pruebas automáticas.

| # | Como | Acción | Resultado esperado |
|---|---|---|---|
| 1 | Administrador | Ver menú | Aparecen Alumnos y Nóminas |
| 2 | Administrador | Crear un alumno **ficticio** con DNI de 7 dígitos | Rechazo por formato; con 8 dígitos se guarda |
| 3 | Administrador | Repetir el mismo DNI | Rechazo por documento repetido |
| 4 | Administrador | Matricular al alumno en una sección del año vigente; matricular 2 más en forma masiva | Nómina de la sección con los tres |
| 5 | Administrador | Retirar uno con motivo | Sale de la nómina activa; sigue en su historial |
| 6 | Administrador | Crear desde el panel una cuenta Padre de Familia (datos ficticios) y vincularla a un alumno | Vínculo visible en la ficha |
| 7 | Padre de Familia (la cuenta del paso 6) | Abrir *Estudiantes a mi cargo*; intentar abrir un alumno ajeno y una nómina por URL | Ve solo a su hijo; lo ajeno da 403 |

## 3. Despliegue a staging (FR-030)

1. Etiquetar `v0.4.0` sobre `main` (ya integrada la rama), esperar a que el workflow `release` publique las imágenes y
   ejecutar `deploy.sh v0.4.0`: tras `migrate` corre `sge:sincronizar-base` (permisos nuevos y tipos de documento).
   Comprobar que los permisos suman **20** (19 del seeder más `reportes.ver`, creado a mano en el Módulo 1).
2. Cargar los alumnos y matrículas ficticios (sin cuentas de usuario): `docker compose … exec app php artisan db:seed --class=MatriculaDemoSeeder --force`. **En staging solo datos ficticios.**
3. Ejecutar la prueba de humo del §2. No se repite el despliegue ni se prueba con el Director: ya se demostraron en
   el Módulo 2 y las cubren las pruebas automáticas (Constitución 2.2.0).
4. Registrar el resultado en `specs/004-matricula/evidencias/staging.md`, dentro del **primer commit de la rama del
   Módulo 4**, sin PR aparte.
