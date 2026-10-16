# Certificado HTTPS (Let's Encrypt)

El sitio usa un certificado de Let's Encrypt para el nombre DNS de Azure (`*.cloudapp.azure.com`).

## Emisión inicial

La hace `deploy/bootstrap-servidor.sh` con `certbot certonly --standalone` (el puerto 80 está libre porque
la pila aún no se levantó). Después deja la renovación configurada con el método **webroot**
(`/var/www/certbot`, que nginx sirve en `/.well-known/acme-challenge/`), de modo que renovar no interrumpe
el sitio, y un gancho que recarga nginx (`/etc/letsencrypt/renewal-hooks/deploy/sge-recargar-nginx.sh`).

## Renovación automática

El temporizador `certbot.timer` (instalado con el paquete) intenta renovar dos veces al día y renueva
cuando faltan menos de 30 días. Comprobarlo:

```bash
systemctl list-timers certbot.timer
sudo certbot renew --dry-run          # simula la renovación; debe terminar sin errores
sudo certbot certificates             # fecha de vencimiento
cd /opt/sge && ./deploy/deploy.sh --estado    # días de vigencia
```

## Si algo falla

- **El desafío falla** (`Timeout during connect`, `NXDOMAIN`): comprobar que el nombre DNS resuelve a la
  IP de la VM (`nslookup <fqdn>`), que el NSG de Azure permite el puerto 80 y que `ufw status` lo
  permite. Mientras no haya certificado, **no se sirve nada por HTTP**: nginx redirige a HTTPS y no
  arranca sin certificado, así que las credenciales nunca viajan sin cifrar.
- **Límites de Let's Encrypt** (5 certificados duplicados por semana): esperar una semana o usar
  `--staging` solo para pruebas; no repetir `certonly` en bucle.
- **El certificado venció**: `sudo certbot renew --force-renewal` y
  `docker compose -p sge -f /opt/sge/docker-compose.prod.yml exec web nginx -s reload`.
- **nginx no arranca tras un cambio de FQDN**: el certificado debe existir en
  `/etc/letsencrypt/live/<fqdn>/`; emitirlo con `sudo certbot certonly --standalone -d <fqdn>` con la pila
  detenida (`docker compose … stop web`).
