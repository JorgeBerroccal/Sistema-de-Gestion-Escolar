# Prueba de carga de 1000 usuarios virtuales (SC-006 del Módulo 1)

La rampa 100 → 300 → 600 → 1000 usuarios virtuales (`tests/Load/carga.js`, ~15 min) se lanza **desde
GitHub Actions** (workflow `carga`) contra la URL de staging. GitHub no tiene acceso al servidor, así que
CPU, memoria y tamaño de la máquina los registra `deploy/metrics.sh` **en el servidor**, los **créditos de
CPU** se descargan de Azure Monitor con `deploy/creditos-cpu.sh` **desde tu PC**, y tú reúnes las tres
evidencias. Se ejecuta con la VM en **B2als_v2** (2 vCPU, 4 GB, serie B con CPU *burstable*: base 30 %);
nunca en B2ats_v2 (1 GB) ni en producción.

> **La evidencia de una sesión solo se considera completa si `./deploy/metrics.sh cierre` termina con
> `evidencia_completa: true`**, es decir, si el registro del servidor (`/var/log/sge/tamano.log`) muestra
> que la máquina **regresó a B2ats_v2** después de la prueba. Si dice `EVIDENCIA INCOMPLETA`, la VM sigue
> en el tamaño de carga: vuelve a B2ats_v2 y repite `cierre` antes de cualquier otra cosa. Cada hora en
> B2als_v2 cuesta ≈ 0,053 USD; olvidarla encendida una semana ≈ 8,84 USD (6,62 USD más que B2ats_v2).

Prerrequisitos: variable de repositorio `STAGING_URL` en GitHub (Settings → Secrets and variables →
Actions → Variables), servidor desplegado, cuota de la familia **BASv2** (≥ 2 vCPU libres; el cambio de
tamaño no suma, porque la VM es una sola) y la CLI de Azure (`az login`) en tu PC para el paso 13.

> **Inicio de sesión de `az`:** en la cuenta de Azure for Students, `az login --use-device-code` lo bloquea la
> política de seguridad (error `AADSTS530035`). Usa el inicio con navegador indicando el tenant:
> `az login --tenant <id-del-tenant>` (se obtiene en Portal → Microsoft Entra ID → Overview).

## Créditos de CPU: por qué importa en una máquina burstable

B2als_v2 solo garantiza el **30 %** de su CPU (base); por encima de eso gasta *créditos* (1 crédito = 1 vCPU
al 100 % durante 1 minuto). Con 2 vCPU al 100 % el consumo neto es `(30×2 − 100×2)/100 = −1,4 créditos/min`.
Con la VM encendida y ociosa acumula **36 créditos/hora** hasta un máximo de **864**; una VM nueva o
redimensionada parte de ≈ **60** (confirmado el 21/10/2026: el *Resize* **no conserva** el saldo). Si los créditos llegan a
cero, la CPU **queda limitada al 30 %** y la prueba deja de medir lo que el servidor puede dar con CPU plena.
Por eso:

- Antes de la rampa hay que tener **≥ 200 créditos** (≈ 143 min de CPU al 100 %; recomendado ≥ 400). De 60 a
  200 tardan ≈ 4 h con la VM encendida y sin carga; a 400, ≈ 9–10 h; hasta 864, ≈ 22 h (≈ 1,2 USD).
  **Cambia el tamaño la noche anterior** (≥ 10 h antes de la rampa) para llegar a 400. Los créditos **no se
  acumulan con la VM liberada**: la espera es con la VM en B2als_v2 y en marcha.
- Durante toda la prueba se registra «CPU Credits Remaining» (paso 13). La evidencia debe decir si llegaron a
  cero y en qué minuto de la rampa. **Si se agotan, la prueba se registra igual**, con el minuto desde el que
  la CPU quedó limitada (`creditos-resumen.json`), y el análisis lo declara.
