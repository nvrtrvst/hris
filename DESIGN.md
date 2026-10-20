---
name: Presensi Operasional
register: product
colors:
  primary: "#0F3D3E"
  background: "#F4F7F5"
  surface: "#FFFFFF"
  accent: "#C9A227"
---

# Overview

UI mobile HRIS untuk pegawai yayasan yang melakukan presensi dari ponsel, sering di luar ruangan dan memakai perangkat Android kelas menengah. Desain menggabungkan kejelasan terminal operasional dengan ritme kartu administrasi modern: status harus terbaca sebelum dekorasi. Aturan utama: satu layar, satu tindakan utama, status GPS dan bukti selalu terlihat.

# Colors

- Primary: `#0F3D3E`, identitas yayasan dan tindakan utama.
- Background: `#F4F7F5`, netral hangat untuk mengurangi silau.
- Surface: `#FFFFFF`, konten dan formulir.
- Accent: `#C9A227`, hanya lembur atau perhatian khusus.
- Emerald: sukses, tombol konfirmasi geser, indikator progres.
- Sky: informasi netral dan alur dinas luar.
- Rose: bahaya, penolakan, dan kesalahan; menggantikan `red` bawaan.
- Slate: seluruh teks dan permukaan netral. Tidak ada violet, lime, atau indigo.
- Teks kecil minimum `slate-600` (kontras 6.9:1 di permukaan netral, 7.6:1 di putih).
- Teks semantik memakai tingkat `-700`; tingkat `-600` hanya untuk ikon atau teks besar.
- Success, warning, danger selalu disertai teks atau ikon.

# Typography

- Font family: Figtree dengan system sans fallback.
- Page title: `1.5rem`, 700.
- Section label: `0.75rem`, 700, uppercase, tracking `0.14em` (jangan lebih lebar).
- Body: `0.875rem`, minimum line-height 1.4.
- Waktu dan angka: monospace fallback, tabular figures.
- Tanda pisah dalam kalimat memakai koma atau titik, bukan em dash.

# Layout

- Lebar aplikasi maksimum `28rem`.
- Margin layar `1rem`.
- Spacing utama mengikuti 4/8px grid.
- Tinggi layar memakai `100dvh`.
- Bottom navigation selalu menghormati safe-area.

# Elevation & Depth

- Kartu memakai border tipis dan shadow ringan `0_1px_2px_rgba(15,23,42,0.04)`.
- Hero status boleh memakai shadow dalam (`shadow-inner`) karena kontennya berada di atas primary.
- FAB memakai shadow berwarna primary `0_8px_18px_-8px_rgba(15,61,62,0.65)`; hanya satu FAB per layar.
- Blur hanya untuk navigasi fixed.
- Tidak ada orb, partikel, atau blur dekoratif pada konten scroll.

# Motion

- Gerak melalui transform atau opacity saja, tanpa animasi berulang.
- Animasi `pulse`/loop dilarang; status cukup dari warna, teks, dan ikon.
- Dial: ENERGY 1, RHYTHM 2, MOTION 1.

# Theme

- Terkunci light-only. Alasan: presensi dilakukan di luar ruangan dan keterbacaan di bawah sinar matahari lebih penting daripada preferensi sistem.

# Shapes

- Radius kartu `1rem`.
- Radius tombol `0.75rem`.
- Radius overlay di dalam frame kamera atau peta `0.5rem`.
- Badge status berbentuk pill; chip konten (kelas, jumlah JP) memakai radius `0.375rem`.
- Satu treatment badge saja: tint + teks. Jangan menambah border dan uppercase bersamaan.
- Tap target minimum 44px, ideal 48px.

# Components

- Header: identitas singkat, sticky, tanpa dekorasi berat.
- Status card: status hari ini, jam masuk/keluar, satu CTA.
- Camera: aspect ratio tetap, overlay informasi, shutter familiar.
- Verification row: GPS dan bukti foto selalu terlihat sebelum submit.
- Bottom nav: lima tujuan, label satu kata, active indicator garis atas.

# Admin Dashboard (desktop)

- Kontrak mobile di atas tidak berlaku di sini: tabel, grid dua kolom, dan sidebar boleh melebar.
- Daftar yang butuh tindakan memakai kartu putih dengan garis aksen kiri `4px`: `warning` untuk JP mengajar, `info` untuk piket.
- Angka JP memakai bobot `JpCalculator` (menit ÷ `durasi_jp` unit), bukan jumlah baris jadwal. Jangan tampilkan label "JP" dari `count()`.
- Urgensi dibagi tiga label teks: `Terlewat`, `Berjalan`, `Belum mulai`. Badge selalu berisi kata, warna tidak berdiri sendiri.
- "Guru sudah di unit" (ada presensi harian) dan "JP tercatat" (presensi per jadwal) adalah dua sumbu berbeda; jangan digabung jadi satu status.
- Daftar rinci dibuka di tempat (`aria-expanded` + `aria-controls`), bukan modal. Filter unit berupa chip `aria-pressed`; menutup daftar mengosongkan filter unit.
- Bar progres wajib `role="progressbar"` dengan `aria-valuenow/min/max`; gerak hanya pada `motion-safe`.
- Teks kecil memakai `text-text-secondary`, bukan `text-text-muted`.
- Rentang jam memakai tanda hubung `08:00-08:40`.
- Pencarian tabel pakai pola Enter-only: input bebas di state lokal, filter baru diterapkan saat Enter (tanpa debounce/`onChange` filtering), Escape mereset, tombol clear berikon `X` tanpa label visual.
- Pencarian jadwal menyaring di level grup (guru cocok melihat semua JP-nya; selain itu per baris) agar kolom `rowSpan` tetap utuh.

# Decisions

- 2026-10-20 — Cari jadwal (`resources/js/Pages/Dashboard.jsx`): input search di header kartu "Jadwal Hari Ini", Enter-only, tanpa tombol filled primary (accent dijaga satu tempat), token `border-border`/`rounded-button`/`primary`. Empty state membedakan "belum ada jadwal" vs "tidak cocok dengan kata kunci" + aksi Reset.
- 2026-10-20 — Layout PWA PR #28: fixed nav diberi inner `mx-auto max-w-md` (bukan nav-nya yang dibatasi) agar tetap full-viewport; form control mobile dipaksa 16px via rule CSS `.mobile-root :is(input, select, textarea)` (iOS auto-zoom), jadi JS/JSX input baru tak perlu ingat ini lagi; carousel pakai snap CSS dengan degrade aman; mask scroll wajib sertakan `-webkit-mask-image` + `mask-image` (pola `app.css` `.overflow-x-auto`). Audit item yang ternyata sudah benar: ikon header 44px, tab hari Jadwal 2-baris ≥ 44px, `min-[420px]` Piket memang tepat untuk kolom `max-w-md` (breakpoint `sm:` 640px tak akan aktif di HP).

# Do's and Don'ts

- Gunakan status eksplisit dan copy singkat.
- Prioritaskan CTA presensi dan keterbacaan di bawah sinar matahari.
- Gunakan transform/opacity untuk feedback gerak.
- Panah hanya untuk baris yang benar-benar membuka detail; rentang waktu memakai tanda hubung.
- Jangan gunakan glass card pada konten scroll.
- Jangan memuat chart atau peta bila data dapat dijelaskan dengan teks.
- Jangan memakai warna sebagai satu-satunya penanda status.
