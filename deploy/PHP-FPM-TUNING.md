# Tuning PHP-FPM & OpCache — Beban Jam Sibuk (250 absen serentak)

Dokumen ini untuk server produksi (nginx + PHP 8.3-FPM, lihat `deploy/nginx/*.conf`).
Tujuan: menahan peak load jam 8 pagi — 250 pegawai login & absen bersamaan, tiap 5 hari kerja.

## 1. Ringkas alur absen (dan di mana titik bebannya)

```
HP (PWA) ── base64 foto (≤640px JPEG ±50–120KB, dikompres client) ──> nginx
   → PHP-FPM: validasi → geofence (Haversine) → tulis temp → DB transaction
     (upsert lockForUpdate) → dispatchSync ProcessPresensiFoto
     (decode → resize 640 → webp q60 → overlay) → response JSON
```

Titik beban per request absen:
| Tahap | Estimasi CPU/RAM | Keterangan |
|---|---|---|
| Validasi + geofence + DB write | ~5–15 ms | Ringan, query ter-index |
| **Proses foto sinkron** (`dispatchSync`) | **~30–80 ms CPU**, RAM puncak ±25–35 MB | Decode JPEG 640px + GD + webp encode + overlay |
| Upload bandwidth | ±60–150 KB/request | Sudah kecil berkat kompresi client 640px |

Dengan foto sudah 640px dari HP, `ImageUploadService::resize(640)` menjadi **no-op** —
beban foto terbesar sudah dipindah ke HP. Sisa beban server per absen ±50–100 ms,
jadi 250 request serentak setara ±12–25 dtk CPU — sangat aman untuk 1 VPS 2–4 GB.

> **Penting:** jangan regresi `resources/js/Hooks/useCamera.js` (MAX=640, q0.75) ke ukuran
> lebih besar — semua jalur mobile (kamera & galeri) lewat canvas resize di hook ini.
> Batas 5 MB (`PresensiMessages::MAX_FOTO_BYTES`) hanyalah jaring pengaman untuk
> request API langsung (yang bypass client), bukan jalur normal.

## 2. Pool PHP-FPM (`/etc/php/8.3/fpm/pool.d/www.conf`)

Rumus cepat: `max_children = (RAM total − OS ±0.5 GB − MySQL ±1–2 GB − nginx ±0.1 GB) / 60 MB`
(60 MB = baseline PHP ±25 MB + peak foto ±10 MB + overhead request).

| RAM VPS | `pm.max_children` | `pm.start_servers` | `pm.min_spare_servers` | `pm.max_spare_servers` |
|---|---|---|---|---|
| 2 GB | 8 | 2 | 1 | 4 |
| 4 GB | 16 | 3 | 2 | 8 |
| 8 GB | 32 | 5 | 3 | 12 |
| 16 GB | 64 | 8 | 5 | 20 |

Blok config yang disarankan (sesuaikan `max_children` sesuai tabel):

```ini
pm = dynamic
pm.max_children = 16
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 8

; Lepaskan worker yang bocor memory setelah ~500 request (anti memory leak lambat)
pm.max_requests = 500

; Jangan terlalu kecil! Generate payroll / export Excel bisa >30 dtk.
; 120 dtk aman (nginx fastcgi_read_timeout juga 120s di deploy/nginx).
request_terminate_timeout = 120

; Aktifkan status page (untuk monitoring: curl /fpm-status?full)
pm.status_path = /fpm-status

; WAJIB lindungi /fpm-status di nginx — tanpa proteksi, statistik
; (jumlah request, RAM tiap worker) bocor ke publik:
;   location = /fpm-status {
;       access_log off;
;       allow 127.0.0.1;   # + IP admin/VPS Anda
;       deny all;
;       include fastcgi_params;
;       fastcgi_pass unix:/run/php/php8.3-fpm.sock;
;   }
```

Setelah ubah: `sudo systemctl reload php8.3-fpm` (reload, bukan restart — tidak putus request).

### Anti-pattern yang sering terjadi
- ❌ `pm.max_children` terlalu besar (mis. 50 di VPS 2 GB) → OOM kill saat 250 serentak.
- ❌ `request_terminate_timeout = 30` → payroll batch di-PID kill, data corrupt parsial.
- ❌ `pm = static` dengan children besar → RAM terkunci terus walau idle.

## 3. OpCache (`/etc/php/8.3/cli/conf.d/opcache.ini` untuk CLI, dan FPM pool)

