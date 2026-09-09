<?php

namespace App\Imports;

use App\Constants\PegawaiConstants;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\StatusKepegawaian;
use App\Models\UnitSekolah;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import pegawai dari template Excel (format GTYS Yayasan, 17 kolom A–Q).
 *
 * Kolom 4 (JENJANG / JURUSAN) di-parse dengan delimiter " / ".
 * Jika hanya ada satu token → pendidikan_jurusan NULL.
 * Lookup dropdown Jabatan, Unit via tabel master (case-insensitive).
 *
 * Tidak ada NIK/Agama/Status Pernikahan di template — field itu
 * diisi belakangan via Edit Page.
 *
 * Validasi SELURUH file dalam satu Validator (semua error dikumpulkan,
 * tidak throw di tengah jalan) — admin bisa perbaiki semua error sekaligus.
 */
class PegawaiImport implements ToCollection
{
    protected $unitSekolahId;

    /** Kolom nama untuk pesan error yang mudah dipahami. */
    protected const COLUMN_NAMES = [
        0 => 'NAMA',
        1 => 'TEMPAT LAHIR',
        2 => 'TANGGAL LAHIR',
        3 => 'JENIS KELAMIN',
        4 => 'JENJANG / JURUSAN',
        5 => 'TAHUN LULUS',
        6 => 'ASAL SEKOLAH / PERGURUAN TINGGI',
        7 => 'NOMOR SK',
        8 => 'TANGGAL SK',
        9 => 'TMT MENGAJAR',
        10 => 'JABATAN',
        11 => 'NUPTK',
        12 => 'ALAMAT',
        13 => 'EMAIL',
        14 => 'KONTAK',
        15 => 'STATUS',
        16 => 'UNIT SEKOLAH',
    ];

    /** Lookup Jabatan di-prefetch sekali (di collection()) — bukan per-row. */
    protected array $jabatanByName = [];

    /** Lookup Unit di-prefetch sekali (superadmin) — bukan per-row. */
    protected array $unitByName = [];

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

    /**
     * Parse "JENJANG / JURUSAN" → [pendidikan_terakhir, pendidikan_jurusan].
     * Lookup pendidikan_terakhir ke PegawaiConstants::PENDIDIKAN_TERAKHIR
     * (case-insensitive, trim). Jika token pertama tidak cocok dengan
     * daftar valid → return [raw_token, null] agar Validator tangkap di
     * `in:` rule. Jika tidak ada delimiter → [token, null].
     */
    private function parseJenjangJurusan(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [null, null];
        }

        if (str_contains($raw, '/')) {
            [$jenjang, $jurusan] = array_map('trim', explode('/', $raw, 2));
        } else {
            $jenjang = $raw;
            $jurusan = null;
        }

        // Validasi jenjang ada di PENDIDIKAN_TERAKHIR.
        $valid = PegawaiConstants::PENDIDIKAN_TERAKHIR;
        $match = collect($valid)->first(
            fn ($p) => strcasecmp($p, $jenjang) === 0
        );

