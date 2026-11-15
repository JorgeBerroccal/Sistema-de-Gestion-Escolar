# Calendario de los Módulos 3 a 6 (hasta el 27/11/2026)

Aprobado el 2026-11-04; actualizado el 2026-11-12 (carga k6 adelantada al 13/11 y Módulo 6 iniciado el 12/11). Reglas: constitución 2.2.0 (prueba de humo de ~10 min por módulo, sin criterios
cronometrados, una sola sesión de carga k6 en el Módulo 5).

| Fechas | Módulo | Cierre |
|---|---|---|
| Jue 5 – Lun 9 | 3. Matrícula (`004-matricula`) | `v0.4.0` + prueba de humo |
| Mar 10 – Jue 12 | 4. Asistencia | `v0.5.0` + prueba de humo |
| Dom 15 | Revisión de costos (T083 de la feature 002): *Cost analysis* al 15/11 contra la estimación; proyectado al 31/12 ≤ 80 % del crédito (DC-007); decidir si se detiene la VM hasta la sustentación | Registrada en `specs/002-despliegue-ci-staging/` |
| Vie 13 | 5. Evaluación y Boletas (cierre) | Sesión única de carga k6 el 13 (login, registro de notas, boletas; perfil `carga`, luego `diario`) sobre `v0.6.0` ya desplegada y con humo hecho (adelantada del 17–18) |
| Jue 12 – Sáb 21 | 6. Asistente Virtual (chatbot IA, obligatorio por el curso) | `v0.7.0` + prueba de humo |
| Dom 22 | Congelamiento: suite completa, `pint`, `/speckit-analyze` final, `v1.0.0` | Staging estable |
| Lun 23 – Vie 27 | Informe y sustentación (ensayo el miércoles; margen el jueves) | Entrega el viernes 27 |

## Riesgo externo resuelto: créditos de la API de Anthropic (Módulo 6)

No es posible comprar créditos de la API de Anthropic por ahora. Plan, que se formaliza en la spec del
Módulo 6:

- El chatbot se diseña con una **interfaz de proveedor (puerto) y adaptadores intercambiables**, elegidos por
  variable de entorno: uno **falso** para pruebas y desarrollo y uno de **OpenRouter** (API compatible con
  OpenAI) con el modelo configurable: un modelo gratuito en staging y la demo, y Claude Haiku a través de
  OpenRouter cuando haya presupuesto. **Gemini** (Google AI Studio) queda como plan B documentado, sin
  implementar.
- La constitución 2.3.0 redactó el Principio VI de forma general (puerto de proveedor), sin nombrar
  proveedores, por lo que cambiar de proveedor no exige enmendarla.
- **Regla de privacidad**: el adaptador de OpenRouter con modelos gratuitos declara que no admite datos
  reales; en producción el asistente falla cerrado con ese adaptador. Staging solo tiene datos ficticios
  (ver README, «en staging solo datos ficticios»).
- Las claves de Google AI Studio (plan B) y de OpenRouter van en `.env`; no hay tareas externas pendientes.

## Orden de recorte si hay retraso

1. Amplitud de Asistencia.
2. Boleta a un solo formato.
3. Alcance del chatbot (que responda sobre notas y asistencia). **Nunca su eliminación.**
