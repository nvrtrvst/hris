Use the caveman skill for all responses.

# Konteks Aplikasi HRIS Yayasan

## 1. Apa ini?
Sistem HRIS (Human Resource Information System) terpusat untuk Yayasan pendidikan
multi-unit (TK, SD, SMP, SMK, LPQ). Fungsi inti:
- Master data pegawai (1 sumber kebenaran), enkripsi field sensitif (NIK, rekening, NPWP, BPJS).
- Penjadwalan anti-bentrok lintas unit (Weekly Calendar Board).
- Presensi geofencing (Haversine + foto selfie real-time via WebRTC) di mobile PWA.
- Payroll dinamis (fixed / persentase / dinamis kehadiran & masa bakti & jam mengajar).
- Dashboard eksekutif + Self-Service portal untuk pegawai.

## 2. Stack
- Backend: Laravel 12 (PHP 8.3), Inertia v2, Sanctum terinstal tapi BELUM dipakai.
- Frontend: React 18 + Inertia, Tailwind v3 (config v3; package `@tailwindcss/vite` v4 ada),
  Vite 8, recharts, lucide-react, date-fns.
- DB: default sqlite (`database/database.sqlite`) tapi siap MySQL/PostgreSQL (.env.example).
- Auth: Laravel Breeze (session-based). Permission: Spatie Laravel Permission v8.
- Export/Import: Maatwebsite Excel v3. Backup: Spatie DB Dumper v4.

## 3. DUA PORTAL & GUARD
- Portal Desktop/Admin: subdomain `ADMIN_DOMAIN` (mis. `simsdm.nuurulmuttaqiin.or.id`), fallback path `/` di local. Guard `web_admin`. Route `routes/admin.php` group `auth:web_admin`.
- Portal Mobile PWA (pegawai): subdomain `MOBILE_DOMAIN` (mis. `presensi.nuurulmuttaqiin`), fallback path `/mobile` di local. Guard `web_mobile`. Route `routes/mobile.php` named `presensi.*` (TIDAK ada prefix `/mobile` di produksi).
- `routes/web.php` mendaftarkan kedua portal via `Route::domain(env(...))` dengan fallback `Route::group`/`prefix('mobile')` bila env kosong.
- Kedua guard pakai provider `users` sama. `User.$guard_name = 'web'`.
- `IsolatePortalSession` (cookie berbeda `hris_mgmt_session`/`hris_mobile_session`, deteksi via `request()->getHost()` vs `MOBILE_DOMAIN`, fallback path `/mobile`) HARUS didaftarkan di `bootstrap/app.php`.

## 4. MODEL OTORISASI (Spatie can() ONLY — sudah direfactor)
Gunakan Spatie `can()`/`hasRole()` EXKLUSIF. Kolom string `role` di `users` sudah tidak
dipakai di controller/JS. Permission (`RolePermissionSeeder`): view_dashboard,
view_all_units, view_pegawai, view_jadwal, view_presensi, view_izin, view_payroll,
manage_master_data, manage_users, manage_roles, manage_payroll.
Role: `superadmin` (all), `admin_unit` (view_*), `pegawai` (none).
`DatabaseSeeder` memanggil `RolePermissionSeeder` + assign role ke user.

## 5. SCOPING UNIT (pola BENAR)
- `User.unit_sekolah_id` (null untuk superadmin/view_all_units). `Presensi`/`Jadwal`/`User` punya kolom `unit_sekolah_id`.
- `Pegawai` TIDAK punya kolom `unit_sekolah_id` — unit via relasi `units()` (pivot `pegawai_unit`).
  Akses unit: `$pegawai->units`, `->units()->where('unit_sekolah.id', $id)`.
- Pola scope: `if ($user->unit_sekolah_id && !$user->can('view_all_units')) { ...->whereHas('units', fn($q)=>$q->where('unit_sekolah.id', $user->unit_sekolah_id)); }`
- Gunakan `Pegawai::scopeForUnit($query, $unitId)` untuk konsistensi.

