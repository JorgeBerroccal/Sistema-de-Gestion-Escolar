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

- **Antes del 19/11**: conseguir la clave de Google AI Studio (plan gratuito, sin tarjeta) para el adaptador de
  Gemini; va en `.env`. La clave de Anthropic queda diferida (ver «Riesgo externo resuelto»).

## Riesgo externo resuelto: créditos de la API de Anthropic (Módulo 6)

No es posible comprar créditos de la API de Anthropic por ahora. Plan, que se formalizará en la spec del
Módulo 6:

- El chatbot se diseña con una **interfaz de proveedor (puerto) y adaptadores intercambiables**, elegidos por
  variable de entorno: uno **falso** para pruebas y desarrollo, uno de **Gemini** (plan gratuito de Google AI
  Studio, sin tarjeta) para staging y la demo, y uno de **Claude Haiku** para cuando haya presupuesto.
- Habrá que **enmendar el Principio VI de la constitución** (hoy fija «la API de Claude con un modelo de la
  familia Haiku, por costo»), con esta justificación de costo: sin presupuesto para créditos, el proveedor por
  defecto pasa a ser un plan gratuito, manteniendo Claude Haiku como adaptador disponible.
- **Regla de privacidad**: con un proveedor de plan gratuito, nunca se envían datos reales de alumnos al
  modelo; staging solo tiene datos ficticios (ver README, «en staging solo datos ficticios»).
- La clave de la API de Anthropic deja de ser tarea urgente de esta semana; la clave de Google AI Studio sí
  se necesita antes del 19/11.

## Orden de recorte si hay retraso

1. Amplitud de Asistencia.
2. Boleta a un solo formato.
3. Alcance del chatbot (que responda sobre notas y asistencia). **Nunca su eliminación.**
