<?php

namespace App\Imports;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PegawaiImport implements ToCollection
{
    protected $unitSekolahId;

    public function __construct($unitSekolahId)
    {
        $this->unitSekolahId = $unitSekolahId;
    }

    private function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function collection(Collection $rows)
    {
        // Skip header row
        $rows->shift();

        // Convert dates to standard format for validation
        $data = $rows->map(function ($row) {
            $row[4] = $this->parseDate($row[4]); // Tanggal Lahir
            $row[11] = $this->parseDate($row[11]); // Tanggal Mulai Kerja

            return $row;
        })->toArray();

        $validator = Validator::make($data, [
            '*.0' => 'required|string|size:16|unique:pegawai,nik', // NIK
            '*.1' => 'nullable|string|max:50|unique:pegawai,nip', // NIP
            '*.2' => 'required|string|max:255', // Nama Lengkap
            '*.3' => 'required|string|max:255', // Tempat Lahir
            '*.4' => 'required|date', // Tanggal Lahir
            '*.5' => 'required|in:L,P', // Jenis Kelamin
            '*.6' => 'required|string|max:255', // Agama
            '*.7' => 'required|string|max:255', // Status Pernikahan
            '*.8' => 'required|string|max:20', // No HP
            '*.9' => 'required|string', // Alamat KTP
            '*.10' => 'required|in:tetap,kontrak,honorer,gtt', // Status Kepegawaian
            '*.11' => 'required|date', // Tanggal Mulai Kerja
            '*.12' => 'required|string|max:255', // Pendidikan Terakhir
            '*.13' => 'required|string|max:255', // Nama Jabatan
        ], [
            '*.0.required' => 'NIK wajib diisi pada baris :position.',
            '*.0.size' => 'NIK harus 16 digit pada baris :position.',
            '*.0.unique' => 'NIK sudah terdaftar pada baris :position.',
            '*.13.required' => 'Nama Jabatan wajib diisi pada baris :position.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate that the jabatan exists
        $jabatanNames = collect($data)->pluck(13)->unique()->toArray();
        $jabatans = Jabatan::whereIn('nama', $jabatanNames)->get()->keyBy(function ($item) {
            return strtolower($item->nama);
        });

        foreach ($data as $index => $row) {
            $namaJabatan = strtolower(trim($row[13]));
            if (! $jabatans->has($namaJabatan)) {
                $validator->errors()->add($index.'.13', "Jabatan '{$row[13]}' tidak ditemukan dalam sistem pada baris ".($index + 2));
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        // Process all rows since validation passed
        foreach ($data as $row) {
            $user = User::create([
                'name' => $row[2],
                'email' => $row[0].'@yayasan.com', // email berbasis NIK pasti unik
                'password' => Hash::make($row[0]), // password default is NIK
                'role' => 'pegawai',
                'unit_sekolah_id' => $this->unitSekolahId,
            ]);
            $user->assignRole('pegawai');

            $pegawai = Pegawai::create([
                'user_id' => $user->id,
                'nik' => $row[0],
                'nip' => $row[1],
                'nama_lengkap' => $row[2],
                'tempat_lahir' => $row[3],
                'tanggal_lahir' => $row[4],
                'jenis_kelamin' => $row[5],
                'agama' => $row[6],
                'status_pernikahan' => $row[7],
                'no_hp' => $row[8],
                'alamat_ktp' => $row[9],
                'status_kepegawaian' => $row[10],
                'tanggal_mulai_kerja' => $row[11],
                'pendidikan_terakhir' => $row[12],
                'status_aktif' => 'aktif',
                'jumlah_tanggungan' => 0,
            ]);

            $jabatanId = $jabatans[strtolower(trim($row[13]))]->id;

            $pegawai->units()->attach($this->unitSekolahId, ['jabatan_id' => $jabatanId, 'is_primary' => true]);
        }
    }
}
