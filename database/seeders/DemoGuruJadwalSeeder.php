<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dummy guru tetap + jadwal mengajar padat untuk smoke test
 * 1-slide-to-cover-multi-jadwal (fitur slide grup back-to-back).
 *
 * Grup A: kelas 7 - A, 3 JP back-to-back (08:00-10:15) → 1 slide = 3 jam.
 * Grup B: kelas 8 - B, 2 JP back-to-back (10:30-12:00) → 1 slide = 2 jam.
 * Senin-Jumat → 5 JP/hari, 25 JP/minggu.
 *
 * Idempotent: re-run akan menghapus user/pegawai/jadwal demo lama dulu.
 */
class DemoGuruJadwalSeeder extends Seeder
{
    private const EMAIL = 'guru.demo@demo.test';

    public function run(): void
    {
        $unit = UnitSekolah::where('nama', 'SMP')->first()
            ?? UnitSekolah::orderBy('id')->first();
        abort_unless($unit, 1, 'Unit sekolah tidak ditemukan — jalankan seeder master dulu.');

        $jabatan = Jabatan::where('is_guru', true)->first()
            ?? Jabatan::first();
        abort_unless($jabatan, 1, 'Jabatan tidak ditemukan.');

        $mapel = MataPelajaran::where('nama', 'Matematika')->first()
            ?? MataPelajaran::first();
        abort_unless($mapel, 1, 'Mata pelajaran tidak ditemukan.');

        // Cleanup run sebelumnya (termasuk pegawai orphan tanpa user).
        // NIK/email encrypted — lookup via nik_hash. forceDelete: unique
        // nik_hash tidak peduli soft-delete. Observer riwayat dimatikan —
        // row pegawai sudah dihapus duluan, insert riwayat malah FK-fail.
        $nikHashLama = Pegawai::nikHash('999900000001');
        Pegawai::withoutEvents(fn () => Pegawai::withTrashed()->where('nik_hash', $nikHashLama)->get()
            ->each(function (Pegawai $p) {
                Presensi::where('pegawai_id', $p->id)->delete();
                Jadwal::where('pegawai_id', $p->id)->delete();
                PegawaiMapel::where('pegawai_id', $p->id)->delete();
                $p->units()->detach();
                $p->forceDelete();
            }));
        User::where('email', self::EMAIL)->delete();

        $user = User::create([
            'email' => self::EMAIL,
            'name' => 'Guru Demo SMP',
            'password' => Hash::make('password'),
            'unit_sekolah_id' => $unit->id,
        ]);
        $user->syncRoles('pegawai');

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nik' => '999900000001',
            'nama_lengkap' => 'Guru Demo SMP',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_pernikahan' => 'Belum Menikah',
            'alamat' => 'Jl. Demo No. 1',
            'no_hp' => '081234567899',
            'email' => self::EMAIL,
            'status_aktif' => 'aktif',
            'status_kepegawaian' => 'guru_tetap_yayasan',
            'wajib_kantor' => true,
            'tmt_mengajar' => '2020-01-01',
            'pendidikan_terakhir' => 'S1',
        ]);
        $pegawai->units()->attach($unit->id, [
            'jabatan_id' => $jabatan->id,
            'is_primary' => true,
        ]);

        $pegawaiMapel = PegawaiMapel::create([
            'pegawai_id' => $pegawai->id,
            'mata_pelajaran_id' => $mapel->id,
            'unit_sekolah_id' => $unit->id,
        ]);

        // Grup A: 7 - A → 3 JP back-to-back. Grup B: 8 - B → 2 JP back-to-back.
        $grup = [
            ['kelas' => '7 - A', 'jam' => [
                ['08:00:00', '08:45:00'],
                ['08:45:00', '09:30:00'],
                ['09:30:00', '10:15:00'],
            ]],
            ['kelas' => '8 - B', 'jam' => [
                ['10:30:00', '11:15:00'],
                ['11:15:00', '12:00:00'],
            ]],
        ];

        $hariKerja = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $tahunAjaran = now()->month >= 7
            ? now()->year.'/'.(now()->year + 1)
            : (now()->year - 1).'/'.now()->year;
        $semester = now()->month >= 7 || now()->month <= 1 ? 1 : 2;

        $jumlah = 0;
        foreach ($hariKerja as $hari) {
            foreach ($grup as $g) {
                foreach ($g['jam'] as [$mulai, $selesai]) {
                    Jadwal::create([
                        'pegawai_id' => $pegawai->id,
                        'unit_sekolah_id' => $unit->id,
                        'pegawai_mapel_id' => $pegawaiMapel->id,
                        'kelas_label' => $g['kelas'],
                        'hari' => $hari,
                        'jam_mulai' => $mulai,
                        'jam_selesai' => $selesai,
                        'jenis_jadwal' => 'mengajar',
                        'tahun_ajaran' => $tahunAjaran,
                        'semester' => $semester,
                    ]);
                    $jumlah++;
                }
            }
        }

        $this->command?->info(sprintf(
            'Demo guru siap: login %s / password (%s, %s) — %d jadwal (5 JP/hari, Senin-Jumat).',
            self::EMAIL,
            $unit->nama,
            $pegawai->status_kepegawaian,
            $jumlah
        ));

        // ===== Presensi 14 hari ke belakang (hari ini hanya kantor pagi) =====
        // Meniru hasil 1-slide-cover: JP utama punya jam_masuk/jam_keluar,
        // JP cover NULL/NULL. Variasi: hari ke-3 telat, hari ke-6 alpa penuh.
        $jumlahPresensi = 0;
        for ($d = 14; $d >= 1; $d--) {
            $tanggal = Carbon::today()->subDays($d);
            if ($tanggal->isWeekend()) {
                continue;
            }

            $hariIndo = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'][$tanggal->format('l')];
            $jadwalHari = Jadwal::where('pegawai_id', $pegawai->id)
                ->where('hari', $hariIndo)
                ->where('jenis_jadwal', 'mengajar')
                ->orderBy('jam_mulai')
                ->get()
                ->groupBy('kelas_label');

            $hariAlpa = $d === 6; // 1 hari kerja kosong — alpa implisit.
            $hariTelat = $d === 3; // JP utama masuk 08:10 → telat.

            // Presensi kantor (foto pagi + sore).
            if (! $hariAlpa) {
                Presensi::create([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => null,
                    'unit_sekolah_id' => $unit->id,
                    'tipe_presensi' => 'kantor',
                    'tanggal' => $tanggal->toDateString(),
                    'jam_masuk' => '07:05:00',
                    'jam_keluar' => '15:10:00',
                    'status' => 'hadir',
                ]);
                $jumlahPresensi++;
            }

            if ($hariAlpa) {
                continue;
            }

            foreach ($jadwalHari as $kelas => $jps) {
                $jps = $jps->values();
                $jamMasuk = $kelas === '7 - A'
                    ? ($hariTelat ? '08:10:00' : '07:55:00')
                    : '10:25:00'; // Grup B mulai 10:30 — masuk tidak mungkin 07:55.
                $statusMengajar = $hariTelat && $kelas === '7 - A' ? 'telat' : 'hadir';

                // JP pertama grup: hanya jam_masuk (slide masuk).
                Presensi::create([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $jps[0]->id,
                    'unit_sekolah_id' => $unit->id,
                    'tipe_presensi' => 'mengajar',
                    'tanggal' => $tanggal->toDateString(),
                    'jam_masuk' => $jamMasuk,
                    'status' => $statusMengajar,
                ]);
                $jumlahPresensi++;

                // JP tengah: cover otomatis — NULL/NULL.
                foreach ($jps->slice(1, -1) as $jp) {
                    Presensi::create([
                        'pegawai_id' => $pegawai->id,
                        'jadwal_id' => $jp->id,
                        'unit_sekolah_id' => $unit->id,
                        'tipe_presensi' => 'mengajar',
                        'tanggal' => $tanggal->toDateString(),
                        'status' => $statusMengajar,
                    ]);
                    $jumlahPresensi++;
                }

                // JP terakhir grup: hanya jam_keluar (slide pulang).
                if ($jps->count() > 1) {
                    Presensi::create([
                        'pegawai_id' => $pegawai->id,
                        'jadwal_id' => $jps->last()->id,
                        'unit_sekolah_id' => $unit->id,
                        'tipe_presensi' => 'mengajar',
                        'tanggal' => $tanggal->toDateString(),
                        'jam_keluar' => $jps->last()->jam_selesai,
                        'status' => $statusMengajar,
                    ]);
                    $jumlahPresensi++;
                }
            }
        }

        // Hari ini: kantor pagi saja — supaya flow slide manual tetap bisa diuji.
        Presensi::create([
            'pegawai_id' => $pegawai->id,
            'jadwal_id' => null,
            'unit_sekolah_id' => $unit->id,
            'tipe_presensi' => 'kantor',
            'tanggal' => Carbon::today()->toDateString(),
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
        ]);
        $jumlahPresensi++;

        $this->command?->info($jumlahPresensi.' presensi demo (14 hari, termasuk kantor hari ini) — hari ini belum di-slide.');

        if (in_array(Carbon::now()->format('l'), ['Sunday', 'Saturday'])) {
            $this->command?->warn('Hari ini weekend — tidak ada jadwal mengajar untuk diuji hari ini.');
        }
    }
}