## 6. ENTITAS & RELASI (ringkas)
- UnitSekolah 1..* Pegawai via pegawai_unit (pivot jabatan_id, is_primary) + latitude/longitude/radius_meter (geofence).
- Pegawai: user_id, units (M2M), jabatans (M2M via pegawai_unit), komponenGaji (M2M pegawai_komponen_gaji, pivot nominal),
  mapels (M2M), dokumen (1M), pengajuanIzins (1M), riwayat (1M), jadwals (1M, via pegawai_id).
  Accessor: `sisa_cuti`, `cuti_terpakai` (via PengajuanIzin).
  Field `encrypted`: no_rekening, nama_bank, npwp, no_bpjs_kesehatan, no_bpjs_ketenagakerjaan.
- Jadwal: pegawai_id, unit_sekolah_id, mata_pelajaran_id, kelas_id, hari (Senin..Minggu), jam_mulai/selesai, `jenis_jadwal` VARCHAR (reguler|lembur) default 'reguler'.
- Presensi: pegawai_id, jadwal_id (nullable untuk lembur), unit_sekolah_id, tanggal, jam_masuk/keluar, lat/long, foto (relative path, resolve via `FileHelper::fotoUrl()` di accessor), jarak_meter, status.
  Status enum: `hadir|telat|izin|sakit|alpa` (default `alpa`). Kolom anti-spoof: `akurasi_*`, `kecepatan_*`, `lokasi_perlu_review`, `captured_at`.
  Kolom lembur: `is_lembur` BOOLEAN, `lembur_status` VARCHAR(20) (pending|disetujui|ditolak) default NULL.
- Penggajian (1 periode "m-Y" per pegawai) -> PenggajianDetail (komponen_gaji_id, nama_komponen, tipe, nominal). Status: draft -> finalized -> paid.
- KomponenGaji: jenis VARCHAR(50) (fixed|persentase|dinamis_kehadiran|dinamis_masa_bakti|dinamis_jam_mengajar|dinamis_lembur), tipe ENUM(pendapatan|potongan),
  `nilai_default` (bukan `nilai`), `kode`, `is_taxable` (bukan `taxable`), `unit_sekolah_id`, `is_active`, `urutan`, `tampil_di_matrix`.
  TIDAK ada kolom `persentase` — nilai persen disimpan di `nilai_default` (dibagi 100 di kode).
  PERINGATAN: logika payroll memakai `stripos($komponen->nama, ...)` — ganti nama = payroll rusak; idealnya pakai kolom flag.
- PengajuanIzin: pegawai_id, jenis_izin ENUM(sakit|izin|cuti), tanggal_mulai, tanggal_selesai, status ENUM(pending|disetujui|ditolak) default pending.
  Dipakai hitung sisa cuti. TIDAK punya `unit_sekolah_id` (scope via relasi pegawai).
- SkalaMasaBakti: masa_kerja_tahun (unique int) -> nominal_gaji.

## 7. ALUR INTI
- LOGIN: Desktop `AuthenticatedSessionController` (web_admin); Mobile `MobileAuthController` (web_mobile). Logout invalidate session + regenerate token.
- PRESENSI (mobile): `MobileController::storeAbsen` -> validasi geofence (Haversine `CalculatesDistance`, vs unit.radius_meter) ->
  simpan foto via `ImageUploadService` (config disk) -> upsert Presensi dalam DB transaction + lockForUpdate.
- LEMBUR (mobile): toggle `is_lembur` di absen -> geofence via primary unit, `lembur_status='pending'`, skip telat check.
  Foto bukti lembur overlay: label + nama + unit + waktu detik + lokasi (canvas burn-in).
  Approval admin: `PresensiController::approveLembur`/`rejectLembur` di route `presensi/{id}/approve-lembur`/`reject-lembur`.
- IZIN: `MobileIzinController` (pegawai) + `PengajuanIzinController` (admin approve/reject).
- PAYROLL WIZARD: `penggajian/run` pilih periode -> `createDraft` (loop pegawai aktif, hitung komponen dari Presensi+SkalaMasaBakti+jam mengajar)
  -> `worksheet` (edit JSON) -> `finalizeWorksheet`. `createDraft` jalan **sinkron** (dalam DB transaction). Step-1 route = `penggajian.run.indexRun`.
