# HRIS Yayasan — Progress Audit

**Last updated:** 2026-07-25
**Status tracker:** Semua fase hardening & non-critical features yang sudah dikerjakan di sesi ini.

---

## Fase 1 — Security Hardening (2026-07-25)

| Item | Status | File |
|------|--------|------|
| Rate limit password update | ✅ | `routes/auth.php` |
| Throttle mutating endpoints admin | ✅ | `routes/admin.php` |
| IDOR fix — scoping unit di controller | ✅ | Multiple controllers |
| Mass assignment guard (fillable/guarded) | ✅ | Models |
| Default password `\Str::random(12)` | ✅ | `PegawaiController.php` |
| Missing DB indexes (6) | ✅ | Migration `2026_07_25_000001_*` |
| CORS config explicit origins | ✅ | `config/cors.php` |

---

## Fase 1.5 — Force Password Change (2026-07-25)

| Item | Status | File |
|------|--------|------|
| Migration `force_password_change` + `default_password` | ✅ | `2026_07_25_000002_*` |
| Flash password display after create | ✅ | `PegawaiController.php` |
| Copy-to-clipboard toast di frontend | ✅ | Pegawai form |
| Redirect ke profile.edit jika `needsPasswordChange()` | ✅ | `AuthenticatedSessionController.php` |
| Auto-generate placeholder password | ✅ | `UserObserver.php` |

---

## Fase 2 — Cron, Hari Libur, Locking, Audit, Fallback (2026-07-25)

| Item | Status | File |
|------|--------|------|
| `FinalizeAlpa` command (daily 01:00) | ✅ | `app/Console/Commands/FinalizeAlpa.php` |
| Skip libur nasional & unit (`HariLibur` model) | ✅ | `app/Models/HariLibur.php` |
| Skip lembur jadwal | ✅ | `FinalizeAlpa.php` |
| `PayrollLockHelper` — cegah finalize/regen conflict | ✅ | `app/Helpers/PayrollLockHelper.php` |
| `AuditPresensi` model — log perubahan status | ✅ | `app/Models/AuditPresensi.php` |
| `ApprovalHelper` — fallback approver ke admin unit | ✅ | `app/Helpers/ApprovalHelper.php` |
| Bug fix: libur check per-jadwal unit (bukan all units pegawai) | ✅ | `FinalizeAlpa.php` |
| Schedule di `bootstrap/app.php` (01:00 + 01:30) | ✅ | `bootstrap/app.php` |

---

## Fase 3 — Queue Foto & Notifikasi (2026-07-25)

### Queue Foto
| Item | Status | File |
|------|--------|------|
| Migration 4 kolom `foto_masuk_status/error`, `foto_keluar_status/error` | ✅ | `2026_07_25_195354_*` |
| `ProcessPresensiFoto` job (`$tries=3`, `$backoff=[5,15,30]`, `$timeout=60`) | ✅ | `app/Jobs/ProcessPresensiFoto.php` |
| Failed job cleanup (status=expired + hapus temp) | ✅ | `ProcessPresensiFoto.php::failed()` |
| Wiring `MobileController::storeAbsen` → temp file → dispatch job | ✅ | `MobileController.php` |
| Wiring `PresensiController::store` → temp file → dispatch job | ✅ | `PresensiController.php` |

### Notifikasi Izin
| Item | Status | File |
|------|--------|------|
| Migration `notifications` table (database channel) | ✅ | `2026_07_25_195744_*` |
| `IzinBaru` notification (implements ShouldQueue) | ✅ | `app/Notifications/IzinBaru.php` |
| `StatusIzin` notification (implements ShouldQueue) | ✅ | `app/Notifications/StatusIzin.php` |
| `MobileIzinController::store` → notify L1 only | ✅ | `MobileIzinController.php` |
| `PengajuanIzinController::approve` → notify L2 + pegawai | ✅ | `PengajuanIzinController.php` |
| `PengajuanIzinController::reject` → notify pegawai | ✅ | `PengajuanIzinController.php` |

---

## Fase 4 — Non-Critical Items (2026-07-25)

### #1 — Komponen filter di payroll
| Item | Status | File |
|------|--------|------|
| Skip komponen jika `komponen.unit_sekolah_id` tidak match unit pegawai | ✅ | `PenggajianController.php::createDraft()` |
| Eager load `units` di prefetch | ✅ | `PenggajianController.php` |

### #2 — CORS hardening
| Item | Status | File |
|------|--------|------|
| Explicit `allowed_origins` dari `FRONTEND_URL` + `MOBILE_DOMAIN_URL` | ✅ | `config/cors.php` |
| `supports_credentials=true` | ✅ | `config/cors.php` |

### #3 — Mobile jadwal caching
| Item | Status | File |
|------|--------|------|
| `Cache::remember(..., 900)` di `jadwal()`, `dashboard()`, `absen()` | ✅ | `MobileController.php` |
| `clearJadwalCache()` on create/update/destroy/generate/swap | ✅ | `JadwalController.php` |

