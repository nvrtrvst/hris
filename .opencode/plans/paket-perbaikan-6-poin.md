# Rencana Final — 6 Poin Perbaikan HRIS

Status: menunggu "lanjut" / nomor dari user. Urutan eksekusi: **1a → 1b → 3 → 4 → 6 → 1c**.
Verifikasi tiap fase: `vendor\bin\pint` (filtered) → `vendor\bin\phpunit` → `rtk npm run build`.
Tidak ada perubahan DB prod tanpa persetujuan (migrasi jalan via CI/deploy biasa).

---

## 1a. Fix Rekap Mengajar — bobot JP per durasi (bukan per baris)

**Masalah**: 1 jadwal blok (07:00–11:40 = 7 JP) dihitung `terjadwal++` = 1.

**Perubahan — `app/Exports/LaporanRekapMengajarExport.php`:**
1. Memoize `collection()` → `private ?Collection $cachedRows` — saat ini `summaryData()` memanggil `collection()` lagi (query ke-2 percuma).
2. Tambah eager `jadwal.unitSekolah:id,durasi_jp` di `with()` (:73).
3. Helper `jpWeight(Serializable $p): float` = `diffInMinutes(jadwal.jam_mulai→jam_selesai) / (unit.durasi_jp ?? 45)`, `round(,2)`, min 0. (default kolom `durasi_jp` = 45; selaras `PenggajianController:748`).
4. `collection()` :109/:124: `+= $w` bukan `++` (terjadwal/hadir/telat/alpa — 4 count, total & per-minggu). Data jadwal = kelipatan `durasi_jp` by konstruksi import → bobot integer; non-kelipatan → desimal, tidak di-round kasar.
5. `calendarData()` :145-202: **buang raw query SELECT/GROUP BY** → derive `$cells` dari cached rows (satu iterasi PHP, identik filter). Ini menyelesaikan 3 masalah sekaligus: SQL dialect (sqlite vs mysql `SUM(CASE…)`), duplikasi query, dan pemangkasan `take(500)` di caller. Signature: `calendarData(): array` (param `$rows` dihapus).
6. `map()` :246-252: `sprintf('%d/%d (telat %d)')` → `%d` mem-truncate float (1.5→1). Ganti ke `number_format` (desimal dibuang bila bulat) + totals (:239-242) `round(,2)`.

**Perubahan — `app/Http/Controllers/LaporanController.php`:**
- `:92` panggil `calendarData()` tanpa argumen.
- `rekapMengajarDetail()` :358-383: eager `jadwal.unitSekolah:id,durasi_jp`; `$detail` tiap baris + `'jp' => bobot`; `$summary` = jumlah bobot (bukan `count()`).
- `summaryData()` otomatis ikut memoized.

**FE**: `Laporan/Index.jsx` :511/:591/:683 format cell/KPI (desimal → `6,2 JP` / buang `.0`); `RekapMengajarDetail.jsx` tambah kolom "JP" per baris.

**Query**: 1 query data (turun dari 2) + eager 1 relasi. Tanpa SQL raw.

**Test**: `LaporanRekapMengajarTest` — pastikan fixture45-menit tetap = 1 JP (paritas), tambah case blok (1 baris 07:00–11:40, durasi 45 → 6.22 / durasi 40 → 7) pada `collection()` + `calendarData()` + `summaryData()`.

---

## 1b. Fix Riwayat PWA — label JP & expand per JP

**Perubahan — FE saja (tanpa backend):**
- `resources/js/Utils/waktuPresensi.js`: tambah `hitungJp(item)` = `(toMin(jam_selesai)-toMin(jam_mulai)) / (unit.durasi_jp ?? 45)` dan `splitJadwalKeJp(item)` → array sub-slot `[start+k·durasi_jp, +durasi_jp]` (n = round(durasi/durasi_jp); fallback 1 slot bila < durasi_jp).
- `resources/js/Pages/Mobile/Riwayat.jsx`:
  - `:245` `{g.count} JP` → `sum(hitungJp(g.items))` (format desimal rapat).
  - Expand `:259-267`: baris item → `splitJadwalKeJp(p)` di-map jadi sub-baris (`HH.MM–HH.MM` + badge status parent), alih-alih 1 baris `jadwal.jam_mulai–jam_selesai`.

