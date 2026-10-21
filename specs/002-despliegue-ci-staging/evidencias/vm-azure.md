# Creación de la VM en Azure (T054, T055)

Fecha: 2026-10-20 · Datos aportados por el desarrollador desde el portal y la sesión SSH (no verificados desde el repositorio).

| Elemento | Valor |
|---|---|
| Grupo de recursos | `rg-sge-staging` · Chile Central |
| VM | `sge-staging` · `Standard_B2ats_v2` · Ubuntu Server 24.04 LTS x64 Gen2 · tipo de seguridad Estándar |
| Disco del SO | Standard SSD 30 GiB (29 GB útiles en el servidor) · controladora SCSI · diagnóstico de arranque activado |
| Usuario inicial | `azureuser`, clave ed25519 del desarrollador (acceso SSH verificado) |
| FQDN | `sge-peru.chilecentral.cloudapp.azure.com` · IP pública estática |
| NSG | `sge-staging-nsg` · entrada abierta solo en 22, 80 y 443 |
| Memoria en el servidor | 887 Mi, sin swap (el swap de 2 GB lo crea `bootstrap-servidor.sh`) |

Diferencias con los nombres sugeridos en `contracts/azure-portal.md`: la VM se llama `sge-staging` (no
`vm-sge-staging`), el NSG `sge-staging-nsg`, el FQDN usa la etiqueta `sge-peru`. Los comandos de los documentos
deben leerse con esos nombres.

## Variable de repositorio (T061, primera parte)

`gh variable set STAGING_URL --body "https://sge-peru.chilecentral.cloudapp.azure.com"` → `gh variable list`
muestra `STAGING_URL` con ese valor (2026-10-20 21:17 UTC). Falta ejecutar `carga.yml` con `humo=true` (T061).

## Hallazgo y decisión sobre el tamaño de carga (2026-10-20)

En Chile Central, `Standard_D2s_v4` aparece como `NotAvailableForSubscription` aunque la familia DSv4 tiene
cuota (restricción del catálogo de la suscripción de estudiante). El único no burstable de 2 vCPU disponible era
`Standard_DS2_v2` (2 vCPU, 7 GB, 196,37 USD/mes, «se retirará pronto», con disco temporal local). **Decisión del
desarrollador**: descartar DS2_v2 y toda familia no burstable; la prueba de carga se hace en
`Standard_B2als_v2` (2 vCPU, 4 GB, 38,40 USD/mes, misma familia Basv2, cuota de 10 vCPU) con control de los
créditos de CPU. Detalle y razones: `research.md` D-01c.

## Prueba de ida y vuelta B2ats_v2 ↔ B2als_v2 con el servidor vacío (T071a)

Paso D (B2ats_v2 → B2als_v2) hecho el 21/10/2026, hora de Lima (UTC−5):

| Verificación | Resultado |
|---|---|
| (a) B2als_v2 en *Size* | Aparece y el *Resize* se aplicó |
| (b) Arranque, IP/DNS y SSH | *Start*: 14:44:57 (Registro de actividad). SSH sin advertencias de identidad |
| `free -m` | total 3906 MB · usado 488 · disponible 3417 · swap 0 |
| (d) Métrica «CPU Credits Remaining» | Sí aparece en *Metrics* (Métricas de host de máquina virtual) |
| (c) Créditos tras el *Resize* | ≈ 60 a las 14:47 → ≈ 69 a las 15:02 (promedio 65,27 en 30 min): **no se conservan; se parte de 60** y se acumulan 36/h, como en una VM nueva |

Conclusión (c): el saldo que tuviera la VM en B2ats_v2 no se traslada. Para llegar a ≥ 200 créditos hacen falta
≈ 4 h en reposo, para ≥ 400 ≈ 9–10 h y para 864 ≈ 22 h. Consecuencia: el cambio a B2als_v2 se hace **la noche
anterior** a la rampa (lista de verificación, casillas 3–5 y 10). El costo no cambia: el presupuesto ya contempla
24 h de acumulación por sesión (`plan.md`); con ≈ 12 h bastan (≈ 0,63 USD).

Paso E (B2als_v2 → B2ats_v2) hecho el 21/10/2026, hora de Lima (UTC−5, Registro de actividad):

| Verificación | Resultado |
|---|---|
| *Deallocate* | 15:08:41 |
| *Resize* (Create or Update Virtual Machine) | 15:12:54 |
| *Start* | 15:14:41 |
| *Overview* | Standard B2ats v2 (2 vCPU, 1 GiB), En ejecución, IP 57.156.62.36 y DNS `sge-peru.chilecentral.cloudapp.azure.com` sin cambios |
| SSH | como `azureuser`, sin advertencias de identidad |
| `free -m` | total 887 MB · usado 228 · disponible 658 · swap 0 |

Notas:
- El usuario `sge` **aún no existe**: lo crea `bootstrap-servidor.sh`. Hasta entonces el acceso es con `azureuser`
  (las casillas de la prueba de carga que usan `ssh sge@<fqdn>` valen solo después del bootstrap).
- Ida y vuelta completa: 14:44:57 (Start en B2als_v2) → 15:14:41 (Start en B2ats_v2); ≈ 30 min en B2als_v2, < 0,05 USD.
- El banner de Azure Advisor que sugiere migrar a la serie D se ignora: ver `research.md` D-01c.
