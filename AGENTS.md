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
- Rate limiting: endpoint mutating WAJIB throttle (10-60 req/min).
- Constants: gunakan file constants untuk hardcoded strings.
- Error handling: React root pakai ErrorBoundary.
- FormRequest: gunakan untuk validasi + preprocessing, bukan `$request->merge()`.

## 9. PERHITUNGAN (CALCULATIONS)

### 9.1 Presensi
- **Geofence**: `CalculatesDistance::calculateDistance()` Haversine, earthRadius 6371000m. Tolak jika `$distance > $unit->radius_meter` (default 50m).
- **Flow**: `MobileController::storeAbsen` (mobile) & `PresensiController::store` (admin). Transaction + `lockForUpdate`, upsert per `pegawai_id + jadwal_id + tanggal`.
- **Status** ditentukan SAAT absen MASUK: `now('H:i:s') > $jadwal->jam_mulai ? 'telat' : 'hadir'`. Cek string `H:i:s` — TANPA toleransi menit. Absen keluar TIDAK ubah status. `izin/sakit/alpa` dari PengajuanIzin or admin manual. Default `alpa`.
- **Anti-spoof** (migrasi `2026_07_02_...`): kolom `akurasi_masuk/keluar`, `kecepatan_masuk/keluar`, `lokasi_perlu_review` (OR dari `$mockSuspect || $accuracy < 10`), `captured_at`. TIDAK ada kolom `device_id`/`mock_location` — nilai input `mock_suspect` hanya feed ke flag review lalu dibuang.
- **TIDAK ADA** perhitungan jam kerja / menit telat / lembur dari `jam_masuk−jam_keluar`. Kolom anti-spoof cuma disimpan, TIDAK dipakai perhitungan.
- **Lembur**: saat absen MASUK dgn toggle, `jadwal_id` nullable (skip telat check), geofence via primary unit. `lembur_status='pending'`. Approval admin via `PresensiController::approveLembur`/`rejectLembur`. Foto overlay: label BUKTI LEMBUR + nama + unit + waktu HH:mm:ss + lokasi.

### 9.2 Payroll (Komponen Gaji & Penggajian)
Semua di `PenggajianController` (`computeComponentNominal`, `computeAttendance`).

**KomponenGaji**:
- `jenis` VARCHAR(50) divalidasi: `fixed|persentase|dinamis_kehadiran|dinamis_masa_bakti|dinamis_jam_mengajar|dinamis_lembur`.
- `tipe` ENUM `pendapatan|potongan`.
- `nilai_default` (bkn `nilai`), `kode`, `is_taxable` (bkn `taxable`), `unit_sekolah_id`, `is_active`, `urutan`, `tampil_di_matrix`.
- TIDAK ada kolom `persentase` — nilai persen disimpan di `nilai_default` (dibagi 100 di kode).
- **Override**: pivot `pegawai_komponen_gaji.nominal` (jika tidak null) menimpa `nilai_default`.

**Rumus per jenis**:
- **fixed**: pivot nominal else `nilai_default`.
- **persentase**: base = gaji pokok via helper `findKomponenByKode($globalKomponens, 'gaji_pokok', ['Gaji Pokok', 'Basic Salary'])`. `nominal = (nilai_default / 100) × baseSalary`. Base HANYA gaji pokok.
- **dinamis_kehadiran**: gunakan helper `isKehadiranType()` untuk lookup. Priority: kode column → stripos(nama) fallback. Cek: `kehadiran_telat/alpa/sakit/izin/cuti/tunjangan_kehadiran`.
- **dinamis_masa_bakti**: tenure = `tanggal_mulai_kerja->diffInYears($periodeEnd)`. Ambil skala PERTAMA dgn `masa_kerja_tahun <= yearsOfService` (skala DESC).
- **dinamis_jam_mengajar**: `rate × totalJamBulanan`. Jam dari jadwal (skip `jenis_jadwal='lembur'`).
- **dinamis_lembur**: `rate × totalJamLembur`. Jam dari Presensi dengan `is_lembur=true` AND `lembur_status='disetujui'`.

