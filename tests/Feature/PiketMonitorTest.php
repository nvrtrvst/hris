<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\LaporanPiket;
use App\Models\Pegawai;
use App\Models\PiketItemTemplate;
use App\Models\PiketPetugas;
use App\Models\PiketSesi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PiketMonitorTest extends TestCase
{
    use RefreshDatabase;

    private UnitSekolah $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-10-26 16:00:00');

        $this->unit = UnitSekolah::create([
            'nama' => 'SMK Uji',
            'singkatan' => 'SMK',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 100,
            'durasi_jp' => 45,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');

        foreach (['gerbang', 'cek_absen', 'kelas_kosong', 'buku', 'insiden'] as $urutan => $kode) {
            PiketItemTemplate::create([
                'unit_sekolah_id' => $this->unit->id,
                'kode' => $kode,
                'label' => ucfirst(str_replace('_', ' ', $kode)),
                'seksi' => 'kbm',
                'scope' => 'global',
                'tipe' => 'centang',
                'wajib' => true,
                'urutan' => $urutan,
            ]);
        }
    }

    private function sesiDenganPetugas(array $items = [], string $statusPetugas = 'aktif'): PiketSesi
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Guru']);
        $pegawai = Pegawai::create([
            'nik' => '3273'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
            'nama_lengkap' => 'Arif Miftah',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'status_aktif' => 'aktif',
            'tmt_mengajar' => '2020-01-01',
        ]);
        $pegawai->units()->attach($this->unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

        $sesi = PiketSesi::create([
            'unit_sekolah_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '07:00',
            'jam_selesai' => '15:40',
            'status' => 'incomplete',
        ]);

        $laporan = LaporanPiket::create([
            'pegawai_id' => $pegawai->id,
            'unit_sekolah_id' => $this->unit->id,
            'sesi_id' => $sesi->id,
            'tanggal' => now()->toDateString(),
            'status' => LaporanPiket::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'items' => $items,
        ]);

        PiketPetugas::create([
            'sesi_id' => $sesi->id,
            'pegawai_id' => $pegawai->id,
            'peran' => 'area',
            'status' => $statusPetugas,
            'laporan_piket_id' => $laporan->id,
        ]);

        return $sesi;
    }

    private function itemsLengkap(array $insidenEntries = []): array
    {
        $items = [];
        foreach (['gerbang', 'cek_absen', 'kelas_kosong', 'buku', 'insiden'] as $kode) {
            $items[$kode] = ['done_at' => '16:00:00', 'entries' => []];
        }
        $items['insiden']['entries'] = $insidenEntries;
        // Item wajib mode simple selalu 6, terlepas dari jumlah template.
        $items['absen_siswa'] = ['done_at' => '16:00:00', 'entries' => []];

        return $items;
    }

    public function test_insiden_sudah_diverifikasi_tidak_memicu_perlu_tindakan(): void
    {
        $this->sesiDenganPetugas($this->itemsLengkap([
            ['nama_guru' => 'Guru X', 'verified' => 'setujui', 'verified_at' => '2026-10-26 10:05:00'],
        ]));

        $this->actingAs($this->admin)
            ->get(route('piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Piket/Index')
                ->where('sessions.0.perlu_tindakan', false)
                ->where('sessions.0.insiden', 0)
                ->where('sessions.0.petugas.0.selesai', 6)
                ->where('sessions.0.petugas.0.wajib', 6)
                ->where('sessions.0.petugas.0.submitted', true)
                ->where('sessions.0.petugas.0.terlambat_lapor', true));
    }

    public function test_insiden_belum_diverifikasi_memicu_perlu_tindakan_dengan_alasan(): void
    {
        $this->sesiDenganPetugas($this->itemsLengkap([
            ['nama_guru' => 'Guru X', 'verified' => null, 'verified_at' => null],
        ]));

        $this->actingAs($this->admin)
            ->get(route('piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Piket/Index')
                ->where('sessions.0.perlu_tindakan', true)
                ->where('sessions.0.insiden', 1)
                ->where('sessions.0.perlu_alasan', fn ($alasan) => $alasan->contains('Insiden belum diverifikasi: 1')));
    }

    public function test_petugas_tidak_hadir_ditampilkan_dan_memicu_perlu_tindakan(): void
    {
        $this->sesiDenganPetugas($this->itemsLengkap(), 'tidak_hadir');

        $this->actingAs($this->admin)
            ->get(route('piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Piket/Index')
                ->where('sessions.0.petugas.0.status', 'tidak_hadir')
                ->where('sessions.0.perlu_tindakan', true)
                ->where('sessions.0.perlu_alasan', fn ($alasan) => $alasan->contains('Petugas tidak hadir: 1')));
    }

    public function test_mode_simple_memakai_enam_item_wajib_saat_data_siswa_tersedia(): void
    {
        config(['keuangan.url' => 'http://keuangan.test', 'keuangan.key' => 'test-key']);
        $this->sesiDenganPetugas($this->itemsLengkap());

        $this->actingAs($this->admin)
            ->get(route('piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sessions.0.petugas.0.selesai', 6)
                ->where('sessions.0.petugas.0.wajib', 6));
    }

    public function test_insiden_dapat_divalidasi_dan_menghapus_perlu_tindakan(): void
    {
        $sesi = $this->sesiDenganPetugas($this->itemsLengkap([
            ['nama_guru' => 'Guru X', 'verified' => null, 'verified_at' => null],
        ]));
        $laporan = LaporanPiket::where('sesi_id', $sesi->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('laporan-piket.validasi', $laporan->id), [
                'kode' => 'insiden',
                'entry_index' => 0,
                'aksi' => 'setujui',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('piket.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sessions.0.insiden', 0)
                ->where('sessions.0.perlu_tindakan', false));
    }
}
