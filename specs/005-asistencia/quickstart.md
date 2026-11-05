# Quickstart: validar la Asistencia

**Feature**: `005-asistencia`

Guía de validación; reglas en [data-model.md](data-model.md) y contratos en [contracts/](contracts/).

## Prerrequisitos

```bash
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
# En local, DatabaseSeeder carga también AsistenciaDemoSeeder (datos FICTICIOS). A mano, por ejemplo en staging:
docker compose exec app php artisan db:seed --class=AsistenciaDemoSeeder
```

## 1. Pruebas automáticas

```bash
docker compose exec app php artisan test --filter=Asistencia
docker compose exec app php artisan test        # suite completa, incluye SinCondicionalesDeRolTest
```

Esperado: todo en verde; incluye concurrencia (sin duplicados), reglas de fecha (fin de semana, futuro) y volumen (SC-005).

## 2. Prueba de humo manual en staging (~10 minutos, Constitución 2.2.0)

| # | Como | Acción | Resultado esperado |
|---|---|---|---|
| 1 | Administrador | Menú → Tomar asistencia; abrir una sección con alumnos y un día hábil pasado | Lista con todos «presente» |
| 2 | Administrador | Marcar una falta y una tardanza; guardar; reabrir | Estados persistidos |
| 3 | Administrador | Abrir un sábado | Aviso y sin botón de guardar |
| 4 | Administrador | Justificar la falta con motivo | Pasa a «falta justificada» |
| 5 | Administrador | Reportes → esa sección y rango | Totales y porcentaje coherentes |
| 6 | Padre de Familia (cuenta ficticia vinculada) | Mi asistencia | Ve solo a su hijo, con resumen; sin controles de edición |
| 7 | Padre de Familia | Abrir por URL una sección o un alumno ajeno | 403 |

## 3. Despliegue a staging

1. Etiquetar `v0.5.0` sobre `main`, esperar el workflow `release` y ejecutar `deploy.sh v0.5.0`: tras `migrate`
   corre `sge:sincronizar-base` (permisos nuevos y estados). Comprobar que los permisos suman **24**.
2. Cargar asistencia ficticia: `db:seed --class=AsistenciaDemoSeeder --force`. **En staging solo datos ficticios.**
3. Ejecutar la prueba de humo del §2 y registrar el resultado en `specs/005-asistencia/evidencias/staging.md`,
   dentro del primer commit de la rama del Módulo 5.