**Attendance counts** (`computeAttendance`): hadir/telat/sakit/izin/cuti dari `Presensi::groupBy(status)`. Skip `is_lembur=true`. `alpa = manual_alpa + max(0, workingDays - (hadir+telat+sakit+izin+cuti))`. `workingDays` pakai formula O(1) di `countWeekdayInRange()`.

**Total**: akumulasi `bcadd`/`bcsub`. `gaji_bersih = totalPendapatan - totalPotongan`.

**Status**: `draft` → `finalized` → `paid`. Hanya `draft` bisa edit/hapus.

### 9.3 Izin / Cuti / Sisa Cuti
- `PengajuanIzin`: `jenis_izin ∈ {sakit,izin,cuti}`, `status ∈ {pending,disetujui,ditolak}`. Approve → `Presensi::updateOrCreate` per hari.
- `sisa_cuti`: Pegawai accessor. `jatah_cuti_tahunan` (default 12) − `cuti_terpakai`.
- Validasi: `requestedDays > sisa_cuti` → tolak.

### 9.4 Skala Masa Bakti & Tenure
- Lookup: bracket tertinggi dgn `masa_kerja_tahun <= yearsOfService` (DESC).

### 9.5 Jadwal Anti-Bentrok
Clash = pegawai SAMA + hari SAMA + overlap waktu. Cek di `store`, `generate`, `swap` dengan `lockForUpdate`.

## 10. STANDAR KEAMANAN (wajib dijaga)
- CSRF aktif di group `web`. Jangan matikan.
- Semua route mutating butuh auth + otorisasi (`can:`).
- Validasi input di server (jangan percaya frontend).
- Parameter binding Eloquent (hindari raw SQL dengan input user).
- Rate-limit endpoint auth & mobile mutating: 10 req/min.
- FormRequest untuk override data, bukan `$request->merge()`.
- Extract authorization logic ke helper method untuk consistency.
- Error boundary di React root untuk graceful degradation.

## 11. PRD - PRODUCT REQUIREMENTS DOCUMENT

### 11.1 Overview Sistem
**Nama:** HRIS Yayasan  
**Tujuan:** Sistem terpusat untuk manajemen SDM yayasan pendidikan multi-unit  
**Target Users:** Pegawai (Mobile PWA), Admin HR (Desktop), Superadmin (Full Access)

### 11.2 User Stories Detail

#### A. Modul Presensi (Mobile PWA)

**US-PRESENSI-001: Absen Masuk**
```
Sebagai pegawai
Saya ingin melakukan absen masuk dari lokasi unit
Agar kehadiran saya tercatat secara real-time

Acceptance Criteria:
- GIVEN: Pegawai berada dalam radius GPS unit (≤50m)
- WHEN: Pegawai ambil foto selfie dan kirim
- THEN: Sistem simpan presensi dengan status HADIR/TELAT

Business Rules:
- Status TELAT jika jam_masuk > jadwal.jam_mulai (NO toleransi)
- Foto HARUS valid (base64 + mime check)
- GPS akurasi > 0 (tolak jika 0 = mock GPS)

Error Scenarios:
- E001: GPS tidak terdeteksi → "Pastikan GPS aktif dan coba di tempat terbuka"
- E002: Di luar radius → "Anda di luar jangkauan Unit. Jarak: Xm (Batas: Ym)"
- E003: Akurasi 0 meter → "Akurasi 0 meter dicurigai mock GPS"
- E004: Kamera tidak bisa diakses → "Gunakan tombol upload foto dari galeri"
- E005: Sudah absen masuk → "Anda sudah melakukan absen masuk"

Data Disimpan: pegawai_id, jadwal_id, tanggal, jam_masuk, lat/long, foto, akurasi, status
```