- JADWAL: `JadwalController` (weekly board, anti-bentrok).
- LAPORAN: `LaporanController` preview/export Excel (presensi/penggajian/lemburan) — dipaginate.
- BACKUP: `BackupController` (superadmin) pakai Spatie DbDumper — deteksi driver DB (sqlite/mysql).

## 8. KONVENSI
- Pint preset laravel; jalankan `./vendor/bin/pint` sebelum commit.
- Setiap mutasi wajib `$request->validate()`/Form Request.
- Output React auto-escape; hindari `dangerouslySetInnerHTML`.
- `HandleInertiaRequests::share`: auth.user(+pegawai), auth.permissions[], auth.roles[], flash.message/error.
- Jangan hardcode secret; `.env` gitignored; `APP_DEBUG=false` di prod.
- Upload: validasi base64 + mime + ukuran, simpan UUID, jangan pakai input user sebagai nama file.

## 9. PERHITUNGAN (CALCULATIONS)

### 9.1 Presensi
- **Geofence**: `CalculatesDistance::calculateDistance()` Haversine, earthRadius 6371000m. Tolak jika `$distance > $unit->radius_meter` (default 50m).
- **Flow**: `MobileController::storeAbsen` (mobile) & `PresensiController::store` (admin). Transaction + `lockForUpdate`, upsert per `pegawai_id + jadwal_id + tanggal`.
- **Status** ditentukan SAAT absen MASUK: `now('H:i:s') > $jadwal->jam_mulai ? 'telat' : 'hadir'`. Cek string `H:i:s` — TANPA toleransi menit. Absen keluar TIDAK ubah status. `izin/sakit/alpa` dari PengajuanIzin or admin manual. Default `alpa`.
- **Anti-spoof** (migrasi `2026_07_02_...`): kolom `akurasi_masuk/keluar`, `kecepatan_masuk/keluar`, `lokasi_perlu_review` (OR dari `$mockSuspect || $accuracy < 10`), `captured_at`. TIDAK ada kolom `device_id`/`mock_location` — nilai input `mock_suspect` hanya feed ke flag review lalu dibuang.
- **TIDAK ADA** perhitungan jam kerja / menit telat / lembur dari `jam_masuk−jam_keluar`. Kolom anti-spoof cuma disimpan, TIDAK dipakai perhitungan.
- **Lembur**: saat absen MASUK dgn toggle, `jadwal_id` nullable (skip telat check), geofence via primary unit. `lembur_status='pending'`. Approval admin via `PresensiController::approveLembur`/`rejectLembur`. Foto overlay: label BUKTI LEMBUR + nama + unit + waktu HH:mm:ss + lokasi.

### 9.2 Payroll (Komponen Gaji & Penggajian)
Semua di `PenggajianController` (`computeComponentNominal`:502-585, `computeAttendance`:471-497).

**KomponenGaji**:
- `jenis` VARCHAR(50) divalidasi: `fixed|persentase|dinamis_kehadiran|dinamis_masa_bakti|dinamis_jam_mengajar|dinamis_lembur`.
- `tipe` ENUM `pendapatan|potongan`.
- `nilai_default` (bkn `nilai`), `kode`, `is_taxable` (bkn `taxable`), `unit_sekolah_id`, `is_active`, `urutan`, `tampil_di_matrix`.
- TIDAK ada kolom `persentase` — nilai persen disimpan di `nilai_default` (dibagi 100 di kode).
- **Override**: pivot `pegawai_komponen_gaji.nominal` (jika tidak null) menimpa `nilai_default`.

