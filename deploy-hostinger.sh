#!/usr/bin/env bash
set -Eeuo pipefail

# Sekali saja, di hPanel → Advanced → Cron Jobs (tiap menit):
#   cd <folder aplikasi> && php artisan schedule:run >> /dev/null 2>&1
# Tanpa cron ini push kelas kosong dan penghapusan foto masuk kelas
# tidak berjalan; widget dashboard tetap berfungsi.

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

if [[ ! -f .env ]]; then
    printf 'ERROR: .env belum tersedia. Buat dan isi konfigurasi produksi terlebih dahulu.\n' >&2
    exit 1
fi

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

printf 'Mengaktifkan maintenance mode...\n'
"$PHP_BIN" artisan down --retry=60 || true

cleanup() {
    "$PHP_BIN" artisan up >/dev/null 2>&1 || true
}
trap cleanup EXIT

printf 'Mengambil pembaruan main...\n'
git pull --ff-only origin main

printf 'Memasang dependency produksi...\n'
"$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

printf 'Menjalankan migrasi...\n'
"$PHP_BIN" artisan migrate --force

printf 'Menyiapkan storage publik...\n'
"$PHP_BIN" artisan storage:link || true

printf 'Menyegarkan cache produksi...\n'
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

"$PHP_BIN" artisan up
trap - EXIT
printf 'Deployment selesai.\n'