**US-PRESENSI-002: Absen Keluar**
```
Sebagai pegawai
Saya ingin melakukan absen keluar
Agar jam kerja saya tercatat lengkap

Acceptance Criteria:
- GIVEN: Pegawai sudah absen masuk hari ini
- WHEN: Ambil foto dan kirim
- THEN: Update record dengan jam_keluar

Business Rules:
- HARUS sudah absen masuk dulu
- Tidak ada status TELAT untuk keluar
- Geofence tetap di-check

Error Scenarios:
- E001: Belum absen masuk → "Anda belum absen masuk"
- E002: Sudah absen keluar → "Anda sudah melakukan absen keluar"
```

**US-PRESENSI-003: Lembur**
```
Sebagai pegawai
Saya ingin mencatat lembur tanpa jadwal
Agar jam lembur bisa dihitung untuk payroll

Acceptance Criteria:
- GIVEN: Pegawai toggle "Mode Lembur"
- WHEN: Ambil foto dan kirim
- THEN: Buat record dengan is_lembur=true, lembur_status='pending'

Business Rules:
- Skip jadwal selection (jadwal_id NULL)
- Skip status TELAT check
- Geofence via PRIMARY unit pegawai
- Foto overlay: label "BUKTI LEMBUR" + nama + unit + waktu
- Approval required: admin harus approve

Approval Flow:
1. Pegawai submit → PENDING
2. Admin buka "Presensi" → select record
3. Admin klik Approve/Reject
4. Status update: DISETUJUI/DITOLAK
```

#### B. Modul Izin/Cuti (Mobile PWA)

**US-IZIN-001: Ajukan Cuti**
```
Sebagai pegawai
Saya ingin mengajukan cuti tahunan
Agar bisa beristirahat dengan izin

Acceptance Criteria:
- GIVEN: Sisa cuti > 0
- WHEN: Input tanggal + alasan
- THEN: Buat pengajuan status PENDING

Business Rules:
- Requested days = (selesai - mulai) + 1
- Validation: requested_days ≤ sisa_cuti
- Default jatah_cuti_tahunan = 12 hari

Error Scenarios:
- E001: Requested > sisa → "Sisa cuti tidak mencukupi. Anda mengajukan X hari, sisa: Y hari"
- E002: Tanggal selesai < mulai → "Tanggal selesai harus setelah tanggal mulai"
```

**US-IZIN-002: Ajukan Izin/Sakit**
```
Sebagai pegawai
Saya ingin mengajukan izin atau melaporkan sakit
Agar kehadiran tercatat dengan benar

Business Rules:
- Jenis: SAKIT | IZIN | CUTI
- Sakit/Izin: tanpa sisa cuti check
- Approval flow sama dengan cuti
```

#### C. Modul Payroll (Desktop Admin)

**US-PAYROLL-001: Generate Payroll Baru**
```
Sebagai admin HR
Saya ingin generate payroll otomatis per periode
Agar perhitungan gaji konsisten dan akurat

Acceptance Criteria:
- GIVEN: Pilih periode (bulan + tahun)
- WHEN: Klik "Generate Draft"
- THEN: Buat Penggajian untuk semua pegawai aktif status DRAFT

Business Rules:
- Overwrite draft existing (delete + regenerate)
- Skip pegawai status_aktif != 'aktif'
- Scope by unit untuk admin unit
- Transaction-based: semua sukses atau rollback

Performance: 100 pegawai → ≤ 10 detik

Error Scenarios:
- E001: Pegawai tidak punya jadwal → Skip dengan warning log
- E002: Komponen tidak aktif → Skip komponen tersebut
- E003: Finalized exists → Tidak bisa regenerate
```

**US-PAYROLL-002: Edit Komponen di Worksheet**
```
Sebagai admin HR
Saya ingin edit nominal komponen sebelum finalize
Agar bisa adjustment sesuai kondisi aktual

Business Rules:
- Inline edit di table worksheet
- Add new component ad-hoc (kasbon, bonus)
- Auto-save saat blur/leave cell

Error Scenarios:
- E001: Negative nominal → Validation error
- E002: Non-numeric → Format harus angka
- E003: Already finalized → "Tidak bisa edit, status FINALIZED"
```