Laravel + vendor besar (±3–5 ribu file) WAJIB opcache aktif. Tanpa opcache, tiap request
re-compile seluruh framework — CPU naik 3–5× di jam sibuk.

```ini
opcache.enable = 1
opcache.enable_cli = 1          ; percepat artisan queue/command
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60    ; deploy terdeteksi ≤60 dtk tanpa restart
```

Catatan deploy:
- `revalidate_freq = 60` → taruh kode baru, dalam ≤1 menit terpakai. **Paling aman.**
- Kalau mau nol overhead (`validate_timestamps = 0`): WAJIB `systemctl reload php8.3-fpm`
  setiap deploy, kalau tidak admin akan lihat bundle/error lama ("Terjadi Kesalahan").

## 4. nginx (tambahan kecil di `deploy/nginx/*.conf`)

```nginx
gzip on;
gzip_comp_level 5;
gzip_min_length 512;
gzip_types application/json text/plain text/css application/javascript image/svg+xml;
gzip_vary on;

# Ukuran body sudah 10M/20M — cukup, foto ≤150KB setelah kompresi client.
# Jangan dinaikkan: batas 5MB yang besar hanya jaring pengaman, bukan jalur normal.
```

`gzip` penting karena respons Inertia (JSON) polling live tiap 60 dtk — terkompresi 70–80%.

## 5. MySQL (jika MySQL, bukan sqlite)

```sql
-- max_connections ≥ pm.max_children (tiap child bisa butuh 1 koneksi saat peak)
SET GLOBAL max_connections = 200;  -- atau via my.cnf

-- Buffer pool sesuai RAM (jangan seluruh RAM, sisakan untuk PHP)
-- my.cnf: innodb_buffer_pool_size = 1G   (VPS 4GB) / 2G (VPS 8GB)
```

Indeks penting yang sudah ada & wajib tetap dipertahankan (dipakai query jam sibuk):
- `presensi(tanggal)`, `presensi(pegawai_id, tanggal)`, `presensi(jadwal_id)`
- `jadwal(pegawai_id, hari)`, `jadwal(unit_sekolah_id)`
- `users(email)` — login

Cek cepat setelah tuning:
```sql
EXPLAIN SELECT * FROM presensi WHERE tanggal = '2026-08-14' AND status IN ('hadir','telat');
-- pastikan type = range/ref/index, bukan ALL
```

## 6. Verifikasi & monitoring

```bash
# 1. Status FPM (setelah pm.status_path aktif)
curl -s "http://localhost/fpm-status?full" | head -30
#   lihat: accepted conn, max children reached (jangan 100%), current queue (jangan menumpuk)

# 2. RAM & swap
free -h
#   swap terpakai >0 saat jam sibuk = RAM kurang → turunkan max_children atau upgrade

# 3. Opcache terisi?
php -r 'var_export(opcache_get_status()["opcache_statistics"]);'

# 4. Latensi request absen saat jam sibuk
#   tail storage/logs/laravel.log — cari slow/error 500/429.
```

Load test ringan (jangan pakai endpoint mutasi tanpa session):
```bash
ab -n 500 -c 50 -k http://localhost/dashboard
# tanpa cache hit (data live), lihat Requests/sec & Time per request.
# Kalau < 20 req/s → cek max_children, opcache, atau MySQL.
```

## 7. Jika masih kurang (langkah lanjutan, dampak lebih besar)

1. **Foto async** — ganti `Bus::dispatchSync(ProcessPresensiFoto)` → `dispatch()` (queue
   database) + supervisor `queue:work`. Request absen balas <100 ms, foto diproses
   background. Skema DB sudah siap (`foto_*_status` pending→processing→done).
   Butuh: `QUEUE_CONNECTION=database` + satu service `artisan queue:work --sleep=2`.
2. **Session/cache Redis** — bila admin banyak membuka dashboard (cache file lock bisa
   serialize request). Untuk 5–10 admin, file session masih aman.
3. **CDN/traffic** — PWA di-cloudflare gratis (cache asset build/, yang selalu hashed).

---

Terakhir: **jangan melakukan tuning FPM sendirian tanpa mengukur dulu.** Jalankan
monitoring (`free -h` + `fpm-status`) selama satu jam sibuk nyata, baru tentukan arah.
Dokumen ini memberi angka awal yang aman untuk skala 250 user; angka final tergantung
VPS & MySQL Anda.
