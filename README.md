# HRIS Yayasan (Multi-Unit Sekolah)

Sistem Informasi Sumber Daya Manusia (HRIS) terpusat yang dirancang khusus untuk Yayasan yang menaungi berbagai unit sekolah (LPQ, TK, SD, SMP, SMK). Sistem ini dibangun untuk mengatasi masalah data pegawai yang tersebar, memberikan solusi absensi pintar berbasis lokasi (Geofencing), mencegah bentrok jadwal lintas unit, serta memproses penggajian (Payroll) secara otomatis dan dinamis.

## 🌟 Fitur Utama

1. **Manajemen Master Data Terpusat (Single Source of Truth)**
   - Mengelola entitas Yayasan: Unit Sekolah, Jabatan, Mata Pelajaran, Jurusan, hingga Kelas.
   - Manajemen profil pegawai terpadu yang dilengkapi fitur enkripsi data sensitif (NIK, Rekening, Kontak).

2. **Sistem Penjadwalan Anti-Bentrok**
   - Validasi otomatis mencegah seorang pegawai/guru dialokasikan pada dua unit sekolah berbeda di jam yang sama.
   - Tampilan visual *Weekly Calendar Board*.

3. **Presensi Pintar (Live Geofencing & Face Capture)**
   - Pengecekan lokasi berbasis radius (*Haversine Formula*). Pegawai hanya dapat absen jika berada di dalam radius koordinat unit sekolah yang ditugaskan.
   - Wajib menyertakan bukti swafoto (*Selfie*) secara *real-time* via kamera web/HP (*WebRTC*).

4. **Penggajian (Payroll) Dinamis**
   - Fleksibilitas dalam menentukan komponen gaji (*Pendapatan & Potongan*).
   - Mendukung perhitungan *Fixed* (Gaji Pokok), *Persentase* (PPh21, BPJS), dan *Dinamis Kehadiran* (Potongan Telat/Alpa otomatis dari log Presensi).
   - Cetak Slip Gaji digital responsif.

5. **Dashboard Eksekutif & Self-Service Portal**
   - **Dashboard Pimpinan:** Analitik lengkap kehadiran, komposisi pegawai, dan tren *payroll*.
   - **Self-Service:** Portal bagi pegawai untuk mengecek jadwal harian, meninjau riwayat presensi, dan mengunduh slip gaji secara mandiri.

## 🛠️ Teknologi yang Digunakan

- **Backend:** Laravel 12.x
- **Frontend:** React 18 + Inertia.js
- **Styling:** Tailwind CSS
- **Database:** MySQL / PostgreSQL
- **Otentikasi:** Laravel Breeze
- **Role & Permission:** Spatie Laravel Permission

## 🚀 Panduan Instalasi (Development)

Ikuti langkah-langkah di bawah ini untuk menjalankan proyek secara lokal:

### 1. Kloning Repositori
```bash
git clone <url-repo-anda>
cd hris
```

### 2. Instalasi Dependensi PHP (Composer)
```bash
composer install
```

### 3. Instalasi Dependensi Node.js (NPM)
*Catatan: Pastikan menggunakan `--legacy-peer-deps` karena ada beberapa spesifikasi dependensi.*
```bash
npm install --legacy-peer-deps
```

### 4. Konfigurasi Environment
Buat file `.env` dari *example* dan atur kredensial database Anda.
```bash
cp .env.example .env
php artisan key:generate
```
Pastikan pengaturan DB di `.env` sudah sesuai:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_hris
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Migrasi Database dan Seeder
Jalankan migrasi untuk membangun skema beserta data awal (*Master Data*).
```bash
php artisan migrate --seed
```

### 6. Jalankan Server
Kompilasi aset frontend menggunakan Vite:
```bash
npm run build
# Atau untuk mode development: npm run dev
```

Jalankan server PHP lokal:
```bash
php artisan serve
```

Akses aplikasi melalui browser di: `http://localhost:8000`

## 🔒 Keamanan
Data NIK dan nomor rekening dienkripsi di level *database* menggunakan fitur *Cast* `encrypted` bawaan Laravel untuk mematuhi standar privasi data.

---
**Versi MVP 1.0** - Dikembangkan khusus untuk pengelolaan operasional Yayasan Pendidikan.