- Para seguirlos en vivo: Portal → VM → *Monitoring* → *Metrics* → métrica **CPU Credits Remaining**
  (agregación *Avg*, 1 minuto) junto con *Percentage CPU*.

## Lista de verificación de la sesión (marcar cada paso en el registro de la sesión)

Copia `specs/001-auth-roles-rbac/evidencias/k6-sesion-plantilla.md` como `k6-sesion-<fecha>.md` y marca
las casillas a medida que avanzas.

- [ ] **1.** 48 h antes: cuota BASv2 confirmada y gasto acumulado con ≥ 5 USD de holgura.
- [ ] **2.** Respaldo manual: `./deploy/backup.sh manual`.
- [ ] **3.** *La noche anterior* (≥ 10 h antes de la rampa; el *Resize* reinicia los créditos a ≈ 60): Portal: **Stop** de la VM y esperar *Stopped (deallocated)* (solo «Stopped» sigue facturando).
- [ ] **4.** Portal → VM → *Size* → **B2als_v2** → *Resize*. Anotar la hora (UTC) de inicio de facturación.
- [ ] **5.** Portal: **Start**. La IP y el nombre DNS no cambian; `ssh sge@<fqdn>`. Anotar la hora UTC del arranque (será `<desde-utc>` del paso 13).
- [ ] **6.** `.env`: `PERFIL_TAMANO=carga` → `./deploy/deploy.sh --reconfigurar` → `curl https://<fqdn>/up` correcto.
- [ ] **7.** `./deploy/metrics.sh tamano` imprime `Standard_B2als_v2`.
- [ ] **8.** Sembrar los 1000 usuarios de prueba (solo staging): `docker compose -p sge -f docker-compose.prod.yml exec app php artisan db:seed --class=LoadTestSeeder --force`.
- [ ] **9.** Humo: GitHub → Actions → **carga** → *Run workflow* con `humo` marcado, `tamano_vm=Standard_B2als_v2`, `version=<etiqueta desplegada>`; debe terminar en verde.
- [ ] **10.** **Créditos de partida**: en *Metrics*, «CPU Credits Remaining» **≥ 200** (recomendado ≥ 400). Si es menor, esperar con la VM encendida y sin carga, y volver a mirar (36 créditos/h). Anotar valor y hora UTC. Se repite antes de cada nueva rampa.
- [ ] **11.** `./deploy/metrics.sh start` (imprime la hora UTC de inicio de la rampa; anotarla) y a continuación lanzar **carga** con `humo` desmarcado (rampa completa).
- [ ] **12.** Al terminar el workflow: `./deploy/metrics.sh stop`; descargar `/var/log/sge/metricas-*.csv` y `meta-servidor.json` (`scp`), y el artefacto `evidencia-carga-<run_id>` de GitHub.
- [ ] **13.** **Créditos de CPU** (en tu PC, ≥ 10 min después del `stop`): `./deploy/creditos-cpu.sh <desde-utc> <fin-utc> <inicio-rampa-utc>` → `creditos-cpu-*.csv` y `creditos-resumen.json` (créditos al iniciar, mínimo y ¿agotados?, minuto de la rampa en que la CPU quedó limitada).
- [ ] **14.** Copiar la evidencia a `specs/001-auth-roles-rbac/evidencias/` y resumir umbrales, picos y créditos en el registro de la sesión (si hay otra repetición en la misma sesión, volver al paso 10).
- [ ] **15.** `.env`: `PERFIL_TAMANO=diario` → `./deploy/deploy.sh --reconfigurar`.
- [ ] **16.** **Volver a B2ats_v2** (último paso): Portal **Stop** (*deallocated*) → *Size* **B2ats_v2** → **Start**; `curl https://<fqdn>/up`; `./deploy/metrics.sh cierre` → **`evidencia_completa: true`**. Solo entonces descargar `cierre-carga.json` y hacer commit de la evidencia.

## Qué contiene la evidencia

