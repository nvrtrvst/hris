<?php

namespace App\Exports;

use App\Constants\PegawaiConstants;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PegawaiExport implements FromCollection, WithHeadings, WithStyles
{
    /**     * @param  Collection  $pegawais  Hasil query ter-filter (units, jabatans, user sudah eager-load).
     * @param  bool  $withNik  true = NIK plaintext (superadmin), false = NIK ter-mask.
\n     */
    public function __construct(
        private Collection $pegawais,
        private bool $withNik = false
    ) {}

    public function headings(): array
    {
        return [
            'NIK',
            'NIP',
            'Nama Lengkap',
            'Jenis',
            'Unit',
            'Jabatan',
            'Status Kepegawaian',
            'Status Aktif',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Jenis Kelamin',
            'Agama',
            'Status Pernikahan',
            'No HP',
            'Alamat KTP',
            'Tanggal Mulai Kerja',
            'Pendidikan Terakhir',
            'Email Login',
            'Jumlah Tanggungan',
            'Wajib Masuk Kantor',
        ];
    }

    public function collection(): Collection
    {
        return $this->pegawais
            ->sortBy(fn ($p) => ($p->units->first()->nama ?? '').' '.strtolower($p->nama_lengkap))
            ->values()
            ->map(function ($p) {
                return [
                    $this->withNik ? $this->plainNik($p) : $p->nik_masked,
                    $p->nip ?? '',
                    $p->nama_lengkap,
                    $p->jenisPegawaiLabel(),
                    $p->units->pluck('nama')->implode(', '),
                    $p->jabatans->pluck('nama')->implode(', '),
                    PegawaiConstants::STATUS_KEPEGAWAIAN_LABELS[$p->status_kepegawaian] ?? $p->status_kepegawaian,
                    $p->status_aktif,
                    $p->tempat_lahir ?? '',
                    $p->tanggal_lahir,
                    $p->jenis_kelamin,
                    $p->agama ?? '',
                    $p->status_pernikahan ?? '',
                    $p->no_hp ?? '',
                    $p->alamat_ktp ?? '',
                    $p->tanggal_mulai_kerja,
                    $p->pendidikan_terakhir ?? '',
                    $p->user?->email ?? '',
                    (int) ($p->jumlah_tanggungan ?? 0),
                    $p->wajib_kantor ? 'Ya' : 'Tidak',
                ];
            });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * NIK plaintext aman — model meng-hide kolom nik dari serialisasi, tapi\n     * akses properti di PHP mengembalikan plaintext via cast encrypted.\n     */
    private function plainNik($pegawai): string
    {
        try {
            $raw = $pegawai->getRawOriginal('nik');
            if (! is_string($raw) || $raw === '') {
                return '';
            }

            $plain = Crypt::decryptString($raw);
            if (preg_match('/^eyJ[A-Za-z0-9+\/=]+$/', $plain)) {
                $plain = Crypt::decryptString($plain);
            }

            return $plain;
        } catch (DecryptException) {
            return '';
        }
    }
}
