# Sesión de carga de 2000 usuarios (enmienda 2.4.0 de la constitución)

Sesión **adicional** a la de 1000 usuarios del 13/11 ([`prueba-de-carga.md`](prueba-de-carga.md)), pedida por el
Sprint 2 (hasta 2000 usuarios). Mismos umbrales (`specs/006-evaluacion-boletas/contracts/prueba-de-carga.md`),
mismo script (`tests/Load/evaluacion.js`) y mismos seeders, pero con la carga parametrizada y una máquina mayor.
**No se cambian umbrales ni el costo de bcrypt.** Si no se cumplen, la evidencia se guarda igual y se analiza.

## Qué cambia respecto a la sesión de 1000

| Tema | 1000 usuarios | 2000 usuarios |
|---|---|---|
| VM | Standard_B2als_v2 (2 vCPU, 4 GiB) | **Standard_B8als_v2** (8 vCPU, 16 GiB) |
| Perfil (`PERFIL_TAMANO`) | `carga` | `carga2000` ([`variables-entorno.md`](../../specs/002-despliegue-ci-staging/contracts/variables-entorno.md)) |
| Siembra | `LoadTestSeeder` y `EvaluacionCargaSeeder` | los mismos con `CARGA_TOTAL=2000`: `carga0001…2000`, 2020 alumnos en `K01…K68`, 20 docentes |
| Workflow `carga` | `carga_total=1000` | `carga_total=2000` (solo con `evaluacion.js` y `tamano_vm` B8als_v2) |
| Rampa de `evaluacion.js` | 100 → 300 → 600 → 1000 (14 min) | … → 1000 → **2000** (18 min 30 s) |
| Humo | 10 VUs con las primeras cuentas | 10 VUs con las **10 últimas** cuentas (`carga1991…2000`) |
| Registro del generador | — | `runner-recursos.csv` y `runner-resumen.json` (CPU y memoria del runner de GitHub) en el artefacto |
| Base de datos | queda sembrada (cuentas desactivadas) | **respaldo antes de sembrar y restauración al final** |

### Créditos de CPU de B8als_v2