**US-PAYROLL-003: Finalize Payroll**
```
Sebagai admin unit
Saya ingin mengunci payroll
Agar data gaji final dan siap pembayaran

Business Rules:
- Setelah finalize: READ-ONLY
- Only admin unit bisa finalize (superadmin tidak berhak)
```

#### D. Modul Laporan (Desktop Admin)

**US-LAPORAN-001: Export Laporan Presensi**
```
Sebagai admin HR
Saya ingin export laporan presensi ke Excel
Agar bisa analisis dan arsip

Business Rules:
- Format: XLSX (Maatwebsite Excel)
- Filter: unit, periode (required)
- Data: pegawai, tanggal, jam_masuk, jam_keluar, status, lembur

Performance: 1000 rows → ≤ 30 detik
```

### 11.3 Business Flows

#### Flow Presensi Harian
```
START → Buka Mobile PWA
  ↓
GPS detected? → NO → Error: GPS tidak aktif
  ↓ YES
Load jadwal hari ini
  ↓
Toggle Lembur? → YES → Set is_lembur=true, jadwal_id=NULL
  ↓ NO
Pilih jadwal dari list
  ↓
Ambil foto selfie
  ↓
Validasi foto? → NO → Error: Foto tidak valid
  ↓ YES
Check geofence: distance ≤ radius? → NO → Error: Di luar radius
  ↓ YES
Sudah absen masuk/keluar? → YES → Error: Sudah absen
  ↓ NO
Save presensi record → Determine status (TELAT jika > jam_mulai, else HADIR)
  ↓
Return success → END
```

#### Flow Approval Lembur
```
START → Admin buka menu Presensi → Filter is_lembur=true
  ↓
Select record dengan lembur_status='pending'
  ↓
Review: jam lembur, lokasi, foto
  ↓
Decision: APPROVE or REJECT
  ↓ APPROVE                    ↓ REJECT
Update status='disetujui'      Update status='ditolak'
  ↓                              ↓
Log approval                   Log rejection
  ↓                              ↓
Notifikasi pegawai             Notifikasi pegawai
  ↓                              ↓
END                            END
```

#### Flow Payroll Generation
```
START → Admin pilih periode → Klik "Generate Draft"
  ↓
Load pegawai aktif + scope by unit
  ↓
Prefetch: komponens, skalas, presensi counts, lembur data
  ↓
Start DB Transaction
  ↓
Loop per pegawai:
  - Check existing → If DRAFT: delete & regenerate
  - If FINALIZED/PAID: skip
  - Calculate attendance counts
  - Loop per komponen:
    - Lookup via kode → fallback stripos
    - Compute nominal based on jenis
    - If > 0: add to details
  - Create Penggajian header + details
  ↓
DB Commit → If error: Rollback
  ↓
Redirect to Worksheet → END
```

### 11.4 Data Validation Rules

#### Presensi Validation
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| jadwal_id | integer | NO | exists:jadwal,id |
| is_lembur | boolean | NO | nullable |
| tipe | string | YES | in:masuk,keluar |
| latitude | numeric | YES | min:-90,max:90 |
| longitude | numeric | YES | min:-180,max:180 |
| foto | base64 | YES | regex:/^data:image\/\w+;base64,/ |

#### Cuti Validation
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| jenis_izin | enum | YES | in:sakit,izin,cuti |
| tanggal_mulai | date | YES | date |
| tanggal_selesai | date | YES | date|after_or_equal:tanggal_mulai |
| alasan | text | YES | min:10,max:500 |

### 11.5 Security Requirements

| Aspect | Requirement | Implementation |
|--------|-------------|----------------|
| Authentication | Session-based, timeout 120min (desktop), 1440min (mobile) | Laravel Breeze + Guards |
| Authorization | Role-based: superadmin, admin_unit, pegawai | Spatie Permission |
| Data Encryption | NIK, rekening, NPWP, BPJS encrypted | Laravel encrypted cast |
| Rate Limiting | 10 req/min mutating endpoints | Throttle middleware |
| Logging | All mutations logged with user + timestamp | Audit log |

