# Costo, cambio de tamaño y alertas de presupuesto

Crédito: **100 USD** (Azure for Students, vigente hasta el 16/10/2027) que deben alcanzar hasta la
sustentación del curso (diciembre de 2026 como máximo). Tope de planificación: **80 %** del crédito.

| Tamaño | Uso | Precio |
|---|---|--:|
| **Standard_B2ats_v2** (2 vCPU, 1 GB, base 20 %) | Día a día (demos, verificación manual) | 9,64 USD/mes |
| **Standard_B2als_v2** (2 vCPU, 4 GB, base 30 %, serie B con créditos de CPU) | Solo durante la prueba de carga y la espera previa de créditos | 38,40 USD/mes (≈ 0,0526 USD/h) |

Descartados (research D-01c): D2s_v5 (cuota 0, aumento rechazado), D2s_v4 (`NotAvailableForSubscription` en la
suscripción de estudiante), DS2_v2 (196,37 USD/mes, retiro previsto, riesgo de costo) y B2ls_v2 (42,49 USD/mes).

## Estimación de costo hasta el 31/12/2026

Periodo: 16/10 a 31/12/2026 = **77 días = 1848 h** (730 h/mes). Los precios de cómputo son los
consultados por el desarrollador en el portal; **disco e IP son estimaciones** (no provistas) que
deben confirmarse en la calculadora de precios de Azure para Chile Central.

Cada sesión de carga incluye hasta **24 h de acumulación de créditos** (VM encendida y sin carga) más 4 h de
prueba; 2 sesiones = 56 h en B2als_v2.

| Concepto | Supuesto | USD |
|---|---|--:|
| B2ats_v2 continua | 9,64/730 = 0,013205 USD/h × (1848 − 56) h | 23,66 |
| B2als_v2 en sesiones | 0,052603 USD/h × 56 h (2 × (24 h de créditos + 4 h de prueba)) | 2,95 |
| Disco Standard SSD 30 GiB (E4) | ≈ 3,00 USD/mes × 2,53 meses (se factura aunque la VM esté liberada) | 7,60 |
| IP pública estática (Standard) | ≈ 0,005 USD/h × 1848 h (se factura aunque la VM esté liberada) | 9,24 |
| Tráfico de salida | Dentro del cupo gratuito mensual; reserva | 2,00 |
| **Total** | | **45,45 (45,5 %)** |
| Con 20 % de holgura | | 54,54 (54,5 %) |
| **Tope DC-007 de la spec (80 %)** | | 80,00 |

Lectura: el margen contra el tope es de ≈25 USD **incluso con holgura**. Olvidar B2als_v2 encendida cuesta
8,84 USD por semana (6,62 por encima del diario) y 38,40 USD por mes. Mitigaciones: alertas 50/75/90 %
(50/75/90 USD), recordatorio al final del procedimiento de prueba y comprobación de tamaño en `deploy.sh`
(advierte si detecta B2als_v2 con el perfil `diario`). Las horas de espera de créditos se facturan como B2als_v2:
`metrics.sh cierre` cuenta todas las horas en ese tamaño.

> Los precios de **disco** e **IP** son estimaciones: confírmalos en la calculadora de precios de Azure
> (Chile Central) y actualiza la tabla si difieren. Revisa *Cost analysis* al 15/11 y al 15/12.

## Alertas de presupuesto (50 %, 75 %, 90 %)

Cost Management + Billing → Cost Management → **Budgets** → Add, ámbito: la suscripción.

| Campo | Valor |
|---|---|
| Nombre | `credito-estudiante` |
| Periodo | mensual ⚠ (si el crédito no admite otro periodo, el monto sigue siendo 100 USD y el gasto acumulado se revisa en *Cost analysis*) |
| Vigencia | hoy → 31-12-2026 |
| Monto | 100 USD |
| Alerta 1 | *Actual* 50 % (50 USD) → `jorgeby2000@gmail.com` |
| Alerta 2 | *Actual* 75 % (75 USD) → mismo correo |
| Alerta 3 | *Actual* 90 % (90 USD) → mismo correo |

Opcional: una alerta *Forecasted* al 100 %. Verificar: captura de la lista de alertas guardada en
`specs/002-despliegue-ci-staging/evidencias/Presupuestos.png`. ⚠ Si la suscripción de estudiante no
expone *Budgets*, usar *Cost alerts* del propio crédito y dejar constancia con una captura.

## Cambio de tamaño de la máquina

La VM siempre se **libera** (*Stop* hasta ver «Stopped (deallocated)») antes de cambiar de tamaño: «Stopped»
sin «deallocated» sigue facturando. La IP estática y el nombre DNS no cambian; los datos persisten en el
disco. Tarda 3–6 minutos en cada sentido. Ubuntu Gen2 es compatible con ambos tamaños (misma serie Basv2: Generación 1 y 2, sin disco temporal), de modo
que no hay que recrear la máquina.

### Cambiar a B2als_v2 (antes de la prueba)