**Query**: 0 (data sudah ada di payload `jadwal.jam_*` + `unit_sekolah`).

**Test**: util `waktuPresensi` — unit test blok2 JP & 1 JP (vitest? cek runner FE yang ada; bila tidak ada runner FE → verifikasi via build + manual note).

---

## 3. Hapus section "Riwayat Terbaru" Dashboard mobile

**Perubahan — `resources/js/Pages/Mobile/Dashboard.jsx`:**
- Hapus `:249-265` (SectionTitle + Card list records).
- Bersihkan import yang jadi unused (`Clock3`/`CalendarDays` bila tak terpakai lagi — grep dulu dalam file).
- **Payload backend tidak diubah** (`records` bisa dipakai halaman lain; cek pemakaian `records` di file — bila hanya di situ, biarkan payload, hemat diff).

**Query**: 0.

---

## 4. Fitur Wajib Pulang Mengajar (toggle per unit)

**Keputusan user**: `wajib_pulang_mengajar` bool default false + `pulang_sebelum_menit` int default 20 (dinamis per unit). OFF = perilaku persis sekarang. ON = pre-fill dimatikan, tombol "Selesai Mengajar" muncul N menit sebelum JP terakhir per grup (sesi), 1 ketuk → `jam_keluar` hanya di row JP terakhir sesi (row tengah kosong by design), lupa = permanen → admin ralat via Poin 6. Geofence pulang wajib (sudah ada di `slideJadwal`).

**Migrasi**: `unit_sekolah` + `wajib_pulang_mengajar` BOOLEAN default false, `pulang_sebelum_menit` INT default 20.

**Backend:**
- `UnitSekolah::$fillable` +2 field.
- `UnitSekolahController` store :50-64 & update :98-112: validasi `'wajib_pulang_mengajar' => 'boolean'`, `'pulang_sebelum_menit' => 'nullable|integer|min:1|max:120'` + default 20 (pola `toleransi_slide_menit`).
- **Matikan pre-fill** saat flag ON (hanya pegawai `is_tetap` — jalur honorer/`storeAbsen` tetap pre-fill agar tidak buntu, tidak ada tombol keluar di flow itu):
  - `MobileController:941` (`storeAbsenTransaction`, gate `if ($jadwal->jenis_jadwal === 'mengajar')` → tambah `&& !flag`) — flag dari `$unit/$unitSekolah`.
  - `MobileController:1457` (pre-fill slide masuk) + `:1558` (`coverSesiMengajar` cover rows) — flag dari `$jadwal->unitSekolah` (sudah di-load di `slideJadwal:1341`, tanpa query tambahan).
- **Window keluar** di `slideJadwal` (setelah `:1371`): saat `tipe === 'keluar'` + unit flag ON → `sekarang` wajib `>= sesiEnd - pulang_sebelum_menit`, else 422 `sprintf('Tombol pulang tersedia pukul %s', HH:MM)`. Tidak ada batas atas (telat tetap boleh = fallback lupa). Flag OFF → window apa adanya (kini tanpa batas; path FE memang belum ada tombolnya).
- Idempotency sudah aman: target lookup `:1413-1420` + `presensiKeluarTarget:1497-1507` reject bila sudah terisi; `lockForUpdate` + unique `presensi_key` sudah ada.

**FE — `TetapPresensi.jsx`, branch `done && !closed` (:624-628):**
- Bila `g.unit_sekolah?.wajib_pulang_mengajar`:
  - `jamSekarang >= toMin(g.jamSelesaiLast) - pulang_sebelum_menit` → render `SlideToConfirm` `onConfirm={() => handleSlideGroup(g, 'keluar')}`, label "Selesai Mengajar", disabled = `slideLoading !== null || !geofence?.inside` (geofence pulang ✓ — `handleSlideGroup('keluar')` sudah lengkap: optimis/rollback/success "Presensi pulang tercatat." :383/:426/:442/:449).
  - terlalu awal → teks "Presensi mengajar tercatat · tombol pulang HH.MM".