## 12. CARA PAKAI CEPAT

### 12.1 Setup Awal

```bash
# Clone & install
git clone <repo-url> hris
cd hris
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Build
npm run build

# Dev server
composer run dev

# Access:
# Desktop: http://localhost:8000
# Mobile: http://localhost:8000/mobile
```

### 12.2 Common Tasks

#### Tambah Pegawai Baru
1. Login admin/superadmin
2. Pegawai → Tambah Pegawai
3. Isi data dasar (NIK, nama, kontak)
4. Assign unit + jabatan ("+ Tambah Unit")
5. Upload foto (opsional)
6. Simpan

#### Absen Harian (Mobile)
1. Login di Mobile PWA
2. Klik "Presensi"
3. Pilih jadwal (jika tidak lembur)
4. Klik tombol ambil foto
5. Pastikan GPS detected
6. Kirim Presensi → Status: HADIR/TELAT

#### Lembur (Mobile)
1. Toggle "Mode Lembur"
2. Skip pilih jadwal
3. Ambil foto (overlay "BUKTI LEMBUR")
4. Kirim → Status PENDING
5. Admin approve di desktop

#### Generate Payroll
1. Login admin/superadmin
2. Payroll → Run Payroll
3. Pilih periode (bulan + tahun)
4. Klik "Generate Draft"
5. Tunggu ≤10 detik
6. Review worksheet
7. Finalize

#### Export Laporan
1. Login admin
2. Buka "Laporan"
3. Pilih tipe: Presensi/Penggajian/Lemburan
4. Filter periode + unit
5. Preview → Export Excel

### 12.3 Troubleshooting Error Scenarios

#### A. Presensi Errors

**ERROR: "GPS tidak terdeteksi"**
```
Causes:
1. GPS permission denied
2. GPS hardware disabled
3. Browser tidak support Geolocation
4. HTTP (butuh HTTPS)

Solutions:
1. Allow location permission di browser
2. Enable GPS di device settings
3. Gunakan browser modern (Chrome-based)
4. Pastikan HTTPS di production

Location: Absen.jsx:146-154
```

**ERROR: "Akurasi 0 meter (mock GPS)"**
```
Causes:
1. Fake GPS app active
2. GPS hardware malfunction
3. Indoor location

Solutions:
1. NONAKTIFKAN fake GPS app
2. Restart device
3. Pindah ke lokasi terbuka

Location: MobileController.php:260-262
```

**ERROR: "Di luar jangkauan Unit"**
```
Causes:
1. Pegawai benar-benar di luar lokasi
2. Unit GPS coordinate tidak akurat
3. Radius meter terlalu kecil

Solutions:
1. Approaching unit location
2. Admin update unit.latitude/longitude
3. Admin increase radius_meter

Location: MobileController.php:252-254
```

**ERROR: "Kamera tidak dapat diakses"**
```
Causes:
1. Camera permission denied
2. Camera dipakai app lain
3. Hardware camera disabled

Solutions:
1. Allow camera permission
2. Close app lain yang pakai camera
3. Enable camera di settings
4. Use fallback: upload foto dari galeri

Location: Absen.jsx:67-79
```

#### B. Payroll Errors

**ERROR: "Maximum execution time exceeded"**
```
Causes:
1. Terlalu banyak pegawai (>200)
2. Query N+1 tidak optimized
3. Slow database connection

Solutions:
1. Increase max_execution_time di php.ini:
   max_execution_time = 300
2. Check database indexes
3. Upgrade database resources

Location: PenggajianController.php:114-189
```

**ERROR: "Integrity constraint violation"**
```
Causes:
1. Duplicate payroll untuk periode sama
2. Foreign key error

Solutions:
1. Check apakah periode sudah ada
2. If DRAFT → akan di-overwrite
3. If FINALIZED → tidak bisa regenerate

Location: PenggajianController.php:118-130
```

