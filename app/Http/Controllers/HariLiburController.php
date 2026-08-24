<?php

namespace App\Http\Controllers;

use App\Models\HariLibur;
use App\Models\UnitSekolah;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class HariLiburController extends Controller
{
    /**
     * Daftar hari libur (nasional + per-unit) dengan filter tahun & unit.
     */
    public function index(Request $request)
    {
        $query = HariLibur::query()->with('unitSekolah');

        if ($request->filled('year')) {
            $query->whereYear('tanggal', $request->integer('year'));
        }
        if ($request->filled('unit_sekolah_id')) {
            $uid = $request->integer('unit_sekolah_id');
            $query->where(function ($q) use ($uid) {
                $q->whereNull('unit_sekolah_id')->orWhere('unit_sekolah_id', $uid);
            });
        }

        $holidays = $query->orderBy('tanggal')->paginate(50)->withQueryString();

        $units = UnitSekolah::orderBy('nama')->get(['id', 'nama']);
        $years = range(2024, 2030);

        return Inertia::render('HariLibur/Index', [
            'holidays' => $holidays,
            'units' => $units,
            'years' => $years,
            'filters' => [
                'year' => $request->input('year'),
                'unit_sekolah_id' => $request->input('unit_sekolah_id'),
            ],
        ]);
    }

    /**
     * Simpan hari libur baru. Idempoten per (tanggal, unit) agar aman dari double-submit.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date', 'max:10'],
            'nama' => ['required', 'string', 'max:191'],
            'unit_sekolah_id' => ['nullable', 'exists:unit_sekolah,id'],
            'tipe' => ['required', 'in:nasional,cuti_bersama,sekolah,lainnya'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->authorizeMaster();

        $unitId = $validated['unit_sekolah_id'] ? (int) $validated['unit_sekolah_id'] : null;

        // Cegah duplikat (race) via unique key (tanggal, unit_sekolah_id).
        HariLibur::updateOrCreate(
            ['tanggal' => $validated['tanggal'], 'unit_sekolah_id' => $unitId],
            [
                'nama' => $validated['nama'],
                'tipe' => $validated['tipe'],
                'keterangan' => $validated['keterangan'] ?? null,
            ]
        );

        return redirect()->back()->with('message', 'Hari libur berhasil disimpan.');
    }

    /**
     * Update hari libur.
     */
    public function update(Request $request, HariLibur $hariLibur)
    {
        $this->authorizeMaster();

        $validated = $request->validate([
            'tanggal' => ['required', 'date', 'max:10'],
            'nama' => ['required', 'string', 'max:191'],
            'unit_sekolah_id' => ['nullable', 'exists:unit_sekolah,id'],
            'tipe' => ['required', 'in:nasional,cuti_bersama,sekolah,lainnya'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $unitId = $validated['unit_sekolah_id'] ? (int) $validated['unit_sekolah_id'] : null;

        // Jika tanggal/unit diubah sehingga bentrok dengan baris lain, tangani duplikat.
        $existing = HariLibur::where('tanggal', $validated['tanggal'])
            ->where('unit_sekolah_id', $unitId)
            ->where('id', '<>', $hariLibur->id)
            ->first();

        if ($existing) {
            // Gabungkan: pindahkan nilai ke baris existing, hapus yang lama.
            $existing->update([
                'nama' => $validated['nama'],
                'tipe' => $validated['tipe'],
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
            $hariLibur->delete();

            return redirect()->back()->with('message', 'Hari libur digabung dengan entri yang sudah ada.');
        }

        $hariLibur->update([
            'tanggal' => $validated['tanggal'],
            'nama' => $validated['nama'],
            'unit_sekolah_id' => $unitId,
            'tipe' => $validated['tipe'],
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

        return redirect()->back()->with('message', 'Hari libur berhasil diperbarui.');
    }

    /**
     * Hapus hari libur.
     */
    public function destroy(HariLibur $hariLibur)
    {
        $this->authorizeMaster();

        $hariLibur->delete();

        return redirect()->back()->with('message', 'Hari libur berhasil dihapus.');
    }

    /**
     * Import hari libur nasional dari bundled JSON lokal (offline-safe).
     * Source of truth = database; JSON hanya cadangan/seed awal.
     */
    public function importLokal(Request $request)
    {
        $this->authorizeMaster();

        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2024,2035'],
        ]);

        $path = database_path('seeders/data/hari_libur_nasional.json');
        if (! File::exists($path)) {
            return redirect()->back()->with('error', 'Berkas data hari libur lokal tidak ditemukan.');
        }

        $data = json_decode(File::get($path), true);
        if (! is_array($data)) {
            return redirect()->back()->with('error', 'Berkas data hari libur lokal rusak.');
        }

        $year = (int) $validated['year'];
        $imported = 0;

        DB::transaction(function () use ($data, $year, &$imported) {
            foreach ($data as $row) {
                if (($row['tanggal'] ?? '') === '' || (int) substr((string) $row['tanggal'], 0, 4) !== $year) {
                    continue;
                }
                // Validasi defensif terhadap data bundled (jangan percaya file).
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $row['tanggal'])) {
                    continue;
                }
                HariLibur::updateOrCreate(
                    ['tanggal' => $row['tanggal'], 'unit_sekolah_id' => null],
                    [
                        'nama' => substr((string) ($row['nama'] ?? 'Hari Libur'), 0, 191),
                        'tipe' => in_array($row['tipe'] ?? '', ['nasional', 'cuti_bersama', 'sekolah', 'lainnya'])
                            ? $row['tipe'] : 'nasional',
                    ]
                );
                $imported++;
            }
        });

        return redirect()->back()->with('message', "Import hari libur nasional $year: $imported entri diproses.");
    }

    /**
     * Sync hari libur nasional dari API publik (indonesia-holiday-api).
     * Data eksternal TIDAK dipercaya: divalidasi sebelum disimpan.
     */
    public function syncApi(Request $request)
    {
        $this->authorizeMaster();

        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2024,2035'],
        ]);

        $year = (int) $validated['year'];
        $url = config('hris.harilibur_api_url', 'https://indonesia-holiday-api.vercel.app/api?year=').$year;

        try {
            $response = Http::timeout(15)->get($url);
        } catch (ConnectionException $e) {
            Log::warning('Sync hari libur gagal: '.$e->getMessage());

            return redirect()->back()->with('error', 'Gagal menghubungi API hari libur. Periksa koneksi server atau gunakan Import Lokal.');
        }

        if (! $response->successful()) {
            return redirect()->back()->with('error', 'API hari libur mengembalikan status '.$response->status().'.');
        }

        $rows = $response->json();
        if (! is_array($rows)) {
            return redirect()->back()->with('error', 'Format respons API hari libur tidak dikenali.');
        }

        $imported = 0;
        DB::transaction(function () use ($rows, $year, &$imported) {
            foreach ($rows as $row) {
                $date = (string) ($row['holiday_date'] ?? '');
                // Hanya tahun yang diminta & format tanggal valid (anti manipulasi).
                if ((int) substr($date, 0, 4) !== $year) {
                    continue;
                }
                $dt = \DateTime::createFromFormat('Y-m-d', $date);
                if (! $dt || $dt->format('Y-m-d') !== $date) {
                    continue;
                }
                $name = substr((string) ($row['holiday_name'] ?? 'Hari Libur'), 0, 191);
                $tipe = ! empty($row['is_joint_holiday']) ? 'cuti_bersama' : 'nasional';

                HariLibur::updateOrCreate(
                    ['tanggal' => $date, 'unit_sekolah_id' => null],
                    ['nama' => $name, 'tipe' => $tipe]
                );
                $imported++;
            }
        });

        return redirect()->back()->with('message', "Sync hari libur nasional $year dari API: $imported entri diproses.");
    }

    /**
     * Defense-in-depth: pastikan hanya pemilik izin manage_master_data yang boleh mutasi.
     */
    private function authorizeMaster(): void
    {
        abort_unless(request()->user()?->can('manage_master_data'), 403, 'Akses ditolak.');
    }
}
