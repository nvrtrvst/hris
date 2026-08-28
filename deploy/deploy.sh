#!/usr/bin/env bash
#
# deploy.sh — Deploy HRIS Yayasan ke server produksi.
#
# Sekali jalan: git pull → composer install (hanya jika lock berubah) →
# migrate → build asset (Node 22) → clear cache → smoke check.
# Idempoten: aman dijalankan berulang tanpa efek samping.
#
# Pemakaian:
#   ssh ubuntu@<server> 'bash /var/www/hris/deploy/deploy.sh'
#   # atau langsung di server:
#   cd /var/www/hris && bash deploy/deploy.sh
#
# Persyaratan:
#   - Dijalankan sebagai user pemilik repo (ubuntu), bukan root/www-data.
#   - Node 22 untuk Vite 8: /opt/node22/bin/node (Node 18 bawaan Ubuntu GAGAL build).
#   - Sudo tanpa password untuk www-data (php artisan, chown).

set -euo pipefail

# ── Konfigurasi ────────────────────────────────────────────────────────────
REPO_DIR="${1:-/var/www/hris}"
PHP_BIN="${PHP_BIN:-sudo -u www-data php}"
NODE22_BIN="/opt/node22/bin/node"
ADMIN_DOMAIN="${ADMIN_DOMAIN:-https://simsdm.nuurulmuttaqiin.or.id}"
MOBILE_DOMAIN="${MOBILE_DOMAIN:-https://presensi.nuurulmuttaqiin.or.id}"
LOG_FILE="${LOG_FILE:-$REPO_DIR/storage/logs/hris-deploy.log}"