        // Jika tidak match → return null di slot jenjang agar Validator
        // tangkap dengan pesan jelas (bukan value mentah yang lolos).
        return [$match !== null ? $match : null, $jurusan !== '' ? $jurusan : null];
    }

    private function parseYear($value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (is_numeric($value)) {
            $year = (int) $value;
            if ($year >= 1900 && $year <= 2100) {
                return $year;
            }
        }

        try {
            $year = (int) Carbon::parse((string) $value)->format('Y');
            if ($year >= 1900 && $year <= 2100) {
                return $year;
            }
        } catch (\Exception $e) {
            // fall through
        }

        return null;
    }

    public function collection(Collection $rows)
    {
        Log::debug('[Import] Raw rows received', [
            'count' => $rows->count(),
        ]);

        // === STEP 1: Validate header ===
        // Template pegawai WAJIB punya header "NAMA", "EMAIL", "JABATAN".
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'import' => 'File kosong. Silakan download template dari menu Pegawai dan isi datanya.',
            ]);
        }

        $firstRowValues = collect($rows->first())->map(fn ($v) => strtolower(trim((string) ($v ?? ''))));
        $hasNamaHeader = $firstRowValues->contains('nama');
        $hasEmailHeader = $firstRowValues->contains(fn ($v) => str_contains($v, 'email'));
        $hasJabatanHeader = $firstRowValues->contains('jabatan');

        if (! $hasNamaHeader && ! $hasEmailHeader && ! $hasJabatanHeader) {
            $firstRowPreview = collect($rows->first())->filter()->values()->take(4)->implode(', ');
            Log::warning('[Import] Invalid template uploaded');

            throw ValidationException::withMessages([
                'import' => "File bukan template pegawai.\n"
                    ."Kolom ditemukan: {$firstRowPreview}\n"
                    .'Silakan download template dari menu Pegawai → Download Template, isi datanya, lalu upload kembali.',
            ]);
        }

        // === STEP 2: Skip header row(s) ===
        while ($rows->isNotEmpty()) {
            $firstRow = collect($rows->first())->map(fn ($v) => strtolower(trim((string) ($v ?? ''))));
            if ($firstRow->contains('nama') || $firstRow->contains('email') || $firstRow->contains('jabatan')) {
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

        // === STEP 3: Normalize data ===
        $data = $rows->map(function ($row) {
            // Pad ke 17 kolom (index 0..16).
            $row = collect($row)->pad(17, null)->map(fn ($v) => $v === null ? null : (string) $v);

            // Skip baris kosong.
            $nonEmpty = $row->filter(fn ($v) => $v !== null && trim($v) !== '')->values();
            if ($nonEmpty->isEmpty()) {
                return null;
            }

            // Parse JENJANG/JURUSAN → [pendidikan_terakhir, pendidikan_jurusan].
            // Disimpan sebagai array sementara di index 4, dipecah di langkah insert.
            [$jenjang, $jurusan] = $this->parseJenjangJurusan((string) ($row[4] ?? ''));
            $row[4] = $jenjang;
            $row['_jurusan'] = $jurusan;

            // TAHUN LULUS (numeric year).
            $row[5] = $this->parseYear($row[5]);

            // TANGGAL (string 'YYYY-MM-DD').
            $row[2] = $this->parseDate($row[2]);
            $row[8] = $this->parseDate($row[8]);
            $row[9] = $this->parseDate($row[9]);

            // NUPTK, kontak, email → trim.
            $row[11] = empty(trim((string) ($row[11] ?? ''))) ? null : trim((string) $row[11]);
            $row[13] = empty(trim((string) ($row[13] ?? ''))) ? null : trim((string) $row[13]);
            $row[14] = empty(trim((string) ($row[14] ?? ''))) ? null : trim((string) $row[14]);
            $row[15] = empty(trim((string) ($row[15] ?? ''))) ? null : trim((string) $row[15]);
            $row[16] = empty(trim((string) ($row[16] ?? ''))) ? null : trim((string) $row[16]);

            return $row->toArray();
        })->filter()->values()->toArray();

        if (empty($data)) {
            throw ValidationException::withMessages([
                'import' => 'Tidak ada data pegawai yang valid ditemukan di file.',
            ]);
        }

        Log::debug('[Import] Parsed data', ['count' => count($data)]);

        // === STEP 4: Validate (semua baris dalam satu Validator) ===
        $validator = Validator::make($data, [
            '*.0' => 'required|string|max:255',
            '*.1' => 'required|string|max:255',
            '*.2' => 'required|date',
            '*.3' => 'required|in:L,P',
            '*.4' => 'required|in:'.implode(',', PegawaiConstants::PENDIDIKAN_TERAKHIR),
            '*.5' => 'nullable|integer|min:1900|max:2100',
            '*.6' => 'nullable|string|max:255',
            '*.7' => 'nullable|string|max:255',
            '*.8' => 'nullable|date',
            '*.9' => 'required|date',
            '*.10' => 'required|string|max:255',
            '*.11' => 'nullable|string|max:50|unique:pegawai,nuptk',
            '*.12' => 'required|string',
            '*.13' => 'required|email|max:191|unique:users,email',
            '*.14' => 'required|string|max:20',
            '*.15' => 'required|in:'.implode(',', StatusKepegawaian::activeKodes()),
            '*.16' => 'nullable|string|max:255',
        ], [
            '*.0.required' => 'Nama Lengkap wajib diisi.',
            '*.1.required' => 'Tempat Lahir wajib diisi.',
            '*.2.required' => 'Tanggal Lahir wajib diisi.',
            '*.2.date' => 'Tanggal Lahir format tidak valid.',
            '*.3.required' => 'Jenis Kelamin wajib diisi.',
            '*.3.in' => 'Jenis Kelamin harus L atau P.',
            '*.4.required' => 'Jenjang (kolom JENJANG / JURUSAN) wajib diisi.',
            '*.4.in' => 'Jenjang tidak valid. Pilih dari dropdown di template.',
            '*.5.integer' => 'Tahun Lulus harus angka (YYYY).',
            '*.5.min' => 'Tahun Lulus minimal 1900.',
            '*.5.max' => 'Tahun Lulus maksimal 2100.',
            '*.8.date' => 'Tanggal SK format tidak valid.',
            '*.9.required' => 'TMT Mengajar wajib diisi.',
            '*.9.date' => 'TMT Mengajar format tidak valid.',
            '*.10.required' => 'Nama Jabatan wajib diisi.',
            '*.11.unique' => 'NUPTK sudah terdaftar di sistem.',
            '*.12.required' => 'Alamat wajib diisi.',
            '*.13.required' => 'Email wajib diisi.',
            '*.13.email' => 'Format email tidak valid.',
            '*.13.unique' => 'Email sudah terdaftar dalam sistem.',
            '*.14.required' => 'Kontak wajib diisi.',
            '*.15.required' => 'Status wajib diisi.',
            '*.15.in' => 'Status Kepegawaian tidak valid.',
        ]);

        // Prefetch Jabatan (sebelum validasi custom agar tahu daftar).
        $jabatanNames = collect($data)->pluck(10)->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
        $this->jabatanByName = Jabatan::whereIn('nama', $jabatanNames)
            ->get()
            ->keyBy(fn ($item) => strtolower($item->nama))
            ->all();

        $allJabatanNames = Jabatan::orderBy('nama')->pluck('nama');
        $availableHint = $allJabatanNames->take(10)->implode(', ').($allJabatanNames->count() > 10 ? ', ...' : '');

        foreach ($data as $index => $row) {
            $namaJabatan = strtolower(trim((string) ($row[10] ?? '')));
            if ($namaJabatan !== '' && ! isset($this->jabatanByName[$namaJabatan])) {
                $validator->errors()->add($index.'.10', "Jabatan '{$row[10]}' tidak ditemukan. Jabatan yang tersedia: {$availableHint}");
            }
        }

        // Prefetch Unit (hanya jika superadmin).
        if ($this->allowUnitOverride) {
            $unitNames = collect($data)->pluck(16)->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
            $this->unitByName = UnitSekolah::whereIn('nama', $unitNames)
                ->get()
                ->keyBy(fn ($u) => strtolower($u->nama))
                ->all();

            $allUnitNames = UnitSekolah::orderBy('nama')->pluck('nama');
            $unitHint = $allUnitNames->take(10)->implode(', ').($allUnitNames->count() > 10 ? ', ...' : '');

            foreach ($data as $index => $row) {
                $unitName = strtolower(trim((string) ($row[16] ?? '')));
                if ($unitName !== '' && ! isset($this->unitByName[$unitName])) {
                    $validator->errors()->add($index.'.16', "Unit '{$row[16]}' tidak ditemukan. Unit yang tersedia: {$unitHint}");
                }
            }
        }

        // Duplicate email check (across rows).
        $seenEmails = [];
        foreach ($data as $index => $row) {
            $email = $row[13] ?? null;
            if ($email === null) {
                continue;
            }
            $key = strtolower($email);
            if (isset($seenEmails[$key])) {
                $validator->errors()->add($index.'.13', 'Email sudah dipakai pada baris '.($seenEmails[$key] + 2).'.');
            } else {
                $seenEmails[$key] = $index;
            }
        }

        // Duplicate NUPTK check (across rows).
        $seenNuptk = [];
        foreach ($data as $index => $row) {
            $nuptk = $row[11] ?? null;
            if ($nuptk === null) {
                continue;
            }
            if (isset($seenNuptk[$nuptk])) {
                $validator->errors()->add($index.'.11', 'NUPTK sudah dipakai pada baris '.($seenNuptk[$nuptk] + 2).'.');
            } else {
                $seenNuptk[$nuptk] = $index;
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
                if ($this->allowUnitOverride && trim((string) ($row[16] ?? '')) !== '') {
                    $unitId = $this->unitByName[strtolower(trim($row[16]))]->id;
                }

                if ($unitId === null) {
                    throw ValidationException::withMessages(['unit_sekolah_id' => 'Tidak ada unit untuk baris '.($index + 2).'.']);
                }

                $username = ! empty($row[11]) ? $row[11] : explode('@', $row[13])[0];
                // password: default seragam > NUPTK > random.
                $password = $this->defaultPassword ?? ($row[11] ?: Str::random(12));

                $user = User::create([
                    'name' => $row[0],
                    'email' => $row[13],
                    'username' => $username,
                    'password' => Hash::make($password),
                    'role' => 'pegawai',
                    'unit_sekolah_id' => $unitId,
                    'force_password_change' => $this->defaultPassword !== null,
                ]);
                $user->assignRole('pegawai');

                $pegawai = Pegawai::create([
                    'user_id' => $user->id,
                    'nama_lengkap' => $row[0],
                    'tempat_lahir' => $row[1],
                    'tanggal_lahir' => $row[2],
                    'jenis_kelamin' => $row[3],
                    'pendidikan_terakhir' => $row[4],
                    'pendidikan_jurusan' => $row['_jurusan'] ?? null,
                    'pendidikan_tahun_lulus' => $row[5],
                    'pendidikan_asal_sekolah' => $row[6],
                    'sk_nomor' => $row[7],
                    'sk_tanggal' => $row[8],
                    'tmt_mengajar' => $row[9],
                    'nuptk' => $row[11],
                    'alamat' => $row[12],
                    'email' => $row[13],
                    'no_hp' => $row[14],
                    'status_kepegawaian' => $row[15],
                    'status_aktif' => 'aktif',
                    'jumlah_tanggungan' => 0,
                ]);

                $jabatanId = $this->jabatanByName[strtolower(trim($row[10]))]->id;
                $pegawai->units()->attach($unitId, ['jabatan_id' => $jabatanId, 'is_primary' => true]);

                // Update username User kalau berbeda dengan default.
                if ($user->username !== $username) {
                    $user->username = $username;
                    $user->save();
                }

                $imported++;
            } catch (\Throwable $e) {
                Log::error('[Import] Failed at row '.($index + 2).': '.$e->getMessage(), [
                    'row_data' => $row,
                    'trace' => $e->getTraceAsString(),
                ]);

                throw ValidationException::withMessages([
                    'import' => 'Gagal import baris '.($index + 2)." ({$row[0]}): ".$e->getMessage(),
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

            if (count($uniqueRows) <= 10) {
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
