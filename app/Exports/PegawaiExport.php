<?php

namespace App\Exports;

use App\Models\Pegawai;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PegawaiExport implements FromCollection, WithHeadings, WithStyles
{
    private const COLUMNS = [
        'nik' => ['label' => 'NIK', 'group' => 'Identitas', 'sensitive' => false],
        'nuptk' => ['label' => 'NUPTK', 'group' => 'Identitas', 'sensitive' => false],
        'nama_lengkap' => ['label' => 'Nama Lengkap', 'group' => 'Identitas', 'sensitive' => false],
        'jenis_pegawai' => ['label' => 'Jenis Pegawai', 'group' => 'Identitas', 'sensitive' => false],
        'tempat_lahir' => ['label' => 'Tempat Lahir', 'group' => 'Identitas', 'sensitive' => false],
        'tanggal_lahir' => ['label' => 'Tanggal Lahir', 'group' => 'Identitas', 'sensitive' => false],
        'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'group' => 'Identitas', 'sensitive' => false],
        'agama' => ['label' => 'Agama', 'group' => 'Identitas', 'sensitive' => false],
        'status_pernikahan' => ['label' => 'Status Pernikahan', 'group' => 'Identitas', 'sensitive' => false],
        'jumlah_tanggungan' => ['label' => 'Jumlah Tanggungan', 'group' => 'Identitas', 'sensitive' => false],
        'units' => ['label' => 'Unit', 'group' => 'Kepegawaian', 'sensitive' => false],
        'jabatans' => ['label' => 'Jabatan', 'group' => 'Kepegawaian', 'sensitive' => false],
        'mapels' => ['label' => 'Mata Pelajaran', 'group' => 'Kepegawaian', 'sensitive' => false],
        'lokasis' => ['label' => 'Lokasi', 'group' => 'Kepegawaian', 'sensitive' => false],
        'atasan_langsung' => ['label' => 'Atasan Langsung', 'group' => 'Kepegawaian', 'sensitive' => false],
        'status_kepegawaian' => ['label' => 'Status Kepegawaian', 'group' => 'Kepegawaian', 'sensitive' => false],
        'status_aktif' => ['label' => 'Status Aktif', 'group' => 'Kepegawaian', 'sensitive' => false],
        'tmt_mengajar' => ['label' => 'Tanggal Mulai Kerja', 'group' => 'Kepegawaian', 'sensitive' => false],
        'tanggal_akhir_kontrak' => ['label' => 'Tanggal Akhir Kontrak', 'group' => 'Kepegawaian', 'sensitive' => false],
        'sk_nomor' => ['label' => 'SK Nomor', 'group' => 'Kepegawaian', 'sensitive' => false],
        'sk_tanggal' => ['label' => 'SK Tanggal', 'group' => 'Kepegawaian', 'sensitive' => false],
        'wajib_kantor' => ['label' => 'Wajib Masuk Kantor', 'group' => 'Kepegawaian', 'sensitive' => false],
        'no_hp' => ['label' => 'No HP', 'group' => 'Kontak & Alamat', 'sensitive' => false],
        'no_hp_darurat' => ['label' => 'No HP Darurat', 'group' => 'Kontak & Alamat', 'sensitive' => false],
        'alamat' => ['label' => 'Alamat', 'group' => 'Kontak & Alamat', 'sensitive' => false],
        'email_login' => ['label' => 'Email Login', 'group' => 'Kontak & Alamat', 'sensitive' => false],
        'username' => ['label' => 'Username', 'group' => 'Kontak & Alamat', 'sensitive' => false],
        'pendidikan_terakhir' => ['label' => 'Pendidikan Terakhir', 'group' => 'Pendidikan', 'sensitive' => false],
        'pendidikan_jurusan' => ['label' => 'Jurusan', 'group' => 'Pendidikan', 'sensitive' => false],
        'pendidikan_tahun_lulus' => ['label' => 'Tahun Lulus', 'group' => 'Pendidikan', 'sensitive' => false],
        'pendidikan_asal_sekolah' => ['label' => 'Asal Sekolah', 'group' => 'Pendidikan', 'sensitive' => false],
        'nama_bank' => ['label' => 'Nama Bank', 'group' => 'Data Sensitif', 'sensitive' => true],
        'no_rekening' => ['label' => 'No Rekening', 'group' => 'Data Sensitif', 'sensitive' => true],
        'npwp' => ['label' => 'NPWP', 'group' => 'Data Sensitif', 'sensitive' => true],
        'no_bpjs_kesehatan' => ['label' => 'BPJS Kesehatan', 'group' => 'Data Sensitif', 'sensitive' => true],
        'no_bpjs_ketenagakerjaan' => ['label' => 'BPJS Ketenagakerjaan', 'group' => 'Data Sensitif', 'sensitive' => true],
    ];

    private const DEFAULT_COLUMNS = [
        'nik',
        'nuptk',
        'nama_lengkap',
        'jenis_pegawai',
        'units',
        'jabatans',
        'status_kepegawaian',
        'status_aktif',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'status_pernikahan',
        'no_hp',
        'alamat',
        'tmt_mengajar',
        'pendidikan_terakhir',
        'email_login',
        'jumlah_tanggungan',
        'wajib_kantor',
    ];

    public function __construct(
        private Collection $pegawais,
        private array $columns = self::DEFAULT_COLUMNS,
        private bool $withNik = false,
    ) {}

    public static function availableColumns(bool $withSensitive): array
    {
        $columns = [];
        foreach (self::COLUMNS as $key => $meta) {
            if (! $withSensitive && $meta['sensitive']) {
                continue;
            }

            $columns[] = [
                'key' => $key,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'sensitive' => $meta['sensitive'],
            ];
        }

        return $columns;
    }

    public static function defaultColumnsFor(bool $withSensitive): array
    {
        return array_values(array_filter(
            self::DEFAULT_COLUMNS,
            fn (string $key) => $withSensitive || ! self::COLUMNS[$key]['sensitive'],
        ));
    }

    public static function allColumnKeys(): array
    {
        return array_keys(self::COLUMNS);
    }

    public static function sensitiveColumnKeys(): array
    {
        return array_keys(array_filter(self::COLUMNS, fn (array $meta) => $meta['sensitive']));
    }

    public function headings(): array
    {
        return array_map(fn (string $key) => self::COLUMNS[$key]['label'], $this->columns);
    }

    public function collection(): Collection
    {
        return $this->pegawais
            ->sortBy(fn ($p) => ($p->units->first()->nama ?? '').' '.strtolower($p->nama_lengkap))
            ->values()
            ->map(fn (Pegawai $p) => array_map(
                fn (string $key) => $this->valueFor($key, $p),
                $this->columns,
            ));
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function valueFor(string $key, Pegawai $pegawai): mixed
    {
        return match ($key) {
            'nik' => $this->withNik ? $this->plainNik($pegawai) : $pegawai->nik_masked,
            'nuptk' => $pegawai->nuptk ?? '',
            'nama_lengkap' => $pegawai->nama_lengkap,
            'jenis_pegawai' => $pegawai->jenisPegawaiLabel(),
            'tempat_lahir' => $pegawai->tempat_lahir ?? '',
            'tanggal_lahir' => $pegawai->tanggal_lahir,
            'jenis_kelamin' => match ($pegawai->jenis_kelamin) {
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                default => $pegawai->jenis_kelamin ?? '',
            },
            'agama' => $pegawai->agama ?? '',
            'status_pernikahan' => $pegawai->status_pernikahan ?? '',
            'jumlah_tanggungan' => (int) ($pegawai->jumlah_tanggungan ?? 0),
            'units' => $pegawai->units->pluck('nama')->implode(', '),
            'jabatans' => $pegawai->jabatans->pluck('nama')->implode(', '),
            'mapels' => $pegawai->mapels->pluck('nama')->implode(', '),
            'lokasis' => $pegawai->lokasis->pluck('nama')->implode(', '),
            'atasan_langsung' => $pegawai->atasanLangsung?->nama_lengkap ?? '',
            'status_kepegawaian' => $pegawai->statusRef?->label ?? $pegawai->status_kepegawaian,
            'status_aktif' => $pegawai->status_aktif,
            'tmt_mengajar' => $pegawai->tmt_mengajar,
            'tanggal_akhir_kontrak' => $pegawai->tanggal_akhir_kontrak,
            'sk_nomor' => $pegawai->sk_nomor ?? '',
            'sk_tanggal' => $pegawai->sk_tanggal,
            'wajib_kantor' => $pegawai->wajib_kantor ? 'Ya' : 'Tidak',
            'no_hp' => $pegawai->no_hp ?? '',
            'no_hp_darurat' => $pegawai->no_hp_darurat ?? '',
            'alamat' => $pegawai->alamat ?? '',
            'email_login' => $pegawai->user?->email ?? '',
            'username' => $pegawai->user?->username ?? '',
            'pendidikan_terakhir' => $pegawai->pendidikan_terakhir ?? '',
            'pendidikan_jurusan' => $pegawai->pendidikan_jurusan ?? '',
            'pendidikan_tahun_lulus' => $pegawai->pendidikan_tahun_lulus,
            'pendidikan_asal_sekolah' => $pegawai->pendidikan_asal_sekolah ?? '',
            'nama_bank' => $pegawai->nama_bank ?? '',
            'no_rekening' => $pegawai->no_rekening ?? '',
            'npwp' => $pegawai->npwp ?? '',
            'no_bpjs_kesehatan' => $pegawai->no_bpjs_kesehatan ?? '',
            'no_bpjs_ketenagakerjaan' => $pegawai->no_bpjs_ketenagakerjaan ?? '',
            default => '',
        };
    }

    /**
     * NIK plaintext aman — sumber tunggal di Pegawai::getNikPlaintext()
     * (menangani double-encrypt legacy + decrypt error).
     */
    private function plainNik(Pegawai $pegawai): string
    {
        return $pegawai->getNikPlaintext() ?? '';
    }
}
