<?php

namespace App\Imports;

use App\Models\Pegawai;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PegawaiKomponenImport implements ToCollection, WithHeadingRow
{
    protected $komponenId;

    public function __construct($komponenId)
    {
        $this->komponenId = $komponenId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Kita cari pegawai berdasarkan NIK. Bersihkan petik satu (') yang ditambahkan dari template.
            $nik = isset($row['nik']) ? ltrim(trim($row['nik']), "'") : null;
            $nominal = isset($row['nominal']) ? trim($row['nominal']) : null;

            if ($nik) {
                if ($nominal !== null && $nominal !== '') {
                    // Hapus format mata uang jika ada (misal: "Rp 500.000" -> 500000)
                    $nominal = preg_replace('/[^0-9]/', '', $nominal);
                    
                    $pegawai = Pegawai::where('nik', $nik)->first();
                    if ($pegawai) {
                        $pegawai->komponenGaji()->syncWithoutDetaching([
                            $this->komponenId => ['nominal' => $nominal]
                        ]);
                    }
                } else {
                    // Jika di Excel kolom nominal dikosongkan, hapus dari pivot (kembali ke default)
                    $pegawai = Pegawai::where('nik', $nik)->first();
                    if ($pegawai) {
                        $pegawai->komponenGaji()->detach($this->komponenId);
                    }
                }
            }
        }
    }
}
