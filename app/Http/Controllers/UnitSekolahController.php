<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use App\Models\UnitLokasi;
use App\Models\UnitPresensiModeHistory;
use App\Models\UnitSekolah;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitSekolahController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = UnitSekolah::withCount(['pegawais', 'jadwals'])->orderBy('nama');

        // admin_unit hanya melihat unitnya sendiri.
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->where('id', $user->unit_sekolah_id);
        }

        $units = $query->get();

        $stats = [
            'total_unit' => $units->count(),
            'total_pegawai' => $units->sum('pegawais_count'),
            'total_jadwal' => $units->sum('jadwals_count'),
        ];

        return inertia('UnitSekolah/Index', [
            'units' => $units,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return inertia('UnitSekolah/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'singkatan' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,webp|max:1024',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:10|max:100000',
            'durasi_jp' => 'nullable|integer|min:1|max:255',
            'max_jam_minggu' => 'nullable|integer|min:1|max:168',
            'toleransi_menit' => 'nullable|integer|min:0|max:60',
            'toleransi_mengajar_menit' => 'nullable|integer|min:0|max:60',
            'toleransi_slide_menit' => 'nullable|integer|min:0|max:60',
            'max_jeda_sesi_menit' => 'nullable|integer|min:0|max:240',
            'bobot_sakit_surat' => 'nullable|numeric|between:0,1',
            'bobot_sakit_tanpa_surat' => 'nullable|numeric|between:0,1',
            'bobot_izin' => 'nullable|numeric|between:0,1',
            'bobot_izin_setengah' => 'nullable|numeric|between:0,1',
            'bobot_cuti' => 'nullable|numeric|between:0,1',
            'bobot_alpa' => 'nullable|numeric|between:0,1',
            'sakit_wajib_tugas' => 'nullable|boolean',
            'wajib_pulang_mengajar' => 'nullable|boolean',
            'pulang_sebelum_menit' => 'nullable|integer|min:1|max:120',
            'max_lembur_jam' => 'nullable|numeric|min:0.5|max:24',
            'jam_masuk_kantor' => 'required|date_format:H:i',
            'jam_pulang_kantor' => 'nullable|date_format:H:i',
            'jam_kerja_sabtu_mulai' => 'nullable|date_format:H:i',
            'jam_kerja_sabtu_selesai' => 'nullable|date_format:H:i',
            'jam_kerja_minggu_mulai' => 'nullable|date_format:H:i',
            'jam_kerja_minggu_selesai' => 'nullable|date_format:H:i',
            'web' => 'nullable|string|max:255',
            'telepon' => 'nullable|string|max:255',
            'alamat' => 'nullable|string|max:1000',
        ]);

        $validated['max_jam_minggu'] = $validated['max_jam_minggu'] ?? 30;
        $validated['toleransi_slide_menit'] = $validated['toleransi_slide_menit'] ?? 15;
        $validated['max_jeda_sesi_menit'] = $validated['max_jeda_sesi_menit'] ?? 120;
        $validated['wajib_pulang_mengajar'] = (bool) ($validated['wajib_pulang_mengajar'] ?? false);
        $validated['sakit_wajib_tugas'] = (bool) ($validated['sakit_wajib_tugas'] ?? false);
        $validated['pulang_sebelum_menit'] = $validated['pulang_sebelum_menit'] ?? 20;

        $disk = config('filesystems.image_disk', 'public');
        $newLogo = $request->hasFile('logo') ? $request->file('logo')->store('unit_logos', $disk) : null;
        if ($newLogo) {
            $validated['logo'] = $newLogo;
        }

        try {
            UnitSekolah::create($validated);
        } catch (\Throwable $exception) {
            if ($newLogo) {
                Storage::disk($disk)->delete($newLogo);
            }
            throw $exception;
        }

        return redirect()->route('unit-sekolah.index')->with('message', 'Unit Sekolah berhasil ditambahkan.');
    }

    public function edit(UnitSekolah $unit_sekolah)
    {
        return inertia('UnitSekolah/Edit', [
            'unit' => $unit_sekolah,
            'lokalis' => $unit_sekolah->lokalis,
            'jabatans' => Jabatan::orderBy('nama')->get(['id', 'nama']),
            'tembusan' => DB::table('unit_tembusan')->where('unit_sekolah_id', $unit_sekolah->id)->pluck('jabatan_id')->all(),
            ...$this->presensiModePayload($unit_sekolah),
        ]);
    }

    public function update(Request $request, UnitSekolah $unit_sekolah)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'singkatan' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,webp|max:1024',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:10|max:100000',
            'durasi_jp' => 'nullable|integer|min:1|max:255',
            'max_jam_minggu' => 'nullable|integer|min:1|max:168',
            'toleransi_menit' => 'nullable|integer|min:0|max:60',
            'toleransi_mengajar_menit' => 'nullable|integer|min:0|max:60',
            'toleransi_slide_menit' => 'nullable|integer|min:0|max:60',
            'max_jeda_sesi_menit' => 'nullable|integer|min:0|max:240',
            'bobot_sakit_surat' => 'nullable|numeric|between:0,1',
            'bobot_sakit_tanpa_surat' => 'nullable|numeric|between:0,1',
            'bobot_izin' => 'nullable|numeric|between:0,1',
            'bobot_izin_setengah' => 'nullable|numeric|between:0,1',
            'bobot_cuti' => 'nullable|numeric|between:0,1',
            'bobot_alpa' => 'nullable|numeric|between:0,1',
            'sakit_wajib_tugas' => 'nullable|boolean',
            'wajib_pulang_mengajar' => 'nullable|boolean',
            'pulang_sebelum_menit' => 'nullable|integer|min:1|max:120',
            'max_lembur_jam' => 'nullable|numeric|min:0.5|max:24',
            'jam_masuk_kantor' => 'required|date_format:H:i',
            'jam_pulang_kantor' => 'nullable|date_format:H:i',
            'jam_kerja_sabtu_mulai' => 'nullable|date_format:H:i',
            'jam_kerja_sabtu_selesai' => 'nullable|date_format:H:i',
            'jam_kerja_minggu_mulai' => 'nullable|date_format:H:i',
            'jam_kerja_minggu_selesai' => 'nullable|date_format:H:i',
            'web' => 'nullable|string|max:255',
            'telepon' => 'nullable|string|max:255',
            'alamat' => 'nullable|string|max:1000',
            'tembusan_jabatan_ids' => 'nullable|array',
            'tembusan_jabatan_ids.*' => 'integer|exists:jabatan,id',
        ]);

        $validated['max_jam_minggu'] = $validated['max_jam_minggu'] ?? 30;
        $validated['toleransi_slide_menit'] = $validated['toleransi_slide_menit'] ?? $unit_sekolah->toleransi_slide_menit ?? 15;
        $validated['max_jeda_sesi_menit'] = $validated['max_jeda_sesi_menit'] ?? $unit_sekolah->max_jeda_sesi_menit ?? 120;
        if (array_key_exists('wajib_pulang_mengajar', $validated)) {
            $validated['wajib_pulang_mengajar'] = (bool) $validated['wajib_pulang_mengajar'];
        }
        if (array_key_exists('sakit_wajib_tugas', $validated)) {
            $validated['sakit_wajib_tugas'] = (bool) $validated['sakit_wajib_tugas'];
        }
        $validated['pulang_sebelum_menit'] = $validated['pulang_sebelum_menit'] ?? $unit_sekolah->pulang_sebelum_menit ?? 20;

        $disk = config('filesystems.image_disk', 'public');
        $oldLogo = $unit_sekolah->logo;
        $newLogo = $request->hasFile('logo') ? $request->file('logo')->store('unit_logos', $disk) : null;
        if ($newLogo) {
            $validated['logo'] = $newLogo;
        } else {
            unset($validated['logo']);
        }

        try {
            $unit_sekolah->update($validated);
        } catch (\Throwable $exception) {
            if ($newLogo) {
                Storage::disk($disk)->delete($newLogo);
            }
            throw $exception;
        }

        if ($newLogo && $oldLogo) {
            Storage::disk($disk)->delete($oldLogo);
        }

        // Sinkronisasi jabatan penerima tembusan izin unit ini.
        $tembusanIds = $validated['tembusan_jabatan_ids'] ?? null;
        unset($validated['tembusan_jabatan_ids']);
        if (is_array($tembusanIds)) {
            $keep = array_values(array_unique(array_map('intval', $tembusanIds)));
            DB::table('unit_tembusan')->where('unit_sekolah_id', $unit_sekolah->id)
                ->whereNotIn('jabatan_id', $keep ?: [-1])->delete();
            foreach ($keep as $jabatanId) {
                DB::table('unit_tembusan')->updateOrInsert(
                    ['unit_sekolah_id' => $unit_sekolah->id, 'jabatan_id' => $jabatanId],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        return redirect()->route('unit-sekolah.index')->with('message', 'Unit Sekolah berhasil diperbarui.');
    }

    public function storePresensiMode(Request $request, UnitSekolah $unit_sekolah)
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(array_keys(UnitPresensiModeHistory::modes()))],
            'berlaku_mulai' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $this->assertTanggalModeBelumDipakai($unit_sekolah, $validated['berlaku_mulai']);

        $unit_sekolah->presensiModeHistories()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('message', 'Mode presensi unit berhasil dicatat.');
    }

    public function updatePresensiMode(
        Request $request,
        UnitSekolah $unit_sekolah,
        UnitPresensiModeHistory $presensi_mode_history
    ) {
        abort_unless($presensi_mode_history->unit_sekolah_id === $unit_sekolah->id, 404);
        abort_unless($presensi_mode_history->berlaku_mulai->gte(Carbon::today()), 422, 'Riwayat mode yang sudah lampau tidak dapat diubah.');

        $validated = $request->validate([
            'mode' => ['required', Rule::in(array_keys(UnitPresensiModeHistory::modes()))],
            'berlaku_mulai' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $this->assertTanggalModeBelumDipakai($unit_sekolah, $validated['berlaku_mulai'], $presensi_mode_history->id);

        $presensi_mode_history->update($validated);

        return back()->with('message', 'Riwayat mode presensi unit berhasil diperbarui.');
    }

    public function destroyPresensiMode(UnitSekolah $unit_sekolah, UnitPresensiModeHistory $presensi_mode_history)
    {
        abort_unless($presensi_mode_history->unit_sekolah_id === $unit_sekolah->id, 404);
        abort_unless($presensi_mode_history->berlaku_mulai->gte(Carbon::today()), 422, 'Riwayat mode yang sudah lampau tidak dapat dihapus.');

        $presensi_mode_history->delete();

        return back()->with('message', 'Riwayat mode presensi unit berhasil dihapus.');
    }

    private function assertTanggalModeBelumDipakai(UnitSekolah $unit_sekolah, string $tanggal, ?int $ignoreId = null): void
    {
        $exists = $unit_sekolah->presensiModeHistories()
            ->whereDate('berlaku_mulai', $tanggal)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'berlaku_mulai' => 'Tanggal berlaku sudah dipakai. Ubah entri pada tanggal tersebut.',
            ]);
        }
    }

    private function presensiModePayload(UnitSekolah $unit_sekolah): array
    {
        $today = Carbon::today();
        $histories = $unit_sekolah->presensiModeHistories()
            ->with('createdBy:id,name')
            ->get()
            ->sortByDesc('berlaku_mulai')
            ->values();

        $active = $histories->first(fn (UnitPresensiModeHistory $history) => $history->berlaku_mulai->lte($today));
        $next = $histories
            ->filter(fn (UnitPresensiModeHistory $history) => $history->berlaku_mulai->gt($today))
            ->sortBy('berlaku_mulai')
            ->first();

        return [
            'mode_options' => collect(UnitPresensiModeHistory::modes())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'presensi_mode_histories' => $histories->map(function (UnitPresensiModeHistory $history) use ($active, $today) {
                $status = $history->berlaku_mulai->gt($today)
                    ? 'akan_berlaku'
                    : ($active?->id === $history->id ? 'aktif' : 'lampau');

                return [
                    'id' => $history->id,
                    'mode' => $history->mode,
                    'mode_label' => $history->label(),
                    'berlaku_mulai' => $history->berlaku_mulai->toDateString(),
                    'status' => $status,
                    'created_by' => $history->createdBy?->name,
                    'created_at' => $history->created_at?->toDateTimeString(),
                    'can_change' => $history->berlaku_mulai->gte($today),
                ];
            })->values(),
            'mode_aktif' => $active?->mode ?? UnitPresensiModeHistory::MODE_KANTOR,
            'mode_aktif_label' => $active?->label() ?? UnitPresensiModeHistory::modes()[UnitPresensiModeHistory::MODE_KANTOR],
            'mode_aktif_sejak' => $active?->berlaku_mulai->toDateString(),
            'mode_berikutnya' => $next ? [
                'mode' => $next->mode,
                'mode_label' => $next->label(),
                'berlaku_mulai' => $next->berlaku_mulai->toDateString(),
            ] : null,
        ];
    }

    public function storeLokasi(Request $request, UnitSekolah $unit_sekolah)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meter' => 'required|integer|min:10|max:100000',
        ]);

        $unit_sekolah->lokalis()->create($validated);

        return back()->with('message', 'Lokasi berhasil ditambahkan.');
    }

    public function updateLokasi(Request $request, UnitSekolah $unit_sekolah, UnitLokasi $lokasi)
    {
        abort_unless($lokasi->unit_sekolah_id === $unit_sekolah->id, 404);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meter' => 'required|integer|min:10|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $lokasi->update($validated);

        return back()->with('message', 'Lokasi berhasil diperbarui.');
    }

    public function destroyLokasi(UnitSekolah $unit_sekolah, UnitLokasi $lokasi)
    {
        abort_unless($lokasi->unit_sekolah_id === $unit_sekolah->id, 404);

        $lokasi->delete();

        return back()->with('message', 'Lokasi berhasil dihapus.');
    }
}
