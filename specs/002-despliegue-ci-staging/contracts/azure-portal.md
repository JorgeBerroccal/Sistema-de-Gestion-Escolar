# Contrato: configuración en el portal de Azure (la realiza el desarrollador)

Claude Code **no** crea recursos en Azure. Estos son los valores exactos; marcar ⚠ = confirmar en
pantalla antes de crear. Nombres sugeridos entre comillas; cámbialos si prefieres.

## 0. Verificaciones previas (10 min, evitan rehacer la VM)

1. **Costos**: Cost Management → confirmar crédito de 100 USD y fecha de vencimiento.
2. **Regiones permitidas**: al crear el grupo de recursos, "Chile Central" debe aparecer; si no, usar la
   región permitida más cercana (alternativa: Brazil South) y repetir los precios.
3. **Cuotas** (Suscripción → *Usage + quotas* → filtrar por Chile Central): "Total Regional vCPUs" ≥ 4;
   "Standard BASv2 Family vCPUs" ≥ 2 (la VM es una sola: el cambio B2ats_v2 ↔ B2als_v2 no suma vCPU; disponibles 10). No se usan DSv5, DSv4 ni DSv2: ver `research.md` D-01c. Si alguna es 0 o insuficiente,
   *Request increase* (puede tardar horas) **antes** del día de la prueba de carga.
4. **Calculadora de precios** (Chile Central): anotar disco Standard SSD 30 GiB (E4) y IP pública
   Standard estática; ajustar la tabla de costo del plan si difieren mucho.

## 1. Grupo de recursos

Resource groups → Create → Suscripción *Azure for Students*; nombre `rg-sge-staging`; región
**Chile Central**.

## 2. Máquina virtual (Create → Virtual machine)

| Campo | Valor |
|---|---|
| Resource group | `rg-sge-staging` |
| Virtual machine name | `vm-sge-staging` |
| Region | Chile Central |
| Availability options | No infrastructure redundancy required |
| Security type | Standard (sin Trusted Launch obligatorio; si se activa, debe aceptar Gen2) |
| Image | Ubuntu Server 24.04 LTS – x64 **Gen2** |
| VM architecture | x64 (no Arm64) |
| Size | **Standard_B2ats_v2** (2 vCPU, 1 GiB) — 9,64 USD/mes |
| Authentication type | SSH public key |
| Username | `azureuser` (el script crea luego el usuario `sge`) |
| SSH public key source | Use existing public key: pegar tu clave pública (`~/.ssh/id_ed25519.pub`; es la usada en este proyecto, no hay `.pem`) |
| Public inbound ports | **None** (las reglas se definen en §4) |
| OS disk type | **Standard SSD (locally-redundant storage)** — no Premium |
| OS disk size | 30 GiB (default de la imagen) |
| Delete with VM | Activado para NIC e IP solo si quieres limpieza total; **desactivado** para el disco mientras haya datos |
| Encryption | Default (platform-managed) |
| Boot diagnostics | Enable with managed storage (o Disable para ahorrar centavos) |
| Auto-shutdown | Opcional: habilitar a las 23:00 America/Lima **solo si no usas la VM de noche** (ahorra cómputo) |

Networking (misma pantalla): ver §3 y §4; *Public IP* → **Create new** con las opciones de §3.

## 3. IP pública estática con nombre DNS

Public IP addresses → (o desde el asistente de la VM) → crear `pip-sge-staging`:

| Campo | Valor |
|---|---|
| SKU | Standard |
| Assignment | **Static** |
| IP version | IPv4 |
| Routing preference | Microsoft network |
| DNS name label | `sge-<tuapellido>` → URL `sge-<tuapellido>.<región>.cloudapp.azure.com` ⚠ (copiar el FQDN exacto que muestra el portal) |

Verifica desde tu PC: `nslookup <fqdn>` devuelve la IP. Guarda el FQDN: es `APP_URL` y `STAGING_URL`.

## 4. Reglas de red (Network security group `nsg-sge-staging`)

Solo tres reglas de entrada (además de las predeterminadas de Azure, que ya niegan el resto):

