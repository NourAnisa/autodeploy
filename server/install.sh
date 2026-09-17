#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Jalankan sebagai root: sudo bash server/install.sh lab.example.ac.id"
  exit 1
fi

base_domain="${1:-}"
if [[ -z "$base_domain" || ! "$base_domain" =~ ^[A-Za-z0-9.-]+$ || "$base_domain" != *.* ]]; then
  echo "Penggunaan: sudo bash server/install.sh lab.example.ac.id"
  exit 2
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
  ca-certificates curl debian-archive-keyring debian-keyring \
  docker.io gnupg openssl php-cli php-fpm php-zip unzip

if ! command -v caddy >/dev/null 2>&1; then
  install -d -m 0755 /usr/share/keyrings
  curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/gpg.key \
    | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt \
    -o /etc/apt/sources.list.d/caddy-stable.list
  chmod 0644 /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  chmod 0644 /etc/apt/sources.list.d/caddy-stable.list
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y caddy
fi

php_fpm_service="$(find /lib/systemd/system -maxdepth 1 -name 'php*-fpm.service' -printf '%f\n' | sort -V | tail -n1)"
if [[ -z "$php_fpm_service" ]]; then
  echo "Service PHP-FPM tidak ditemukan."
  exit 3
fi

systemctl enable --now docker "$php_fpm_service"

php_fpm_socket="/run/php/${php_fpm_service%.service}.sock"
if [[ ! -S "$php_fpm_socket" ]]; then
  echo "Socket PHP-FPM tidak ditemukan: $php_fpm_socket"
  exit 4
fi

install -d -m 0750 /etc/autodeploy
install -d -m 0755 /etc/caddy/apps
install -d -m 0755 /srv/autodeploy/projects
install -d -m 0755 /srv/autodeploy/templates/php-native
install -d -m 0770 -o www-data -g www-data /srv/autodeploy/uploads
install -d -m 0770 -o www-data -g www-data /srv/autodeploy/logs
install -d -m 0755 /var/www/autodeploy/public

install -m 0755 "$script_dir/bin/autodeploy-project" /usr/local/sbin/autodeploy-project
install -m 0644 "$script_dir/templates/php-native/Dockerfile" /srv/autodeploy/templates/php-native/Dockerfile
install -m 0644 "$script_dir/public/deploy.php" /var/www/autodeploy/public/deploy.php

printf 'BASE_DOMAIN=%s\n' "$base_domain" > /etc/autodeploy/config
chmod 0640 /etc/autodeploy/config

if [[ ! -s /etc/autodeploy/token ]]; then
  openssl rand -hex 32 > /etc/autodeploy/token
fi
chown root:www-data /etc/autodeploy/token
chmod 0640 /etc/autodeploy/token

cat > /etc/sudoers.d/autodeploy-api <<'SUDOERS'
www-data ALL=(root) NOPASSWD: /usr/local/sbin/autodeploy-project *
SUDOERS
chmod 0440 /etc/sudoers.d/autodeploy-api
visudo -cf /etc/sudoers.d/autodeploy-api >/dev/null

php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
cat > "/etc/php/$php_version/fpm/conf.d/99-autodeploy.ini" <<'PHPINI'
upload_max_filesize=50M
post_max_size=52M
max_execution_time=300
PHPINI
systemctl restart "$php_fpm_service"

if [[ -f /etc/caddy/Caddyfile ]]; then
  cp /etc/caddy/Caddyfile "/etc/caddy/Caddyfile.backup.$(date -u +%Y%m%d%H%M%S)"
fi

cat > /etc/caddy/Caddyfile <<CADDY
deploy.$base_domain {
    root * /var/www/autodeploy/public
    php_fastcgi unix/$php_fpm_socket
    file_server
}

import /etc/caddy/apps/*.caddy
CADDY

chown -R root:caddy /etc/caddy/apps
chmod 0750 /etc/caddy/apps
caddy validate --config /etc/caddy/Caddyfile
systemctl enable --now caddy
systemctl reload caddy

token="$(cat /etc/autodeploy/token)"

echo
echo "AutoDeploy berhasil dipasang."
echo "Deploy URL : https://deploy.$base_domain/deploy.php"
echo "Token      : $token"
echo
echo "Simpan token sebagai GitHub Actions secret DEPLOY_TOKEN."
echo "Pastikan DNS deploy.$base_domain dan *.$base_domain mengarah ke IP VPS."
echo "Pastikan firewall mengizinkan TCP 80 dan 443."
