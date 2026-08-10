<?php

namespace App\Exports;

use App\Models\Penggajian;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class TransferBankExport implements FromView
{
    protected string $periodeBulan;

    public function __construct(string $periodeBulan)
    {
        $this->periodeBulan = $periodeBulan;
    }

    public function view(): View
    {
        $data = Penggajian::with('pegawai')
            ->where('periode_bulan', $this->periodeBulan)
            ->whereIn('status', ['finalized', 'paid'])
            ->orderBy('pegawai_id')
            ->get()
            ->map(function ($p) {
                $pegawai = $p->pegawai;
                $primaryUnit = $pegawai?->units()?->wherePivot('is_primary', true)->first()
                    ?? $pegawai?->units()->first();

                return [
                    'nama' => $pegawai?->nama_lengkap ?? '-',
                    'unit' => $primaryUnit?->nama ?? '-',
                    'bank' => $pegawai?->nama_bank ?? '-',
                    'no_rekening' => $pegawai?->no_rekening ?? '-',
                    'nominal' => $p->gaji_bersih,
                ];
            });

        return view('exports.transfer-bank', [
            'periode' => $this->periodeBulan,
            'data' => $data,
        ]);
    }
}
