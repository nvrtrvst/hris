<?php

namespace App\Imports;

use App\Constants\PegawaiConstants;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
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

    /**
     * @param  int|null  $unitSekolahId  Unit default (dari pilihan modal).
     * @param  bool  $allowUnitOverride  Superadmin: kolom Unit di template bisa menimpa per baris.
     *                                   Admin unit: false, selalu dipaksa ke unitnya sendiri.
     * @param  string|null  $defaultPassword  Password seragam untuk semua user hasil import.
     *                                        Bila diisi, user wajib ganti password saat login pertama.
     *                                        Bila null, password default = NIK (per baris).
     */
    public function __construct($unitSekolahId, protected bool $allowUnitOverride = false, protected ?string $defaultPassword = null)
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

        // Convert dates to standard format for validation.
        // CSV reader PhpSpreadsheet mengubah angka (mis. NIK) jadi integer,
        // jadi semua sel dinormalisasi ke string dulu agar validasi & penyimpanan konsisten.
        $data = $rows->map(function ($row) {
            $row = collect($row)->pad(16, null)->map(fn ($v) => $v === null ? null : (string) $v);

            // Skip baris kosong (trailing rows di Excel)
            $nonEmpty = $row->filter(fn ($v) => $v !== null && $v !== '')->values();
            if ($nonEmpty->isEmpty()) {
                return null;
            }

            $row[4] = $this->parseDate($row[4]); // Tanggal Lahir
            $row[11] = $this->parseDate($row[11]); // Tanggal Mulai Kerja
            $row[15] = empty(trim((string) ($row[15] ?? ''))) ? null : trim((string) $row[15]);

            return $row->toArray();
        })->filter()->values()->toArray();

        $validator = Validator::make($data, [
            '*.0' => 'required|string|size:16', // NIK
            '*.1' => 'nullable|string|max:50|unique:pegawai,nip', // NIP
            '*.2' => 'required|string|max:255', // Nama Lengkap
            '*.3' => 'required|string|max:255', // Tempat Lahir
            '*.4' => 'required|date', // Tanggal Lahir
            '*.5' => 'required|in:L,P', // Jenis Kelamin
            '*.6' => 'required|string|max:255', // Agama
            '*.7' => 'required|string|max:255', // Status Pernikahan
            '*.8' => 'required|string|max:20', // No HP
            '*.9' => 'required|string', // Alamat KTP
            '*.10' => 'required|in:'.implode(',', PegawaiConstants::STATUS_KEPEGAWAIAN), // Status Kepegawaian
            '*.11' => 'required|date', // Tanggal Mulai Kerja
            '*.12' => 'required|string|max:255', // Pendidikan Terakhir
            '*.13' => 'required|string|max:255', // Nama Jabatan
            '*.14' => 'nullable|string|max:255', // Unit Sekolah (opsional, fallback ke unit modal)
            '*.15' => 'required|email|max:191|unique:users,email', // Email (wajib — login)
        ], [
            '*.0.required' => 'NIK wajib diisi pada baris :position.',
            '*.0.size' => 'NIK harus 16 digit pada baris :position.',
            '*.13.required' => 'Nama Jabatan wajib diisi pada baris :position.',
            '*.15.required' => 'Email wajib diisi pada baris :position.',
            '*.15.email' => 'Format email tidak valid pada baris :position.',
            '*.15.unique' => 'Email sudah terdaftar pada baris :position.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate that the jabatan exists
        $jabatanNames = collect($data)->pluck(13)->unique()->toArray();
        $jabatans = Jabatan::whereIn('nama', $jabatanNames)->get()->keyBy(function ($item) {
            return strtolower($item->nama);
        });

        $allJabatanNames = Jabatan::orderBy('nama')->pluck('nama');
        $availableHint = $allJabatanNames->take(10)->implode(', ').($allJabatanNames->count() > 10 ? ', ...' : '');

        foreach ($data as $index => $row) {
            $namaJabatan = strtolower(trim($row[13]));
            if (! $jabatans->has($namaJabatan)) {
                $validator->errors()->add($index.'.13', "Jabatan '{$row[13]}' tidak ditemukan dalam sistem pada baris ".($index + 2).'. Jabatan yang tersedia: '.$availableHint);
            }
        }

        // Validasi unit per baris (opsional): kalau diisi, harus ada di sistem.
        // Admin unit dipaksa ke unitnya sendiri — kolom unit diabaikan (anti-bypass).
        $units = collect();
        if ($this->allowUnitOverride) {
            $unitNames = collect($data)->pluck(14)->map(fn ($v) => trim((string) $v))->filter()->unique();
            $units = UnitSekolah::whereIn('nama', $unitNames)->get()->keyBy(fn ($u) => strtolower($u->nama));
            $allUnitNames = UnitSekolah::orderBy('nama')->pluck('nama');
            $unitHint = $allUnitNames->take(10)->implode(', ').($allUnitNames->count() > 10 ? ', ...' : '');

            foreach ($data as $index => $row) {
                $unitName = strtolower(trim((string) ($row[14] ?? '')));
                if ($unitName !== '' && ! $units->has($unitName)) {
                    $validator->errors()->add($index.'.14', "Unit '{$row[14]}' tidak ditemukan dalam sistem pada baris ".($index + 2).'. Unit yang tersedia: '.$unitHint);
                }
            }
        }

        // Cek duplikat NIK terhadap DB & dalam file.
        // Kolom `nik` terenkripsi jadi `unique:pegawai,nik` tidak bisa dipakai;
        // lookup memakai nik_hash (satu-satunya sumber kebenaran).
        $nikHashes = collect($data)->pluck(0)->map(fn ($nik) => Pegawai::nikHash((string) $nik))->filter();
        $existingHashes = Pegawai::whereIn('nik_hash', $nikHashes)->pluck('nik_hash')->flip();
        $seen = [];
        foreach ($data as $index => $row) {
            $hash = Pegawai::nikHash((string) $row[0]);
            if ($hash !== null && ($existingHashes->has($hash) || isset($seen[$hash]))) {
                $validator->errors()->add($index.'.0', 'NIK sudah terdaftar pada baris '.($index + 2).'.');
            }
            $seen[$hash] = true;
        }

        // Cek duplikat email dalam file. Rule `unique:users,email` hanya memeriksa DB,
        // bukan antar-baris dalam satu batch — dedup manual cegah 500 saat insert kedua.
        $seenEmails = [];
        foreach ($data as $index => $row) {
            $email = $row[15];
            if ($email === null) {
                continue;
            }
            $key = strtolower($email);
            if (isset($seenEmails[$key])) {
                $validator->errors()->add($index.'.15', 'Email sudah dipakai pada baris '.($seenEmails[$key] + 2).'.');
            } else {
                $seenEmails[$key] = $index;
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        // Process all rows since validation passed
        foreach ($data as $index => $row) {
            $unitId = $this->unitSekolahId;
            if ($this->allowUnitOverride && trim((string) ($row[14] ?? '')) !== '') {
                $unitId = $units[strtolower(trim($row[14]))]->id;
            }

            if ($unitId === null) {
                throw ValidationException::withMessages(['unit_sekolah_id' => 'Tidak ada unit untuk baris '.($index + 2).'. Pilih unit di form import atau isi kolom Unit Sekolah di template.']);
            }

            // Password default: seragam (bila diisi) atau NIK per baris.
            // Bila password seragam dipakai, paksa ganti saat login pertama (keamanan).
            $user = User::create([
                'name' => $row[2],
                'email' => $row[15], // wajib & unik (divalidasi di atas)
                'password' => Hash::make($this->defaultPassword ?? $row[0]),
                'role' => 'pegawai',
                'unit_sekolah_id' => $unitId,
                'force_password_change' => $this->defaultPassword !== null,
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

            $pegawai->units()->attach($unitId, ['jabatan_id' => $jabatanId, 'is_primary' => true]);
        }
    }
}
