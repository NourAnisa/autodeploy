#!/usr/bin/env bash
set -Eeuo pipefail

project="${1:-}"
source_dir="${2:-.}"

if [[ ! "$project" =~ ^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$ ]]; then
  echo "Penggunaan: bash examples/deploy-local.sh demo-app examples/php-native"
  echo "Nama proyek: 3-40 karakter, huruf kecil, angka, atau tanda hubung."
  exit 1
fi

if [[ ! -d "$source_dir" ]]; then
  echo "Direktori aplikasi tidak ditemukan: $source_dir"
  exit 2
fi

if [[ -z "${DEPLOY_URL:-}" || -z "${DEPLOY_TOKEN:-}" ]]; then
  if [[ -r /etc/autodeploy/config ]] || sudo test -r /etc/autodeploy/config; then
    base_domain="$(sudo awk -F= '$1=="BASE_DOMAIN" {print $2}' /etc/autodeploy/config)"
    DEPLOY_URL="http://deploy.$base_domain/deploy.php"
    DEPLOY_TOKEN="$(sudo cat /etc/autodeploy/token)"
  else
    echo "Atur DEPLOY_URL dan DEPLOY_TOKEN terlebih dahulu."
    exit 3
  fi
fi

temp_dir="$(mktemp -d)"
archive="$temp_dir/application.zip"
trap 'rm -rf "$temp_dir"' EXIT

(
  cd "$source_dir"
  zip -qr "$archive" . \
    -x ".git/*" \
       ".github/*" \
       "*.log" \
       ".env" \
       ".env.*"
)

echo "Mengirim $project ke $DEPLOY_URL ..."
response_file="$temp_dir/response.json"
http_code="$(
  curl --silent --show-error \
    --output "$response_file" \
    --write-out "%{http_code}" \
    --request POST "$DEPLOY_URL" \
    --header "Authorization: Bearer $DEPLOY_TOKEN" \
    --form "project=$project" \
    --form "branch=local" \
    --form "commit=unknown" \
    --form "artifact=@$archive;type=application/zip"
)"

cat "$response_file"
echo

if [[ "$http_code" -lt 200 || "$http_code" -ge 300 ]]; then
  echo "Deployment gagal dengan HTTP $http_code"
  exit 4
fi