- Flag OFF → tampilan sekarang (grup sudah `closed` via pre-fill → branch `done && closed`).
- Multi-grup: tombol per grup (1 ketuk = 1 sesi, konsisten `presensiKeluarTarget` yang walk per sesi).
- Note: `semuaBeres` (:142) tidak memandang `closed` → FOTO_SORE tetap muncul per waktu; telat ketuk = row terbuka → koreksi (by design).

**Payload**: `jadwal.unitSekolah` dikirim full (bukan select sempit) → field baru otomatis sampai ke `g.unit_sekolah`.

**Test**: extend `SlideKeluarTest` + `BackflowCoverTest`: (a) flag ON pre-fill hilang (anchor & cover rows `jam_keluar` null), (b) keluar sebelum `sesiEnd-N` → 422, sesudahnya → sukses & hanya row terakhir terisi, (c) flag OFF → paritas lama (existing assertions tidak boleh patah).

---

## 6. Pengajuan Koreksi Presensi (fitur baru)

**Keputusan user**: scope = ubah `jam_keluar` saja; submit dari mobile + desktop; kuota hitung **disetujui** 3/bulan; lewat kuota → **tetap boleh** + wajib `penjelasan_khusus` + badge "⚠ Lewat kuota"; formulir cetak **per pengajuan** (1 koreksi = 1 lembar A4) dengan tombol Cetak di desktop + mobile; alur via aplikasi, audit trail.

### Skema — migrasi `pengajuan_koreksi`
| kolom | tipe |
|---|---|
| id | bigint PK |
| pegawai_id, presensi_id | FK |
| tanggal | date (snapshot dari presensi) |
| nilai_baru | time (jam keluar usulan) |
| alasan | enum(sakit→"…", pakai: `lupa_presensi`,`hp_rusak`,`dinas_luar`,`rapat`,`lainnya`) |
| alasan_detail | text nullable |
| penjelasan_khusus | text nullable (wajib saat lewat kuota) |
| status | enum(pending,disetujui,ditolak) default pending |
| approver_id, approved_at, rejected_by, rejected_at | nullable |
| alasan_penolakan, catatan_approval | text nullable |
| nomor | string unique — `KOR/{Y}/{m}/{urut:4}` |

Index: `(pegawai_id, status, created_at)` (kuota), `presensi_id` (duplikat check), `status` (daftar approver). Unique `nomor` → race dijaga retry kecil dalam transaksi.

### Backend
- **Model** `PengajuanKoreksi` (+ relasi presensi/pegawai; cast tanggal/time; scope bulanan).
- **Mobile** `routes/mobile.php` (pola izin, `create` sebelum wildcard):
  - `GET /koreksi` → `presensi.koreksi.index`
  - `GET /koreksi/create?presensi_id=` → `…create`
  - `POST /koreksi` throttle `10,1` → `…store`
  - `GET /koreksi/{koreksi}/cetak` → `…cetak` (owner/approver)
- **Desktop** `routes/admin.php` (pola `pengajuan-izin`, otorisasi di controller/UI): `GET koreksi-presensi`, `POST …/{id}/approve`, `POST …/{id}/reject`, `GET …/{id}/cetak`.
- **`MobileKoreksiController`**: 
  - `index` — daftar milik sendiri (+ `koreksi_count` bulan ini).
  - `create` — guard: presensi milik pegawai, punya `jam_masuk`, belum ada pending untuk presensi sama, `!PayrollLockHelper::isPeriodLocked(pegawai, tanggal)`; hitung kuota disetujui bulan berjalan.
  - `store` — `PengajuanKoreksiStoreRequest`: `nilai_baru required date_format:H:i`, `alasan required in:…`, `alasan_detail nullable max:500`, `penjelasan_khusus required_if kuota_penuh` (kuota dicek ulang di request — server-side, tidak percaya FE); `nilai_baru > jam_masuk` (cross-check di FormRequest via presensi); tolak payroll-locked & duplikat pending (transaction). Notifikasi approver → `NotificationHelper::sendSafely(target, new KoreksiBaru($pengajuan))` (pola `MobileIzinController::notifyPengajuanBaru:180` — L1 → fallback Kepsek → broadcast admin_unit+superadmin via `has_l1`).
