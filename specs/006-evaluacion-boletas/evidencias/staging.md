# Evidencia: prueba de humo en staging (Módulo 5, T048 / SC-001, SC-002, SC-006)

**Fecha**: 2026-11-11 · **Versión**: `v0.6.0` · Prueba de ~10 min hecha por el responsable del proyecto con el
Administrador y cuentas ficticias de Docente y Padre de Familia (Constitución 2.2.0). Solo datos ficticios.

## Despliegue

- `./deploy/deploy.sh v0.6.0`: «Comprobación de salud correcta … `/up` informa version=v0.6.0»; «Desplegada v0.6.0 en 43s» (17:29 Lima).
- `./deploy/deploy.sh --estado`: versión activa `v0.6.0`, servida por `/up` `v0.6.0`; `app` y `web` con las imágenes `v0.6.0`; VM `Standard_B2ats_v2` (perfil `diario`); certificado vigente 68 días.
- **Permisos en staging: 31 = 30 del seeder + `reportes.ver`** (creado a mano en el Módulo 1, T070; sin repetidos). El seeder da 30 (23 del Módulo 4 + 7 de evaluación). Convención: distinguir siempre el conteo del seeder del de staging.

## Datos de la prueba

Escala de la institución: **literal**. Sección: Primaria · 1.° grado A. Curso: Tecnología. Periodo: 2026 · I Bimestre.
Cuentas: `padre.humo` (Padre de Familia, vinculada a «Luna Demo 1») y `docente.humo` (Tecnología de 1.° A, reasignada
temporalmente). Alumnos ficticios `9000xxxx`.

## Pasos (quickstart §2)

| Paso | Resultado | Observación |
|---|---|---|
| 1 | ✔ | Criterios «Práctica calificada» 60 % y «Examen» 40 % (total 100 %). El rechazo de 70/40: el reporte no confirma si se probó |
| 2 | ✔ | Luna AD y B; Sol A y A. Promedio del periodo A para ambos (3,2 y 3,0 en equivalentes). El rechazo de «Z» sin guardar nada: el reporte no confirma si se probó o si el campo no permitía escribirlo |
| 3 | ✔ | Cierre del I Bimestre de 1.° grado A: «Periodo cerrado y publicado» |
| 4 | ✔ | Docente: tabla de solo lectura |
| 5 | ✔ | Padre ve solo a Luna: Tecnología AD/B, promedio A, resto «sin notas», promedio general A; Sol no aparece |
| 6 | ✔ | Boletas del I Bimestre y anual descargadas, con el nivel; asistencia del periodo «sin datos» (esperado: la asistencia ficticia es de oct–nov); anual con 16 registros y 87,5 % |
| 7 | ✔ | Al reabrir el periodo desaparece de «Mis notas» del padre |
| Extra US5 | ✔ | Reportes de notas (Admin): Tecnología promedio A, 0 desaprobados; orden de mérito Luna 1.ª y Sol 2.ª; los alumnos sin notas aparecen sin puesto. El Docente, en «Reportes de notas de mis cursos», ve solo 1.° grado A → Tecnología |

## Hallazgos de interfaz (se corrigen en la rama del Módulo 6)

1. Las pantallas del Módulo 5 muestran «1.° grado A» sin el nivel (hay uno de Primaria y otro de Secundaria): mostrar «Primaria · 1.° grado A», como la boleta.
2. El menú «Mis alumnos» suena a docente para un apoderado: texto según permiso («Estudiantes a mi cargo» con `matricula.ser_apoderado`, «Mi ficha» con `matricula.ser_alumno`).
3. La boleta imprime la hora de generación en UTC (23:52 en vez de ≈19:00 Lima): mostrar fechas y horas en la zona horaria de la institución.
4. La boleta usa punto decimal («87.5 %»): usar coma como en el resto del sistema.
5. En el orden de mérito con escala literal, dos alumnos con la misma letra tienen puestos distintos sin que se vea por qué: mostrar el valor de orden o el desempate.

## Seguridad posterior

- `padre.humo` y `docente.humo`: **desactivadas** al terminar; la asignación de Tecnología de 1.° A se devolvió a Luis Fernando Huamán Soto.
- Cuentas `carga*` en staging: desactivadas hasta la sesión k6 (T049).
