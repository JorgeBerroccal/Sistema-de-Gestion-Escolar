# Prueba de carga de 1000 usuarios virtuales (SC-006 del Módulo 1)

La rampa 100 → 300 → 600 → 1000 usuarios virtuales (`tests/Load/carga.js`, ~15 min) se lanza **desde
GitHub Actions** (workflow `carga`) contra la URL de staging. GitHub no tiene acceso al servidor, así que
CPU, memoria y tamaño de la máquina los registra `deploy/metrics.sh` **en el servidor**, y tú reúnes ambas
evidencias. Se ejecuta con la VM en **D2s_v4** (2 vCPU, 8 GB, sin CPU limitada); nunca en B2ats_v2 ni en
producción.

> **La evidencia de una sesión solo se considera completa si `./deploy/metrics.sh cierre` termina con
> `evidencia_completa: true`**, es decir, si el registro del servidor (`/var/log/sge/tamano.log`) muestra
> que la máquina **regresó a B2ats_v2** después de la prueba. Si dice `EVIDENCIA INCOMPLETA`, la VM sigue
> en el tamaño de carga: vuelve a B2ats_v2 y repite `cierre` antes de cualquier otra cosa. Cada hora en
> D2s_v4 cuesta ≈ 0,134 USD; olvidarla encendida una semana ≈ 22,54 USD.

Prerrequisitos: variable de repositorio `STAGING_URL` en GitHub (Settings → Secrets and variables →
Actions → Variables), servidor desplegado y cuota de la familia **DSv4** (no DSv5: su cuota es 0).

## Lista de verificación de la sesión (marcar cada paso en el registro de la sesión)

Copia `specs/001-auth-roles-rbac/evidencias/k6-sesion-plantilla.md` como `k6-sesion-<fecha>.md` y marca
las casillas a medida que avanzas.

- [ ] **1.** 48 h antes: cuota DSv4 confirmada y gasto acumulado con ≥ 5 USD de holgura.
- [ ] **2.** Respaldo manual: `./deploy/backup.sh manual`.
- [ ] **3.** Portal: **Stop** de la VM y esperar *Stopped (deallocated)* (solo «Stopped» sigue facturando).
- [ ] **4.** Portal → VM → *Size* → **D2s_v4** → *Resize*. Anotar la hora de inicio de facturación.
- [ ] **5.** Portal: **Start**. La IP y el nombre DNS no cambian; `ssh sge@<fqdn>`.
- [ ] **6.** `.env`: `PERFIL_TAMANO=carga` → `./deploy/deploy.sh --reconfigurar` → `curl https://<fqdn>/up` correcto.
- [ ] **7.** `./deploy/metrics.sh tamano` imprime `Standard_D2s_v4`.
- [ ] **8.** Sembrar los 1000 usuarios de prueba (solo staging): `docker compose -p sge -f docker-compose.prod.yml exec app php artisan db:seed --class=LoadTestSeeder --force`.
- [ ] **9.** Humo: GitHub → Actions → **carga** → *Run workflow* con `humo` marcado, `tamano_vm=Standard_D2s_v4`, `version=<etiqueta desplegada>`; debe terminar en verde.
- [ ] **10.** `./deploy/metrics.sh start` y a continuación lanzar **carga** con `humo` desmarcado (rampa completa).
- [ ] **11.** Al terminar el workflow: `./deploy/metrics.sh stop`; descargar `/var/log/sge/metricas-*.csv` y `meta-servidor.json` (`scp`), y el artefacto `evidencia-carga-<run_id>` de GitHub.
- [ ] **12.** Copiar la evidencia a `specs/001-auth-roles-rbac/evidencias/` y resumir umbrales y picos en el registro de la sesión (si hay otra repetición en la misma sesión, volver al paso 10).
- [ ] **13.** `.env`: `PERFIL_TAMANO=diario` → `./deploy/deploy.sh --reconfigurar`.
- [ ] **14.** **Volver a B2ats_v2** (último paso): Portal **Stop** (*deallocated*) → *Size* **B2ats_v2** → **Start**; `curl https://<fqdn>/up`; `./deploy/metrics.sh cierre` → **`evidencia_completa: true`**. Solo entonces descargar `cierre-carga.json` y hacer commit de la evidencia.

## Qué contiene la evidencia

| Archivo | Origen | Contenido |
|---|---|---|
| `k6-resumen.json`, `k6-salida.txt`, `meta.json` | Artefacto de GitHub Actions (90 días) | Resumen de k6 con la evaluación de cada umbral, la salida completa, URL, versión declarada e informada por `/up`, tamaño declarado, commit y run ID |
| `metricas-AAAAMMDD-HHMM.csv` | Servidor (`metrics.sh`) | Cada ~5 s: CPU %, carga, memoria y swap del servidor, y CPU/memoria de `app`, `db`, `redis`, `web` |
| `meta-servidor.json` | Servidor | Tamaño real (metadatos de Azure), vCPU, RAM, **modelo de CPU** (Dsv4 corre sobre distintos Intel), versión, perfil, inicio y fin |
| `cierre-carga.json` | Servidor (`metrics.sh cierre`) | `evidencia_completa`, horas aproximadas en D2s_v4 y su costo |

Umbrales de SC-006 del Módulo 1 (DC-006 de la spec 002): login p95 < 3,5 s, consultas p95 < 1,5 s,
solicitudes fallidas < 1 %. Si no se cumplen, la evidencia **se guarda igual** y se registra el análisis
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
5. Seguir en el paso 13 de la lista: perfil `diario`, **Stop → B2ats_v2 → Start** y
   `./deploy/metrics.sh cierre` hasta obtener `evidencia_completa: true`. No dejar la VM en D2s_v4 «para
   reintentar mañana»: un nuevo intento es otra sesión que empieza en el paso 1.
6. Registrar la hora del fallo, la causa probable (memoria/swap/CPU del CSV) y qué ajustar del perfil
   `carga` (`FPM_*`, `MYSQL_*`) antes del siguiente intento. No se cambia el costo de bcrypt.

## Costo de la sesión

Horas en D2s_v4 × 0,1341 USD (lo calcula `cierre`; contrástalo con *Cost analysis*). El presupuesto
estimado contempla 3 sesiones de 4 h más una de holgura (≈ 2,15 USD en total).