**ERROR: "Tidak bisa edit, status FINALIZED"**
```
Explanation: Ini normal behavior (payroll locked setelah finalize)

Solutions:
- Superadmin bisa unfinalize (rare case)
- Alternative: Generate periode baru + manual adjustment

Location: PenggajianController.php:235-236
```

#### C. Login/Auth Errors

**ERROR: "Akses ditolak. Akun tidak terhubung dengan data pegawai"**
```
Causes:
1. User tidak punya record Pegawai
2. Pegawai record deleted
3. Login di portal yang salah

Solutions:
1. Admin create Pegawai → link ke User via user_id
2. Admin restore deleted Pegawai
3. Use correct credentials untuk portal yang tepat

Location: ResolvesPegawai.php:24-47
```

**ERROR: "Session expired"**
```
Causes:
1. Session timeout (120min desktop, 1440min mobile)
2. Browser clear cookies

Solutions:
1. Re-login
2. Extend SESSION_LIFETIME di .env
```

#### D. Database Errors

**ERROR: "Connection refused"**
```
Causes:
1. MySQL server tidak running
2. DB credentials salah
3. Firewall blocking

Solutions:
1. Start MySQL: systemctl start mysql / net start mysql
2. Check .env: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
3. Check firewall rules
```

**ERROR: "Column not found"**
```
Causes:
1. Migration belum dijalankan
2. Schema mismatch

Solutions:
1. Run: php artisan migrate --force
2. Pull latest code → migrate again
```

### 12.4 Performance Tips

#### Untuk Admin
- Large Export: Filter periode pendek (1 bulan max)
- Payroll: Pastikan komponen aktif sebelum generate
- Dashboard: Clear cache jika lambat

#### Untuk Pegawai
- PWA Caching: Hard refresh (Ctrl+F5) jika data tidak update
- GPS Lock: Tunggu 30-60 detik di tempat terbuka
- Upload Foto: Kompres foto, gunakan WiFi

## 13. BEST PRACTICES (WAJIB DIIKUTI)

### 13.1 Performance Standards
- **Hindari N+1 Query**: Eager load dengan `with()` atau prefetch.
- **Algorithm Optimization**: Gunakan formula O(1) untuk iteratif calculations.
- **Batch Processing**: Fetch data di luar loop, proses di dalam.
- **useMemo di React**: Untuk computed values yang mahal.

### 13.2 Code Quality
- **DRY Principle**: Extract duplikasi ke helper/private methods.
- **Constants**: Pusatkan strings di constants class/file.
- **Component Breakdown**: Pecah komponen React > 300 lines.
- **FormRequest**: Gunakan untuk validasi + preprocessing.

### 13.3 Security Checklist
- **Rate Limiting**: Endpoint mutating wajib throttle (10-60x/menit).
- **Error Boundary**: React root wrap ErrorBoundary.
- **AbortController**: Cancel API calls saat unmount.
- **FormRequest Override**: Override di FormRequest, bukan `$request->merge()`.

### 13.4 Maintainability
- **PHPDoc**: Document complex methods dengan parameter + return types.
- **Helper Methods**: Extract pattern matching dengan backward compatibility.
- **Consistent Imports**: Gunakan alias imports (@/).

## 14. PERFORMANCE GUIDELINES

### 14.1 Database Queries
- **Eager Load**: Gunakan `->with()` untuk relasi yang diakses.
- **Prefetch**: Fetch data relasi di luar loop, group by key.
- **Index**: Pastikan kolom yang sering di-query punya index.

### 14.2 Algorithm Optimization
```php
// countWeekdayInRange: O(1) formula
$totalDays = $start->diffInDays($end) + 1;
$fullWeeks = intdiv($totalDays, 7);
$remainderDays = $totalDays % 7;
$count = $fullWeeks;
// + remainder check
```