- **`PengajuanKoreksiController` (desktop)**:
  - `index` — pola `PengajuanIzinController::index` (scoping approver: `determineApprovers`/`headUnitIds`/`view_all_units`).
  - `approve` — `DB::transaction` + `lockForUpdate` presensi → `presensi.jam_keluar = nilai_baru` → set status/approver/catatan → `AuditPresensi::log` (aksi `koreksi_jam_keluar`, data lama/baru) → `NotificationHelper::sendSafely(pegawai.user, new StatusKoreksi($pengajuan,'disetujui'))`.
  - `reject` — status + alasan_penolakan + notif.
  - `print` — `Pdf::loadView('exports.pdf-koreksi-presensi', …)->stream()`; authorization: owner | `ApprovalHelper::isUnitHead` | `can('view_presensi')`. Method ini DIPAKAI bersama route mobile (guard-agnostic, cek user di dalam).
- **Notifikasi kelas baru**: `KoreksiBaru` (clone `IzinBaru`), `StatusKoreksi` (clone `StatusIzin`).
- **Entry FE mobile**: chip/link **"Pengajuan Koreksi"** di header halaman Riwayat (tanpa nav baru — bottom nav sudah 5 item); `Notifikasi.jsx:17` tambah mapping tipe koreksi → `presensi.koreksi.index`.

### FE
- **Mobile** `Pages/Mobile/Koreksi/Index.jsx` (pola `Izin/Index`: list + expand detail + status + tombol `window.open(route('presensi.koreksi.cetak'))`) dan `Create.jsx` (pola `Izin/Create`: back-link, info presensi asal, input jam, select alasan, indikator "Kuota bulan ini: n/3", field penjelasan khusus conditional).
- **Entry di `Riwayat.jsx`**:
  - Kartu single (kantor/lembur) `:215` — tombol "Ajukan Koreksi" saat `jam_masuk && !jam_keluar` (badge "Belum pulang" sudah ada :210).
  - Item expand mengajar `:259` — tombol **hanya pada row sesi-terakhir**: util FE baru `isSesiTerakhir(item, siblings)` = tidak ada saudara mapel+kelas sama (sudah di-group) berikutnya dengan `jam_mulai <= jam_selesai + 60 menit` (sort dulu by jam_mulai; kriteria = `sesiMengajar`/`groupConsecutiveJadwals`, GAP 60 menit). Row tengah (kosong by design saat wajib ON) TIDAK menawarkan koreksi.
- **Desktop** `Pages/Koreksi/Index.jsx` (clone `PengajuanIzin/Index.jsx`: tabel, filter status, modal approve/reject + catatan, badge lewat kuota, tombol Cetak). Nav `AuthenticatedLayout.jsx:56` tambah item "Koreksi Presensi" dengan gating sama (`view_izin || is_approver`).

### Cetak — `resources/views/exports/pdf-koreksi-presensi.blade.php`
Clone kop + `.twd/.ttd .box` dari `pdf-belum-presensi.blade.php`. Isi (sesuai teks yang disetujui user): judul + `No. KOR/…` → kalimat pembuka "Dengan ini saya mengajukan koreksi…" → tabel identitas → tabel koreksi (tanggal, jenis, jam sebelumnya→diusulkan, alasan) → **prosa alasan per enum** (5 varian) → conditional "melebihi kuota…" → status approval + catatan → blok TTD 2 kolom (Pengaju / Mengetahui Atasan-Kepsek; kosong bila masih PENDING) → footer "Dicetak pada: … · No. KOR/…".

### Query & quality
- Kuota: 1 query `count` per request (indexed `(pegawai_id,status,created_at)`).
- Approve: 1 transaksi — `lockForUpdate` presensi (idempotency: pending→disetujui sekali; reject setelah approve → status check di bawah lock).
- Duplikat pending: cek dalam transaksi (portable, tanpa partial unique).
- Audit: `AuditPresensi` + notifikasi `sendSafely` (error tidak membatalkan).

