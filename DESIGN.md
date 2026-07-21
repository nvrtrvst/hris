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
- Success, warning, danger selalu disertai teks atau ikon.

# Typography

- Font family: Figtree dengan system sans fallback.
- Page title: `1.5rem`, 700.
- Section label: `0.75rem`, 700, uppercase dengan tracking terbatas.
- Body: `0.875rem`, minimum line-height 1.4.
- Waktu dan angka: monospace fallback, tabular figures.

# Layout

- Lebar aplikasi maksimum `28rem`.
- Margin layar `1rem`.
- Spacing utama mengikuti 4/8px grid.
- Tinggi layar memakai `100dvh`.
- Bottom navigation selalu menghormati safe-area.

# Elevation & Depth

- Kartu memakai border tipis dan shadow ringan.
- Blur hanya untuk navigasi fixed.
- Tidak ada orb, partikel, atau blur dekoratif pada konten scroll.

# Shapes

- Radius kartu `1rem`.
- Radius tombol `0.75rem`.
- Badge status berbentuk pill.
- Tap target minimum 44px, ideal 48px.

# Components

- Header: identitas singkat, sticky, tanpa dekorasi berat.
- Status card: status hari ini, jam masuk/keluar, satu CTA.
- Camera: aspect ratio tetap, overlay informasi, shutter familiar.
- Verification row: GPS dan bukti foto selalu terlihat sebelum submit.
- Bottom nav: lima tujuan, label satu kata, active indicator garis atas.

# Do's and Don'ts

- Gunakan status eksplisit dan copy singkat.
- Prioritaskan CTA presensi dan keterbacaan di bawah sinar matahari.
- Gunakan transform/opacity untuk feedback gerak.
- Jangan gunakan glass card pada konten scroll.
- Jangan memuat chart atau peta bila data dapat dijelaskan dengan teks.
- Jangan memakai warna sebagai satu-satunya penanda status.