> **Hazlo la noche anterior** (≥ 10 h antes de la rampa): el *Resize* reinicia los créditos de CPU a ≈ 60 y solo
> se acumulan (36/h) con la VM encendida. Los pasos 3–5 pueden hacerse por el portal o por la CLI de Azure
> (ver [«Alternativa con la CLI de Azure»](#alternativa-con-la-cli-de-azure) más abajo).

1. **48 h antes**: confirmar cuota BASv2 ([aprovisionamiento-azure.md](aprovisionamiento-azure.md) §0.3) y que el gasto acumulado deja ≥ 5 USD de holgura.
2. En tu PC: `ssh sge@<fqdn> 'cd /opt/sge && ./deploy/backup.sh manual'` (respaldo previo).
3. Portal → VM → **Stop** (espera "Stopped (deallocated)"; *Stopped* sin "deallocated" **sigue
   facturando**).
4. VM → *Availability + scale* → **Size** → buscar **B2als_v2** (2 vCPU, 4 GiB) → *Resize*. Si no aparece,
   contingencia: crear una *Snapshot* del disco del SO, crear desde ella una VM nueva B2als_v2 con la misma IP pública estática y, al terminar, repetir el camino inverso (no se espera necesitarlo: es la misma serie).
5. VM → **Start**. Esperar ≈ 2 min; `ssh sge@<fqdn>`; la IP y el FQDN no cambian.
6. En el servidor: editar `/opt/sge/.env` → `PERFIL_TAMANO=carga`; `./deploy/deploy.sh --reconfigurar`.
7. Comprobar: `curl -sS https://<fqdn>/up`; `./deploy/metrics.sh tamano` imprime `Standard_B2als_v2`.
8. **Créditos de CPU**: Portal → VM → *Metrics* → «CPU Credits Remaining» (*Avg*, 1 min). Hace falta **≥ 200** antes de la rampa (recomendado ≥ 400); si hay menos, deja la VM encendida y sin carga (+36 créditos/h; de 60 a 200 ≈ 4 h) y anota valor y hora.
9. Seguir `docs/despliegue/prueba-de-carga.md` (siembra de usuarios, `metrics.sh start`, lanzar el
   workflow, `metrics.sh stop`, descargar evidencias).

### Volver a B2ats_v2 (después de la prueba) — obligatorio el mismo día

1. En el servidor: `PERFIL_TAMANO=diario` en `.env`; `./deploy/deploy.sh --reconfigurar`.
2. Portal → VM → **Stop** (deallocated) → **Size** → **B2ats_v2** → *Resize* → **Start**.
3. Comprobar `./deploy/metrics.sh tamano` → `Standard_B2ats_v2` y `curl https://<fqdn>/up`.
4. Anotar en el registro de despliegues la hora de inicio y fin en B2als_v2 (costo: horas × 0,0526 USD).
5. Ejecutar `./deploy/metrics.sh cierre`: debe imprimir `evidencia_completa: true`. Si imprime
   `EVIDENCIA INCOMPLETA`, la VM no volvió al tamaño diario: repetir este procedimiento antes de cualquier otra cosa.
6. Mirar *Cost analysis*: sin cargos nuevos de B2als_v2 desde el paso 2.

### Alternativa con la CLI de Azure

Sirve para cambiar el tamaño desde tu PC, y sobre todo cuando el portal falla: **el botón *Stop* del portal
puede devolver un error genérico («Error: error») aunque no haya nada mal (ocurrió el 23/10/2026).
Reintenta una vez; si vuelve a fallar, usa la CLI**, que muestra el error real. Requiere `az login --tenant
<id-del-tenant>` (ver [prueba-de-carga.md](prueba-de-carga.md)). Los nombres son los de
[aprovisionamiento-azure.md](aprovisionamiento-azure.md): grupo `rg-sge-staging`, VM `sge-staging`.

```bash
RG=rg-sge-staging; VM=sge-staging
az vm deallocate -g $RG -n $VM                          # equivale a Stop (deallocated)
az vm resize -g $RG -n $VM --size Standard_B2als_v2     # Standard_B2ats_v2 para volver
az vm start -g $RG -n $VM
az vm show -d -g $RG -n $VM --query "{tamano:hardwareProfile.vmSize, estado:powerState, ip:publicIps}" -o table
```

- Antes de cambiar: `az vm list-vm-resize-options -g $RG -n $VM -o table | grep -i B2` debe listar el tamaño destino.
- Con la VM liberada, `az vm resize` solo cambia el tamaño; no la enciende (por eso el `start` aparte).
- La comprobación final es la misma de siempre: `estado` = `VM running`, `tamano` esperado, `/up` = 200 y
  `./deploy/metrics.sh tamano`.
- Las horas del *Activity log* del portal salen en la zona horaria del navegador: para el registro de la sesión
  conviértelas a UTC (Lima = UTC−5).

## Detener la VM cuando no se use

Portal → VM → **Stop** (deallocated). Disco e IP siguen costando (≈ 0,017 USD/h). Para volver: **Start**;
la URL no cambia y los contenedores arrancan solos. Para eliminar todo al terminar el curso: borrar
`rg-sge-staging`.

El procedimiento completo de la sesión de carga (lista de 16 pasos, cuyo último paso es volver a
B2ats_v2) está en [prueba-de-carga.md](prueba-de-carga.md).

## Riesgo principal: dejar B2als_v2 encendida

Una semana cuesta ≈ 8,84 USD (6,62 más que B2ats_v2) y un mes ≈ 38,40 USD. Mitigaciones: las alertas, el
aviso de `deploy.sh` cuando detecta B2als_v2 con el perfil `diario`, y `./deploy/metrics.sh cierre`, que falla
(`EVIDENCIA INCOMPLETA`) mientras la VM no vuelva a B2ats_v2.
