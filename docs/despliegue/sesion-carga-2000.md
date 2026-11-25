# Sesión de carga de 2000 usuarios (enmienda 2.4.0 de la constitución)

Sesión **adicional** a la de 1000 usuarios del 13/11 ([`prueba-de-carga.md`](prueba-de-carga.md)), pedida por el
Sprint 2 (hasta 2000 usuarios). Mismos umbrales (`specs/006-evaluacion-boletas/contracts/prueba-de-carga.md`),
mismo script (`tests/Load/evaluacion.js`) y mismos seeders, pero con la carga parametrizada y una máquina mayor.
**No se cambian umbrales ni el costo de bcrypt.** Si no se cumplen, la evidencia se guarda igual y se analiza.

## Limitación: el tamaño lo fijó la cuota de Azure, no el diseño

La sesión se hace en **Standard_B4als_v2** (4 vCPU, 8 GiB) porque es el tamaño máximo que permite la cuota regional de
la suscripción Azure for Students en Chile Central. No es una decisión de diseño: el tamaño previsto era
Standard_B8als_v2 (8 vCPU, 16 GiB).

- `az vm list-usage` (Chile Central): `Standard Basv2 Family vCPUs` uso 2, límite 10; `Total Regional vCPUs` uso 2, límite **6**.
  El portal lo confirma al intentar B8als_v2: «Se necesitan 8 vCPU para esta configuración, pero en su suscripción solo
  quedan 6 vCPU (de 6)».
- La solicitud de aumento de la cuota regional a 10 desde el servicio *Cuotas* fue **rechazada automáticamente** («No pudimos
  completar 1 solicitud»).
- Con 4 vCPU el generador de carga (k6 en un runner de GitHub) y la VM tienen una relación de recursos distinta a la
  prevista; el registro de CPU y memoria del runner y de la VM permite distinguir si el límite fue la VM o el generador.
  Este documento no anticipa el resultado: se mide y se registra como salga.
- Si en el futuro hubiera cuota, el workflow y el perfil también admiten `Standard_B8als_v2`.

## Qué cambia respecto a la sesión de 1000

| Tema | 1000 usuarios | 2000 usuarios |
|---|---|---|
| VM | Standard_B2als_v2 (2 vCPU, 4 GiB) | **Standard_B4als_v2** (4 vCPU, 8 GiB), el máximo que permite la cuota |
| Perfil (`PERFIL_TAMANO`) | `carga` | `carga2000` ([`variables-entorno.md`](../../specs/002-despliegue-ci-staging/contracts/variables-entorno.md)) |
| Siembra | `LoadTestSeeder` y `EvaluacionCargaSeeder` | los mismos con `CARGA_TOTAL=2000`: `carga0001…2000`, 2020 alumnos en `K01…K68`, 20 docentes |
| Workflow `carga` | `carga_total=1000` | `carga_total=2000` (solo con `evaluacion.js` y `tamano_vm` B4als_v2 o B8als_v2) |
| Rampa de `evaluacion.js` | 100 → 300 → 600 → 1000 (14 min) | … → 1000 → **2000** (18 min 30 s) |
| Humo | 10 VUs con las primeras cuentas | 10 VUs con las **10 últimas** cuentas (`carga1991…2000`) |
| Registro del generador | — | `runner-recursos.csv` y `runner-resumen.json` (CPU y memoria del runner de GitHub) en el artefacto |
| Base de datos | queda sembrada (cuentas desactivadas) | **respaldo antes de sembrar y restauración al final** |

### Créditos de CPU de B4als_v2

