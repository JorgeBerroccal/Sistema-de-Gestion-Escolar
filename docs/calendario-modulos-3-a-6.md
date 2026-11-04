# Calendario de los Módulos 3 a 6 (hasta el 27/11/2026)

Aprobado el 2026-11-04. Reglas: constitución 2.2.0 (prueba de humo de ~10 min por módulo, sin criterios
cronometrados, una sola sesión de carga k6 en el Módulo 5).

| Fechas | Módulo | Cierre |
|---|---|---|
| Jue 5 – Lun 9 | 3. Matrícula (`004-matricula`) | `v0.4.0` + prueba de humo |
| Mar 10 – Jue 12 | 4. Asistencia | `v0.5.0` + prueba de humo |
| Dom 15 | Revisión de costos (T083 de la feature 002): *Cost analysis* al 15/11 contra la estimación; proyectado al 31/12 ≤ 80 % del crédito (DC-007); decidir si se detiene la VM hasta la sustentación | Registrada en `specs/002-despliegue-ci-staging/` |
| Vie 13 – Mié 18 | 5. Evaluación y Boletas | Sesión única de carga k6 el 17–18 (login, registro de notas, boletas; perfil `carga`, luego `diario`); `v0.6.0` + prueba de humo |
| Jue 19 – Sáb 21 | 6. Asistente Virtual (chatbot IA, obligatorio por el curso) | `v0.7.0` + prueba de humo |
| Dom 22 | Congelamiento: suite completa, `pint`, `/speckit-analyze` final, `v1.0.0` | Staging estable |
| Lun 23 – Vie 27 | Informe y sustentación (ensayo el miércoles; margen el jueves) | Entrega el viernes 27 |

## Tareas externas

- **Esta semana**: conseguir la clave de la API de Anthropic (requiere comprar créditos con tarjeta); la
  gestiona la persona responsable. Riesgo externo del Módulo 6; debe estar en `.env` antes del 19/11.

## Orden de recorte si hay retraso

1. Amplitud de Asistencia.
2. Boleta a un solo formato.
3. Alcance del chatbot (que responda sobre notas y asistencia). **Nunca su eliminación.**