# Warna output (fallback plain kalau terminal non-TTY)
if [[ -t 1 ]]; then
    C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'; C_RED=$'\033[31m'; C_BOLD=$'\033[1m'; C_RESET=$'\033[0m'
else
    C_GREEN=""; C_YELLOW=""; C_RED=""; C_BOLD=""; C_RESET=""
fi

info()  { echo "${C_GREEN}✔${C_RESET} $*"; }
warn()  { echo "${C_YELLOW}⚠${C_RESET} $*"; }
fail()  { echo "${C_RED}✖${C_RESET} $*" >&2; exit 1; }
step()  { echo; echo "${C_BOLD}── $* ──${C_RESET}"; }

log()   { mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true; echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$LOG_FILE" 2>/dev/null || true; }

# ── Pre-flight ─────────────────────────────────────────────────────────────
step "Pre-flight"
cd "$REPO_DIR" || fail "Folder repo tidak ditemukan: $REPO_DIR"

[[ -f artisan ]] || fail "Bukan direktori Laravel (artisan tidak ada): $REPO_DIR"
[[ -d .git ]]    || fail "Bukan repo git: $REPO_DIR"

# Pastikan repo dianggap aman oleh git (dubious ownership)
git config --global --add safe.directory "$REPO_DIR" 2>/dev/null || true

# Deteksi Node 22 (Vite 8 butuh ≥20.19; Node 18 Ubuntu error "CustomEvent is not defined")
if [[ -x "$NODE22_BIN" ]]; then
    export PATH="$(dirname "$NODE22_BIN"):$PATH"
    NODE_VERSION=$("$NODE22_BIN" -v 2>/dev/null || echo "?")
    info "Node: $NODE_VERSION ($NODE22_BIN)"
elif node -v >/dev/null 2>&1 && [[ "$(node -v | cut -d. -f1 | tr -d 'v')" -ge 20 ]]; then
    info "Node: $(node -v) (sistem, sudah ≥20)"
else
    warn "Node 22 tidak ditemukan di $NODE22_BIN dan node sistem <20. Build Vite 8 kemungkinan GAGAL."
    warn "Install Node 22:  curl -sL https://nodejs.org/dist/v22.17.0/node-v22.17.0-linux-x64.tar.xz | sudo tar -xJ -C /opt --strip-components=1 --transform='s/^node-v[^/]*/node22/'"
    warn "Atau simpan binary di: $NODE22_BIN"
fi

# ── Git pull ───────────────────────────────────────────────────────────────
step "Git pull (origin/main)"
BEFORE=$(git rev-parse HEAD)
git fetch origin main 2>&1 | tail -1 || true

if ! git rev-parse --verify -q origin/main >/dev/null; then
    fail "Tidak ada branch origin/main. Cek remote: git remote -v"
fi

# Server harus selalu bersih (hanya menarik dari repo). Pakai reset --hard
# bukan merge-pull supaya tidak gagal saat working tree kotor (mis.
# composer.lock yang di-regenerate oleh `composer install --no-dev`).
# Aman: server produksi tidak menyimpan perubahan lokal yang perlu dipertahankan.
git reset --hard origin/main 2>&1 | tail -2

AFTER=$(git rev-parse HEAD)
if [[ "$BEFORE" == "$AFTER" ]]; then
    info "Sudah terbaru ($(git log --oneline -1))."
else
    info "Update: $(git log --oneline "$BEFORE..$AFTER" | wc -l) commit baru → $(git log --oneline -1)"
fi
log "pull: $BEFORE → $AFTER"

# ── Composer install (hanya jika composer.lock berubah) ───────────────────
step "Composer install"
composer_lock_changed=false
if [[ "$BEFORE" != "$AFTER" ]]; then
    if git diff --quiet "$BEFORE" "$AFTER" -- composer.lock; then
        info "composer.lock tidak berubah — skip."
    else
        composer_lock_changed=true
    fi
else
    info "Tidak ada commit baru — skip (vendor sudah sesuai)."
fi

if [[ "$composer_lock_changed" == "true" ]]; then
    if command -v composer >/dev/null 2>&1; then
        composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader 2>&1 | tail -3
        info "composer install selesai."
    else
        warn "composer tidak ada di PATH — jalankan manual: composer install --no-dev --optimize-autoloader"
    fi
fi

# ── Migrate ────────────────────────────────────────────────────────────────
step "Migrate database"
$PHP_BIN artisan migrate --force 2>&1 | tail -4

# ── Seed permissions (idempoten: pastikan permission baru ada di DB) ───────
step "Seed permissions"
$PHP_BIN artisan db:seed --class=RolePermissionSeeder --force 2>&1 | tail -4

# ── Build asset (selalu build ulang agar mencerminkan source terbaru) ──────
step "Build asset (Vite)"
# npm ci hanya jika package-lock berubah (node_modules sudah ada umumnya)
if [[ "$BEFORE" != "$AFTER" ]]; then
    if ! git diff --quiet "$BEFORE" "$AFTER" -- package-lock.json; then
        npm ci --no-audit --no-fund 2>&1 | tail -2 || true
    fi
fi

# Bersihkan cache Vite + build lama supaya tidak ada asset kadaluarsa.
rm -rf node_modules/.vite public/build
npm run build 2>&1 | tail -8
if [[ ${PIPESTATUS[0]} -ne 0 ]]; then
    fail "npm run build GAGAL — asset tidak diperbarui. Cek error di atas."
fi
# Asset hasil build harus bisa dibaca www-data (web server)
if command -v sudo >/dev/null 2>&1; then
    sudo chown -R www-data:www-data public/build
fi
info "Build asset selesai + chown www-data."

# ── Clear cache ───────────────────────────────────────────────────────────
step "Clear cache (routes/views/config)"
$PHP_BIN artisan optimize:clear 2>&1 | tail -3

# ── Scheduler cron (php artisan schedule:run) ───────────────────────────────
# Pastikan cron OS terpasang agar command terjadwal (mis. presensi:finalize-alpa)
# berjalan tiap menit. Idempoten: tidak menambah duplikat.
step "Scheduler cron (schedule:run)"
CRON_CMD="* * * * * cd $REPO_DIR && sudo -u www-data php artisan schedule:run >> /dev/null 2>&1"
if crontab -l 2>/dev/null | grep -qF "artisan schedule:run"; then
    info "Cron schedule:run sudah terpasang."
else
    ( crontab -l 2>/dev/null; echo "$CRON_CMD" ) | crontab - 2>/dev/null \
        && info "Cron schedule:run dipasang." \
        || warn "Gagal pasang cron (perlu akses crontab). Pasang manual: $CRON_CMD"
fi

# ── Smoke check ───────────────────────────────────────────────────────────
step "Smoke check endpoint"
check_url() {
    local url="$1" label="$2"
    local code
    code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$url" || echo "000")
    if [[ "$code" == "200" ]]; then
        info "$label: HTTP $code"
    else
        warn "$label: HTTP $code (perlu dicek manual)"
    fi
}
check_url "$ADMIN_DOMAIN/login"  "Admin  "
check_url "$MOBILE_DOMAIN/login" "Mobile "

log "deploy selesai: $(git log --oneline -1)"
step "Selesai ✅"
echo "Log lengkap: $LOG_FILE"
