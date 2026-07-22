# Security & Performance Audit - HRIS Yayasan

**Date:** 2026-07-22
**Scope:** Full repository (read-only)
**Stack:** Laravel 13.8 / PHP 8.3, Inertia v2 + React 18 + Vite 8 + Tailwind v3
**Methodology:** Static review of routes, middleware, controllers, models, config, and frontend sources. `composer audit` + `npm audit --omit=dev` run. No code modified.

---

## 1. Executive Summary

| Severity | Count |
|---|---|
| Critical | 0 |
| High | 2 |
| Medium | 3 |
| Low / Info | 9 |
| Performance | 5 |
| Dependency advisories | 0 (`composer audit` clean, `npm audit --omit=dev` clean) |

Both `composer audit` and `npm audit --omit=dev` returned **no security vulnerability advisories**. Findings below are all configuration or code-pattern issues.

---

## 2. High Severity

### H1 - `password.update` POST has no rate limit
- **File:** `routes/auth.php` (line 30)
- **Issue:** Route `Route::put('password', ...)` is registered outside the `throttle:6,1` subgroup. No brute-force protection on the password-change endpoint.
- **Fix:** Move inside the `throttle:6,1` subgroup, or attach an explicit `->middleware('throttle:6,1')`.

### H2 - Mutating endpoints in `routes/admin.php` lack explicit throttle (categorical)
- **File:** `routes/admin.php`
- **Issue:** Resource controllers (`penggajian`, `pegawai`, `jadwal`, `laporan`, `presensi`, `backup`, `izin`, `komponen-gaji`, `users`, `roles`) and most named POST actions are not wrapped in a per-endpoint throttle. Only mobile routes carry explicit throttles.
- **Fix:** Add `throttle:10-30/min` for write paths, `60/min` for reads.

---

## 3. Medium Severity

### M1 - `KEUANGAN_API_URL` defaults to insecure `http://localhost:3000`
- **File:** `config/keuangan.php` (line 9)
- **Issue:** Plaintext HTTP default. If unset in production, payroll payloads travel unencrypted.
- **Fix:** Require HTTPS in production; add a startup guard in `AppServiceProvider::boot()` that aborts when `APP_ENV=production` and the URL is not `https`.

### M2 - Sensitive Pegawai fields stored plaintext and exposed via public accessors
- **File:** `app/Models/Pegawai.php`
- **Issue:** `nik`, `npwp`, `no_bpjs_kesehatan`, `no_bpjs_ketenagakerjaan` are **not** encrypted. Public `*_decrypted` accessors leak PII on any serialization (Inertia shared props, exports, API).
- **Fix:** Apply `encrypted` cast to those columns. Gate or remove the `*_decrypted` accessors; if kept, restrict to `manage_master_data` permission.

### M3 - Heavy POST endpoints missing throttle
- **File:** `routes/admin.php`
- **Endpoints:** `penggajian.run.store`, `penggajian.run.worksheet`, `penggajian.run.finalize`, `laporan.export`, `backup.store`, `komponen-gaji.tambah-komponen`, `users.import`.
- **Fix:** Attach `throttle:10,1` to each.

---

## 4. Low / Info

| ID | Title | File | Recommendation |
|---|---|---|---|
| L1 | `LOG_LEVEL=debug` default may leak context in production | `config/logging.php` | Override to `warning` or `error` in prod. |
| L2 | `CACHE_STORE=database` adds DB load | `config/cache.php` | Use `redis` in production. |
| L3 | `cuti_terpakai` accessor N+1 risk | `app/Models/Pegawai.php` | Eager-load `pengajuanIzins` in controllers; add index on `(pegawai_id, jenis_izin, status)`. |
| L4 | Pegawai create falls back to password = NIK | `app/Http/Controllers/PegawaiController.php` | Generate random password + email it, or require admin-supplied value. |
| L5 | Pegawai import accepts null `unit_sekolah_id` | `app/Http/Controllers/PegawaiController.php` | Validate `unit_sekolah_id` is present except for explicit global-staff flag. |
| L6 | Pegawai foto deletion uses `str_replace` heuristic | `app/Services/ImageUploadService.php` | Store relative path on model; resolve via `Storage::delete($pegawai->foto_path)`. |
| L7 | `ImageUploadService` missing dimension cap | `app/Services/ImageUploadService.php` (line 8) | Add max-dimension cap (~1600px) in `storeBase64`. |
| L8 | `cache.serializable_classes=false` secure-by-default (good) | `config/cache.php` | Keep. |
| L9 | `config/hashing.php` and `config/sanctum.php` intentionally absent | `config/` | No change required (framework defaults used). |

---

## 5. Performance Findings

