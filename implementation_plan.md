# Implementasi PRD Addendum #2: Isolasi Akses Portal & Perbaikan Login

Dokumen ini memuat rencana implementasi untuk memisahkan sesi akses antara Portal Manajemen (`/`) dan Portal Presensi Mobile (`/mobile`), merombak tampilan Login Desktop, serta mengaktifkan akses _read-only_ (Self-Service) bagi Pegawai di Portal Manajemen.

## User Review Required

> [!WARNING]
> **Perubahan Arsitektur Sesi (Session Architecture)**
> Kita akan menggunakan middleware custom untuk memisahkan *Session Cookie* secara dinamis berdasarkan URL yang diakses (`/mobile` vs `/`). Ini memungkinkan login independen untuk satu pengguna yang sama di kedua portal. Pendekatan ini merupakan alternatif dari pembuatan *subdomain* (yang membutuhkan modifikasi DNS/Server tambahan). Apakah Anda menyetujui pendekatan middleware dinamis ini?

> [!IMPORTANT]
> **Penghapusan Guard Bawaan Laravel (`web`)**
> Kita akan mendaftarkan dua buah guard baru: `web_admin` dan `web_mobile`, dan keduanya akan merujuk ke tabel `users`. Default guard di `config/auth.php` akan diubah, sehingga akan ada perubahan di berbagai file controller yang menggunakan `Auth::guard()`.

## Open Questions

> [!NOTE]
> 1. Terkait **Role/Permission** untuk Staff: Apakah saat ini kita menggunakan package khusus seperti `spatie/laravel-permission`, atau kita cukup menggunakan validasi manual berupa pengecekan nilai `email` (seperti `email !== 'admin@yayasan.com'`) untuk membedakan Admin dan Staff?
> 2. Untuk **Desain Login Desktop**, apakah ada referensi warna sekunder atau logo Yayasan spesifik yang wajib disertakan, atau saya boleh mendesain dengan tema warna utama (Indigo/Biru) yang sudah ada?

## Proposed Changes

---

### Konfigurasi Autentikasi & Session

Kita akan memisahkan guard di Laravel agar sesi tidak bocor antar portal.

#### [MODIFY] `config/auth.php`
- Menambahkan `web_admin` dan `web_mobile` di bagian `guards`.
- Keduanya akan menggunakan provider `users`.

#### [NEW] `app/Http/Middleware/IsolatePortalSession.php`
- Middleware baru yang akan berjalan di tingkat teratas.
- Middleware ini mendeteksi URL request: jika berawalan `/mobile`, maka konfigurasi `session.cookie` diubah menjadi `hris_mobile_session` dan session lifetime lebih lama (misal 24 jam). Jika tidak, menjadi `hris_mgmt_session` dengan lifetime lebih pendek (misal 2 jam).

#### [MODIFY] `bootstrap/app.php`
- Mendaftarkan middleware `IsolatePortalSession` agar berjalan sebelum sesi dimulai.
- Memisahkan penanganan *guest redirect* (`redirectGuestsTo`) agar menggunakan guard yang sesuai dengan prefix URL.

---

### Refactor Middleware & Route

Route akan dikelompokkan ulang dengan guard yang eksplisit.

#### [MODIFY] `routes/web.php`
- Mengganti middleware `'auth'` bawaan menjadi `'auth:web_admin'` untuk rute manajemen.
- Mengganti rute di bawah `prefix('mobile')` agar menggunakan middleware `'auth:web_mobile'`.

#### [MODIFY] Controller Authentication (Breeze)
- `AuthenticatedSessionController` (Desktop): Disetel untuk menggunakan guard `web_admin`.
- `MobileAuthController` (Baru): Menangani login/logout murni untuk guard `web_mobile`. Menggantikan *redirect logic* gabungan sebelumnya.

---

### Redesain Login Desktop

Mengubah UI scaffolding bawaan Laravel Breeze menjadi lebih profesional untuk Portal Manajemen.

#### [MODIFY] `resources/js/Pages/Auth/Login.jsx`
- Merombak tampilan menjadi *Split-Screen 50:50* (atau 60:40).
- Panel kiri berisi *branding visual* Yayasan (Logo, tagline, *pattern* pendidikan, dengan background kustom).
- Panel kanan berisi form dengan UX yang dioptimalkan: label di luar input, ikon indikator, status loading pada tombol submit, dan pesan *error* dinamis (Kredensial salah vs Akun tidak aktif vs Rate limit).

---

### Akses Pegawai (Staff) di Portal Desktop

Pegawai akan diizinkan masuk ke `/` untuk melihat data mandiri secara *read-only*.

#### [MODIFY] `resources/js/Layouts/AuthenticatedLayout.jsx`
- Menu di-*render* secara bersyarat. Jika yang login adalah *Superadmin*, semua menu (Pegawai, Unit Sekolah, Payroll, dll) muncul. Jika yang login adalah *Staff biasa*, menu yang muncul hanya **Dashboard Pribadi, Riwayat Presensi, Slip Gaji, Jadwal, Profil**.
- Fitur Clock-In/Clock-Out dihilangkan dari Layout Desktop.

#### [MODIFY] Controller Manajemen Data (Misal: `PresensiController.php`, `PegawaiController.php`)
- **Policy Check**: Menambahkan validasi `if (!$isAdmin && $targetUserId !== Auth::id()) { abort(403); }` untuk mencegah staf mengintip data milik staf lain dengan sekadar merubah ID di URL.

## Verification Plan

### Manual Verification
1. Menggunakan 2 browser / tab rahasia (*Incognito*).
2. Login sebagai *Staff* di portal `/mobile`.
3. Buka tab baru dan akses portal `/` (Desktop) -> Sistem harus *menolak* akses dan meminta login terpisah.
4. Login kembali sebagai *Staff* di portal `/` -> Tampilan *sidebar* tidak boleh memiliki menu administratif seperti Payroll atau Edit Pegawai.
5. Coba ubah URL ID secara manual saat mengakses halaman Slip Gaji (misal `/penggajian/99`) -> Harus muncul halaman *403 Forbidden*.
6. Coba salahkan password 5 kali berturut-turut di portal `/login` -> Akan muncul batas Rate Limit. Coba login di `/mobile/login` menggunakan akun yang sama -> Harus tetap bisa diakses secara terpisah.
