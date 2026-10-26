<?php

namespace App\Services;

use App\Helpers\ApprovalHelper;
use App\Models\ApprovalPolicy;
use App\Models\HariLibur;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\UnitSekolah;
use Carbon\Carbon;

class ApprovalChain
{
    private const MAX_DEPTH = 6;

    private const NAMA_HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /**
     * @return array{l1:?int,l2:?int,auto:bool}
     */
    public static function resolve(Pegawai $pegawai, string $jenis, Carbon $acuan, int $hariPengajuan = 1): array
    {
        $policy = ApprovalPolicy::for($jenis);
        $butuhL2 = $policy->levels >= 2
            && ($policy->second_level_min_days === null || $hariPengajuan > $policy->second_level_min_days);

        $chain = [];
        $cursor = $pegawai->atasanLangsung;

        for ($depth = 0; $depth < self::MAX_DEPTH && $cursor; $depth++) {
            if ((int) $cursor->id === (int) $pegawai->id) {
                break;
            }

            if ($cursor->status_aktif !== 'aktif' || ! $cursor->user || self::sedangCuti($cursor, $acuan)) {
                $cursor = $cursor->atasanLangsung;

                continue;
            }

            // Yayasan hanya jadi penyetuju bila atasan langsung; di tahap kedua cuma menerima salinan.
            if (self::isYayasanHeadUser((int) $cursor->user_id)) {
                if ($chain === []) {
                    $chain[] = (int) $cursor->user_id;
                }

                break;
            }

            $chain[] = (int) $cursor->user_id;

            if (count($chain) >= ($butuhL2 ? 2 : 1)) {
                break;
            }

            $cursor = $cursor->atasanLangsung;
        }

        if ($chain === []) {
            $head = ApprovalHelper::findHeadInUnit(self::unitOf($pegawai)?->id);

            // Puncak organisasi bisa "menjadi atasannya sendiri"; jangan sampai lolos sebagai penyetuju.
            if ($head && (int) $head->id !== (int) $pegawai->user_id) {
                $chain[] = (int) $head->id;
            }
        }

        return [
            'l1' => $chain[0] ?? null,
            'l2' => $chain[1] ?? null,
            'auto' => $chain === [] && self::isKetuaYayasan($pegawai),
        ];
    }

    /**
     * Unit utama dibaca via query agar tidak memakai relasi yang mungkin sudah basi.
     */
    public static function unitOf(Pegawai $pegawai): ?UnitSekolah
    {
        return $pegawai->units()
            ->orderByDesc('pegawai_unit.is_primary')
            ->first();
    }

    public static function isKetuaYayasan(Pegawai $pegawai): bool
    {
        $ids = config('approval.yayasan_head_jabatan_ids', [24]);

        return $pegawai->jabatans()->whereIn('jabatan.id', $ids)->exists();
    }

    public static function isYayasanHeadUser(int $userId): bool
    {
        $ids = config('approval.yayasan_head_jabatan_ids', [24]);

        return Pegawai::where('user_id', $userId)
            ->whereHas('jabatans', fn ($q) => $q->whereIn('jabatan.id', $ids))
            ->exists();
    }

    public static function dueAt(Carbon $acuan, int $slaHari, ?UnitSekolah $unit): Carbon
    {
        $hariKerja = Pegawai::hariKerjaUnit($unit);
        $batas = $acuan->copy()->addDays(90);

        $libur = HariLibur::query()
            ->where(fn ($q) => $q->whereNull('unit_sekolah_id')->orWhere('unit_sekolah_id', $unit?->id))
            ->whereBetween('tanggal', [$acuan->toDateString(), $batas->toDateString()])
            ->pluck('tanggal')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $hari = max(1, $slaHari);
        $cursor = $acuan->copy()->startOfDay()->addDay();
        $hitung = 0;

        while ($hitung < $hari && $cursor->lte($batas)) {
            if (in_array(self::NAMA_HARI[$cursor->dayOfWeekIso], $hariKerja, true)
                && ! in_array($cursor->toDateString(), $libur, true)) {
                $hitung++;
            }

            if ($hitung < $hari) {
                $cursor->addDay();
            }
        }

        return $cursor->setTime(23, 59, 59);
    }

    private static function sedangCuti(Pegawai $pegawai, Carbon $tanggal): bool
    {
        return PengajuanIzin::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->exists();
    }
}
