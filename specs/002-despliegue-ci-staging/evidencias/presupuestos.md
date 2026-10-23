# Presupuestos y alertas de costo (T051–T053, FR-023, DC-007)

Hechos por el desarrollador el 16/10/2026 en la suscripción *Azure for Students*. Captura: [`Presupuestos.png`](Presupuestos.png). Los avisos llegan por correo a `jorgeby2000@gmail.com`.

| Presupuesto | Periodo | Monto | Alertas (costo real) |
|---|---|--:|---|
| `credito-estudiante` | Anual | 100 USD | 50 % (50 USD), 75 % (75 USD), 90 % (90 USD) |
| `gasto-mensual` | Mensual | 25 USD | 80 % (20 USD), 100 % (25 USD) |

## Por qué dos presupuestos

- **`credito-estudiante` (anual)** sigue el **crédito acumulado**: el crédito de 100 USD no se renueva cada mes, y este presupuesto lo mide contra el gasto desde el inicio de la suscripción. Es el que protege el recurso escaso (FR-023, DC-007).
- **`gasto-mensual`** es una **alarma temprana**: con un gasto esperado de ≈ 45 USD en 77 días (≈ 18 USD/mes, [plan](../plan.md#costo-estimado-hasta-el-31122026)), superar 20 USD en un mes indica algo anómalo (p. ej. B2als_v2 olvidada encendida, que cuesta 38,40 USD/mes) semanas antes de que el presupuesto anual lo muestre.

## Verificaciones previas (T051, T052)

Realizadas el 16/10/2026 (región Chile Central permitida, cuotas BASv2 y precios de disco e IP). Los valores que se usaron figuran en `vm-azure.md` y en la tabla de costos del plan.
