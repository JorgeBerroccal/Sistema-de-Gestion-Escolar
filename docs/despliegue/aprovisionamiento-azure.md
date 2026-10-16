# Aprovisionamiento en Azure (portal)

Pasos exactos para crear el servidor de staging con **Azure for Students**. Los valores marcados con ⚠
deben confirmarse en pantalla antes de crear el recurso. Región: **Chile Central** (la permitida más
cercana a Perú). Tamaño diario **Standard_B2ats_v2** (9,64 USD/mes); tamaño de la prueba de carga
**Standard_D2s_v4** (97,92 USD/mes, solo por horas; ver [costo-y-tamano.md](costo-y-tamano.md)).
`D2s_v5` **no** se usa: la cuota de su familia (DSv5) en la suscripción de estudiante es 0 y el aumento
fue rechazado.

Después de crear los recursos, sigue [primer-despliegue.md](primer-despliegue.md).

## 0. Verificaciones previas (10 min, evitan rehacer la VM)

1. **Costos**: Cost Management → confirmar crédito de 100 USD y fecha de vencimiento.
2. **Regiones permitidas**: al crear el grupo de recursos, "Chile Central" debe aparecer; si no, usar la
   región permitida más cercana (alternativa: Brazil South) y repetir los precios.
3. **Cuotas** (Suscripción → *Usage + quotas* → filtrar por Chile Central): "Total Regional vCPUs" ≥ 4;
   "Standard BASv2 Family vCPUs" ≥ 2; "Standard DSv4 Family vCPUs" ≥ 2 (DSv5 tiene cuota 0 y su aumento fue rechazado: no usarla). Si alguna es 0 o insuficiente,
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
| SSH public key source | Generate new key pair (nombre `sge-staging-key`, **descargar el `.pem` y guardarlo fuera del repositorio**, `chmod 600`), o pegar tu clave pública existente (`~/.ssh/id_ed25519.pub`) |
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
chmod 600 ~/Descargas/sge-staging-key.pem
ssh -i ~/Descargas/sge-staging-key.pem azureuser@<fqdn>
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
