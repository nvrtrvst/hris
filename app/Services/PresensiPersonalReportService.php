<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PresensiPersonalReportService
{
    public function summary(Pegawai $pegawai, Carbon $start, Carbon $end, ?string $status = null): array
    {
        $query = Presensi::where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()]);

        if ($status) {
            $query->where('status', $status);
        }

        $perDay = $query->get(['tanggal', 'status'])
            ->groupBy(fn (Presensi $row) => Carbon::parse($row->tanggal)->toDateString())
            ->map(fn (Collection $rows) => $this->effectiveStatus($rows));

        $counts = $perDay->countBy();

        return [
            'total' => $perDay->count(),
            'hadir' => (int) $counts->get('hadir', 0),
            'telat' => (int) $counts->get('telat', 0),
            'sakit' => (int) $counts->get('sakit', 0),
            'izin' => (int) $counts->get('izin', 0),
            'cuti' => (int) $counts->get('cuti', 0),
            'alpa' => (int) $counts->get('alpa', 0),
        ];
    }

    private function effectiveStatus(Collection $rows): string
    {
        foreach (['hadir', 'telat', 'sakit', 'izin', 'cuti'] as $status) {
            if ($rows->contains('status', $status)) {
                return $status;
            }
        }

        return 'alpa';
    }
}
