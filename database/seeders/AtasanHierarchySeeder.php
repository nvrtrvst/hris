<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use Illuminate\Database\Seeder;

/**
 * Hierarki atasan langsung ala Dapodik:
 *
 *   Ketua/Kepala Yayasan (unit Yayasan)  ← atasan semua Kepala Sekolah & pegawai kantor pusat
 *     └─ Kepala Sekolah                  ← atasan guru + wakil + kepala perpustakaan/lab + kepala TU
 *          └─ Kepala Tata Usaha          ← atasan tendik adm (TU, Bendahara, Kasir, Operator, ...)
 *               └─ staf tendik
 *
 * Aturan penugasan (berdasarkan jabatan PRIMARY):
 *   - Guru (is_guru)            → Kepala Sekolah unitnya
 *   - Wakil Kepala Sekolah      → Kepala Sekolah unitnya
 *   - Kepala Perpustakaan/Lab   → Kepala Sekolah unitnya
 *   - Kepala Tata Usaha         → Kepala Sekolah unitnya
 *   - Tendik adm & lainnya      → Kepala TU unitnya (fallback: Kepala Sekolah)
 *   - Kepala Sekolah            → Ketua/Kepala Yayasan (unit Yayasan)
 *   - Pegawai unit Yayasan      → Ketua/Kepala Yayasan
 *   - Ketua/Kepala Yayasan      → NULL (puncak)
 *
 * Idempoten & aman: hanya mengisi pegawai yang atasan_langsung_id-nya masih
 * NULL (tidak menimpa penugasan manual). Tambahkan argumen --force untuk
 * menimpa semua (php artisan db:seed --class=AtasanHierarchySeeder --force
 * dijalankan lewat seeder ini bila env SEED_FORCE_ATASAN=true).
 */
class AtasanHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $force = (bool) env('SEED_FORCE_ATASAN', false);

        $units = UnitSekolah::orderBy('id')->get();
        $unitIds = $units->pluck('id');
        $jabatans = Jabatan::all()->keyBy('id');

        $pegawai = Pegawai::with(['units' => fn ($q) => $q->withPivot('jabatan_id', 'is_primary')])
            ->whereHas('units', fn ($q) => $q->whereIn('unit_sekolah.id', $unitIds))
            ->where('status_aktif', 'aktif')
            ->get();

        // Kelompokkan per unit PRIMARY (pegawai lintas unit hanya dihitung
        // di unit utamanya — hindari double-assign).
        $byUnit = [];
        foreach ($pegawai as $p) {
            $primaryUnit = $p->units->first(fn ($u) => ! empty($u->pivot->is_primary)) ?? $p->units->first();
            if ($primaryUnit) {
                $byUnit[(int) $primaryUnit->id][] = $p;
            }
        }

        $ketuaYayasan = $this->firstWithJabatan($byUnit, $jabatans, $units->firstWhere('singkatan', 'YAYASAN')?->id, ['Ketua Yayasan', 'Kepala Yayasan']);

        $assigned = 0;
        $skipped = 0;

        foreach ($units as $unit) {
            $kepsek = $this->firstWithJabatan($byUnit, $jabatans, $unit->id, ['Kepala Sekolah']);
            $kepalaTU = $this->firstWithJabatan($byUnit, $jabatans, $unit->id, ['Kepala Tata Usaha']);

            foreach ($byUnit[(int) $unit->id] ?? [] as $p) {
                if (! $force && $p->atasan_langsung_id !== null) {
                    $skipped++;

                    continue;
                }

                $jabatanPrimer = $p->jabatanPrimer();
                $jabatan = $jabatanPrimer ? $jabatans->get($jabatanPrimer->id) : null;
                $namaJabatan = $jabatan?->nama;

                $atasan = match (true) {
                    // Puncak struktur
                    in_array($namaJabatan, ['Ketua Yayasan', 'Kepala Yayasan'], true) => null,
                    // Kepala sekolah → yayasan
                    $namaJabatan === 'Kepala Sekolah' => $ketuaYayasan,
                    // Guru & pimpinan fungsional → kepala sekolah
                    ($jabatan && $jabatan->is_guru) || in_array($namaJabatan, ['Wakil Kepala Sekolah', 'Kepala Perpustakaan', 'Kepala Laboratorium', 'Kepala Tata Usaha'], true) => $kepsek,
                    // Tendik adm & lainnya → kepala TU (fallback kepala sekolah,
                    // lalu ketua yayasan untuk pegawai kantor pusat)
                    default => $kepalaTU ?? $kepsek ?? $ketuaYayasan,
                };

                if ($atasan && (int) $atasan->id === (int) $p->id) {
                    // Jangan jadikan diri sendiri atasan (data rusak).
                    $atasan = null;
                }

                $p->atasan_langsung_id = $atasan?->id;
                $p->save();
                $assigned++;
            }
        }

        $this->command?->info("Hierarki atasan selesai: {$assigned} ditetapkan, {$skipped} dilewati (sudah ada).");
    }

    private function firstWithJabatan(array $byUnit, $jabatans, ?int $unitId, array $namaJabatan): ?Pegawai
    {
        if (! $unitId) {
            return null;
        }

        foreach ($byUnit[$unitId] ?? [] as $p) {
            $jabatanPrimer = $p->jabatanPrimer();
            $jabatan = $jabatanPrimer ? $jabatans->get($jabatanPrimer->id) : null;
            if ($jabatan && in_array($jabatan->nama, $namaJabatan, true)) {
                return $p;
            }
        }

        return null;
    }
}
