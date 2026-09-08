<?php

namespace App\Imports;

use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\UnitSekolah;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Import jadwal massal via Excel (semua unit — tiap unit beda format file
 * asli, jadi template seragam ini jadi jalur umum).
 *
 * Kolom (baris 1 = header, data mulai baris 2):
 *   Hari | Kelas | Nama Guru | Mata Pelajaran | Jam Mulai | Jumlah JP | Jam Selesai | Jenis Jadwal | Tahun Ajaran | Semester
 *
 * Jam selesai otomatis bila Jumlah JP terisi: mulai + JP × durasi_jp unit.
 * Template lama (tanpa Jumlah JP, jam selesai di kolom F) tetap didukung.
 */
class JadwalImport implements ToCollection, WithStartRow
{
    protected int $unitId;

    protected string $defaultTahunAjaran;

    protected int $defaultSemester;

    /** @var array<int, string> pesan error per baris (nomor baris Excel) */
    protected array $failures = [];

    protected int $created = 0;

    protected int $skipped = 0;

    /** Durasi 1 JP unit (menit) — dipakai hitung jam selesai dari Jumlah JP. */
    protected int $durasiJpMenit = 40;

    public function __construct(int $unitId, ?string $tahunAjaran = null, ?int $semester = null)
    {
        $this->unitId = $unitId;
        $this->defaultTahunAjaran = $tahunAjaran ?: (now()->month >= 7
            ? now()->year.'/'.(now()->year + 1)
            : (now()->year - 1).'/'.now()->year);
        $this->defaultSemester = $semester ?: (now()->month >= 7 || now()->month <= 1 ? 1 : 2);
        $this->durasiJpMenit = (int) (UnitSekolah::find($unitId)?->durasi_jp ?: 40);
    }

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        // Prefetch lookup — 3 query, bukan per-baris.
        $pegawaiByName = Pegawai::query()->where('status_aktif', 'aktif')
            ->get()->keyBy(fn ($p) => trim($p->nama_lengkap));
        $mapelByNama = MataPelajaran::query()
            ->where('unit_sekolah_id', $this->unitId)
            ->orWhereNull('unit_sekolah_id')
            ->get()->keyBy(fn ($m) => trim($m->nama));

        // Jadwal existing unit+periode untuk cek duplikat & bentrok (1 query).
        // Baris file yang lolos validasi juga di-push ke sini supaya
        // bentrok ANTAR-baris file ikut terdeteksi.
        $existing = Jadwal::where('unit_sekolah_id', $this->unitId)
            ->where('tahun_ajaran', $this->defaultTahunAjaran)
            ->where('semester', $this->defaultSemester)
            ->get(['id', 'pegawai_id', 'kelas_label', 'hari', 'jam_mulai', 'jam_selesai']);

        // Two-pass: validasi semua baris dulu, insert hanya kalau semua valid
        // (Maatwebsite membungkus collection() dalam transaksi — exception =
        // rollback, jadi insert parsial akan hilang anyway; lebih baik
        // eksplisit all-or-nothing + laporan baris bermasalah).
        $pending = [];