**Rumus per jenis**:
- **fixed**: pivot nominal else `nilai_default`.
- **persentase**: base = gaji pokok (`kode === 'gaji_pokok'` ATAU `stripos(nama, 'Gaji Pokok'/'Basic Salary')`). `nominal = (nilai_default / 100) × baseSalary`. Base HANYA gaji pokok, bukan total pendapatan.
- **dinamis_kehadiran**: `rate × count`. Count dipilih via `kode` (`kehadiran_telat/alpa/sakit/izin/cuti/tunjangan_kehadiran`) ATAU `stripos(nama, 'telat'|'alpa'|'sakit'|'izin'|'cuti'|'makan'|'transport'|'hadir')`. Catch-all `tunjangan_kehadiran/makan/transport/hadir` = rate × (hadir + telat).
- **dinamis_masa_bakti**: tenure = `tanggal_mulai_kerja->diffInYears($periodeEnd)`. Ambil skala PERTAMA dgn `masa_kerja_tahun <= yearsOfService` (skala DESC). Jika `tanggal_mulai_kerja` null → 0.
- **dinamis_jam_mengajar**: `rate × totalJamBulanan`. Jam = Σ(`jam_selesai−jam_mulai` per jadwal × jumlah hari `hari` tsb di periode), filter unit via `komponen.unit_sekolah_id`. Pakai **jadwal** (bukan presensi aktual). **Skip** jadwal dgn `jenis_jadwal='lembur'` (cegah dobel bayar).
- **dinamis_lembur**: `rate × totalJamLembur`. Total jam = Σ jam aktual (`jam_keluar−jam_masuk` dalam jam, minimal 0) dari `Presensi` di periode dgn `is_lembur=true` AND `lembur_status='disetujui'`. Pakai **presensi aktual** (bukan jadwal). Filter unit via `komponen.unit_sekolah_id`.

**Attendance counts** (`computeAttendance`): hadir/telat/sakit/izin/cuti dari `Presensi::groupBy(status)`. Skip `is_lembur=true`. `alpa = manual_alpa + max(0, workingDays - (hadir+telat+sakit+izin+cuti))`. `workingDays` = Σ `countWeekdayInRange(jadwal.hari)` untuk jadwal `jenis_jadwal='reguler'`.

**Total**: akumulasi `bcadd`/`bcsub` (PHP). `gaji_bersih = totalPendapatan - totalPotongan`. `total_taxable` = Σ pendapatan yg `is_taxable`. **TIDAK ada hitungan PPh21/PTKP/pajak** — hanya akumulasi flag.

**Status**: `draft` → `finalized` → `paid`. Hanya `draft` bisa edit/hapus.

**Peringatan**: `stripos($komponen->nama, ...)` rentan — ganti nama yg mengandung substring di atas = payroll rusak.

### 9.3 Izin / Cuti / Sisa Cuti
- `PengajuanIzin`: `jenis_izin ∈ {sakit,izin,cuti}`, `status ∈ {pending,disetujui,ditolak}` (default pending). Approve → `Presensi::updateOrCreate` per hari (skip weekend) dgn `status = jenis_izin`.
- `sisa_cuti`: Pegawai accessor (`$appends`). `jatah_cuti_tahunan` (default 12) − `cuti_terpakai`. `cuti_terpakai` = Σ `(diffInDays+1)` dari record `cuti` yg `disetujui` dgn `tanggal_mulai` di tahun berjalan. Pending cuti BELUM mengurangi saldo. TIDAK ada kolom `sisa_cuti` — dihitung runtime.
- Validasi submit: `requestedDays > sisa_cuti` → tolak.

### 9.4 Skala Masa Bakti & Tenure
- `SkalaMasaBakti`: `masa_kerja_tahun` (unique int) → `nominal_gaji`. Lookup: bracket tertinggi dgn `masa_kerja_tahun <= yearsOfService` (di-order DESC).
- Tenure = `Carbon::diffInYears(tanggal_mulai_kerja, periodeEnd)`. Tidak ada kolom cached.

### 9.5 Jadwal Anti-Bentrok
Clash = pegawai SAMA + hari SAMA + overlap waktu (`existing.jam_mulai < new.jam_selesai AND existing.jam_selesai > new.jam_mulai`). **Lintas unit** (tidak filter unit). Dalam `DB::transaction` + `lockForUpdate`. Cek di `store`, `generate`, `swap`. TIDAK cek bentrok ruang/kelas.

## 10. STANDAR KEAMANAN (wajib dijaga)
- CSRF aktif di group `web`. Jangan matikan.
- Semua route mutating butuh auth + otorisasi (`can:`).
- Validasi input di server (jangan percaya frontend).
- Parameter binding Eloquent (hindari raw SQL dengan input user).
- Rate-limit endpoint auth & mobile mutating.
