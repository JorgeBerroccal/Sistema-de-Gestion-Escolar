# HTTPS, red y memoria del servidor (T059, T060)

21/10/2026, hora de Lima (UTC−5). v0.2.0 desplegada en `sge-peru.chilecentral.cloudapp.azure.com` (B2ats_v2, `PERFIL_TAMANO=diario`).

## Verificaciones (T059)

| Verificación | Resultado |
|---|---|
| Inicio de sesión del Administrador | Correcto (ver `despliegue-y-vuelta-atras.md`) |
| `curl -s https://<fqdn>/up` | `{"status":"ok","version":"v0.2.0"}` |
| `http://` | `HTTP/1.1 301 Moved Permanently` (redirige a https) |
| Escaneo externo `nmap -Pn -p 22,80,443,3306,6379` (18:01) | 22, 80 y 443 **open**; 3306 y 6379 **filtered** (DC-002, FR-032) |
| `sudo certbot renew --dry-run` | «all simulated renewals succeeded» (renovación por webroot con la pila en marcha) |
| Permisos de `/opt/sge/.env` | `600 sge` |
| `exec app env \| grep -ci root` | 0 |

Nota: `sge` no tiene `sudo` sin contraseña; los comandos con `sudo` (como `certbot renew`) se ejecutan como `azureuser`.

Pendientes de T059: candado sin advertencias en el navegador (visto en la raíz y en `/up`, falta captura), `opcache.validate_timestamps=0`
y la búsqueda de secretos en el repositorio.

## Memoria en reposo con la pila corriendo (T060)

`free -m`:

| | total | usado | libre | caché | disponible |
|---|--:|--:|--:|--:|--:|
| Mem | 887 | 366 | 53 | 515 | 520 |
| Swap | 2047 | 232 | 1815 | | |

`docker stats --no-stream`:

| Contenedor | CPU | Memoria |
|---|--:|--:|
| sge-web-1 | 3,56 % | 14,8 MiB |
| sge-app-1 | 0,00 % | 40,4 MiB |
| sge-redis-1 | 0,35 % | 4,7 MiB |
| sge-db-1 | 0,32 % | 38,8 MiB |

Lectura: la pila cabe en 1 GB con el perfil `diario`; en reposo usa ≈ 366 MB, deja 520 MB disponibles y ≈ 232 MB en swap
(dato de referencia: parte del swap son páginas inactivas del sistema que `vm.swappiness=10` aún traslada). Los cuatro
contenedores suman ≈ 100 MiB. No hace falta ajustar `PERFIL_TAMANO=diario`.