        foreach ($rows as $idx => $row) {
            $excelRow = $idx + 2; // header = baris 1
            $hari = trim((string) ($row[0] ?? ''));
            $kelas = trim((string) ($row[1] ?? ''));
            $namaGuru = trim((string) ($row[2] ?? ''));
            $namaMapel = trim((string) ($row[3] ?? ''));
            $jamMulai = $this->normalizeTime((string) ($row[4] ?? ''));

            // Template baru (dengan Jumlah JP): kolom H (index 7) berisi jenis
            // jadwal yang valid. Template lama: jenis ada di kolom G (index 6).
            $jenisDiH = trim((string) ($row[7] ?? ''));
            $isNewTemplate = $jenisDiH !== '' && in_array($jenisDiH, ['mengajar', 'piket', 'ekskul', 'shift_satpam', 'shift_kebersihan', 'lainnya'], true);

            if ($isNewTemplate) {
                $jumlahJp = (int) (is_numeric($row[5] ?? null) ? $row[5] : 0);
                $jamSelesai = $this->normalizeTime((string) ($row[6] ?? ''));
                // Auto-hitung: mulai + JP × durasi_jp unit (template juga
                // punya formula Excel; server hitung ulang sebagai sumber
                // kebenaran — formula bisa tidak dievaluasi saat upload).
                if ($jumlahJp > 0) {
                    $jamSelesai = $this->addMenit($jamMulai, $jumlahJp * $this->durasiJpMenit);
                }
                $jenis = $jenisDiH;
                $tahunAjaran = trim((string) ($row[8] ?? '')) ?: $this->defaultTahunAjaran;
                $semester = (int) (trim((string) ($row[9] ?? '')) ?: $this->defaultSemester);
            } else {
                $jumlahJp = 0;
                $jamSelesai = $this->normalizeTime((string) ($row[5] ?? ''));
                $jenis = trim((string) ($row[6] ?? '')) ?: 'mengajar';
                $tahunAjaran = trim((string) ($row[7] ?? '')) ?: $this->defaultTahunAjaran;
                $semester = (int) (trim((string) ($row[8] ?? '')) ?: $this->defaultSemester);
            }

            // Baris kosong penuh → skip diam.
            if ($hari === '' && $namaGuru === '' && $jamMulai === null) {
                continue;
            }

            if ($this->fail($excelRow, $hari, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'], 'Hari tidak valid')) {
                continue;
            }

            $pegawai = $pegawaiByName[$namaGuru] ?? null;
            if (! $pegawai) {
                $this->failures[] = "Baris {$excelRow}: Guru '{$namaGuru}' tidak ditemukan / tidak aktif";

                continue;
            }

            if (! in_array($jenis, ['mengajar', 'piket', 'ekskul', 'shift_satpam', 'shift_kebersihan', 'lainnya'], true)) {
                $this->failures[] = "Baris {$excelRow}: Jenis jadwal '{$jenis}' tidak valid";

                continue;
            }

            if ($jamMulai === null || $jamSelesai === null) {
                $this->failures[] = "Baris {$excelRow}: Format jam harus H:i (mis. 08:00)";

                continue;
            }
            if ($jamSelesai <= $jamMulai) {
                $this->failures[] = "Baris {$excelRow}: Jam selesai ({$jamSelesai}) harus setelah jam mulai ({$jamMulai})";

                continue;
            }

            // Mapel wajib untuk mengajar, opsional untuk jenis lain.
            $mapelId = null;
            if ($jenis === 'mengajar') {
                $mapel = $mapelByNama[$namaMapel] ?? null;
                if (! $mapel) {
                    $this->failures[] = "Baris {$excelRow}: Mapel '{$namaMapel}' tidak ditemukan";

                    continue;
                }
                $mapelId = $mapel->id;
            }

            if (! in_array($semester, [1, 2], true)) {
                $this->failures[] = "Baris {$excelRow}: Semester harus 1 atau 2";

                continue;
            }

            // Duplikat persis (guru+kelas+hari+jam_mulai+periode) → skip.
            $duplikat = $existing->contains(fn ($j) => $j->pegawai_id === $pegawai->id
                && $j->kelas_label === ($kelas ?: null)
                && $j->hari === $hari
                && substr((string) $j->jam_mulai, 0, 5) === $jamMulai);
            if ($duplikat) {
                $this->skipped++;

                continue;
            }

            // Bentrok waktu: guru sama, hari sama, overlap.
            $bentrok = $existing->first(fn ($j) => $j->pegawai_id === $pegawai->id
                && $j->hari === $hari
                && substr((string) $j->jam_mulai, 0, 5) < $jamSelesai
                && substr((string) $j->jam_selesai, 0, 5) > $jamMulai);
            if ($bentrok) {
                $this->failures[] = "Baris {$excelRow}: Bentrok — {$pegawai->nama_lengkap} sudah punya jadwal {$hari} ".substr((string) $bentrok->jam_mulai, 0, 5).'-'.substr((string) $bentrok->jam_selesai, 0, 5);

                continue;
            }

            $pending[] = [
                'pegawai_id' => $pegawai->id,
                'kelas_label' => $kelas !== '' ? $kelas : null,
                'mapel_id' => $mapelId,
                'hari' => $hari,
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
                'jenis' => $jenis,
                'tahun_ajaran' => $tahunAjaran,
                'semester' => $semester,
            ];

            // Tandai slot terpakai untuk deteksi bentrok antar-baris file.
            $existing->push((object) [
                'pegawai_id' => $pegawai->id,
                'kelas_label' => $kelas ?: null,
                'hari' => $hari,
                'jam_mulai' => $jamMulai.':00',
                'jam_selesai' => $jamSelesai.':00',
            ]);
        }

        if ($this->failures !== []) {
            throw ValidationException::withMessages([
                'import' => 'Tidak ada baris diimport ('.count($this->failures)." baris bermasalah). Perbaiki file lalu upload ulang:\n"
                    .implode("\n", array_slice($this->failures, 0, 50)),
            ]);
        }

        foreach ($pending as $p) {
            Jadwal::create([
                'pegawai_id' => $p['pegawai_id'],
                'unit_sekolah_id' => $this->unitId,
                'kelas_label' => $p['kelas_label'],
                'pegawai_mapel_id' => $p['mapel_id'] ? $this->resolvePegawaiMapelId($p['pegawai_id'], $p['mapel_id']) : null,
                'hari' => $p['hari'],
                'jam_mulai' => $p['jam_mulai'].':00',
                'jam_selesai' => $p['jam_selesai'].':00',
                'jenis_jadwal' => $p['jenis'],
                'tahun_ajaran' => $p['tahun_ajaran'],
                'semester' => $p['semester'],
            ]);
            $this->created++;
        }
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    protected function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{1,2}[:.]\d{2}$/', $value)) {
            [$h, $m] = array_pad(preg_split('/[:.]/', $value), 2, 0);

            return sprintf('%02d:%02d', (int) $h, (int) $m);
        }
        // Excel time serial (0.x fraksi hari).
        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            $minutes = (int) round(((float) $value) * 24 * 60);

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        return null;
    }

    /**
     * Tambah menit ke jam H:i — wrap lintas hari tidak relevan (jadwal
     * sehari), cukup modulo 24 jam.
     */
    protected function addMenit(?string $jamMulai, int $menit): ?string
    {
        if (! $jamMulai) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $jamMulai));
        $total = ($h * 60 + $m + $menit) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    protected function fail(int $row, string $value, array $allowed, string $message): bool
    {
        if (! in_array($value, $allowed, true)) {
            $this->failures[] = "Baris {$row}: {$message} ('{$value}')";

            return true;
        }

        return false;
    }

    protected function resolvePegawaiMapelId(int $pegawaiId, int $mapelId): int
    {
        return PegawaiMapel::firstOrCreate([
            'pegawai_id' => $pegawaiId,
            'mata_pelajaran_id' => $mapelId,
            'unit_sekolah_id' => $this->unitId,
        ])->id;
    }
}
