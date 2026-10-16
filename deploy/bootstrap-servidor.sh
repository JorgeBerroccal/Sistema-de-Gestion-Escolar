#!/usr/bin/env bash
# Preparación única (e idempotente) del servidor de staging: Ubuntu 24.04 en Azure (B2ats_v2).
# Se ejecuta como root (sudo) desde la sesión SSH del usuario inicial (azureuser):
#   sudo apt-get update && sudo apt-get install -y git
#   git clone https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar.git ~/sge-src
#   sudo bash ~/sge-src/deploy/bootstrap-servidor.sh <fqdn> <correo-letsencrypt>
# Hace: zona horaria, swap de 2 GB, actualizaciones de seguridad, Docker, usuario sge, SSH solo con clave,
# ufw (22/80/443), fail2ban, clon del repositorio en /opt/sge, certificado de Let's Encrypt con renovación
# automática y cron de respaldo. Contrato: specs/002-despliegue-ci-staging/contracts/scripts-servidor.md
set -euo pipefail

FQDN="${1:-}"
CORREO="${2:-}"
REPO_URL="${SGE_REPO_URL:-https://github.com/JorgeBerroccal/Sistema-de-Gestion-Escolar.git}"
USUARIO_OPERACION="sge"
DIR="/opt/sge"