| Archivo | Origen | Contenido |
|---|---|---|
| `k6-resumen.json`, `k6-salida.txt`, `meta.json` | Artefacto de GitHub Actions (90 días) | Resumen de k6 con la evaluación de cada umbral, la salida completa, URL, versión declarada e informada por `/up`, tamaño declarado, commit y run ID |
| `metricas-AAAAMMDD-HHMM.csv` | Servidor (`metrics.sh`) | Cada ~5 s: CPU %, carga, memoria y swap del servidor, y CPU/memoria de `app`, `db`, `redis`, `web` |
| `meta-servidor.json` | Servidor | Tamaño real (metadatos de Azure), vCPU, RAM, **modelo de CPU** (Basv2 corre sobre AMD EPYC Milan), versión, perfil, inicio y fin |
| `cierre-carga.json` | Servidor (`metrics.sh cierre`) | `evidencia_completa`, horas aproximadas en B2als_v2 (incluida la espera de créditos) y su costo |
| `creditos-cpu-*.csv`, `creditos-resumen.json` | Tu PC (`creditos-cpu.sh`, Azure Monitor) | «CPU Credits Remaining/Consumed» y «Percentage CPU» cada minuto; créditos al iniciar, mínimo, si se agotaron y el minuto de la rampa en que la CPU quedó limitada |

Umbrales de SC-006 del Módulo 1 (DC-006 de la spec 002): login p95 < 3,5 s, consultas p95 < 1,5 s,
solicitudes fallidas < 1 %. Si los créditos de CPU se agotaron durante la rampa, la ejecución se guarda y se
analiza con el minuto desde el que la CPU quedó limitada (los umbrales medidos después de ese minuto reflejan
la CPU base, no la capacidad de la máquina); para declarar el criterio cumplido sirve una ejecución que los
satisfaga, tenga `evidencia_completa: true` y se haya hecho con créditos suficientes. Si no se cumplen, la evidencia **se guarda igual** y se registra el análisis
(picos de CPU/memoria, cuello de botella); el criterio solo se declara cumplido con una ejecución que lo
satisfaga y tenga `evidencia_completa: true`.

## Si el servidor se cuelga durante la prueba

Síntomas: `/up` no responde, `ssh` no entra o el CSV deja de crecer. Aunque la prueba haya fallado, **la
sesión no termina hasta volver a B2ats_v2**:

1. Dejar terminar o cancelar el workflow `carga` (*Cancel workflow*); su artefacto se sube igual.
2. Portal → VM → **Restart** (o **Stop** → **Start** si no responde) y esperar a poder entrar por SSH.
3. Si el muestreo quedó a medias: `./deploy/metrics.sh stop` y descargar el CSV parcial. La evidencia
   hasta el punto de falla se conserva y se anota como «servidor colgado».
4. `docker compose -p sge -f docker-compose.prod.yml ps` y `curl https://<fqdn>/up`; si algún contenedor
   no volvió, `./deploy/deploy.sh --reconfigurar`.
5. Seguir en el paso 15 de la lista: perfil `diario`, **Stop → B2ats_v2 → Start** y
   `./deploy/metrics.sh cierre` hasta obtener `evidencia_completa: true`. No dejar la VM en B2als_v2 «para
   reintentar mañana»: un nuevo intento es otra sesión que empieza en el paso 1.
6. Registrar la hora del fallo, la causa probable (memoria/swap/CPU del CSV) y qué ajustar del perfil
   `carga` (`FPM_*`, `MYSQL_*`) antes del siguiente intento. No se cambia el costo de bcrypt.

## Costo de la sesión

Horas en B2als_v2 × 0,0526 USD (lo calcula `cierre`, **incluida la espera para acumular créditos**; contrástalo con
*Cost analysis*). El presupuesto contempla 2 sesiones (una completa y una de repetición) con hasta 24 h de acumulación y 4 h de prueba cada una
(56 h ≈ 2,95 USD en total).
