# SC-007 del Módulo 1 medido en staging

Criterio: el Administrador crea una cuenta nueva y le asigna un rol en **menos de 1 minuto**.
Método: cronómetro desde pulsar «Crear usuario» (con el formulario ya abierto) hasta ver al usuario en el
listado. Se registran **al menos 5 intentos** y el **peor caso** (spec 002, FR-027 y DC-008).

**Fecha**: 2026-10-22 (intentos entre las 08:05 y las 08:35, hora de Lima) · **URL**: `https://sge-peru.chilecentral.cloudapp.azure.com` · **Versión** (`/up`): `v0.2.0` · **Navegador/red**: Brave sobre Windows

| Intento | Hora | Tiempo | Observaciones |
|--:|---|--:|---|
| 1 | 08:05–08:35 | 55,85 s | |
| 2 | 08:05–08:35 | 55,91 s | |
| 3 | 08:05–08:35 | 49,22 s | |
| 4 | 08:05–08:35 | 57,71 s | |
| 5 | 08:05–08:35 | 54,62 s | |
| 6 | 08:05–08:35 | 51,23 s | |

- **Peor caso**: 57,71 s (intento 4)
- **Promedio**: 54,09 s (6 intentos)
- **Veredicto**: ☑ Cumplido (peor caso < 60 s) · ☐ No cumplido

Referencia en local (Módulo 1): 45 s tras T103; limitación conocida de apellidos compuestos (R-19).

## Análisis

- El criterio se cumple, pero con **margen estrecho**: el peor caso queda a 2,29 s del límite de 60 s y 4 de los 6 intentos superan los 54 s.
- Los tiempos son mayores que en local (45 s tras T103), atribuibles a la latencia de red hacia Chile Central y a la máquina de 1 GB (`Standard_B2ats_v2`).
- Las horas individuales no se registraron; se anota el rango de la sesión (08:05–08:35, hora de Lima).
