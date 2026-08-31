<?php

namespace App\Imports;

use App\Constants\PegawaiConstants;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PegawaiImport implements ToCollection
{
    protected $unitSekolahId;

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
     *                                   Admin unit: false, selalu dipaksa ke unitnya sendiri.
     * @param  string|null  $defaultPassword  Password seragam untuk semua user hasil import.
     *                                        Bila diisi, user wajib ganti password saat login pertama.
     *                                        Bila null, password default = NIK (per baris).
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

        // PhpSpreadsheet sometimes returns DateTime objects for date cells
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Excel serial numbers (e.g., 32874 for 1990-01-01)
        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num > 25569 && $num < 2958462) {
                // Reasonable date range: 1970-01-01 to 9999-12-31
                return Date::excelToDateTimeObject($num)->format('Y-m-d');
            }

            // Not a date serial number, return as-is (will fail date validation later)
            return (string) $value;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalize NIK: strip all non-digit chars, handle PhpSpreadsheet float→string conversion.
     * E.g., "3.20101000000000E+15" → "3201010000000000", "3201010000000000.0" → "3201010000000000".
     */
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
        // Debug: dump first 5 rows to log for troubleshooting
        Log::info('[Import] Raw rows received', [
            'count' => $rows->count(),
            'first_3' => $rows->take(3)->map(fn ($r) => collect($r)->toArray())->toArray(),
        ]);

        // Skip header row(s) — detect header by checking if first row contains header keywords
        while ($rows->isNotEmpty()) {
            $firstRow = collect($rows->first())->map(fn ($v) => strtolower(trim((string) ($v ?? ''))));
            if ($firstRow->contains('nik') || $firstRow->contains('nama lengkap') || $firstRow->contains('email')) {
                $rows->shift();
            } else {
                break;
            }
        }

        Log::info('[Import] After header skip', [
            'count' => $rows->count(),
            'first_3' => $rows->take(3)->map(fn ($r) => collect($r)->toArray())->toArray(),
        ]);

        // Convert data: normalize all values, handle dates, pad to 16 columns
        $data = $rows->map(function ($row) {
            $row = collect($row)->pad(16, null)->map(fn ($v) => $v === null ? null : (string) $v);

            // Skip completely empty rows (trailing rows in Excel)
            $nonEmpty = $row->filter(fn ($v) => $v !== null && trim($v) !== '')->values();
            if ($nonEmpty->isEmpty()) {
                return null;
            }

            // Normalize NIK to plain digits string (handles float/scientific notation)
            $row[0] = $this->normalizeNik($row[0]);

            $row[4] = $this->parseDate($row[4]); // Tanggal Lahir
            $row[11] = $this->parseDate($row[11]); // Tanggal Mulai Kerja
            $row[15] = empty(trim((string) ($row[15] ?? ''))) ? null : trim((string) $row[15]);

            return $row->toArray();
        })->filter()->values()->toArray();

        Log::info('[Import] Parsed data sample', [
            'count' => count($data),
            'first_3' => array_slice($data, 0, 3),
        ]);

        // Custom validation messages — human-readable field names
        $fieldMessages = [];
        foreach (self::COLUMN_NAMES as $idx => $name) {
            $fieldMessages["*.$idx.required"] = "{$name} wajib diisi (baris :position).";
            $fieldMessages["*.$idx.string"] = "{$name} harus berupa teks (baris :position).";
            $fieldMessages["*.$idx.max"] = "{$name} terlalu panjang (baris :position).";
            $fieldMessages["*.$idx.email"] = "{$name} format email tidak valid (baris :position).";
            $fieldMessages["*.$idx.unique"] = "{$name} sudah terdaftar dalam sistem (baris :position).";
            $fieldMessages["*.$idx.size"] = "{$name} harus tepat :size karakter (baris :position).";
            $fieldMessages["*.$idx.in"] = "{$name} harus salah satu dari: :values (baris :position).";
            $fieldMessages["*.$idx.date"] = "{$name} harus berupa tanggal yang valid (baris :position).";
            $fieldMessages["*.$idx.regex"] = "{$name} format tidak sesuai (baris :position).";
        }

        $validator = Validator::make($data, [
            '*.0' => 'required|regex:/^\d{16}$/', // NIK — regex lebih robust dari size:16
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
        ], array_merge($fieldMessages, [
            '*.0.regex' => 'NIK harus tepat 16 digit angka (baris :position).',
            '*.15.unique' => 'Email sudah terdaftar dalam sistem (baris :position).',
        ]));

        if ($validator->fails()) {
            $this->throwGroupedValidationException($validator);
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

        // Cek duplikat email dalam file.
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

        // Process all rows since validation passed
        foreach ($data as $index => $row) {
            $unitId = $this->unitSekolahId;
            if ($this->allowUnitOverride && trim((string) ($row[14] ?? '')) !== '') {
                $unitId = $units[strtolower(trim($row[14]))]->id;
            }

            if ($unitId === null) {
                throw ValidationException::withMessages(['unit_sekolah_id' => 'Tidak ada unit untuk baris '.($index + 2).'. Pilih unit di form import atau isi kolom Unit Sekolah di template.']);
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
        }
    }

    /**
     * Group validation errors by message, listing affected rows.
     * Instead of showing 100+ individual lines, group identical errors:
     *   "• NIK harus tepat 16 digit angka (baris 2, 3, 4, ...)"
     */
    private function throwGroupedValidationException($validator): void
    {
        $rawErrors = $validator->errors()->toArray();

        // Group by error message → list of row numbers
        $grouped = [];
        foreach ($rawErrors as $key => $messages) {
            foreach ($messages as $msg) {
                // Extract row index from key (format: "rowIndex.fieldIndex")
                $parts = explode('.', $key);
                $rowIndex = (int) ($parts[0] ?? 0);
                $excelRow = $rowIndex + 2; // +1 for header, +1 for 1-based

                if (! isset($grouped[$msg])) {
                    $grouped[$msg] = [];
                }
                $grouped[$msg][] = $excelRow;
            }
        }

        // Build a single readable error message
        $errorLines = [];
        foreach ($grouped as $msg => $rows) {
            // Deduplicate row numbers and sort
            $uniqueRows = array_values(array_unique($rows));
            sort($uniqueRows);

            if (count($uniqueRows) <= 5) {
                $rowStr = 'baris '.implode(', ', $uniqueRows);
            } else {
                $rowStr = 'baris '.implode(', ', array_slice($uniqueRows, 0, 5)).' ... ('.count($uniqueRows).' baris total)';
            }

            // Remove trailing " (baris X)." from the message if present, since we add our own
            $cleanMsg = preg_replace('/\s*\(baris\s+\d+\)\.?\s*$/', '', $msg);
            $errorLines[] = "• {$cleanMsg} ({$rowStr})";
        }

        $fullMessage = "Gagal import. Periksa file Anda:\n".implode("\n", $errorLines);

        Log::warning('[Import] Validation failed', [
            'total_errors' => count($rawErrors),
            'grouped_count' => count($grouped),
            'sample_errors' => array_slice($errorLines, 0, 10),
        ]);

        throw ValidationException::withMessages(['import' => $fullMessage]);
    }
}