Valores de Microsoft ([Basv2](https://learn.microsoft.com/azure/virtual-machines/basv2)): base **30 %**, créditos
iniciales **240**, se acumulan **144 por hora** (8 vCPU × 30 % × 60 min) hasta **3456**. Con las 8 vCPU al 100 % se
consumen `(100 − 30) × 8 / 100 = 5,6` créditos por minuto neto, ≈ 104 créditos en los 18,5 min de la rampa.

- Tras el *Resize* el saldo parte de ≈ 240 (como en B2als_v2, que partía de ≈ 60: el *Resize* no conserva el saldo).
- Para llegar a **≥ 400** hacen falta ≈ 160 créditos más: **≈ 1 h 10 min** con la VM encendida y ociosa. La siembra y el
  humo consumen algo, por eso el saldo **se mide justo antes de la rampa** (paso 10), no al arrancar.
- Si se agotan durante la rampa, la prueba se registra igual con el minuto en que la CPU quedó limitada (`creditos-resumen.json`).

### Costo estimado

B8als_v2 no tiene precio en el repositorio: **estimación ≈ 0,21 USD/h** (4 × B2als_v2, misma serie; `deploy/metrics.sh`
la usa como `SGE_TARIFA_HORA_B8`). Confírmala en la calculadora de precios de Azure antes de la sesión. Con ≈ 4 h en
B8als_v2 (espera de créditos, humo, rampa y limpieza) ≈ 0,85 USD. Olvidarla encendida una semana ≈ 35 USD: el último
paso no es opcional.

## Antes del día de la sesión

1. **Integrar y desplegar la preparación.** Los seeders y el perfil `carga2000` van en la imagen: tras integrar el PR de
   preparación hay que etiquetar una versión nueva (`v…`) y desplegarla con el perfil `diario`
   (`./deploy/deploy.sh <etiqueta>`). Anotar la etiqueta: es la `version` del workflow.
2. **Cuotas (48 h antes).** Hacen falta **8 vCPU libres** en la familia BASv2 **y** en el total regional (una cuenta de
   estudiante suele tener un total regional bajo). En tu PC:
   ```bash
   az login --tenant <id-del-tenant>
   az vm list-usage -l chilecentral -o table | grep -i -E "BASv2|Total Regional"   # Límite − Uso actual ≥ 8 (la VM actual ya cuenta 2)
   az vm list-vm-resize-options -g rg-sge-staging -n sge-staging -o table | grep -i B8als_v2   # debe aparecer
   ```
   Si no hay cuota para 8 vCPU o el tamaño no aparece, **la sesión no puede empezar**: se detiene y se decide con el
   responsable (solicitar aumento de cuota o cambiar de tamaño).
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
  az vm resize -g $RG -n $VM --size Standard_B8als_v2
  az vm start -g $RG -n $VM
  az vm show -d -g $RG -n $VM --query "{tamano:hardwareProfile.vmSize, estado:powerState, ip:publicIps}" -o table
  date -u +%FT%TZ                        # <desde-utc>: arranque tras el Resize (anótalo)
  ```
  Si `az` falla, el error es el real (el portal a veces devuelve «Error: error»). Anotar la hora UTC de inicio de facturación.
- [ ] **3.** Servidor (`ssh sge@<fqdn>`): `.env` → `PERFIL_TAMANO=carga2000` → `./deploy/deploy.sh --reconfigurar` → `curl https://<fqdn>/up` correcto.
- [ ] **4.** `./deploy/metrics.sh tamano` imprime `Standard_B8als_v2`; `nproc` = 8 y `free -m` ≈ 16 GiB.
- [ ] **5.** **Sembrar 2000** (solo staging; `read -s`, nunca en la línea de comandos):
  ```bash
  read -s -p 'CARGA_PASSWORD: ' CARGA_PASSWORD; echo; export CARGA_PASSWORD
  docker compose -p sge -f docker-compose.prod.yml exec -e CARGA_PASSWORD="$CARGA_PASSWORD" -e CARGA_TOTAL=2000 app php artisan db:seed --class=LoadTestSeeder --force
  docker compose -p sge -f docker-compose.prod.yml exec -e CARGA_PASSWORD="$CARGA_PASSWORD" -e CARGA_TOTAL=2000 app php artisan db:seed --class=EvaluacionCargaSeeder --force
  unset CARGA_PASSWORD
  ```
  Comprobar: `…exec app php artisan tinker --execute="echo App\\Models\\Usuario::where('username','like','carga%')->where('activo',true)->count();"` → **2020** (2000 familias + 20 docentes).
  **`CARGA_TOTAL` debe ser el mismo en la siembra, en el workflow (`carga_total`) y en k6**; si no, k6 inicia sesión con cuentas que no existen.
- [ ] **6.** **Humo corto**: GitHub → Actions → **carga** → *Run workflow*: `humo` marcado, `script=evaluacion.js`, `carga_total=2000`, `tamano_vm=Standard_B8als_v2`, `version=<etiqueta>`. Debe terminar en verde (usa `carga1991…2000`: prueba que existen y están vinculadas). Revisar `runner-resumen.json` del artefacto.
- [ ] **7.** **Espera de créditos hasta ≥ 400** (tu PC; VM encendida y **sin carga**, +144/h). Para vigilarlo:
  ```bash
  ID="$(az vm show -g $RG -n $VM --query id -o tsv)"
  creditos() { az monitor metrics list --resource "$ID" --metric "CPU Credits Remaining" --interval PT1M --aggregation Average --offset 30m --query "value[0].timeseries[0].data[?average!=null]|[-1].average" -o tsv 2>/dev/null | cut -d. -f1; }
  while c="$(creditos)"; [ "${c:-0}" -lt 400 ]; do echo "$(date -u +%T) créditos: ${c:-sin dato}"; sleep 300; done; echo "listo: ${c} créditos a las $(date -u +%T) UTC"
  ```
  (Azure Monitor retrasa el dato unos minutos. Alternativa: Portal → VM → *Metrics* → «CPU Credits Remaining».) Anotar el valor y la hora UTC.
- [ ] **8.** `./deploy/metrics.sh start` (anotar la hora UTC de inicio de la rampa).
- [ ] **9.** **Rampa**: workflow **carga** con `humo` **desmarcado**, `script=evaluacion.js`, `carga_total=2000`, `tamano_vm=Standard_B8als_v2`, `version=<etiqueta>` (≈ 18 min 30 s; el job admite 45).
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
  Luego `curl https://<fqdn>/up` y en el servidor `./deploy/metrics.sh cierre` → **`evidencia_completa: true`**. Solo entonces descargar `cierre-carga.json` (trae `tamano_de_carga` y el costo estimado) y hacer commit de la evidencia. Revisar *Cost analysis*: sin cargos nuevos de B8als_v2.
- [ ] **17.** *(opcional)* Rotar o borrar el secreto `CARGA_PASSWORD`.

## Si el servidor se cuelga o algo falla

Mismas reglas que en [`prueba-de-carga.md`](prueba-de-carga.md#si-el-servidor-se-cuelga-durante-la-prueba): la sesión **no termina hasta volver a B2ats_v2** (pasos 13 a 16). Si la restauración (paso 14) falla, las cuentas ya están
desactivadas (paso 13): resolver la restauración sin prisa, pero **no dejar la VM en B8als_v2**; la restauración puede
hacerse con la VM ya en B2ats_v2.

- Si el workflow falla en «Comprobar objetivo» con `carga_total`: revisar que `script=evaluacion.js` y `tamano_vm` sea B8als_v2.
- Si k6 reporta muchos `login` fallidos: `CARGA_TOTAL` distinto entre siembra y workflow, o cuentas desactivadas (repetir el paso 5).
- Si el humo falla por memoria o conexiones: ajustar `FPM_*`/`MYSQL_*` del perfil `carga2000` en el `.env` (un valor del `.env` tiene prioridad) y `./deploy/deploy.sh --reconfigurar`; registrar el valor final.