### #4 — Retensi foto presensi
| Item | Status | File |
|------|--------|------|
| `presensi:cleanup-foto` command with `--dry-run` | ✅ | `app/Console/Commands/CleanupFotoPresensi.php` |
| Payroll draft safety check (skip if draft exists) | ✅ | `CleanupFotoPresensi.php` |
| Idempotent: soft-delete file from disk, set path null, status `expired` | ✅ | `CleanupFotoPresensi.php` |
| Dry-run (dev): 0 files affected (expected — no data) | ✅ | Run output |

### #5 — Dashboard perbandingan unit
| Item | Status | File |
|------|--------|------|
| `DashboardController::perbandinganUnit()` | ✅ | `DashboardController.php` |
| Query: `GROUP BY unit_sekolah_id` via `SUM(CASE ...)`, exclude `is_lembur` | ✅ | `DashboardController.php` |
| Cache 5 menit | ✅ | `DashboardController.php` |
| Include unit tanpa data | ✅ | `DashboardController.php` |
| Filter periode: bulan ini / bulan lalu / custom range | ✅ | `PerbandinganUnit.jsx` |
| Route `/dashboard/perbandingan-unit` middleware `can:view_all_units` | ✅ | `routes/admin.php` |
| Frontend: tabel per unit + bar % kehadiran | ✅ | `resources/js/Pages/PerbandinganUnit.jsx` |
| Sidebar link (Modul Utama, hanya superadmin) | ✅ | `AuthenticatedLayout.jsx` |

---

## Tertunda

### #6 — Push notification reminder presensi

**Alas an ditunda:** Membutuhkan setup PWA installable dari nol — service worker, manifest.json, VAPID keys, Web Push API, izin user. Scope besar dan berisiko dikerjakan terburu-buru di app production.

**Yang harus dikerjakan nanti:**
1. Generate manifest.json + icon assets (192x192, 512x512)
2. Service worker (`sw.js`) — register, install, fetch, push event
3. `composer require laravel-notification-channels/webpush`
4. `php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"`
5. `php artisan webpush:vapid` — isi `.env`
6. DB table `push_subscriptions` + model
7. Controller endpoint `POST /mobile/push/subscribe`
8. Frontend: `Notification.requestPermission()` → `pushManager.subscribe()` → POST subscription ke backend
9. `sw.js`: `self.addEventListener('push')` → tampilkan notifikasi
10. Command reminder: jadwal push ke pegawai yang belum absen

**Rekomendas i:** Kerjakan di repo terpisah / staging dulu dengan testing matang (service worker sulit di-debug tanpa production HTTPS).

---

## Deployment Checklist (Manual)

| Item | Command / Config | Priority |
|------|-----------------|----------|
| **Set crontab** | `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1` | **WAJIB** — tanpa ini FinalizeAlpa & CleanupFoto tidak jalan |
| **Supervisor queue worker** | `php artisan queue:work database --sleep=3 --tries=3 --max-time=3600` | **WAJIB** — tanpa ini proses foto & notifikasi tidak diproses |
| **Timezone PHP** | `date.timezone = Asia/Jakarta` di `php.ini` atau override di `.env` `APP_TIMEZONE=Asia/Jakarta` | **WAJIB** — logika hari libur & jam absen bergantung TZ |
| **Dry-run cleanup foto di produksi** | `php artisan presensi:cleanup-foto --dry-run` → lihat angka real → jalankan tanpa flag | **SARAN** — validasi dulu sebelum eksekusi |
| Verifikasi `APP_ENV=production` | `.env` | **WAJIB** |
| Verifikasi `APP_DEBUG=false` | `.env` | **WAJIB** |
| Verifikasi `SESSION_SECURE_COOKIE=true` | `.env` (HTTPS only) | **WAJIB** |
| Verifikasi `LOG_LEVEL=warning` | `.env` | **SARAN** |
| Verifikasi `CACHE_STORE=redis` (jika ada Redis) | `.env` | **SARAN** |
| Verifikasi `QUEUE_CONNECTION=database` atau redis | `.env` | **WAJIB** — default sudah database, aman |
| Migration | `php artisan migrate --force` | **WAJIB** |
| Build frontend | `npm run build` | **WAJIB** |

---

## Catatan

- Semua fase dikerjakan **sinkron** di sesi yang sama (2026-07-25). Belum ada deploy ke production.
- `SECURITY_AUDIT.md` (2026-07-22) adalah audit read-only independent sebelum fase-fase ini — beberapa temuan sudah di-fix di Fase 1.
- Pending item audit yang belum ditangani: M1 (KEUANGAN_API_URL guard), L3/L6/L7 (N+1 cuti, foto path, image dimension cap), P2 (HandleInertiaRequests eager-load).
