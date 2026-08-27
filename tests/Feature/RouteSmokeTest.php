<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\KomponenGaji;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiDokumen;
use App\Models\PengajuanIzin;
use App\Models\Penggajian;
use App\Models\PenggajianDetail;
use App\Models\Presensi;
use App\Models\Reminder;
use App\Models\SkalaMasaBakti;
use App\Models\TugasLuar;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke test seluruh portal: GET route harus 200, route mutasi (POST/PUT/PATCH/DELETE)
 * tidak boleh 5xx/exception dengan payload valid minimal.
 *
 * Tujuan: mencegah error "Terjadi Kesalahan" (500/exception) terulang
 * setelah refactor/rework UI. Route didaftarkan ke controller yang tidak
 * punya method-nya, eager-load yang crash, dll. — semua akan gagal di sini.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Route auth Breeze — diuji terpisah (guest/signed/redirect flow). */
    private const ADMIN_EXCLUDE = [
        'verification.notice', // redirect (302) jika email sudah verified
        'verification.verify', // butuh signed URL
        'password.confirm',    // Breeze boilerplate (session confirm)
        'backup.download',     // side-effect: dump DB sungguhan
    ];

    /** Route yang bergantung pada API eksternal (keuangan) — bukan bug 500. */
    private const MOBILE_EXCLUDE = [
        'presensi.jadwal.siswa', // butuh integrasi keuangan; tanpa itu 502 (by design)
    ];

    /**
     * Route mutasi admin yang dikecualikan.
     *
     * - logout / verification.send: Breeze boilerplate (sesi/email).
     * - profile.destroy: butuh password asli + menghapus user aktor (mengganggu run).
     * - pegawai.import & komponen-gaji.pegawai.import: butuh file Excel dengan
     *   format row yang benar; kegagalan parse row bukan bug 500.
     */
    private const ADMIN_MUTATION_EXCLUDE = [
        'logout',
        'verification.send',
        'profile.destroy',
        'pegawai.import',
        'komponen-gaji.pegawai.import',
    ];

    /**
     * Route mutasi mobile yang dikecualikan.
     *
     * - presensi.logout: invalidasi sesi — akan merusak request berikutnya
     *   dalam run yang sama (bukan bug 500).
     */
    private const MOBILE_MUTATION_EXCLUDE = [
        'presensi.logout',
    ];

    /**
     * Map route name → komponen Inertia yang HARUS dirender.
     *
     * Sumber kebenaran: method controller (inertia()/Inertia::render). Jika
     * komponen berubah di controller tapi map ini tidak diupdate, test gagal.
     * Route yang return JSON/download (nik-asli, template, export, preview,
     * audit, review, worksheet_data, kelas-by-unit, photo, push.subscriptions)
     * TIDAK masuk map — komponennya dicek via respon 200 saja.
     */
    private const INERTIA_COMPONENTS = [
        // ── Admin (web_admin) ──
        // Catatan: dashboard → Dashboard hanya untuk user dengan view_dashboard
        // (superadmin). Staff tanpa permission akan dapat DashboardSelfService.
        'dashboard' => 'Dashboard',
        'dashboard.perbandingan-unit' => 'PerbandinganUnit',
        'profile.edit' => 'Profile/Edit',
        // lengkapi-data → Profile/LengkapiData hanya karena superadmin (user
        // admin portal) TIDAK punya record pegawai; jika fixture berubah,
        // controller bisa mengarahkan ke halaman lain.
        'lengkapi-data' => 'Profile/LengkapiData',
        'pegawai.index' => 'Pegawai/Index',
        'pegawai.create' => 'Pegawai/Create',
        'pegawai.show' => 'Pegawai/Show',
        'pegawai.edit' => 'Pegawai/Edit',
        'pegawai.keuangan' => 'Pegawai/Keuangan',
        'jadwal.index' => 'Jadwal/Index',
        'jadwal.create' => 'Jadwal/Create',
        'jadwal.edit' => 'Jadwal/Edit',
        'presensi.index' => 'Presensi/Index',
        'presensi.create' => 'Presensi/Create',
        'penggajian.index' => 'Payroll/Index',
        'penggajian.run' => 'Payroll/Run/Index',
        'penggajian.run.worksheet' => 'Payroll/Run/Worksheet',
        'penggajian.show' => 'Payroll/Show',
        'notifications.index' => 'Notifications/Index',
        'komponen-gaji.index' => 'Payroll/Komponen',
        'komponen-gaji.matrix' => 'Payroll/Matrix',
        'komponen-gaji.pegawai.index' => 'Payroll/PegawaiKomponen',
        'skala-masa-bakti.index' => 'Payroll/SkalaMasaBakti',
        'unit-sekolah.index' => 'UnitSekolah/Index',
        'unit-sekolah.create' => 'UnitSekolah/Create',
        'unit-sekolah.edit' => 'UnitSekolah/Edit',
        'pengajuan-izin.index' => 'PengajuanIzin/Index',
        'laporan.index' => 'Laporan/Index',
        'users.index' => 'Users/Index',
        'users.create' => 'Users/Create',
        'users.edit' => 'Users/Edit',
        'pengumuman.index' => 'Pengumuman/Index',
        'roles.index' => 'Roles/Index',
        'roles.create' => 'Roles/Form',
        'roles.edit' => 'Roles/Form',
        'backup.index' => 'Backup/Index',
        'mata-pelajaran.index' => 'MataPelajaran/Index',
        'jabatan.index' => 'Jabatan/Index',
        'hari-libur.index' => 'HariLibur/Index',
        'tugas-luar.index' => 'TugasLuar/Index',
        'reminders.index' => 'Reminder/Index',
        'laporan.kcd' => 'Laporan/Kcd',

        // ── Mobile (web_mobile) ──
        'presensi.dashboard' => 'Mobile/Dashboard',
        'presensi.jadwal' => 'Mobile/Jadwal',
        'presensi.riwayat' => 'Mobile/Riwayat',
        'presensi.pengumuman' => 'Mobile/Pengumuman',
        'presensi.gaji.index' => 'Mobile/Gaji/Index',
        'presensi.gaji.show' => 'Mobile/Gaji/Show',
        'presensi.notifikasi.index' => 'Mobile/Notifikasi',
        'presensi.izin.index' => 'Mobile/Izin/Index',
        'presensi.izin.create' => 'Mobile/Izin/Create',
        'presensi.izin.show' => 'Mobile/Izin/Show',
        'presensi.absen' => 'Mobile/Absen',
        'presensi.profile.edit' => 'Mobile/Profile',
        // presensi.lengkapi-data → Mobile/LengkapiData karena test berjalan di
        // prefix /mobile (is('mobile*') true); di produksi (domain mobile tanpa
        // prefix) path-nya /lengkapi-data → Profile/LengkapiData.
        'presensi.lengkapi-data' => 'Mobile/LengkapiData',
    ];

    /**
     * Map route name → prop wajib yang HARUS ada di halaman Inertia.
     *
     * Nilai:
     * - true  → prop harus ada DAN tidak kosong/null. Ini detektor query/eager-load
     *           yang hilang (mis. paginator tanpa data, relation null, model tak
     *           ditemukan).
     * - false → prop harus ada, tapi boleh kosong/null (boolean, session status,
     *           koleksi yang memang bisa kosong, atau pegawai null utk admin).
     *
     * Sumber kebenaran: render controller (inertia()/Inertia::render) ∩ konsumsi
     * komponen (destructuring props di resources/js/Pages/...). Jika prop yang
     * dikonsumsi komponen dihapus dari controller, test gagal.
     *
     * Catatan: entry dgn nilai true (harus terisi) bergantung pada fixture
     * setUp() yang memang membuat data (presensi hari ini, jadwal, komponen,
     * dll). Jika fixture diubah, update map ini agar tetap sinkron.
     */
    private const INERTIA_PROPS = [
        // ── Admin (web_admin) ──
        'dashboard' => [
            'roleType' => true,
            'stats' => true,
            'trends' => true,
            // Fixture tanpa pegawai kontrak → kontrakBerakhir = [] (wajar kosong).
            'kontrakBerakhir' => false,
            'jadwalHariIni' => true,
            'presensiHariIni' => true,
        ],
        'dashboard.perbandingan-unit' => ['units' => true, 'filter' => true],
        'profile.edit' => ['mustVerifyEmail' => false, 'status' => false],
        // Superadmin tidak punya record pegawai → pegawai null di fixture (wajar).
        'lengkapi-data' => ['pegawai' => false],
        'pegawai.index' => [
            'pegawais' => true,
            'stats' => true,
            'filters' => false,
            'unitSekolahs' => true,
            'mataPelajarans' => true,
            'jabatans' => true,
        ],
        'pegawai.create' => ['unitSekolahs' => true, 'jabatans' => true],
        'pegawai.show' => ['pegawai' => true],
        'pegawai.edit' => ['pegawai' => true, 'unitSekolahs' => true, 'jabatans' => true, 'mapels' => true],
        'pegawai.keuangan' => ['pegawai' => true, 'komponens' => true],
        'jadwal.index' => [
            'jadwals' => true,
            'pegawais' => true,
            'units' => true,
            'mapel' => true,
            'kelasLabels' => true,
            'stats' => true,
            'filters' => false,
        ],
        'jadwal.create' => ['pegawais' => true, 'units' => true, 'mapel' => true],
        'jadwal.edit' => ['jadwal' => true, 'pegawais' => true, 'units' => true, 'mapel' => true],
        'presensi.index' => [
            'presensis' => true,
            // Admin → pegawai null oleh desain (hanya untuk non-admin).
            'pegawai' => false,
            'filters' => false,
            'units' => true,
            'stats' => true,
        ],
        'presensi.create' => ['jadwals' => true, 'presensis' => true, 'pegawai' => true],
        'penggajian.index' => [
            'penggajians' => true,
            'stats' => true,
            'periodeOptions' => true,
            'filters' => false,
        ],
        'penggajian.run' => [],
        'penggajian.run.worksheet' => ['month' => true, 'year' => true, 'periode' => true],
        'penggajian.show' => ['penggajian' => true],
        'notifications.index' => ['notifications' => true, 'filters' => false],
        'komponen-gaji.index' => ['komponens' => true, 'units' => true],
        'komponen-gaji.matrix' => ['pegawais' => true, 'komponens' => true, 'unitSekolahs' => true],
        'komponen-gaji.pegawai.index' => ['komponen' => true, 'pegawais' => true],
        'skala-masa-bakti.index' => ['skalas' => true],
        'unit-sekolah.index' => ['units' => true, 'stats' => true],
        'unit-sekolah.create' => [],
        'unit-sekolah.edit' => ['unit' => true],
        'pengajuan-izin.index' => ['pengajuans' => true, 'filters' => false, 'stats' => true],
        'laporan.index' => ['units' => true],
        'users.index' => ['users' => true, 'filters' => false, 'stats' => true],
        'users.create' => ['allRoles' => true, 'unitSekolah' => true],
        'users.edit' => ['userData' => true, 'allPermissions' => true, 'allRoles' => true],
        'pengumuman.index' => ['announcements' => true, 'units' => true, 'userUnitId' => false],
        'roles.index' => ['roles' => true, 'filters' => false, 'stats' => true],
        // role null & rolePermissions [] saat create — oleh desain.
        'roles.create' => ['role' => false, 'rolePermissions' => false, 'allPermissions' => true],
        // role = role pegawai (tanpa permission) → rolePermissions boleh kosong.
        'roles.edit' => ['role' => true, 'rolePermissions' => false, 'allPermissions' => true],
        'backup.index' => [],
        'mata-pelajaran.index' => ['mapels' => true, 'stats' => true],
        'jabatan.index' => ['jabatans' => true, 'stats' => true],

        // ── Mobile (web_mobile) ──
        'presensi.dashboard' => [
            'pegawai' => true,
            'presensi' => true,
            'presensiSeminggu' => true,
            'jadwalsHariIni' => true,
        ],
        'presensi.jadwal' => ['pegawai' => true, 'jadwalPerHari' => true],
        'presensi.riwayat' => ['presensi' => true, 'summary' => true, 'filters' => true],
        'presensi.pengumuman' => ['pengumuman' => true],
        'presensi.gaji.index' => ['pegawai' => true, 'penggajians' => true],
        'presensi.gaji.show' => ['penggajian' => true],
        'presensi.notifikasi.index' => ['notifications' => true, 'filters' => false],
        'presensi.izin.index' => ['pengajuan' => true],
        'presensi.izin.create' => ['pegawai' => true],
        'presensi.absen' => [
            'pegawai' => true,
            'jadwals' => true,
            'presensiHariIni' => true,
            'officeAttendance' => false, // boolean
            'attestation_token' => false, // string token
        ],
        'presensi.profile.edit' => ['mustVerifyEmail' => false, 'status' => false],
        'presensi.lengkapi-data' => ['pegawai' => true],
    ];

    /**
     * Map route name → struktur NESTED yang HARUS terpenuhi di props halaman.
     *
     * Ini lapisan verifikasi di atas INERTIA_PROPS: bukan cuma cek prop ada,
     * tapi juga cek relasi eager-load yang dikonsumsi komponen benar-benar
     * ada di dalam data (mis. presensis.data.*.jadwal.mata_pelajaran).
     *
     * Nilai per path:
     * - true  → path harus resolve DAN nilai akhir terisi (deteksi eager-load
     *           yang tidak mengembalikan baris / accessor yang hilang).
     * - false → path cukup resolve (key ada di tiap level); nilai boleh kosong/
     *           null — untuk relasi yang memang bisa legitimately kosong (mis.
     *           pegawai tanpa override komponen, role tanpa permission).
     *
     * Pola path: dotted path dengan '*' = elemen mana pun (list ataupun
     * associative array). Semantik '*': minimal SATU elemen harus memenuhi
     * sisa path. Jadi jika eager-load dihapus dari controller, SEMUA elemen
     * gagal → test gagal. Jika hanya sebagian data yang legitimately null
     * (mis. lembur tanpa jadwal), test tetap lulus.
     *
     * Sumber kebenaran: eager-load di controller ∩ konsumsi relasi di komponen
     * React (mis. jadwal.mata_pelajaran?.nama, pegawai.sisa_cuti). Jika relasi
     * yang dikonsumsi komponen dihapus dari with(), test gagal.
     *
     * Catatan: entry bernilai true (wajib terisi) bergantung pada fixture
     * setUp() — mis. penggajian.details butuh PenggajianDetail fixture, dan
     * pegawai.sisa_cuti butuh default jatah_cuti_tahunan. Jika fixture diubah,
     * update map ini agar tetap sinkron.
     */
    private const INERTIA_PROPS_NESTED = [
        // ── Admin (web_admin) ──
        'dashboard' => [
            'jadwalHariIni.*.mata_pelajaran' => false,
            'jadwalHariIni.*.unit_sekolah' => false,
            'presensiHariIni.*.pegawai' => false,
        ],
        'pegawai.index' => [
            'pegawais.data.*.units' => false,
            'pegawais.data.*.jabatans' => false,
        ],
        'pegawai.show' => [
            'pegawai.units' => false,
            'pegawai.jabatans' => false,
            'pegawai.user' => false,
            // Accessor dari loadCutiInfo() — deteksi kalau panggilannya dihapus.
            'pegawai.sisa_cuti' => true,
        ],
        'pegawai.edit' => [
            'pegawai.units' => false,
            'pegawai.mapels' => false,
        ],
        'pegawai.keuangan' => [
            'pegawai.komponen_gaji' => false,
        ],
        'jadwal.index' => [
            'jadwals.*.pegawai' => false,
            'jadwals.*.mata_pelajaran' => false,
            'jadwals.*.unit_sekolah' => false,
        ],
        'presensi.index' => [
            'presensis.data.*.pegawai' => false,
            'presensis.data.*.unit_sekolah' => false,
            'presensis.data.*.jadwal.mata_pelajaran' => false,
        ],
        'presensi.create' => [
            'jadwals.*.unit_sekolah' => false,
        ],
        'penggajian.index' => [
            'penggajians.data.*.pegawai' => false,
        ],
        'penggajian.show' => [
            // Detail selalu ada di setiap penggajian (createDraft selalu isi ≥1).
            'penggajian.details' => true,
            'penggajian.pegawai' => true,
            'penggajian.pegawai.units' => false,
        ],
        'komponen-gaji.matrix' => [
            'pegawais.*.komponen_gaji' => false,
            'pegawais.*.units' => false,
        ],
        // komponen-gaji.pegawai.index: pegawai di-flatten jadi {id, nik_masked,
        // nama_lengkap, unit, nominal} — relasi TIDAK diserialize → tidak bisa
        // (dan tidak perlu) diverifikasi via nested props.
        'users.index' => [
            'users.data.*.roles' => false,
            'users.data.*.unit_sekolah' => false,
        ],
        'users.edit' => [
            'userData.roles' => false,
        ],
        'pengumuman.index' => [
            'announcements.data.*.creator' => false,
            'announcements.data.*.unit_sekolah_count' => false,
        ],
        'roles.index' => [
            'roles.data.*.permissions' => false,
        ],
        'unit-sekolah.index' => [
            'units.*.pegawais_count' => false,
            'units.*.jadwals_count' => false,
        ],
        'pengajuan-izin.index' => [
            'pengajuans.data.*.pegawai' => false,
        ],

        // ── Mobile (web_mobile) ──
        'presensi.dashboard' => [
            'jadwalsHariIni.*.mata_pelajaran' => false,
            'jadwalsHariIni.*.unit_sekolah' => false,
        ],
        // jadwalPerHari = groupBy('hari') → assoc hari → list jadwal → relasi.
        'presensi.jadwal' => [
            'jadwalPerHari.*.*.mata_pelajaran' => false,
            'jadwalPerHari.*.*.unit_sekolah' => false,
        ],
        'presensi.riwayat' => [
            'presensi.*.unit_sekolah' => false,
            'presensi.*.jadwal.mata_pelajaran' => false,
        ],
        'presensi.absen' => [
            'jadwals.*.unit_sekolah' => false,
            'jadwals.*.mata_pelajaran' => false,
        ],
        'presensi.gaji.show' => [
            'penggajian.details' => true,
            'penggajian.pegawai' => true,
            'penggajian.pegawai.units' => false,
        ],
    ];

    private UnitSekolah $unit;

    private Pegawai $pegawai;

    private Jabatan $jabatan;

    private MataPelajaran $mapel;

    private Jadwal $jadwal;

    private Jadwal $jadwal2;

    private Presensi $presensi;

    private Presensi $presensiLembur;

    private Presensi $presensiLembur2;

    private KomponenGaji $komponen;

    private Penggajian $penggajianDraft;

    private Penggajian $penggajianPaid;

    private PegawaiDokumen $dokumen;

    private Announcement $announcement;

    private User $superadmin;

    private User $adminUnit;

    private User $mobileUser;

    private User $payrollOperator;

    private Pegawai $payrollOperatorPegawai;

    private Jabatan $payrollOperatorJabatan;

    private string $photoPath;

    private string $adminNotifId;

    private string $mobileNotifId;

    /** Record "sekali pakai" untuk route destruktif — jangan merusak fixture inti. */
    private Pegawai $disposablePegawai;

    private KomponenGaji $disposableKomponen;

    private SkalaMasaBakti $disposableSkala;

    private MataPelajaran $disposableMapel;

    private Jabatan $disposableJabatan;

    private Role $disposableRole;

    private PengajuanIzin $pengajuanApprove;

    private PengajuanIzin $pengajuanReject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = UnitSekolah::create([
            'nama' => 'SMP Smoke Test',
            'singkatan' => 'SMP',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
            'toleransi_menit' => 0,
            'jam_masuk_kantor' => '07:00',
            'jam_pulang_kantor' => '16:00',
            'max_jam_minggu' => 30,
            'toleransi_tap_menit' => 15,
        ]);

        $this->jabatan = Jabatan::create(['nama' => 'Guru', 'is_guru' => true]);
        $this->mapel = MataPelajaran::create(['nama' => 'Matematika']);

        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->superadmin->assignRole('superadmin');

        $this->adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'unit_sekolah_id' => $this->unit->id,
        ]);
        $this->adminUnit->assignRole('admin_unit');

        $this->mobileUser = User::factory()->create(['role' => 'pegawai']);
        $this->mobileUser->assignRole('pegawai');

        $this->pegawai = Pegawai::create([
            'user_id' => $this->mobileUser->id,
            'nik' => '8899001122334455',
            'nama_lengkap' => 'Guru Smoke Test',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 1,
            'alamat_ktp' => 'Jl. Smoke No. 1',
            'no_hp' => '081200000000',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2020-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $this->pegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->jabatan->id, 'is_primary' => true]);
        $this->pegawai->mapels()->attach($this->mapel->id, ['unit_sekolah_id' => $this->unit->id]);

        // Operator payroll = jabatan dgn flag is_payroll_operator (bendahara).
        // Admin unit TIDAK lagi pegang view_payroll/manage_payroll (seeder),
        // jadi route mutasi payroll diuji pakai operator ini.
        // Dibuat SETELAH $this->pegawai agar `Pegawai::first()` (Presensi/Create)
        // tetap mengembalikan pegawai utama yang punya jadwal + presensi.
        $this->payrollOperatorJabatan = Jabatan::create(['nama' => 'Bendahara', 'is_guru' => false, 'is_payroll_operator' => true]);

        $this->payrollOperator = User::factory()->create(['role' => 'pegawai']);
        $this->payrollOperator->assignRole('pegawai');

        $this->payrollOperatorPegawai = Pegawai::create([
            'user_id' => $this->payrollOperator->id,
            'nik' => '9988776655443322',
            'nama_lengkap' => 'Bendahara Smoke Test',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1988-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'kawin',
            'jumlah_tanggungan' => 2,
            'alamat_ktp' => 'Jl. Smoke No. 2',
            'no_hp' => '081200000002',
            'status_kepegawaian' => 'tetap',
            'tanggal_mulai_kerja' => '2015-01-01',
            'status_aktif' => 'aktif',
            'pendidikan_terakhir' => 'S1',
        ]);
        $this->payrollOperatorPegawai->units()->attach($this->unit->id, ['jabatan_id' => $this->payrollOperatorJabatan->id, 'is_primary' => true]);

        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[now()->format('l')];

        $this->jadwal = Jadwal::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'kelas_label' => '7 - A',
            'mata_pelajaran_id' => $this->mapel->id,
            'hari' => $hariIni,
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:30',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        // Jadwal kedua (hari beda) untuk route swap.
        $hariLain = $hariIni === 'Sabtu' ? 'Minggu' : 'Sabtu';
        $this->jadwal2 = Jadwal::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'kelas_label' => '8 - A',
            'mata_pelajaran_id' => $this->mapel->id,
            'hari' => $hariLain,
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:30',
            'jenis_jadwal' => 'mengajar',
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
        ]);

        $this->presensi = Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'jadwal_id' => $this->jadwal->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => now()->format('Y-m-d'),
            'jam_masuk' => now()->format('H:i:s'),
            'status' => 'hadir',
            'tipe_presensi' => 'mengajar',
        ]);

        // Dua record lembur pending untuk route approve/reject (masing-masing dipakai sekali).
        $this->presensiLembur = Presensi::create([
            'pegawai_id' => $this->pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => now()->format('Y-m-d'),
            'jam_masuk' => now()->format('H:i:s'),
            'status' => 'hadir',
            'tipe_presensi' => 'lembur',
            'is_lembur' => true,
            'lembur_status' => 'pending',
        ]);
        $this->presensiLembur2 = $this->presensiLembur->replicate()->fill(['id' => null]);
        $this->presensiLembur2->save();

        $this->komponen = KomponenGaji::create([
            'nama' => 'Gaji Pokok',
            'kode' => 'gaji_pokok',
            'tipe' => 'pendapatan',
            'jenis' => 'fixed',
            'nilai_default' => 2000000,
            'unit_sekolah_id' => $this->unit->id,
            'is_taxable' => true,
            'is_active' => true,
            'urutan' => 1,
            'tampil_di_matrix' => true,
        ]);

        $this->penggajianDraft = Penggajian::create([
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan' => now()->format('m-Y'),
            'tanggal_generate' => now()->format('Y-m-d'),
            'total_pendapatan' => 2000000,
            'total_potongan' => 0,
            'gaji_bersih' => 2000000,
            'total_taxable' => 2000000,
            'status' => 'draft',
        ]);

        $this->penggajianPaid = Penggajian::create([
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan' => now()->subMonth()->format('m-Y'),
            'tanggal_generate' => now()->subMonth()->format('Y-m-d'),
            'total_pendapatan' => 2000000,
            'total_potongan' => 0,
            'gaji_bersih' => 2000000,
            'total_taxable' => 2000000,
            'status' => 'paid',
        ]);

        // Detail komponen agar relasi `penggajian.details` (Payroll/Show &
        // Mobile/Gaji/Show) terverifikasi sebagai TERISI di INERTIA_PROPS_NESTED.
        PenggajianDetail::create([
            'penggajian_id' => $this->penggajianDraft->id,
            'komponen_gaji_id' => $this->komponen->id,
            'nama_komponen' => 'Gaji Pokok',
            'tipe' => 'pendapatan',
            'nominal' => 2000000,
            'is_taxable' => true,
        ]);
        PenggajianDetail::create([
            'penggajian_id' => $this->penggajianPaid->id,
            'komponen_gaji_id' => $this->komponen->id,
            'nama_komponen' => 'Gaji Pokok',
            'tipe' => 'pendapatan',
            'nominal' => 2000000,
            'is_taxable' => true,
        ]);

        $this->dokumen = PegawaiDokumen::create([
            'pegawai_id' => $this->pegawai->id,
            'nama_dokumen' => 'Ijazah',
            'jenis' => 'ijazah',
            'file_path' => 'smoke-dokumen.txt',
        ]);
        Storage::disk('local')->put('smoke-dokumen.txt', 'dummy');

        // File foto dummy untuk route presensi.photo (path menyertakan /{pegawai_id}_ agar
        // pemilik (non-admin) juga lolos scope).
        $this->photoPath = 'smoke/'.$this->pegawai->id.'_smoke.webp';
        Storage::disk('presensi')->put($this->photoPath, 'dummy');

        // Notifikasi dummy untuk route notifications.read/archive/restore (admin & mobile).
        $this->adminNotifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $this->adminNotifId,
            'type' => 'App\\Notifications\\StatusIzin',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->superadmin->id,
            'data' => json_encode(['message' => 'Smoke test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->mobileNotifId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $this->mobileNotifId,
            'type' => 'App\\Notifications\\StatusIzin',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->mobileUser->id,
            'data' => json_encode(['message' => 'Smoke test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->announcement = Announcement::create([
            'title' => 'Pengumuman Smoke',
            'body' => 'Isi pengumuman smoke test.',
            'unit_sekolah_id' => $this->unit->id,
            'is_pinned' => false,
            'published_at' => now(),
            'created_by' => $this->superadmin->id,
        ]);

        // Record sekali-pakai untuk route destruktif.
        $this->disposablePegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Hapus',
            'nik' => '7777666655554444',
            'no_hp' => '081200000099',
            'status_kepegawaian' => 'tetap',
        ]);
        $this->disposableKomponen = KomponenGaji::create([
            'nama' => 'Komponen Hapus',
            'kode' => 'komponen_hapus',
            'tipe' => 'pendapatan',
            'jenis' => 'fixed',
            'nilai_default' => 100,
            'unit_sekolah_id' => $this->unit->id,
            'is_taxable' => false,
            'is_active' => true,
            'urutan' => 99,
            'tampil_di_matrix' => false,
        ]);
        $this->disposableSkala = SkalaMasaBakti::create([
            'masa_kerja_tahun' => 30,
            'nominal_gaji' => 3000000,
        ]);
        $this->disposableMapel = MataPelajaran::create(['nama' => 'Mapel Hapus']);
        $this->disposableJabatan = Jabatan::create(['nama' => 'Jabatan Hapus', 'is_guru' => false]);
        $this->disposableRole = Role::create(['name' => 'smoke_role_test', 'guard_name' => 'web']);

        // Fixture master (hari-libur / tugas-luar / reminders) untuk smoke route.
        $this->hariLibur = HariLibur::create([
            'tanggal' => '2026-08-17',
            'nama' => 'Smoke Libur',
            'tipe' => 'nasional',
            'unit_sekolah_id' => null,
        ]);
        $this->disposableHariLibur = HariLibur::create([
            'tanggal' => '2026-12-25',
            'nama' => 'Smoke Libur Hapus',
            'tipe' => 'nasional',
            'unit_sekolah_id' => null,
        ]);
        $this->tugasLuar = TugasLuar::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => now()->format('Y-m-d'),
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'tujuan' => 'Smoke Tugas Luar',
            'keterangan' => 'smoke',
        ]);
        $this->disposableTugasLuar = TugasLuar::create([
            'pegawai_id' => $this->pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => now()->format('Y-m-d'),
            'tujuan' => 'Smoke Tugas Luar Hapus',
            'keterangan' => 'smoke',
        ]);
        $this->reminder = Reminder::create([
            'title' => 'Smoke Reminder',
            'message' => 'Test reminder',
            'type' => 'presensi',
            'unit_sekolah_id' => $this->unit->id,
            'created_by' => $this->superadmin->id,
        ]);
        $this->disposableReminder = Reminder::create([
            'title' => 'Smoke Reminder Hapus',
            'message' => 'Test reminder hapus',
            'type' => 'presensi',
            'unit_sekolah_id' => $this->unit->id,
            'created_by' => $this->superadmin->id,
        ]);

        $this->pengajuanApprove = $this->makePengajuan();
        $this->pengajuanReject = $this->makePengajuan();
    }

    /** Pengajuan izin pending untuk route approve/reject (status guarded → forceFill). */
    private function makePengajuan(): PengajuanIzin
    {
        $pengajuan = PengajuanIzin::create([
            'pegawai_id' => $this->pegawai->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => now()->format('Y-m-d'),
            'tanggal_selesai' => now()->format('Y-m-d'),
            'alasan' => 'Izin smoke test',
        ]);
        $pengajuan->forceFill(['status' => 'pending', 'approval_stage' => 'pending_l1'])->save();

        return $pengajuan;
    }

    public function test_admin_portal_get_routes_respond_200(): void
    {
        $failures = $this->checkPortalRoutes('web_admin');

        $this->assertSame([], $failures, 'Route admin yang tidak merespons 200:'.PHP_EOL.implode(PHP_EOL, $failures));
    }

    public function test_mobile_portal_get_routes_respond_200(): void
    {
        $failures = $this->checkPortalRoutes('web_mobile');

        $this->assertSame([], $failures, 'Route mobile yang tidak merespons 200:'.PHP_EOL.implode(PHP_EOL, $failures));
    }

    public function test_admin_portal_mutation_routes_do_not_error(): void
    {
        $failures = $this->checkMutationRoutes('web_admin');

        $this->assertSame([], $failures, 'Route mutasi admin yang error:'.PHP_EOL.implode(PHP_EOL, $failures));
    }

    public function test_mobile_portal_mutation_routes_do_not_error(): void
    {
        $failures = $this->checkMutationRoutes('web_mobile');

        $this->assertSame([], $failures, 'Route mutasi mobile yang error:'.PHP_EOL.implode(PHP_EOL, $failures));
    }

    public function test_admin_portal_inertia_components_and_props_match(): void
    {
        $failures = $this->checkInertiaComponents('web_admin');

        $this->assertSame([], $failures, 'Komponen/props Inertia admin tidak cocok:'.PHP_EOL.implode(PHP_EOL, $failures));
    }

    public function test_mobile_portal_inertia_components_and_props_match(): void
    {
        $failures = $this->checkInertiaComponents('web_mobile');

        $this->assertSame([], $failures, 'Komponen/props Inertia mobile tidak cocok:'.PHP_EOL.implode(PHP_EOL, $failures));
    }

    private function checkPortalRoutes(string $guard): array
    {
        $routes = $this->portalRoutes($guard);

        // Sanity guard: discovery tidak boleh kosong (jika middleware berubah,
        // test harus gagal, bukan lulus diam-diam).
        $min = $guard === 'web_admin' ? 25 : 10;
        $this->assertGreaterThan($min, $routes->count(), "[{$guard}] discovery terlalu sedikit route — cek filter middleware.");

        $failures = [];
        foreach ($routes as $route) {
            $name = $route->getName();

            try {
                $url = $this->buildUrl($route);
                $user = $this->userFor($guard, $name);

                $response = $this->actingAs($user, $guard)->get($url);

                if ($this->statusCode($response) !== 200) {
                    $detail = $this->extractError($response);
                    $failures[] = sprintf('%s → HTTP %d (%s) %s', $name, $this->statusCode($response), $url, $detail);
                }
            } catch (\Throwable $e) {
                $failures[] = sprintf('%s → EXCEPTION: %s', $name, $e->getMessage());
            }
        }

        return $failures;
    }

    /**
     * Hit semua route mutasi (POST/PUT/PATCH/DELETE) dengan payload valid minimal.
     * Assertion: tidak boleh 5xx maupun exception. 302/422/400 = route bekerja
     * (business rule / validasi) — bukan bug 500 yang dicari.
     */
    private function checkMutationRoutes(string $guard): array
    {
        $routes = $this->mutationRoutes($guard);

        $min = $guard === 'web_admin' ? 30 : 10;
        $this->assertGreaterThan($min, $routes->count(), "[{$guard}] discovery mutasi terlalu sedikit — cek filter middleware.");

        $failures = [];
        foreach ($routes as $route) {
            $name = $route->getName();
            $method = collect($route->methods())
                ->first(fn ($m) => in_array($m, ['POST', 'PUT', 'PATCH', 'DELETE'], true));

            try {
                $url = $this->buildUrl($route);
                $user = $this->userFor($guard, $name);
                $payload = $this->payloadFor($name);
                $files = $this->filesFor($name);

                $response = $this->actingAs($user, $guard)->call($method, $url, $payload, [], $files);

                if ($this->statusCode($response) >= 500) {
                    $detail = $this->extractError($response);
                    $failures[] = sprintf('%s [%s] → HTTP %d (%s) %s', $name, $method, $this->statusCode($response), $url, $detail);
                }
            } catch (\Throwable $e) {
                $failures[] = sprintf('%s [%s] → EXCEPTION: %s', $name, $method, $e->getMessage());
            }
        }

        return $failures;
    }

    /**
     * Verifikasi halaman Inertia dalam SATU sweep per portal:
     *
     * 1. Komponen yang dirender BENAR (assertInertia component) dan file komponen
     *    benar-benar ada di resources/js/Pages (cegah typo path).
     * 2. Setiap prop yang dikonsumsi komponen ADA di props halaman (deteksi
     *    query/eager-load yang dihapus dari controller), dan untuk prop data
     *    (true di INERTIA_PROPS) tidak kosong/null.
     *
     * Route yang return JSON/download (bukan Inertia) dilewati — tapi jika ternyata
     * merender halaman Inertia padahal tidak di-map, itu juga gagal (anti-silent-gap).
     */
    private function checkInertiaComponents(string $guard): array
    {
        $routes = $this->portalRoutes($guard);
        $failures = [];

        foreach ($routes as $route) {
            $name = $route->getName();
            $expected = self::INERTIA_COMPONENTS[$name] ?? null;

            try {
                $url = $this->buildUrl($route);
                $user = $this->userFor($guard, $name);

                $response = $this->actingAs($user, $guard)->get($url);

                if ($this->statusCode($response) !== 200) {
                    continue; // sudah di-cover test GET 200
                }

                if ($expected === null) {
                    // Route tidak di-map: JSON/download boleh; halaman Inertia wajib di-map.
                    if ($this->looksLikeInertiaPage($response)) {
                        $failures[] = sprintf('%s → merender halaman Inertia tapi belum ada di INERTIA_COMPONENTS', $name);
                    }

                    continue;
                }

                $response->assertInertia(function (Assert $page) use ($name, $expected, &$failures) {
                    $data = $page->toArray();
                    $actual = $data['component'];

                    if ($actual !== $expected) {
                        $failures[] = sprintf('%s → komponen "%s", diharapkan "%s"', $name, $actual, $expected);

                        return;
                    }

                    // Komponen benar → pastikan file-nya ada di disk (cegah typo path).
                    $page->component($expected, true);

                    // Props yang dikonsumsi komponen harus ada (dan terisi jika data).
                    $props = $data['props'] ?? [];
                    foreach (self::INERTIA_PROPS[$name] ?? [] as $prop => $mustBeFilled) {
                        if (! array_key_exists($prop, $props)) {
                            $failures[] = sprintf('%s → prop "%s" TIDAK ADA di props halaman', $name, $prop);

                            continue;
                        }

                        if ($mustBeFilled && ! $this->isPropFilled($props[$prop])) {
                            $failures[] = sprintf('%s → prop "%s" ada tapi kosong/null — query/eager-load hilang?', $name, $prop);
                        }
                    }

                    // Struktur nested yang dikonsumsi komponen (deteksi eager-load hilang).
                    foreach (self::INERTIA_PROPS_NESTED[$name] ?? [] as $path => $mustBeFilled) {
                        if (! $this->nestedHas($props, $path, $mustBeFilled)) {
                            $failures[] = sprintf('%s → nested prop "%s" TIDAK terpenuhi — eager-load/relasi hilang?', $name, $path);
                        }
                    }
                });
            } catch (\Throwable $e) {
                $failures[] = sprintf('%s → EXCEPTION: %s', $name, $e->getMessage());
            }
        }

        return $failures;
    }

    /**
     * Apakah nilai prop dianggap "terisi"?
     *
     * - null → tidak terisi.
     * - paginator (array dgn key 'data') → terisi jika data-nya tidak kosong
     *   (deteksi query yang tidak mengembalikan baris).
     * - array → terisi jika punya elemen.
     * - string → terisi jika bukan string kosong.
     * - lainnya (bool/int/objek) → terisi.
     */
    private function isPropFilled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_array($value)) {
            // Paginator serialization: { current_page, data: [...], total, ... }.
            // Deteksi via key khas paginator (bukan sekedar key 'data' — array
            // assosiatif lain juga bisa punya key 'data').
            if (array_key_exists('current_page', $value) && array_key_exists('data', $value)) {
                return is_array($value['data']) && count($value['data']) > 0;
            }

            return count($value) > 0;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    /**
     * Cek apakah struktur props memenuhi dotted path (mis. 'presensis.data.*.pegawai').
     *
     * Data selalu berasal dari $page->toArray() (array murni hasil serialisasi
     * Inertia), jadi traversal cukup untuk array.
     *
     * - Segment biasa → key array dengan nama itu — harus ADA (deteksi
     *   eager-load hilang = relasi tidak diserialize sama sekali).
     * - Segment '*' → minimal SATU elemen (list ataupun associative array)
     *   memenuhi sisa path. Jika SEMUA elemen gagal (mis. eager-load relasi
     *   dihapus dari controller), hasil false → terdeteksi.
     * - Nilai akhir: jika $mustBeFilled=true, harus terisi (aturan isPropFilled).
     *   Jika false, cukup path-nya resolve (nilai boleh kosong/null — relasi
     *   yang memang bisa legitimately kosong).
     */
    private function nestedHas(array $root, string $path, bool $mustBeFilled): bool
    {
        $segments = explode('.', $path);
        $current = $root;

        foreach ($segments as $i => $segment) {
            if ($segment === '*') {
                if (! is_array($current)) {
                    return false;
                }

                $rest = implode('.', array_slice($segments, $i + 1));
                foreach (array_values($current) as $value) {
                    // Nilai non-array (mis. relasi null di baris tertentu) tidak
                    // bisa memenuhi sisa path — lewati, jangan TypeError.
                    if (is_array($value) && $this->nestedHas($value, $rest, $mustBeFilled)) {
                        return true;
                    }
                }

                return false;
            }

            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return $mustBeFilled ? $this->isPropFilled($current) : true;
    }

    /**
     * Deteksi halaman Inertia dari response HTML penuh: root element dengan
     * atribut data-page (pola `<div id="app" data-page=...>`). Response
     * JSON/Binary/Streamed (download, foto, export) tidak punya ini, dan
     * getContent()-nya string kosong — aman dipanggil untuk semua tipe.
     */
    private function looksLikeInertiaPage(mixed $response): bool
    {
        try {
            $content = (string) $response->getContent();
        } catch (\Throwable) {
            return false;
        }

        return str_contains($content, 'data-page=') && str_contains($content, 'id="app"');
    }

    /**
     * Ambil status HTTP dari TestResponse maupun response mentah (Streamed/Binary
     * untuk route download/export). Laravel 13 TestResponse meneruskan panggilan
     * via __call ke base response, jadi getStatusCode() (milik Symfony base
     * Response) berlaku untuk semua tipe — tanpa method_exists yang keliru
     * karena __call tidak terlihat oleh method_exists.
     */
    private function statusCode(mixed $response): int
    {
        return $response->getStatusCode();
    }

    /**
     * Ekstrak pesan exception dari response 500 (halaman error Laravel).
     *
     * Catatan: TestResponse Laravel 13 meneruskan getContent()/status() via
     * __call ke base response, sehingga method_exists() tidak bisa dipakai
     * sebagai guard — panggil langsung dan biarkan __call yang menangani.
     */
    private function extractError(mixed $response): string
    {
        try {
            $content = (string) $response->getContent();
        } catch (\Throwable) {
            return '';
        }

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $content, $m)) {
            return '['.strip_tags($m[1]).']';
        }
        if (preg_match('/class="exception-message"[^>]*>(.*?)<\//s', $content, $m)) {
            return '['.strip_tags($m[1]).']';
        }
        if (str_contains($content, 'Vite manifest')) {
            return '[Vite manifest not found — build belum jalan]';
        }
        if (strlen($content) > 0) {
            return '['.substr(preg_replace('/\s+/', ' ', strip_tags($content)), 0, 120).']';
        }

        return '';
    }

    /** Semua GET route dalam portal tertentu (guard), minus daftar pengecualian. */
    private function portalRoutes(string $guard): Collection
    {
        $exclude = $guard === 'web_admin' ? self::ADMIN_EXCLUDE : self::MOBILE_EXCLUDE;

        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true))
            ->filter(fn (Route $route) => $route->getName() !== null)
            ->filter(fn (Route $route) => collect($route->middleware())
                ->contains(fn (string $middleware) => str_contains($middleware, 'auth:'.$guard)))
            ->reject(fn (Route $route) => in_array($route->getName(), $exclude, true))
            ->values();
    }

    /** Semua route mutasi (POST/PUT/PATCH/DELETE) dalam portal, minus pengecualian. */
    private function mutationRoutes(string $guard): Collection
    {
        $exclude = $guard === 'web_admin' ? self::ADMIN_MUTATION_EXCLUDE : self::MOBILE_MUTATION_EXCLUDE;

        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => collect($route->methods())
                ->contains(fn ($m) => in_array($m, ['POST', 'PUT', 'PATCH', 'DELETE'], true)))
            ->filter(fn (Route $route) => $route->getName() !== null)
            ->filter(fn (Route $route) => collect($route->middleware())
                ->contains(fn (string $middleware) => str_contains($middleware, 'auth:'.$guard)))
            ->reject(fn (Route $route) => in_array($route->getName(), $exclude, true))
            ->values();
    }

    private function userFor(string $guard, string $name): User
    {
        if ($guard === 'web_mobile') {
            return $this->mobileUser;
        }

        // Route yang sengaja membatasi superadmin (hanya payroll operator):
        // export-bank, destroy payroll, destroy_period, finalize worksheet.
        if (in_array($name, [
            'penggajian.export-bank',
            'penggajian.destroy',
            'penggajian.destroy_period',
            'penggajian.run.worksheet_finalize',
        ], true)) {
            return $this->payrollOperator;
        }

        return $this->superadmin;
    }

    /**
     * Payload valid minimal per route mutasi.
     * Route tanpa body (approve/reject/read/archive/restore/destroy) → [].
     */
    private function payloadFor(string $name): array
    {
        return match ($name) {
            'profile.update', 'presensi.profile.update' => [
                'name' => 'Nama Diperbarui',
                'email' => $name === 'presensi.profile.update' ? $this->mobileUser->email : $this->superadmin->email,
            ],
            'lengkapi-data.store', 'presensi.lengkapi-data.store' => [
                'nik' => '8899001122334455',
                'nama_lengkap' => 'Guru Smoke Test',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_pernikahan' => 'kawin',
                'jumlah_tanggungan' => 1,
                'alamat_ktp' => 'Jl. Smoke No. 1',
                'no_hp' => '081200000000',
                'status_kepegawaian' => 'tetap',
                'tanggal_mulai_kerja' => '2020-01-01',
                'pendidikan_terakhir' => 'S1',
                'nama_bank' => 'BCA',
                'no_rekening' => '1234567890',
            ],
            'pegawai.store' => [
                'nama_lengkap' => 'Pegawai Baru',
                'email' => 'baru'.Str::random(6).'@test.com',
                'password' => 'password123',
                'no_hp' => '081200000001',
                'unit_sekolah_id' => $this->unit->id,
                'jabatan_id' => $this->jabatan->id,
                'status_kepegawaian' => 'tetap',
            ],
            'pegawai.update' => [
                'nik' => '8899001122334455',
                'nip' => '',
                'nama_lengkap' => 'Guru Smoke Test Updated',
                'email' => $this->mobileUser->email,
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_pernikahan' => 'kawin',
                'alamat_ktp' => 'Jl. Smoke No. 1',
                'no_hp' => '081200000000',
                'status_kepegawaian' => 'tetap',
                'jatah_cuti_tahunan' => 12,
                'status_aktif' => 'aktif',
                'tanggal_mulai_kerja' => '2020-01-01',
                'pendidikan_terakhir' => 'S1',
                'units' => [[
                    'unit_sekolah_id' => $this->unit->id,
                    'jabatan_id' => $this->jabatan->id,
                    'is_primary' => true,
                ]],
                'mapels' => [[
                    'mata_pelajaran_id' => $this->mapel->id,
                    'unit_sekolah_id' => $this->unit->id,
                ]],
            ],
            'pegawai.destroy' => ['alasan_nonaktif' => 'Smoke test'],
            'pegawai.keuangan.update' => ['komponens' => [$this->komponen->id => '2000000']],
            'pegawai.dokumen.store' => [
                'nama_dokumen' => 'Ijazah S1',
                'jenis' => 'ijazah',
            ],
            'unit-sekolah.store' => [
                'nama' => 'SD Smoke',
                'singkatan' => 'SD',
                'latitude' => -6.2,
                'longitude' => 106.8,
                'radius_meter' => 100,
                'durasi_jp' => 35,
                'max_jam_minggu' => 30,
                'toleransi_menit' => 5,
                'toleransi_tap_menit' => 15,
                'jam_masuk_kantor' => '07:00',
                'jam_pulang_kantor' => '16:00',
            ],
            'unit-sekolah.update' => [
                'nama' => 'SMP Smoke Test',
                'singkatan' => 'SMP',
                'latitude' => -6.2,
                'longitude' => 106.8,
                'radius_meter' => 100,
                'durasi_jp' => 45,
                'max_jam_minggu' => 30,
                'toleransi_menit' => 0,
                'toleransi_tap_menit' => 15,
                'jam_masuk_kantor' => '07:00',
                'jam_pulang_kantor' => '16:00',
            ],
            'jadwal.store' => [
                'pegawai_id' => $this->pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'kelas_label' => '7 - B',
                'mata_pelajaran_id' => $this->mapel->id,
                'hari' => ['Sabtu'],
                'jam_mulai' => '13:00',
                'jam_selesai' => '14:30',
                'jenis_jadwal' => 'mengajar',
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
            ],
            'jadwal.update' => [
                'pegawai_id' => $this->pegawai->id,
                'unit_sekolah_id' => $this->unit->id,
                'kelas_label' => '7 - A',
                'mata_pelajaran_id' => $this->mapel->id,
                'hari' => 'Senin',
                'jam_mulai' => '08:00',
                'jam_selesai' => '09:30',
                'jenis_jadwal' => 'mengajar',
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
            ],
            'jadwal.generate' => [
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
                'unit_sekolah_id' => $this->unit->id,
                'mata_pelajaran_id' => $this->mapel->id,
            ],
            'jadwal.swap' => [
                'jadwal_asal_id' => $this->jadwal->id,
                'jadwal_tujuan_id' => $this->jadwal2->id,
            ],
            'presensi.store' => [
                'pegawai_id' => $this->pegawai->id,
                'jadwal_id' => $this->jadwal->id,
                'tipe' => 'keluar',
                'latitude' => -6.2,
                'longitude' => 106.8,
                'foto' => $this->validFotoBase64(),
            ],
            'presensi.update' => [
                'status' => 'hadir',
            ],
            'presensi.absen.store' => [
                'jadwal_id' => $this->jadwal->id,
                'is_lembur' => false,
                'tipe' => 'keluar',
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
                'foto' => $this->validFotoBase64(),
            ],
            'presensi.absen.tetap' => [
                'tipe' => 'masuk',
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
                'foto' => $this->validFotoBase64(),
            ],
            'presensi.absen.tap' => [
                'jadwal_id' => $this->jadwal->id,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy' => 15,
            ],
            'presensi.izin.store' => [
                'jenis_izin' => 'izin',
                'tanggal_mulai' => now()->addDay()->format('Y-m-d'),
                'tanggal_selesai' => now()->addDay()->format('Y-m-d'),
                'alasan' => 'Izin keluarga (smoke test)',
            ],
            'presensi.push.subscribe' => [
                'endpoint' => 'https://example.com/push/smoke',
                'public_key' => 'test-key',
                'auth_token' => 'test-auth',
                'content_encoding' => 'aesgcm',
            ],
            'presensi.push.unsubscribe' => [
                'endpoint' => 'https://example.com/push/smoke',
            ],
            'komponen-gaji.store' => [
                'nama' => 'Tunjangan Smoke',
                'kode' => 'tunjangan_smoke',
                'tipe' => 'pendapatan',
                'jenis' => 'fixed',
                'nilai_default' => 500000,
                'unit_sekolah_id' => $this->unit->id,
                'is_taxable' => true,
                'is_active' => true,
                'urutan' => 2,
                'tampil_di_matrix' => true,
            ],
            'komponen-gaji.update' => [
                'nama' => 'Gaji Pokok',
                'kode' => 'gaji_pokok',
                'tipe' => 'pendapatan',
                'jenis' => 'fixed',
                'nilai_default' => 2000000,
                'unit_sekolah_id' => $this->unit->id,
                'is_taxable' => true,
                'is_active' => true,
                'urutan' => 1,
                'tampil_di_matrix' => true,
            ],
            'komponen-gaji.matrix.update' => [
                'pegawai_data' => [[
                    'pegawai_id' => $this->pegawai->id,
                    'komponens' => [$this->komponen->id => '2000000'],
                ]],
            ],
            'komponen-gaji.pegawai.batch' => [
                'pegawai_data' => [[
                    'id' => $this->pegawai->id,
                    'nominal' => '2000000',
                ]],
            ],
            'skala-masa-bakti.store' => [
                'masa_kerja_tahun' => 25,
                'nominal_gaji' => 2500000,
            ],
            'penggajian.run.init' => [
                'month' => now()->addMonth()->format('m'),
                'year' => now()->addMonth()->format('Y'),
            ],
            'penggajian.run.worksheet_save' => [
                'penggajian_id' => $this->penggajianDraft->id,
                'details' => [[
                    'komponen_gaji_id' => $this->komponen->id,
                    'nama_komponen' => 'Gaji Pokok',
                    'tipe' => 'pendapatan',
                    'nominal' => 2000000,
                ]],
            ],
            'penggajian.destroy_period' => [
                'periode_bulan' => now()->addMonth()->format('m-Y'),
            ],
            'pengajuan-izin.reject' => ['alasan_penolakan' => 'Berkas tidak lengkap'],
            'users.store' => [
                'name' => 'User Baru',
                'email' => 'user'.Str::random(6).'@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'pegawai',
                'unit_sekolah_id' => $this->unit->id,
            ],
            'users.update' => [
                'role' => 'admin_unit',
                'permissions' => [],
            ],
            'pengumuman.store' => [
                'title' => 'Pengumuman Smoke',
                'body' => 'Isi pengumuman smoke test.',
                'unit_sekolah_id' => $this->unit->id,
                'is_pinned' => false,
            ],
            'pengumuman.update' => [
                'title' => 'Pengumuman Smoke Updated',
                'body' => 'Isi pengumuman sudah diperbarui.',
                'is_pinned' => false,
            ],
            'roles.store' => [
                'name' => 'smoke_role_'.Str::random(6),
                'permissions' => ['view_pegawai'],
            ],
            'roles.update' => [
                'name' => 'smoke_role_updated_'.Str::random(6),
                'permissions' => ['view_pegawai'],
            ],
            'mata-pelajaran.store' => ['nama' => 'IPA Smoke'],
            'mata-pelajaran.update' => ['nama' => 'Matematika'],
            'jabatan.store' => ['nama' => 'Kepala Sekolah Smoke', 'is_guru' => false],
            'jabatan.update' => ['nama' => 'Guru', 'is_guru' => true],
            'hari-libur.update' => [
                'tanggal' => '2026-12-25',
                'nama' => 'Libur Diperbarui',
                'tipe' => 'nasional',
                'unit_sekolah_id' => $this->unit->id,
                'keterangan' => 'smoke',
            ],
            'password.update', 'presensi.password.update' => [
                'current_password' => 'password',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
            // Route mutasi tanpa body (approve/reject/read/archive/restore/destroy) → [].
            default => [],
        };
    }

    /** File upload untuk route yang butuh file. */
    private function filesFor(string $name): array
    {
        if ($name === 'pegawai.dokumen.store') {
            return [
                'file' => UploadedFile::fake()->create('ijazah.pdf', 100, 'application/pdf'),
            ];
        }

        return [];
    }

    /** Foto PNG 1x1 valid (base64) untuk validasi foto presensi/dokumen. */
    private function validFotoBase64(): string
    {
        // 1x1 transparan PNG — lolos finfo MIME + regex base64 tanpa file berat.
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    private function buildUrl(Route $route): string
    {
        $name = $route->getName();

        if ($name === 'presensi.photo') {
            return url('/presensi/photo/'.$this->photoPath);
        }

        $params = $this->resolveParams($route);
        $query = $this->queryFor($name);

        return route($name, array_merge($params, $query));
    }

    private function resolveParams(Route $route): array
    {
        $params = [];
        foreach ($route->parameterNames() as $param) {
            $params[$param] = $this->resolveParam($param, $route->getName());
        }

        return $params;
    }

    private function resolveParam(string $param, string $routeName): mixed
    {
        return match ($param) {
            // Destroy pakai record sekali-pakai agar fixture inti tidak hilang di tengah run.
            'pegawai' => $routeName === 'pegawai.destroy' ? $this->disposablePegawai->id : $this->pegawai->id,
            'user' => $this->adminUnit->id,
            'unit_sekolah' => $this->unit->id,
            'jadwal' => $routeName === 'jadwal.destroy' ? $this->jadwal2->id : $this->jadwal->id,
            'presensi' => match ($routeName) {
                'presensi.approveLembur' => $this->presensiLembur->id,
                'presensi.rejectLembur' => $this->presensiLembur2->id,
                default => $this->presensi->id,
            },
            'komponen_gaji' => $routeName === 'komponen-gaji.destroy' ? $this->disposableKomponen->id : $this->komponen->id,
            'skala_masa_bakti' => $this->disposableSkala->id,
            'mata_pelajaran' => $routeName === 'mata-pelajaran.destroy' ? $this->disposableMapel->id : $this->mapel->id,
            'jabatan' => $routeName === 'jabatan.destroy' ? $this->disposableJabatan->id : $this->jabatan->id,
            'role' => in_array($routeName, ['roles.update', 'roles.destroy'], true) ? $this->disposableRole->id : Role::query()->where('name', 'pegawai')->value('id'),
            'announcement' => $this->announcement->id,
            'dokumen' => $this->dokumen->id,
            'pengajuan' => $this->pengajuanApprove->id,
            'month' => now()->format('m'),
            'year' => now()->format('Y'),
            'id' => match (true) {
                str_starts_with($routeName, 'presensi.notifikasi') => $this->mobileNotifId,
                in_array($routeName, ['notifications.read', 'notifications.archive', 'notifications.restore'], true) => $this->adminNotifId,
                $routeName === 'presensi.gaji.show' => $this->penggajianPaid->id,
                $routeName === 'pengajuan-izin.approve' => $this->pengajuanApprove->id,
                $routeName === 'pengajuan-izin.reject' => $this->pengajuanReject->id,
                $routeName === 'penggajian.destroy' => $this->penggajianDraft->id,
                default => $this->penggajianDraft->id,
            },
            'hari_libur' => $routeName === 'hari-libur.destroy' ? $this->disposableHariLibur->id : $this->hariLibur->id,
            'tugas_luar' => $routeName === 'tugas-luar.destroy' ? $this->disposableTugasLuar->id : $this->tugasLuar->id,
            'reminder' => in_array($routeName, ['reminders.destroy', 'reminders.send'], true) ? $this->disposableReminder->id : $this->reminder->id,
            default => throw new \RuntimeException("Param route tak dikenal: {$param} @ {$routeName}"),
        };
    }

    /** Query string yang wajib untuk sebagian route (form request / filter). */
    private function queryFor(string $name): array
    {
        $range = [
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        return match ($name) {
            'laporan.preview', 'laporan.presensi', 'laporan.penggajian', 'laporan.lemburan' => array_merge(['type' => 'presensi'], $range),
            'penggajian.export-bank' => ['periode_bulan' => now()->format('m-Y')],
            'jadwal.kelas-by-unit' => ['q' => 'smoke'],
            'presensi.jadwal.kelas' => ['jadwal_id' => $this->jadwal->id],
            'laporan.kcd.preview', 'laporan.kcd.pdf' => [
                'unit_sekolah_id' => $this->unit->id,
                'periode' => now()->format('Y-m'),
            ],
            default => [],
        };
    }
}
