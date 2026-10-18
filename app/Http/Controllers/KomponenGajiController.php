<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use App\Models\KomponenGaji;
use App\Models\UnitSekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KomponenGajiController extends Controller
{
    public function index()
    {
        $komponens = KomponenGaji::orderBy('urutan', 'asc')->get();
        $units = UnitSekolah::orderBy('nama')->get();
        $jabatans = Jabatan::orderBy('nama')->get(['id', 'nama']);
        $jabatanMappings = DB::table('komponen_jabatan')->get(['komponen_gaji_id', 'jabatan_id', 'nominal'])
            ->groupBy('komponen_gaji_id');

        return inertia('Payroll/Komponen', [
            'komponens' => $komponens,
            'units' => $units,
            'jabatans' => $jabatans,
            'jabatanMappings' => $jabatanMappings,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string',
            'kode' => 'nullable|string|max:50',
            'tipe' => 'required|in:pendapatan,potongan',
            'jenis' => 'required|in:fixed,persentase,dinamis_kehadiran,dinamis_jam_mengajar,dinamis_masa_bakti,dinamis_lembur,dinamis_jabatan,dinamis_piket,dinamis_ekskul,lookup_reference',
            'payroll_reference_type_id' => [
                'nullable',
                'exists:payroll_reference_types,id',
                Rule::requiredIf(fn () => $request->input('jenis') === 'lookup_reference'),
            ],
            'mata_pelajaran_id' => [
                'nullable',
                'exists:mata_pelajaran,id',
                Rule::requiredIf(fn () => $request->input('jenis') === 'dinamis_ekskul'),
            ],
            'applies_to_status_kepegawaian' => 'nullable|in:tetap,honorer,guru,non_guru',
            'syarat_bayar_jam_mengajar' => 'nullable|in:semua_jadwal,hanya_hadir',
            'persentase_base' => 'nullable|in:taxable,gaji_pokok,total_pendapatan',
            'nilai_default' => 'nullable|numeric',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'urutan' => 'nullable|integer',
            'tampil_di_matrix' => 'boolean',
            'jabatan_nominals' => 'nullable|array',
            'jabatan_nominals.*.jabatan_id' => 'required|exists:jabatan,id',
            'jabatan_nominals.*.nominal' => 'required|numeric|min:0',
        ]);

        if (($validated['jenis'] ?? null) !== 'lookup_reference') {
            $validated['payroll_reference_type_id'] = null;
        }
        if (($validated['jenis'] ?? null) !== 'dinamis_ekskul') {
            $validated['mata_pelajaran_id'] = null;
        }

        $validated['is_taxable'] = $request->has('is_taxable') ? $request->is_taxable : true;
        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;
        $validated['tampil_di_matrix'] = $request->has('tampil_di_matrix') ? $request->tampil_di_matrix : true;
        if (! isset($validated['urutan'])) {
            $validated['urutan'] = 99;
        }

        $touchedMapping = $request->exists('jabatan_nominals');
        $jabatanNominals = $validated['jabatan_nominals'] ?? [];
        unset($validated['jabatan_nominals']);

        $komponen = KomponenGaji::create($validated);
        $this->syncJabatanNominals($komponen->id, $validated['jenis'] ?? null, $jabatanNominals, $touchedMapping);

        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji berhasil ditambah.');
    }

    public function update(Request $request, $id)
    {
        $komponen = KomponenGaji::findOrFail($id);
        $validated = $request->validate([
            'nama' => 'required|string',
            'kode' => 'nullable|string|max:50',
            'tipe' => 'required|in:pendapatan,potongan',
            'jenis' => 'required|in:fixed,persentase,dinamis_kehadiran,dinamis_jam_mengajar,dinamis_masa_bakti,dinamis_lembur,dinamis_jabatan,dinamis_piket,dinamis_ekskul,lookup_reference',
            'payroll_reference_type_id' => [
                'nullable',
                'exists:payroll_reference_types,id',
                Rule::requiredIf(fn () => $request->input('jenis') === 'lookup_reference'),
            ],
            'mata_pelajaran_id' => [
                'nullable',
                'exists:mata_pelajaran,id',
                Rule::requiredIf(fn () => $request->input('jenis') === 'dinamis_ekskul'),
            ],
            'applies_to_status_kepegawaian' => 'nullable|in:tetap,honorer,guru,non_guru',
            'syarat_bayar_jam_mengajar' => 'nullable|in:semua_jadwal,hanya_hadir',
            'persentase_base' => 'nullable|in:taxable,gaji_pokok,total_pendapatan',
            'nilai_default' => 'nullable|numeric',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'urutan' => 'nullable|integer',
            'tampil_di_matrix' => 'boolean',
            'jabatan_nominals' => 'nullable|array',
            'jabatan_nominals.*.jabatan_id' => 'required|exists:jabatan,id',
            'jabatan_nominals.*.nominal' => 'required|numeric|min:0',
        ]);

        if (($validated['jenis'] ?? null) !== 'lookup_reference') {
            $validated['payroll_reference_type_id'] = null;
        }
        if (($validated['jenis'] ?? null) !== 'dinamis_ekskul') {
            $validated['mata_pelajaran_id'] = null;
        }

        if (! isset($validated['tampil_di_matrix'])) {
            $validated['tampil_di_matrix'] = false;
        }
        if (! isset($validated['urutan'])) {
            $validated['urutan'] = 99;
        }

        $touchedMapping = $request->exists('jabatan_nominals');
        $jabatanNominals = $validated['jabatan_nominals'] ?? [];
        unset($validated['jabatan_nominals']);

        $komponen->update($validated);
        $this->syncJabatanNominals($komponen->id, $validated['jenis'] ?? null, $jabatanNominals, $touchedMapping);

        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji berhasil diupdate.');
    }

    public function destroy($id)
    {
        KomponenGaji::destroy($id);

        return redirect()->route('komponen-gaji.index')->with('message', 'Komponen Gaji dihapus.');
    }

    /**
     * Sinkronisasi nominal per jabatan (khusus jenis dinamis_jabatan).
     * Key tak dikirim = jangan sentuh. Jenis lain: mapping lama dibersihkan.
     */
    private function syncJabatanNominals(int $komponenId, ?string $jenis, $rows, bool $touched): void
    {
        if (! $touched) {
            return;
        }
        if ($jenis !== 'dinamis_jabatan' || empty($rows)) {
            DB::table('komponen_jabatan')->where('komponen_gaji_id', $komponenId)->delete();

            return;
        }

        $keep = [];
        foreach ($rows as $row) {
            if (empty($row['jabatan_id'])) {
                continue;
            }
            $jid = (int) $row['jabatan_id'];
            $keep[] = $jid;
            DB::table('komponen_jabatan')->updateOrInsert(
                ['komponen_gaji_id' => $komponenId, 'jabatan_id' => $jid],
                ['nominal' => $row['nominal'] ?? 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }
        DB::table('komponen_jabatan')->where('komponen_gaji_id', $komponenId)
            ->whereNotIn('jabatan_id', $keep ?: [-1])->delete();
    }
}