| Prioridad | Nombre | Puerto | Protocolo | Origen | Acción |
|--:|---|--:|---|---|---|
| 100 | `ssh` | 22 | TCP | Cualquiera (`*`; tu IP doméstica es dinámica) | Allow |
| 110 | `http` | 80 | TCP | Cualquiera | Allow (redirección y desafío de Let's Encrypt) |
| 120 | `https` | 443 | TCP | Cualquiera | Allow |

Nunca crear reglas para 3306 (MySQL) ni 6379 (Redis). La seguridad de SSH descansa en clave pública +
fail2ban (el script de arranque los configura).

## 5. Primer ingreso

```bash
ssh azureuser@<fqdn>
```

Luego sigue `docs/despliegue/primer-despliegue.md` (ejecuta `deploy/bootstrap-servidor.sh`: swap 2 GB,
`ufw`, fail2ban, Docker, certbot, usuario `sge`, cron de respaldo).

## 6. Alertas de presupuesto (50 %, 75 %, 90 %)

Cost Management + Billing → Cost Management → *Budgets* → Add (ámbito: la suscripción):

| Campo | Valor |
|---|---|
| Name | `credito-estudiante` |
| Reset period | Monthly ⚠ (si el crédito no permite otro periodo, usar mensual y fijar el monto en 100 USD; el gasto acumulado del crédito se revisa además en *Cost analysis*) |
| Creation / Expiration | hoy / 31-12-2026 |
| Amount | 100 USD |
| Alert 1 | Type *Actual*, 50 % (= 50 USD), correo `jorgeby2000@gmail.com` |
| Alert 2 | Type *Actual*, 75 % (= 75 USD), mismo correo |
| Alert 3 | Type *Actual*, 90 % (= 90 USD), mismo correo |

Opcional: alerta adicional *Forecasted* al 100 % para anticipar. ⚠ Algunas suscripciones de Azure for
Students no exponen *Budgets*; en ese caso, usar *Cost alerts* del propio crédito y dejar constancia
(captura) en la evidencia, y revisar *Credits* semanalmente. Verificar con captura de la lista de
alertas (DC-007).

## 7. Lista de verificación de la prueba de carga (el último paso es volver a B2ats_v2)

Marcar cada casilla en el registro de la sesión (`evidencias/k6-sesion-<fecha>.md`). La evidencia no
se considera completa mientras la casilla 16 no esté marcada **y** `metrics.sh cierre` lo confirme. El
tamaño de carga es **B2als_v2** (serie B, CPU *burstable*): se registra «CPU Credits Remaining» durante la prueba
y se exige un mínimo de créditos de partida (casilla 10).

- [ ] 1. 48 h antes: cuota BASv2 confirmada y gasto acumulado con ≥ 5 USD de holgura.
- [ ] 2. Respaldo manual (`./deploy/backup.sh manual`).
- [ ] 3. *La noche anterior* (≥ 10 h antes de la rampa; el *Resize* reinicia los créditos a ≈ 60): Portal: **Stop** (esperar "Stopped (deallocated)"; solo "Stopped" sigue facturando).
- [ ] 4. Portal → *Size* → **B2als_v2** → *Resize*. Anotar hora UTC de inicio de facturación B2als_v2.
- [ ] 5. Portal: **Start**; `ssh`; IP y FQDN sin cambios. Anotar la hora UTC del arranque (`<desde-utc>`).
- [ ] 6. `.env`: `PERFIL_TAMANO=carga`; `./deploy/deploy.sh --reconfigurar`; `curl https://<fqdn>/up` = 200.
- [ ] 7. `./deploy/metrics.sh tamano` imprime `Standard_B2als_v2`.
- [ ] 8. Siembra: `LoadTestSeeder` (solo staging).
- [ ] 9. Humo (`humo=true` en el workflow `carga`) correcto.
- [ ] 10. **Créditos de partida**: Portal → VM → *Metrics* → «CPU Credits Remaining» **≥ 200** (recomendado ≥ 400); si no, esperar con la VM encendida y sin carga (36 créditos/h; de 60 a 400 ≈ 9–10 h) y anotar valor y hora UTC. Se repite antes de cada rampa.
- [ ] 11. `./deploy/metrics.sh start` (anotar la hora UTC de inicio de la rampa); lanzar `carga` (rampa completa) con `tamano_vm` y `version`.
- [ ] 12. `./deploy/metrics.sh stop`; descargar CSV y `meta-servidor.json` (`scp`) y el artefacto de GitHub.
- [ ] 13. En el PC, ≥ 10 min después: `./deploy/creditos-cpu.sh <desde-utc> <fin-utc> <inicio-rampa-utc>` → `creditos-resumen.json` (¿agotados?, ¿en qué minuto de la rampa?).
- [ ] 14. Copiar la evidencia al repositorio y resumir umbrales, picos y créditos (si hay otra repetición, volver al 10).
- [ ] 15. `.env`: `PERFIL_TAMANO=diario`; `./deploy/deploy.sh --reconfigurar`.
- [ ] 16. **Volver a B2ats_v2**: ver §8; terminar con `./deploy/metrics.sh cierre` → `evidencia_completa: true`; solo entonces commitear `cierre-carga.json` junto a la evidencia.

### Si el servidor se cuelga durante la prueba

Síntomas: `/up` no responde, SSH no entra o `metrics.sh` deja de escribir. Aunque la prueba haya
fallado, **la sesión no termina hasta volver a B2ats_v2**:

1. Dejar que el workflow `carga` termine o cancelarlo (*Cancel workflow*); su artefacto se sube igual.
2. Portal → VM → **Restart** (o **Stop** → **Start** si no responde); esperar a poder entrar por SSH.
3. Si `metrics.sh` quedó a medias, ejecutar `./deploy/metrics.sh stop` y descargar el CSV parcial: la
   evidencia hasta el punto de falla se conserva y se anota como "servidor colgado" (FR-022).
4. Verificar `docker compose -f docker-compose.prod.yml ps` y `curl https://<fqdn>/up`; si algún contenedor
   no volvió, `./deploy/deploy.sh --reconfigurar`.
5. Continuar en el paso 15 de la lista: perfil `diario`, **Stop (deallocated) → Size B2ats_v2 → Start**
   y `./deploy/metrics.sh cierre` hasta obtener `evidencia_completa: true`. No dejar la VM en B2als_v2 a
   la espera de "reintentar mañana": un nuevo intento empieza otra sesión desde el paso 1.
6. Registrar en el registro de la sesión la hora del fallo, la causa probable (memoria/swap/CPU en el
   CSV) y las medidas para el próximo intento.

## 7b. Detalle de los pasos de cambio de tamaño (antes)

1. **48 h antes**: confirmar cuota BASv2 (§0.3) y que el gasto acumulado deja ≥ 5 USD de holgura.
2. En tu PC: `ssh sge@<fqdn> 'cd /opt/sge && ./deploy/backup.sh manual'` (respaldo previo).
3. Portal → VM → **Stop** (espera "Stopped (deallocated)"; *Stopped* sin "deallocated" **sigue
   facturando**).
4. VM → *Availability + scale* → **Size** → buscar **B2als_v2** (2 vCPU, 4 GiB) → *Resize*. Si no aparece,
   ver la contingencia de `research.md` D-10.
5. VM → **Start**. Esperar ≈ 2 min; `ssh sge@<fqdn>`; la IP y el FQDN no cambian.
6. En el servidor: editar `/opt/sge/.env` → `PERFIL_TAMANO=carga`; `./deploy/deploy.sh --reconfigurar`.
7. Comprobar: `curl -sS https://<fqdn>/up`; `./deploy/metrics.sh tamano` imprime `Standard_B2als_v2`.
7b. Comprobar los **créditos de CPU** y esperar si hacen falta (casilla 10 de §7).
8. Seguir `docs/despliegue/prueba-de-carga.md` (siembra de usuarios, `metrics.sh start`, lanzar el
   workflow, `metrics.sh stop`, descargar evidencias).

## 8. Vuelta al tamaño económico (después) — **obligatorio el mismo día**

1. En el servidor: `PERFIL_TAMANO=diario` en `.env`; `./deploy/deploy.sh --reconfigurar`.
2. Portal → VM → **Stop** (deallocated) → **Size** → **B2ats_v2** → *Resize* → **Start**.
3. Comprobar `./deploy/metrics.sh tamano` → `Standard_B2ats_v2` y `curl https://<fqdn>/up`.
4. Anotar en el registro de despliegues la hora de inicio y fin en B2als_v2 (costo: horas × 0,0526 USD).
5. Ejecutar `./deploy/metrics.sh cierre`: debe imprimir `evidencia_completa: true`. Si imprime
   `EVIDENCIA INCOMPLETA`, la VM no volvió al tamaño diario: repetir §8 antes de cualquier otra cosa.
6. Mirar *Cost analysis*: sin cargos nuevos de B2als_v2 desde el paso 2.

## 9. Detener la VM cuando no se use

Portal → VM → **Stop** (deallocated). Disco e IP siguen costando (≈ 0,017 USD/h). Para volver: **Start**;
la URL no cambia y los contenedores arrancan solos. Para eliminar todo al terminar el curso: borrar
`rg-sge-staging`.