Valores de la tabla oficial de la serie Basv2 ([Microsoft Learn](https://learn.microsoft.com/azure/virtual-machines/sizes/general-purpose/basv2-series);
espejo en [azure-compute-docs](https://github.com/MicrosoftDocs/azure-compute-docs/blob/main/articles/virtual-machines/sizes/general-purpose/basv2-series.md)):

| Tamaño | vCPU | Base | Créditos iniciales | Acumulación (ocioso) | Máximo acumulable |
|---|--:|--:|--:|--:|--:|
| **Standard_B4als_v2** (la sesión) | 4 | 30 % | **120** | **72 por hora** (4 vCPU × 30 % × 60 min ÷ 100) | 1728 |
| Standard_B8als_v2 (solo si hubiera cuota) | 8 | 30 % | 240 | 144 por hora | 3456 |

- **Consumo.** Cada vCPU al 100 % gasta 1 crédito por minuto. Con las 4 vCPU al 100 %: 4 créditos/min brutos, de los
  que 1,2/min se recuperan por la base → **2,8 créditos/min netos** (`(100 − 30) × 4 / 100`). La rampa de 18 min 30 s
  consume como máximo ≈ **52 créditos netos** (≈ 74 brutos).
- **Umbral mínimo antes de empezar la rampa: 200 créditos**, medidos justo antes de lanzarla (paso 7). Son ≈ 4 veces el
  consumo neto máximo de la rampa, la misma proporción que el umbral de 400 previsto para B8als_v2 (≈ 104 de consumo).
- **Tiempo de espera.** Tras el *Resize* el saldo parte de ≈ 120 (el *Resize* no conserva el saldo). Faltan ≈ 80 créditos:
  80 ÷ 72 por hora ≈ **1 h 07 min** (≈ 1 h 10 min) con la VM encendida y **sin carga**. La siembra y el humo consumen algo,
  por eso el saldo se mide justo antes de la rampa y no al arrancar.
- Si se agotan durante la rampa, la prueba se registra igual con el minuto en que la CPU quedó limitada (`creditos-resumen.json`).

### Costo estimado

**Tarifa: 0,186 USD/h** para Standard_B4als_v2 (135,78 USD/mes en el portal, Chile Central; ≈ 3,5 veces la de B2als_v2,
0,0526 USD/h). Fuente: API pública de precios de Azure
([prices.azure.com](https://prices.azure.com/api/retail/prices), Consumption, Linux), consultada para esta enmienda:
B2als_v2 = 0,0526 USD/h y B4als_v2 = 0,186 USD/h. `deploy/metrics.sh` usa 0,186 en `cierre-carga.json`.

Con ≈ 4 h en B4als_v2 (cambio de tamaño y siembra ≈ 0,5 h, espera de créditos ≈ 1 h 10 min, humo, rampa de 18 min 30 s,
desactivación, restauración y regreso) ≈ 4 × 0,186 ≈ **0,74 USD**. Olvidarla encendida una semana (168 h) ≈ 31 USD: el
último paso no es opcional. Standard_B8als_v2 (solo si hubiera cuota): **0,373 USD/h**, misma fuente (prices.azure.com,
Chile Central, Consumption, Linux: «Virtual Machines Basv2 Series · B8als v2»); `metrics.sh` lo usa por defecto y es
ajustable con `SGE_TARIFA_HORA_B8`.

## Antes del día de la sesión

1. **Integrar y desplegar la preparación.** Los seeders y el perfil `carga2000` van en la imagen: tras integrar el PR de
   preparación hay que etiquetar una versión nueva (`v…`) y desplegarla con el perfil `diario`
   (`./deploy/deploy.sh <etiqueta>`). Anotar la etiqueta: es la `version` del workflow.
2. **Cuotas (48 h antes).** Hacen falta **4 vCPU** libres en la familia BASv2 **y** en el total regional (la VM actual,
   B2ats_v2, ya cuenta 2). Con el límite regional actual de 6 alcanza para B4als_v2 pero no para B8als_v2 (ver la
   limitación arriba). En tu PC:
   ```bash
   az login --tenant <id-del-tenant>
   az vm list-usage -l chilecentral -o table | grep -i -E "BASv2|Total Regional"   # Límite − Uso actual ≥ 4 (la VM actual ya cuenta 2)
   az vm list-vm-resize-options -g rg-sge-staging -n sge-staging -o table | grep -i B4als_v2   # debe aparecer
   ```
   Si no hay cuota para 4 vCPU o el tamaño no aparece, **la sesión no puede empezar**: se detiene y se decide con el
   responsable.
3. **Gasto**: *Cost analysis* con ≥ 5 USD de holgura sobre el tope.
4. **Secreto** `CARGA_PASSWORD` de GitHub Actions vigente (el mismo valor que se pedirá con `read -s` en el servidor).

## Lista de verificación del día

Copia [`specs/006-evaluacion-boletas/evidencias/k6-sesion-2000-plantilla.md`](../../specs/006-evaluacion-boletas/evidencias/k6-sesion-2000-plantilla.md)
como `k6-sesion-<fecha>.md` y marca cada casilla. Variables de la sesión en tu PC: `RG=rg-sge-staging VM=sge-staging`.

- [ ] **1.** **Respaldo antes de sembrar** (servidor): `./deploy/backup.sh manual`; anotar el nombre del archivo
  (`/var/backups/sge/sge-<fecha>-manual.sql.gz`) y copiarlo a tu PC (`scp "sge@<fqdn>:/var/backups/sge/sge-*-manual.sql.gz" ~/respaldos-sge/`).
  Anotar también el número de usuarios: `docker compose -p sge -f docker-compose.prod.yml exec app php artisan tinker --execute="echo App\\Models\\Usuario::count();"` → `<usuarios-antes>`.
- [ ] **2.** **Cambio de tamaño con `az`** (tu PC): 
  ```bash
  az vm deallocate -g $RG -n $VM        # esperar a que termine: queda "deallocated"
  az vm resize -g $RG -n $VM --size Standard_B4als_v2
  az vm start -g $RG -n $VM
  az vm show -d -g $RG -n $VM --query "{tamano:hardwareProfile.vmSize, estado:powerState, ip:publicIps}" -o table
  date -u +%FT%TZ                        # <desde-utc>: arranque tras el Resize (anótalo)
  ```
  Si `az` falla, el error es el real (el portal a veces devuelve «Error: error»). Anotar la hora UTC de inicio de facturación.
- [ ] **3.** Servidor (`ssh sge@<fqdn>`): `.env` → `PERFIL_TAMANO=carga2000` → `./deploy/deploy.sh --reconfigurar` → `curl https://<fqdn>/up` correcto.
- [ ] **4.** `./deploy/metrics.sh tamano` imprime `Standard_B4als_v2`; `nproc` = 4 y `free -m` ≈ 8 GiB.
- [ ] **5.** **Sembrar 2000** (solo staging; `read -s`, nunca en la línea de comandos):
  ```bash
  read -s -p 'CARGA_PASSWORD: ' CARGA_PASSWORD; echo; export CARGA_PASSWORD
  docker compose -p sge -f docker-compose.prod.yml exec -e CARGA_PASSWORD="$CARGA_PASSWORD" -e CARGA_TOTAL=2000 app php artisan db:seed --class=LoadTestSeeder --force
  docker compose -p sge -f docker-compose.prod.yml exec -e CARGA_PASSWORD="$CARGA_PASSWORD" -e CARGA_TOTAL=2000 app php artisan db:seed --class=EvaluacionCargaSeeder --force
  unset CARGA_PASSWORD
  ```
  Comprobar: `…exec app php artisan tinker --execute="echo App\\Models\\Usuario::where('username','like','carga%')->where('activo',true)->count();"` → **2020** (2000 familias + 20 docentes).
  **`CARGA_TOTAL` debe ser el mismo en la siembra, en el workflow (`carga_total`) y en k6**; si no, k6 inicia sesión con cuentas que no existen.
- [ ] **6.** **Humo corto**: GitHub → Actions → **carga** → *Run workflow*: `humo` marcado, `script=evaluacion.js`, `carga_total=2000`, `tamano_vm=Standard_B4als_v2`, `version=<etiqueta>`. Debe terminar en verde (usa `carga1991…2000`: prueba que existen y están vinculadas). Revisar `runner-resumen.json` del artefacto.
- [ ] **7.** **Espera de créditos hasta ≥ 200** (tu PC; VM encendida y **sin carga**, +72/h, ≈ 1 h 07 min desde 120). Para vigilarlo:
  ```bash
  ID="$(az vm show -g $RG -n $VM --query id -o tsv)"
  creditos() { az monitor metrics list --resource "$ID" --metric "CPU Credits Remaining" --interval PT1M --aggregation Average --offset 30m --query "value[0].timeseries[0].data[?average!=null]|[-1].average" -o tsv 2>/dev/null | cut -d. -f1; }
  while c="$(creditos)"; [ "${c:-0}" -lt 200 ]; do echo "$(date -u +%T) créditos: ${c:-sin dato}"; sleep 300; done; echo "listo: ${c} créditos a las $(date -u +%T) UTC"
  ```
  (Azure Monitor retrasa el dato unos minutos. Alternativa: Portal → VM → *Metrics* → «CPU Credits Remaining».) Anotar el valor y la hora UTC.
- [ ] **8.** `./deploy/metrics.sh start` (anotar la hora UTC de inicio de la rampa). Durante la sesión se vigila la columna `swap_usada_mb` del CSV de métricas: **si se observa uso de swap, registrarlo en la evidencia como señal de memoria insuficiente con el perfil `carga2000`** (con la hora y el valor máximo).
- [ ] **9.** **Rampa**: workflow **carga** con `humo` **desmarcado**, `script=evaluacion.js`, `carga_total=2000`, `tamano_vm=Standard_B4als_v2`, `version=<etiqueta>` (≈ 18 min 30 s; el job admite 45).
- [ ] **10.** Al terminar: `./deploy/metrics.sh stop`; descargar `/var/log/sge/metricas-*.csv` y `meta-servidor.json` (`scp`) y el artefacto: `gh run download <run_id> -n evidencia-carga-<run_id>` (trae `k6-resumen.json`, `meta.json`, `runner-recursos.csv`, `runner-resumen.json`).
- [ ] **11.** **Créditos de CPU** (tu PC, ≥ 10 min después del `stop`): `./deploy/creditos-cpu.sh <desde-utc> <fin-utc> <inicio-rampa-utc>`. El mínimo de créditos y si se agotaron (y en qué minuto) van al registro.
- [ ] **12.** Copiar la evidencia a `specs/006-evaluacion-boletas/evidencias/` y resumir umbrales, picos, créditos y el uso del runner en el registro. Si hay cuello de botella, anotar si fue la VM (CPU de `app`/`db`) o el generador (CPU del runner).
- [ ] **13.** **Desactivar las cuentas `carga*`** (servidor): `docker compose -p sge -f docker-compose.prod.yml exec app php artisan tinker --execute="echo App\\Models\\Usuario::where('username','like','carga%')->update(['activo'=>false]);"` → debe imprimir **2020**. Se hace aunque después se restaure: es la red de seguridad si la restauración falla.
- [ ] **14.** **Restaurar la base** al estado del paso 1: `./deploy/restore.sh /var/backups/sge/<archivo-manual-del-paso-1>` (pide escribir `RESTAURAR`; el sitio no responde unos minutos). Comprobar: `/up` correcto, el Administrador inicia sesión, `Usuario::count()` = `<usuarios-antes>` y `where('username','like','carga%')->where('activo',true)->count()` = **0**.
- [ ] **15.** `.env` → `PERFIL_TAMANO=diario` → `./deploy/deploy.sh --reconfigurar`.
- [ ] **16.** **Volver a B2ats_v2** (último paso; tu PC):
  ```bash
  az vm deallocate -g $RG -n $VM
  az vm resize -g $RG -n $VM --size Standard_B2ats_v2
  az vm start -g $RG -n $VM
  az vm show -d -g $RG -n $VM --query "{tamano:hardwareProfile.vmSize, estado:powerState}" -o table
  ```
  Luego `curl https://<fqdn>/up` y en el servidor `./deploy/metrics.sh cierre` → **`evidencia_completa: true`**. Solo entonces descargar `cierre-carga.json` (trae `tamano_de_carga` y el costo estimado) y hacer commit de la evidencia. Revisar *Cost analysis*: sin cargos nuevos de B4als_v2.
- [ ] **17.** *(opcional)* Rotar o borrar el secreto `CARGA_PASSWORD`.

## Si el servidor se cuelga o algo falla

Mismas reglas que en [`prueba-de-carga.md`](prueba-de-carga.md#si-el-servidor-se-cuelga-durante-la-prueba): la sesión **no termina hasta volver a B2ats_v2** (pasos 13 a 16). Si la restauración (paso 14) falla, las cuentas ya están
desactivadas (paso 13): resolver la restauración sin prisa, pero **no dejar la VM en B4als_v2**; la restauración puede
hacerse con la VM ya en B2ats_v2.

- Si el workflow falla en «Comprobar objetivo» con `carga_total`: revisar que `script=evaluacion.js` y `tamano_vm` sea B4als_v2 (o B8als_v2).
- Si k6 reporta muchos `login` fallidos: `CARGA_TOTAL` distinto entre siembra y workflow, o cuentas desactivadas (repetir el paso 5).
- Si el humo falla por memoria o conexiones: ajustar `FPM_*`/`MYSQL_*` del perfil `carga2000` en el `.env` (un valor del `.env` tiene prioridad) y `./deploy/deploy.sh --reconfigurar`; registrar el valor final.
