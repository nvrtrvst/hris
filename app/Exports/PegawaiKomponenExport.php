<?php

namespace App\Exports;

use App\Models\Pegawai;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PegawaiKomponenExport implements FromCollection, WithHeadings, WithMapping
{
    protected $komponenId;

    public function __construct($komponenId)
    {
        $this->komponenId = $komponenId;
    }

    public function collection()
    {
        return Pegawai::where('status_aktif', 'aktif')
            ->with(['komponenGaji' => function ($q) {
                $q->where('komponen_gaji_id', $this->komponenId);
            }])->get();
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama Lengkap',
            'Nominal',
        ];
    }

    public function map($pegawai): array
    {
        $pivot = $pegawai->komponenGaji->first();
        $nominal = $pivot ? $pivot->pivot->nominal : '';

        return [
            // Tambahkan petik satu agar NIK dibaca sebagai teks (tidak diubah jadi scientific E+ oleh Excel)
            "'".$pegawai->nik,
            $pegawai->nama_lengkap,
            $nominal,
        ];
    }
}