### 14.3 React Optimization
- **useMemo**: Untuk computed values dari props/state.
- **AbortController**: Cancel pending API calls.
- **Error Boundaries**: Wrap components yang might crash.

## 15. CODE STYLE & MAINTAINABILITY

### 15.1 Helper Methods Pattern

**findKomponenByKode() - Lookup dengan backward compatibility**
```php
// Location: PenggajianController.php
private function findKomponenByKode($komponens, string $targetKode, array $namePatterns = []): ?KomponenGaji
{
    // Priority 1: Exact kode match
    $komponen = $komponens->first(fn($k) => $k->kode === $targetKode);
    
    // Priority 2: Pattern matching nama (backward compat)
    if (!$komponen && !empty($namePatterns)) {
        $komponen = $komponens->first(function ($k) use ($namePatterns) {
            foreach ($namePatterns as $pattern) {
                if (stripos($k->nama, $pattern) !== false) return true;
            }
            return false;
        });
    }
    
    return $komponen;
}

// Usage:
$gajiPokok = $this->findKomponenByKode($globalKomponens, 'gaji_pokok', ['Gaji Pokok', 'Basic Salary']);
```

**isKehadiranType() - Check jenis kehadiran**
```php
private function isKehadiranType(KomponenGaji $komponen, string $targetKode, array $namePatterns = []): bool
{
    if ($komponen->kode === $targetKode) return true;
    
    foreach ($namePatterns as $pattern) {
        if (stripos($komponen->nama, $pattern) !== false) return true;
    }
    
    return false;
}

// Usage:
if ($this->isKehadiranType($komponen, 'kehadiran_telat', ['telat'])) {
    $nominal = $rate * $counts['telat'];
}
```

**authorizePayrollModification() - DRY authorization**
```php
private function authorizePayrollModification(): void
{
    $user = auth()->user();
    if (!$user || !$user->can('view_payroll')) {
        abort(403, 'Akses ditolak.');
    }
    if ($user->can('view_all_units')) {
        abort(403, 'Hanya Admin Unit yang berhak.');
    }
}

// Usage in methods: finalize(), destroy(), markPaid()
$this->authorizePayrollModification();
```

### 15.2 Component Structure

**Breakdown Pattern:**
```
Pegawai/Edit.jsx (312 lines)
  ↓ Breakdown
  ├── Pegawai/Partials/UnitAssignmentSection.jsx
  ├── Pegawai/Partials/MapelSection.jsx
  └── Main form logic stays in Edit.jsx
```

**Utils Extraction:**
```
Absen.jsx: haversine function inline
  ↓ Move to
resources/js/Utils/geo.js
  → export calculateDistance(), checkGeofence()
```

### 15.3 Constants Usage

**PHP Constants:**
```php
// app/Constants/PresensiMessages.php
class PresensiMessages
{
    public const GEOFENCE_OUTSIDE = 'Anda di luar radius. Jarak: %s meter';
    public const ABSEN_MASUK_SUCCESS = 'Absen masuk berhasil. Jarak: %sm';
}

// Usage:
$message = sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance);
```

**React Constants:**
```javascript
// resources/js/Constants/AppConstants.js
export const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

// Usage:
import { OSM_TILE_URL } from '@/Constants/AppConstants';
```

### 15.4 Backward Compatibility Strategy

**Kode Column Migration:**
```php
// Migration populate existing data
DB::table('komponen_gaji')->whereNull('kode')->update([
    'kode' => DB::raw("CASE
        WHEN LOWER(nama) LIKE '%gaji pokok%' THEN 'gaji_pokok'
        WHEN LOWER(nama) LIKE '%tunjangan kehadiran%' THEN 'tunjangan_kehadiran'
        ELSE NULL
    END")
]);
```

**Lookup Priority:**
```
1. Exact kode match (kolom `kode`)
2. Fallback: stripos($nama, 'pattern') jika kode NULL
```

**Why:**
- Existing data tidak rusak setelah migration
- New records pakai kode lebih robust
- Gradual migration tanpa breaking