| ID | Title | File | Recommendation |
|---|---|---|---|
| P1 | `CACHE_STORE=database` overhead per hit | `config/cache.php` | Use redis in production. |
| P2 | `HandleInertiaRequests` eager-loads `pengajuanIzins` | `app/Http/Middleware/HandleInertiaRequests.php` | Move eager-load to controllers that need it; select only required fields. |
| P3 | `cuti_terpakai` accessor re-queries per serialization | `app/Models/Pegawai.php` | Same as L3. |
| P4 | `PenggajianController` generation iterates Pegawai one-by-one | `app/Http/Controllers/PenggajianController.php` | Prefetch komponens, skalas, attendance counts, lembur; compute per-Pegawai from grouped arrays. |
| P5 | New composite index on presensi added | `database/migrations/2026_07_21_120000_add_attendance_type_and_office_start_to_presensi.php` | Verify with EXPLAIN post-deploy; no change required. |

---

## 6. Dependency Audit

- **`composer audit`** -> "No security vulnerability advisories found."
- **`npm audit --omit=dev`** -> "found 0 vulnerabilities"

---

## 7. Production Readiness Checklist

| Key | Default | Required | Status |
|---|---|---|---|
| `APP_DEBUG` | `false` | `false` | ok |
| `APP_URL` | `http://localhost` | real HTTPS URL | **override required** |
| `APP_ENV` | `local` | `production` | **override required** |
| `BCRYPT_ROUNDS` | `12` | `>= 12` | ok |
| `CACHE_STORE` | `database` | `redis` | override recommended |
| `QUEUE_CONNECTION` | `database` | `redis` or external worker | override recommended |
| `MAIL_MAILER` | `log` | `smtp` / transactional | **override required** |
| `SESSION_DRIVER` | `database` | `database` or `redis` | ok |
| `SESSION_LIFETIME` | `120` | per security policy | ok |
| `SESSION_SECURE_COOKIE` | unset | `true` | **override required** |
| `SESSION_SAME_SITE` | `lax` | `lax` | ok |
| `LOG_LEVEL` | `debug` | `warning` or `error` | **override required** |
| `KEUANGAN_API_URL` | `http://localhost:3000` | real HTTPS endpoint | **override required** |
| `KEUANGAN_API_KEY` | `change-me-in-production` | real key | **override required** |
| `SEED_DEFAULT_PASSWORD` | `password` | n/a (seed-only) | ok |
| `FILESYSTEM_IMAGE_DISK` | `public` | `s3` or private + signed URLs | consider |
| `.env` in git | gitignored | should NOT be committed | ok |

---

## 8. Prioritized Fix List (15 items)

1. **H1** - Add `throttle:6,1` to `routes/auth.php` `password.update` (line 30).
2. **H2** - Attach `throttle:10,1` to every mutating action in `routes/admin.php` (resource + named POST).
3. **M1** - Override `KEUANGAN_API_URL` to HTTPS in production; add startup guard in `AppServiceProvider`.
4. **M2** - Cast `nik` / `npwp` / `no_bpjs_*` to `encrypted` in `Pegawai` model; remove or gate `*_decrypted` accessors.
5. **M3** - `throttle:10,1` on `penggajian.run.store` / `worksheet` / `finalize`, `laporan.export`, `backup.store`, `komponen-gaji.tambah-komponen`, `users.import`.
6. **L1** - Override `LOG_LEVEL=warning` in production env.
7. **L2** - Override `CACHE_STORE=redis` in production env.
8. **L3** - Eager-load `pengajuanIzins` or move `cuti_terpakai` to controller; add index on `(pegawai_id, jenis_izin, status)`.
9. **L4** - Require explicit password on Pegawai create; remove NIK fallback.
10. **L5** - Validate `unit_sekolah_id` in Pegawai import rows.
11. **L6** - Store relative `foto_path`; replace `str_replace` with `Storage::delete($pegawai->foto_path)`.
12. **L7** - Cap max image dimension (~1600px) in `ImageUploadService::storeBase64`.
13. **P1** - Same as L2.
14. **P2** - Defer `pengajuanIzins` eager-load to controllers that need it.
15. **P4** - Prefetch komponens / skalas / attendance counts / lembur in `PenggajianController`; compute per-Pegawai from grouped arrays.

---

## 9. Test Coverage (files reviewed)

Routes, middleware, controllers, requests, models, config, key migrations, and the mobile frontend (`Absen`, `Login`, `Dashboard`, `MobileUI`, `LeafletPicker`, `ErrorBoundary`, `AppConstants`). Full list in `SECURITY_AUDIT.json` -> `tested_files` (57 files).

---

## 10. Not Audited

- `database/seeders/*` (only `RolePermissionSeeder` briefly inspected).
- `tests/*` (no tests written yet; outside scope).
- `app/Console/*` (no scheduled commands reviewed).
- `app/Jobs/*` (queue workers not inspected).
- `vendor/` and `node_modules/` (third-party code; only `composer audit` / `npm audit`).
- `storage/*` (runtime-generated artifacts).