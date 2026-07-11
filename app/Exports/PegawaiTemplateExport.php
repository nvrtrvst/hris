<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PegawaiTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'NIK',
            'NIP',
            'Nama Lengkap',
            'Tempat Lahir',
            'Tanggal Lahir (YYYY-MM-DD)',
            'Jenis Kelamin (L/P)',
            'Agama',
            'Status Pernikahan',
            'No HP',
            'Alamat KTP',
            'Status Kepegawaian (tetap/kontrak/honorer/gtt)',
            'Tanggal Mulai Kerja (YYYY-MM-DD)',
            'Pendidikan Terakhir',
            'Nama Jabatan',
        ];
    }

    public function array(): array
    {
        return [
            [
                '1234567890123456',
                '198001012020011001',
                'Budi Santoso',
                'Jakarta',
                '1980-01-01',
                'L',
                'Islam',
                'Menikah',
                '081234567890',
                'Jl. Contoh Alamat No. 123',
                'tetap',
                '2020-01-01',
                'S1 Pendidikan',
                'Guru',
            ],
        ];
    }
}