### Test (baru)
`PengajuanKoreksiTest`: store sukses / kuota ke-4 tanpa penjelasan → 422 / dengan penjelasan → sukses+flag / payroll locked → 422 / duplikat pending → 422 / bukan presensi sendiri → 403 / `nilai_baru <= jam_masuk` → 422 / approve → `presensi.jam_keluar` terubah + audit ada + status `disetujui` / approve ganda → ditolak / reject + alasan / cetak → 200 & stream PDF (pending & approved) / route mobile auth.

---

## 1c. Fix payroll — alpa & telat PER HARI (bukan per baris)

**Model user**: kehadiran = per hari (1 hari 1 hitungan); honor mengajar & tunjangan kehadiran SUDAH benar → tidak disentuh. **`FinalizeAlpa` tidak diubah** (baris per jadwal dibiarkan; laporan sudah dedup `dedupStatusPerTanggal`).

**Perubahan — hanya 2 titik, di data yang SUDAH dimuat (0 query baru):**

1. **`PresensiAggregator::attendanceByPegawai` :78** — `total` dari `count()` baris → **`pluck('tanggal')->unique()->count()`**. Dampak: telat/alpa/sakit/izin/cuti (dan hadir) jadi jumlah **tanggal** unik per status. Menutup: (a) alpa baris-blok vs baris-Per-JP (3 baris/1 hari → 1), (b) dobel `telat` kantor+mengajar pada tanggal sama → 1. Dedup `dedupPassiveByActiveDate` tetap di depannya (parity `PayrollAttendanceDedupTest`). Satu konsumen saja (`computeAttendance`) — aman diubah.
2. **`PenggajianController::computeAttendance` :604-613`workingDays`** — kini menjumlah `countWeekdayInRange` **per jadwal** → 3 jadwal sama-sama "Senin" = Senin dihitung 3× (inflasi `auto-alpa` per-JP 3×). Fix: dedup per `(hari, unit_sekolah_id)` (set key), hitung sekali per kombinasi → union hari kerja efektif. `max(0, …)` + formula `alpa = manual + max(0, working − (present+manual))` struktur tetap (sudah bebas double-count karena manual ikut `presentOrLeave`).

`counts['telat']` otomatis per-hari lewat titik (1) → komponen `kehadiran_telat` benar tanpa cabang baru. `tunjangan_kehadiran` tetap `presentDays` (tidak disentuh).

**Test**:
- Extend `PayrollMathTest` (atau `PayrollAttendanceDedupTest`): fixture paritas — pegawai A blok 1 jadwal Senin vs pegawai B 3 jadwal Senin, sama-sama absen sebulan → counts identik & = jumlah Senin; telat kantor+mengajar tanggal sama → `telat = 1`; auto-alpa: workingDays tanpa pengalian.
- Audit dulu assertion `PayrollAttendanceDedupTest` / `PayrollIzinHadirDoubleCountTest` / `PayrollMergeQueryTest` — skenario tetap harus lama (per tanggal sudah dominan); hanya skenario multi-baris-per-tanggal yang angka turun → pastikan itu memang bug fix, bukan regresi.
- Note: draft di `finalized` tidak dihitung ulang (read-only, by design); regenerasi `draft` periode baru otomatis memakai logika baru.

---

## Cross-cutting (kualitas kode & query)
- Semua eager-load dipastikan `with()` (riwayat sudah `jadwal.pegawaiMapel.mataPelajaran` ✓; unit ditambah selektif `:id,durasi_jp`).
- Tidak ada query baru dalam loop; 1c & 1b murni PHP/FE di data ter-muat.
- Raw SQL `calendarData` dihapus (portabilitas driver).
- Semua endpoint mutating: throttle (10/menit) + FormRequest + transaction+lock bila ada race.
- Index migrasi koreksi: kuota & duplikat check.
- Setelah semua fase: `pint` (diff files) → `phpunit` penuh → `rtk npm run build` → review diff → user review sebelum push (push `main` = deploy prod).
