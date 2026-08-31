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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PegawaiImport implements ToCollection
{
    protected $unitSekolahId;

    /** Kolom yang WAJIB ada di header template pegawai. */
    protected const REQUIRED_HEADERS = ['nik', 'nama lengkap', 'email'];

    /** Kolom nama untuk pesan error yang mudah dipahami. */
    protected const COLUMN_NAMES = [
        0 => 'NIK',
        1 => 'NIP',
        2 => 'Nama Lengkap',
        3 => 'Tempat Lahir',
        4 => 'Tanggal Lahir',
        5 => 'Jenis Kelamin',
        6 => 'Agama',
        7 => 'Status Pernikahan',
        8 => 'No HP',
        9 => 'Alamat KTP',
        10 => 'Status Kepegawaian',
        11 => 'Tanggal Mulai Kerja',
        12 => 'Pendidikan Terakhir',
        13 => 'Nama Jabatan',
        14 => 'Unit Sekolah',
        15 => 'Email',
    ];

    /**
     * @param  int|null  $unitSekolahId  Unit default (dari pilihan modal).
     * @param  bool  $allowUnitOverride  Superadmin: kolom Unit di template bisa menimpa per baris.
     * @param  string|null  $defaultPassword  Password seragam untuk semua user hasil import.
     */
    public function __construct($unitSekolahId, protected bool $allowUnitOverride = false, protected ?string $defaultPassword = null)
    {
        $this->unitSekolahId = $unitSekolahId;
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num > 25569 && $num < 2958462) {
                return Date::excelToDateTimeObject($num)->format('Y-m-d');
            }

            return (string) $value;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizeNik($value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9]/', '', (string) $value);

        return $cleaned !== '' ? $cleaned : null;
    }

    public function collection(Collection $rows)
    {
        Log::warning('[Import] Raw rows received', [
            'count' => $rows->count(),
            'first_row' => $rows->isNotEmpty() ? $rows->first()->toArray() : [],
        ]);

        // === STEP 1: Validate header ===
        // Template pegawai WAJIB punya header "NIK", "Nama Lengkap", "Email".
        // Kalau tidak ada → file salah (bisa hidden sheet dropdown, atau file lain).
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'import' => 'File kosong. Silakan download template dari menu Pegawai dan isi datanya.',
            ]);
        }

        $firstRowValues = collect($rows->first())->map(fn ($v) => strtolower(trim((string) ($v ?? ''))));
        $hasNikHeader = $firstRowValues->contains('nik');
        $hasNamaHeader = $firstRowValues->contains('nama lengkap');
        $hasEmailHeader = $firstRowValues->contains('email');

        if (! $hasNikHeader && ! $hasNamaHeader && ! $hasEmailHeader) {
            // Header tidak dikenali — kemungkinan: hidden sheet dropdown, atau file bukan template
            $firstRowPreview = collect($rows->first())->filter()->values()->take(4)->implode(', ');

            Log::warning('[Import] Invalid template uploaded', [
                'first_row' => $firstRowValues->toArray(),
            ]);

            throw ValidationException::withMessages([
                'import' => "File bukan template pegawai.\n"
                    ."Kolom ditemukan: {$firstRowPreview}\n"
                    .'Silakan download template dari menu Pegawai → Download Template, isi datanya, lalu upload kembali.',
            ]);
        }

        // === STEP 2: Skip header row(s) ===
        while ($rows->isNotEmpty()) {
            $firstRow = collect($rows->first())->map(fn ($v) => strtolower(trim((string) ($v ?? ''))));
            if ($firstRow->contains('nik') || $firstRow->contains('nama lengkap') || $firstRow->contains('email')) {
                $rows->shift();
            } else {
                break;
            }
        }

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'import' => 'File hanya berisi header tanpa data pegawai. Silakan isi data terlebih dahulu.',
            ]);
        }

        Log::warning('[Import] Data after header skip', [
            'count' => $rows->count(),
        ]);

        // === STEP 3: Normalize data ===
        $data = $rows->map(function ($row) {
            $row = collect($row)->pad(16, null)->map(fn ($v) => $v === null ? null : (string) $v);

            // Skip baris kosong
            $nonEmpty = $row->filter(fn ($v) => $v !== null && trim($v) !== '')->values();
            if ($nonEmpty->isEmpty()) {
                return null;
            }

            $row[0] = $this->normalizeNik($row[0]);
            $row[4] = $this->parseDate($row[4]);
            $row[11] = $this->parseDate($row[11]);
            $row[15] = empty(trim((string) ($row[15] ?? ''))) ? null : trim((string) $row[15]);

            return $row->toArray();
        })->filter()->values()->toArray();

        if (empty($data)) {
            throw ValidationException::withMessages([
                'import' => 'Tidak ada data pegawai yang valid ditemukan di file.',
            ]);
        }

        Log::warning('[Import] Parsed data', [
            'count' => count($data),
            'sample' => array_slice($data, 0, 2),
        ]);

        // === STEP 4: Validate ===
        $validator = Validator::make($data, [
            '*.0' => 'required|regex:/^\d{16}$/',
            '*.1' => 'nullable|string|max:50|unique:pegawai,nip',
            '*.2' => 'required|string|max:255',
            '*.3' => 'required|string|max:255',
            '*.4' => 'required|date',
            '*.5' => 'required|in:L,P',
            '*.6' => 'required|string|max:255',
            '*.7' => 'required|string|max:255',
            '*.8' => 'required|string|max:20',
            '*.9' => 'required|string',
            '*.10' => 'required|in:'.implode(',', PegawaiConstants::STATUS_KEPEGAWAIAN),
            '*.11' => 'required|date',
            '*.12' => 'required|string|max:255',
            '*.13' => 'required|string|max:255',
            '*.14' => 'nullable|string|max:255',
            '*.15' => 'required|email|max:191|unique:users,email',
        ], [
            '*.0.required' => 'NIK wajib diisi.',
            '*.0.regex' => 'NIK harus tepat 16 digit angka.',
            '*.2.required' => 'Nama Lengkap wajib diisi.',
            '*.3.required' => 'Tempat Lahir wajib diisi.',
            '*.4.required' => 'Tanggal Lahir wajib diisi.',
            '*.4.date' => 'Tanggal Lahir format tidak valid.',
            '*.5.required' => 'Jenis Kelamin wajib diisi.',
            '*.5.in' => 'Jenis Kelamin harus L atau P.',
            '*.6.required' => 'Agama wajib diisi.',
            '*.7.required' => 'Status Pernikahan wajib diisi.',
            '*.8.required' => 'No HP wajib diisi.',
            '*.9.required' => 'Alamat KTP wajib diisi.',
            '*.10.required' => 'Status Kepegawaian wajib diisi.',
            '*.10.in' => 'Status Kepegawaian tidak valid.',
            '*.11.required' => 'Tanggal Mulai Kerja wajib diisi.',
            '*.11.date' => 'Tanggal Mulai Kerja format tidak valid.',
            '*.12.required' => 'Pendidikan Terakhir wajib diisi.',
            '*.13.required' => 'Nama Jabatan wajib diisi.',
            '*.15.required' => 'Email wajib diisi.',
            '*.15.email' => 'Format email tidak valid.',
            '*.15.unique' => 'Email sudah terdaftar dalam sistem.',
        ]);

        if ($validator->fails()) {
            $this->throwGroupedValidationException($validator);
        }

        // Validate jabatan
        $jabatanNames = collect($data)->pluck(13)->unique()->toArray();
        $jabatans = Jabatan::whereIn('nama', $jabatanNames)->get()->keyBy(fn ($item) => strtolower($item->nama));

        $allJabatanNames = Jabatan::orderBy('nama')->pluck('nama');
        $availableHint = $allJabatanNames->take(10)->implode(', ').($allJabatanNames->count() > 10 ? ', ...' : '');

        foreach ($data as $index => $row) {
            $namaJabatan = strtolower(trim($row[13]));
            if (! $jabatans->has($namaJabatan)) {
                $validator->errors()->add($index.'.13', "Jabatan '{$row[13]}' tidak ditemukan. Jabatan tersedia: {$availableHint}");
            }
        }

        // Validate unit
        $units = collect();
        if ($this->allowUnitOverride) {
            $unitNames = collect($data)->pluck(14)->map(fn ($v) => trim((string) $v))->filter()->unique();
            $units = UnitSekolah::whereIn('nama', $unitNames)->get()->keyBy(fn ($u) => strtolower($u->nama));
            $allUnitNames = UnitSekolah::orderBy('nama')->pluck('nama');
            $unitHint = $allUnitNames->take(10)->implode(', ').($allUnitNames->count() > 10 ? ', ...' : '');

            foreach ($data as $index => $row) {
                $unitName = strtolower(trim((string) ($row[14] ?? '')));
                if ($unitName !== '' && ! $units->has($unitName)) {
                    $validator->errors()->add($index.'.14', "Unit '{$row[14]}' tidak ditemukan. Unit tersedia: {$unitHint}");
                }
            }
        }

        // Duplicate NIK check
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

        // Duplicate email check
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
            $this->throwGroupedValidationException($validator);
        }

        // === STEP 5: Process rows ===
        $imported = 0;
        foreach ($data as $index => $row) {
            try {
                $unitId = $this->unitSekolahId;
                if ($this->allowUnitOverride && trim((string) ($row[14] ?? '')) !== '') {
                    $unitId = $units[strtolower(trim($row[14]))]->id;
                }

                if ($unitId === null) {
                    throw ValidationException::withMessages(['unit_sekolah_id' => 'Tidak ada unit untuk baris '.($index + 2).'.']);
                }

                $user = User::create([
                    'name' => $row[2],
                    'email' => $row[15],
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

                $imported++;
            } catch (\Throwable $e) {
                Log::error('[Import] Failed at row '.($index + 2).': '.$e->getMessage(), [
                    'row_data' => $row,
                    'trace' => $e->getTraceAsString(),
                ]);

                throw ValidationException::withMessages([
                    'import' => 'Gagal import baris '.($index + 2)." ({$row[2]}): ".$e->getMessage(),
                ]);
            }
        }

        Log::warning('[Import] Success', ['imported' => $imported]);
    }

    private function throwGroupedValidationException($validator): void
    {
        $rawErrors = $validator->errors()->toArray();

        $grouped = [];
        foreach ($rawErrors as $key => $messages) {
            $parts = explode('.', $key);
            $rowIndex = (int) ($parts[0] ?? 0);
            $fieldIndex = (int) ($parts[1] ?? -1);
            $excelRow = $rowIndex + 2;

            foreach ($messages as $msg) {
                $groupKey = "field_{$fieldIndex}:{$msg}";
                if (! isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [
                        'field' => self::COLUMN_NAMES[$fieldIndex] ?? "Kolom {$fieldIndex}",
                        'msg' => $msg,
                        'rows' => [],
                    ];
                }
                $grouped[$groupKey]['rows'][] = $excelRow;
            }
        }

        uksort($grouped, function ($a, $b) {
            $fieldA = (int) explode(':', $a)[0];
            $fieldB = (int) explode(':', $b)[0];

            return $fieldA <=> $fieldB;
        });

        $errorLines = [];
        foreach ($grouped as $group) {
            $uniqueRows = array_values(array_unique($group['rows']));
            sort($uniqueRows);

            if (count($uniqueRows) <= 5) {
                $rowStr = 'baris '.implode(', ', $uniqueRows);
            } elseif (count($uniqueRows) <= 10) {
                $rowStr = 'baris '.implode(', ', $uniqueRows);
            } else {
                $rowStr = 'baris '.implode(', ', array_slice($uniqueRows, 0, 5)).' ... ('.count($uniqueRows).' baris)';
            }

            $errorLines[] = "• {$group['msg']} ({$rowStr})";
        }

        $fullMessage = "Gagal import. Periksa file Anda:\n".implode("\n", $errorLines);

        throw ValidationException::withMessages(['import' => $fullMessage]);
    }
}
