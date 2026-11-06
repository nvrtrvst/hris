<?php

namespace App\Services;

use App\Models\AbsenSiswaRevisi;
use App\Models\AbsenSiswaSesi;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AbsenSiswaService
{
    /**
     * Simpan daftar siswa (ganti detail) + catat revisi.
     *
     * @param  array<int, array{nis:string,nama:string,status:string,keterangan?:?string}>  $siswa
     */
    public static function simpan(
        AbsenSiswaSesi $sesi,
        array $siswa,
        ?int $userId,
        string $peran,
        ?string $alasan = null,
        ?int $laporanId = null,
        ?int $pegawaiId = null,
    ): void {
        DB::transaction(function () use ($sesi, $siswa, $userId, $peran, $alasan, $laporanId, $pegawaiId) {
            $sesi = AbsenSiswaSesi::whereKey($sesi->id)->lockForUpdate()->firstOrFail();

            $sesi->forceFill([
                'pegawai_id' => $pegawaiId ?? $sesi->pegawai_id,
                'laporan_piket_id' => $laporanId ?? $sesi->laporan_piket_id,
                'done_at' => now(),
            ])->save();

            $sesi->details()->delete();

            $counts = ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
            $snapshot = [];

            foreach ($siswa as $row) {
                $sesi->details()->create([
                    'nis' => trim($row['nis']),
                    'nama_snapshot' => trim($row['nama']),
                    'status' => $row['status'],
                    'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
                ]);

                $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
                $snapshot[] = [
                    'nis' => trim($row['nis']),
                    'nama' => trim($row['nama']),
                    'status' => $row['status'],
                    'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
                ];
            }

            AbsenSiswaRevisi::create([
                'absen_siswa_sesi_id' => $sesi->id,
                'user_id' => $userId,
                'peran' => $peran,
                'ringkasan' => $counts,
                'details' => $snapshot,
                'alasan' => $alasan,
            ]);
        });
    }

    /**
     * Ganti nama dari client dengan nama kanonik roster sim-keu.
     *
     * @param  array<int, array{nis:string,status:string,keterangan?:?string}>  $submitted
     * @param  array<int, array<string, mixed>>  $students
     * @return array<int, array{nis:string,nama:string,status:string,keterangan:?string}>
     */
    public static function canonicalRows(array $submitted, array $students): array
    {
        $roster = collect($students)->keyBy(fn (array $row) => (string) ($row['nis'] ?? ''));
        $rows = [];
        $missing = [];

        foreach ($submitted as $row) {
            $nis = (string) ($row['nis'] ?? '');
            $student = $roster->get($nis);
            $nama = trim((string) ($student['nama'] ?? $student['name'] ?? ''));

            if (! $student || $nama === '') {
                $missing[] = $nis;

                continue;
            }

            $rows[] = [
                'nis' => $nis,
                'nama' => $nama,
                'status' => (string) ($row['status'] ?? ''),
                'keterangan' => $row['keterangan'] ?? null,
            ];
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'siswa' => 'Siswa tidak ditemukan di kelas tersebut: '.implode(', ', $missing).'.',
            ]);
        }

        return $rows;
    }

    public static function normalizeLabel(string $label): string
    {
        return preg_replace('/[^a-z0-9]+/i', '', strtolower($label)) ?? '';
    }

    /** @param list<string> $allowed */
    public static function labelDiizinkan(array $allowed, string $label): bool
    {
        $target = self::normalizeLabel($label);

        foreach ($allowed as $item) {
            if (self::normalizeLabel($item) === $target) {
                return true;
            }
        }

        return false;
    }

    public static function sudahDiisiOleh(AbsenSiswaSesi $sesi): ?string
    {
        if (! $sesi->done_at) {
            return null;
        }

        $nama = $sesi->pegawai?->nama_lengkap ?? 'petugas';

        return $nama.' pukul '.$sesi->done_at->format('H:i');
    }

    /**
     * Label kelas yang boleh diakses petugas pada mode area.
     *
     * @return list<string>
     */
    public static function kelasArea(UnitSekolah $unit, ?Pegawai $pegawai): array
    {
        if (! PiketService::isAreaMode($unit)) {
            return [];
        }

        $areaId = $pegawai ? PiketService::areaIdUntukPegawai($pegawai, $unit) : null;

        return PiketService::kelasArea($unit, $areaId);
    }
}
