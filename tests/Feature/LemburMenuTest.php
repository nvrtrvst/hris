<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LemburMenuTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixture: 2 unit, 2 pegawai ber-lembur kemarin + 1 presensi biasa,
     * plus lembur lama 8 hari lalu untuk unit A.
     *
     * @return array{0:Presensi,1:Presensi,2:Presensi,3:UnitSekolah,4:UnitSekolah}
     */
    private function boot(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $unitA = UnitSekolah::create([
            'nama' => 'Unit A', 'singkatan' => 'UA',
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);
        $unitB = UnitSekolah::create([
            'nama' => 'Unit B', 'singkatan' => 'UB',
            'latitude' => -6.21, 'longitude' => 106.81,
            'radius_meter' => 100, 'durasi_jp' => 45, 'toleransi_menit' => 0,
        ]);

        $jabatan = Jabatan::create(['nama' => 'Guru']);
        $nikCounter = 0;
        $buatPegawai = function (string $nama, UnitSekolah $unit) use ($jabatan, &$nikCounter): Pegawai {
            $user = User::factory()->create();
            $pegawai = Pegawai::create([
                'user_id' => $user->id,
                'nik' => '1234567890'.sprintf('%06d', ++$nikCounter),
                'nama_lengkap' => $nama,
                'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1992-03-03',
                'jenis_kelamin' => 'L', 'agama' => 'Islam',
                'status_pernikahan' => 'kawin', 'jumlah_tanggungan' => 0,
                'alamat' => 'Jl. Test No. 1', 'no_hp' => '081234567890',
                'status_kepegawaian' => 'guru_pemula', 'tmt_mengajar' => '2021-01-01',
                'status_aktif' => 'aktif', 'pendidikan_terakhir' => 'S1',
            ]);
            $pegawai->units()->attach($unit->id, ['jabatan_id' => $jabatan->id, 'is_primary' => true]);

            return $pegawai;
        };

        $kemarin = Carbon::yesterday()->toDateString();
        $lalu = Carbon::today()->subDays(8)->toDateString();

        $pgA = $buatPegawai('Pegawai A', $unitA);
        $pgB = $buatPegawai('Pegawai B', $unitB);

        $lemburA = Presensi::forceCreate([
            'pegawai_id' => $pgA->id, 'unit_sekolah_id' => $unitA->id,
            'tanggal' => $kemarin, 'jam_masuk' => '17:00:00', 'jam_keluar' => '20:00:00',
            'is_lembur' => true, 'lembur_status' => 'pending', 'status' => 'hadir',
        ]);
        $lemburB = Presensi::forceCreate([
            'pegawai_id' => $pgB->id, 'unit_sekolah_id' => $unitB->id,
            'tanggal' => $kemarin, 'jam_masuk' => '18:00:00', 'jam_keluar' => '20:00:00',
            'is_lembur' => true, 'lembur_status' => 'disetujui', 'status' => 'hadir',
        ]);
        $lemburLama = Presensi::forceCreate([
            'pegawai_id' => $pgA->id, 'unit_sekolah_id' => $unitA->id,
            'tanggal' => $lalu, 'jam_masuk' => '17:00:00', 'jam_keluar' => '19:00:00',
            'is_lembur' => true, 'lembur_status' => 'ditolak', 'status' => 'hadir',
        ]);
        // Presensi biasa: tidak boleh muncul di menu lembur.
        Presensi::forceCreate([
            'pegawai_id' => $pgA->id, 'unit_sekolah_id' => $unitA->id,
            'tanggal' => $kemarin, 'jam_masuk' => '07:00:00', 'jam_keluar' => '15:00:00',
            'is_lembur' => false, 'lembur_status' => null, 'status' => 'hadir',
        ]);

        return [$lemburA, $lemburB, $lemburLama, $unitA, $unitB];
    }

    private function buatUserDenganRole(string $role, ?UnitSekolah $unit = null): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        if ($unit) {
            $user->forceFill(['unit_sekolah_id' => $unit->id])->save();
        }

        return $user;
    }

    public function test_superadmin_melihat_daftar_lembur_tanpa_presensi_biasa(): void
    {
        [$lemburA, $lemburB] = $this->boot();

        $super = $this->buatUserDenganRole('superadmin');

        $res = $this->actingAs($super, 'web_admin')
            ->get(route('lembur.index', ['start_date' => Carbon::today()->subDays(9)->toDateString(), 'end_date' => Carbon::today()->toDateString()]));

        $res->assertOk()->assertInertia(fn ($page) => $page
            ->component('Lembur/Index')
            ->has('lemburs.data', 3)
            ->where('lemburs.data.0.id', $lemburB->id) // urut tanggal desc, id desc
            ->has('counts.pending')
            ->has('counts.disetujui')
            ->has('units.0'));
    }

    public function test_default_rentang_kemarin_saja(): void
    {
        $this->boot();

        $super = $this->buatUserDenganRole('superadmin');

        $this->actingAs($super, 'web_admin')
            ->get(route('lembur.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('lemburs.total', 2));

        // Lembur lama 8 hari lalu tidak termasuk default.
        $super2 = $this->buatUserDenganRole('superadmin');
        $this->actingAs($super2, 'web_admin')
            ->get(route('lembur.index', ['start_date' => Carbon::today()->subDays(10)->toDateString(), 'end_date' => Carbon::today()->subDays(7)->toDateString()]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('lemburs.total', 1));
    }

    public function test_filter_status_lembur(): void
    {
        [$lemburA] = $this->boot();

        $super = $this->buatUserDenganRole('superadmin');

        $this->actingAs($super, 'web_admin')
            ->get(route('lembur.index', ['lembur_status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lemburs.total', 1)
                ->where('lemburs.data.0.id', $lemburA->id));
    }

    public function test_admin_unit_hanya_melihat_unit_nya(): void
    {
        [, $lemburB, , , $unitB] = $this->boot();

        $adminUnitB = $this->buatUserDenganRole('admin_unit', $unitB);

        $this->actingAs($adminUnitB, 'web_admin')
            ->get(route('lembur.index', ['start_date' => Carbon::today()->subDays(9)->toDateString(), 'end_date' => Carbon::today()->toDateString()]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lemburs.total', 1)
                ->where('lemburs.data.0.id', $lemburB->id));
    }

    public function test_tanpa_permission_ditolak(): void
    {
        $this->boot();

        $staf = $this->buatUserDenganRole('pegawai');

        $this->actingAs($staf, 'web_admin')
            ->get(route('lembur.index'))
            ->assertForbidden();
    }
}
