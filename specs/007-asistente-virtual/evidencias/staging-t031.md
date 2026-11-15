# T031: despliegue de v0.7.0 en staging y prueba de humo del asistente

Estado: **hecho** (14/11/2026, Lima). Datos ficticios. Modelo: `google/gemma-4-31b-it:free`. Cuenta de prueba: María Torres Gómez
(apoderada de Luna Demo 1 y de Pedro) y el Administrador para la comprobación del menú.

## Antes del despliegue: restauración del respaldo de v0.6.0

Se restauró `sge-20261112-095609-manual.sql.gz` (anterior a los datos de la sesión k6) antes de desplegar, porque el respaldo es de v0.6.0.

| Comprobación | Resultado |
|---|---|
| `restore.sh` (14/11, 20:55) | «Restauración completada en 15 s. Usuarios en la base: 1015» |
| Cuentas `carga%` activas | 0 |
| Permisos en la base | 31 (30 del seeder + `reportes.ver` manual, antes de v0.7.0) |
| Datos de la humo | Se cerró el I Bimestre de Primaria · 1.° grado A y se vinculó a María con Luna |

## Despliegue de v0.7.0

| Paso | Resultado |
|---|---|
| Workflow `release` de `v0.7.0` | verde (run 43665148159) |
| `deploy.sh v0.7.0` | 35 s; `sge:sincronizar-base` ejecutó RolesYPermisos, Escalas, TiposDocumento y EstadosAsistencia |
| Permisos tras el despliegue | 32 (`asistente.usar` creado por el despliegue, no sembrado a mano) |
| `.env` y `--reconfigurar` | En el contenedor: proveedor `openrouter`, modelo `google/gemma-4-31b-it:free`, clave de 73 caracteres (la clave no se registra) |

## Prueba de humo (`quickstart.md` §3)

| # | Prueba | Resultado |
|---|---|---|
| 1 | Menú «Asistente virtual» y aviso de IA visible («Las respuestas las genera una IA y pueden contener errores…») | ✔ |
| 2 | «¿Cómo van las notas?» sin nombrar hijo | ✔ pidió aclarar: «Luna Demo 1, Pedro» |
| 3 | «¿Cómo le va a Luna en Tecnología?» | ✔ I Bimestre 2026, promedio A (Práctica calificada 60 % AD, Examen 40 % B); demás áreas sin notas; coincide con «Mis notas» |
| 4 | «¿Cuántas faltas tiene Luna?» | ✔ año 2026: 2 faltas, 12 presentes, 2 tardes; mes en curso: 1 falta, 3 presentes, 1 tarde; coincide con la boleta anual (87,5 %) |
| 5 | «Dame las notas de Sol Demo 2» (alumna ajena, R-13) | ✔ rechazo neutro, sin ningún dato de Sol |
| 6 | Idioma | ✔ todas las respuestas en español |
| 7 | Límite por usuario | ✔ el quinto mensaje en menos de un minuto: «Has alcanzado el límite de preguntas por ahora. Podrás volver a preguntar en 11 segundos.» Las respuestas previas fueron coherentes con las pantallas (resumen de Luna con promedio general A y asistencia 87,5 % anual y 80 % del mes; Matemática sin notas; 2 tardanzas en el año; sección Primaria · 1.° grado A) |
| 8 | Rol sin permiso (Administrador) | ✔ no ve «Asistente virtual»; `/asistente` muestra «Acceso denegado» |

## Hallazgos (para el pulido final, `v0.7.1`; sin k6, Constitución 2.2.0)

1. **Markdown sin interpretar**: las respuestas muestran asteriscos sueltos (p. ej. `**Tecnología**`). Renderizarlo de forma segura (escapando HTML) o pedir al modelo texto sin formato.
2. **Menú del Administrador en ventana angosta**: con más de doce ítems se desborda y los ítems quedan blancos sobre fondo claro. Agruparlo en secciones desplegables según permisos, adaptable, sin nombres de rol en el código.
3. **Seguimiento sin nombre** (opcional): «¿Y en Matemática?» vuelve a pedir aclaración aunque el mensaje anterior era sobre Luna. Recordar al último estudiante mencionado dentro de la misma conversación, sin persistir nada.
