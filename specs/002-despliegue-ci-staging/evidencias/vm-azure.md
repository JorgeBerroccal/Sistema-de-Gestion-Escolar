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

## Hallazgo: D2s_v4 no disponible (decisión pendiente)

En Chile Central, `Standard_D2s_v4` aparece como `NotAvailableForSubscription` aunque la familia DSv4 tiene
cuota: es una restricción del catálogo de la suscripción de estudiante. El único tamaño no burstable de
2 vCPU disponible es `Standard_DS2_v2` (2 vCPU, 7 GB, 196,37 USD/mes, «se retirará pronto», con disco temporal
local). Análisis y estado en `research.md` D-01c. **Sin cerrar.**