log() { printf '%s [bootstrap] %s\n' "$(date '+%F %T')" "$*"; }
die() { log "ERROR: $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Ejecuta con sudo."
[[ "$FQDN" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$ ]] || die "Uso: sudo bash bootstrap-servidor.sh <fqdn> <correo>  (FQDN no válido: '$FQDN')"
[[ "$CORREO" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || die "Correo no válido: '$CORREO'"
[[ -n "${SUDO_USER:-}" && "$SUDO_USER" != "root" ]] || die "Ejecuta con sudo desde el usuario inicial (SUDO_USER vacío): se copia su clave SSH al usuario $USUARIO_OPERACION."
[[ -s "/home/$SUDO_USER/.ssh/authorized_keys" ]] || die "No hay /home/$SUDO_USER/.ssh/authorized_keys: sin clave SSH no se puede desactivar la contraseña sin quedarse fuera."

export DEBIAN_FRONTEND=noninteractive

log "Zona horaria America/Lima"
timedatectl set-timezone America/Lima

log "Paquetes base"
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg ufw fail2ban unattended-upgrades git certbot openssl cron

log "Swap de 2 GB (red de seguridad de la máquina de 1 GB)"
if ! swapon --show=NAME --noheadings | grep -q '^/swapfile$'; then
    if [[ ! -f /swapfile ]]; then
        fallocate -l 2G /swapfile
        chmod 600 /swapfile
        mkswap /swapfile >/dev/null
    fi
    swapon /swapfile
fi
grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
echo 'vm.swappiness=10' > /etc/sysctl.d/99-sge.conf
sysctl -q -p /etc/sysctl.d/99-sge.conf

log "Actualizaciones automáticas de seguridad"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
CONF

log "Docker Engine + Compose v2 (repositorio oficial)"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    # shellcheck disable=SC1091
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
cat > /etc/docker/daemon.json <<'CONF'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" }
}
CONF
systemctl enable --now docker
systemctl restart docker

log "Usuario $USUARIO_OPERACION (sin contraseña, con la clave SSH de $SUDO_USER)"
if ! id "$USUARIO_OPERACION" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$USUARIO_OPERACION"
fi
usermod -aG docker "$USUARIO_OPERACION"
install -d -m 700 -o "$USUARIO_OPERACION" -g "$USUARIO_OPERACION" "/home/$USUARIO_OPERACION/.ssh"
install -m 600 -o "$USUARIO_OPERACION" -g "$USUARIO_OPERACION" "/home/$SUDO_USER/.ssh/authorized_keys" "/home/$USUARIO_OPERACION/.ssh/authorized_keys"

log "SSH solo con clave y sin root"
cat > /etc/ssh/sshd_config.d/00-sge-hardening.conf <<'CONF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
PubkeyAuthentication yes
CONF
sshd -t
systemctl reload ssh

log "Cortafuegos ufw: solo 22, 80 y 443"
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow 22/tcp >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null

log "fail2ban (sshd: 5 fallos en 10 min → bloqueo de 1 h; sin filtro por IP de origen)"
cat > /etc/fail2ban/jail.d/sge.local <<'CONF'
[sshd]
enabled  = true
backend  = systemd
maxretry = 5
findtime = 10m
bantime  = 1h
CONF
systemctl enable --now fail2ban
systemctl restart fail2ban

log "Directorios"
install -d -m 755 -o "$USUARIO_OPERACION" -g "$USUARIO_OPERACION" "$DIR"
install -d -m 700 -o "$USUARIO_OPERACION" -g "$USUARIO_OPERACION" /var/backups/sge
install -d -m 755 -o "$USUARIO_OPERACION" -g "$USUARIO_OPERACION" /var/log/sge
install -d -m 755 /var/www/certbot

log "Repositorio de operación en $DIR (solo lectura, HTTPS público)"
if [[ ! -d "$DIR/.git" ]]; then
    sudo -u "$USUARIO_OPERACION" git clone "$REPO_URL" "$DIR"
else
    sudo -u "$USUARIO_OPERACION" git -C "$DIR" fetch --tags --prune --force origin
fi

log "Certificado de Let's Encrypt para $FQDN"
if [[ ! -d "/etc/letsencrypt/live/$FQDN" ]]; then
    # El puerto 80 está libre porque la pila todavía no se ha levantado
    certbot certonly --standalone --non-interactive --agree-tos -m "$CORREO" -d "$FQDN"
fi
# Las renovaciones usan webroot (con la pila en marcha) para no interrumpir el sitio
CONF_RENOVACION="/etc/letsencrypt/renewal/$FQDN.conf"
if grep -q '^authenticator = standalone' "$CONF_RENOVACION"; then
    sed -i 's/^authenticator = standalone/authenticator = webroot/' "$CONF_RENOVACION"
    grep -q '^webroot_path' "$CONF_RENOVACION" || sed -i '/^authenticator = webroot/a webroot_path = /var/www/certbot,' "$CONF_RENOVACION"
    grep -q '^\[\[webroot_map\]\]' "$CONF_RENOVACION" || printf '[[webroot_map]]\n%s = /var/www/certbot\n' "$FQDN" >> "$CONF_RENOVACION"
fi
install -d /etc/letsencrypt/renewal-hooks/deploy
cat > /etc/letsencrypt/renewal-hooks/deploy/sge-recargar-nginx.sh <<HOOK
#!/bin/sh
# Recarga nginx para que tome el certificado renovado (si la pila está en marcha)
docker compose -p sge --project-directory $DIR -f $DIR/docker-compose.prod.yml --env-file $DIR/.env exec -T web nginx -s reload || true
HOOK
chmod 755 /etc/letsencrypt/renewal-hooks/deploy/sge-recargar-nginx.sh
systemctl enable --now certbot.timer

log "Tareas programadas (respaldo diario y registro de tamaño)"
install -m 644 -o root -g root "$DIR/deploy/cron-sge" /etc/cron.d/sge

log "Resumen"
echo "--- swap:";       swapon --show
echo "--- ufw:";        ufw status | head -8
echo "--- fail2ban:";   fail2ban-client status sshd | head -6 || true
echo "--- ssh:";        sshd -T | grep -E '^(passwordauthentication|permitrootlogin) '
echo "--- docker:";     docker --version; docker compose version
echo "--- certificado:"; certbot certificates -d "$FQDN" 2>/dev/null | grep -E 'Certificate Name|Expiry|Domains' || true
echo
log "Listo. Siguiente paso: como '$USUARIO_OPERACION', crear $DIR/.env (chmod 600) y ejecutar ./deploy/deploy.sh <etiqueta>."
