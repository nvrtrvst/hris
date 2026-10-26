<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\PiketArea;
use App\Models\PiketItemTemplate;
use App\Models\PiketPenugasan;
use App\Models\PiketPos;
use App\Models\PiketSesi;
use App\Models\UnitSekolah;
use App\Services\PiketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PiketSettingController extends Controller
{
    public function index(Request $request)
    {
        $units = UnitSekolah::orderBy('nama')->get(['id', 'nama', 'singkatan', 'piket_mode']);

        $unitId = (int) ($request->input('unit') ?: $units->first()?->id);
        $unit = UnitSekolah::find($unitId);

        if (! $unit) {
            return Inertia::render('Piket/Settings', [
                'units' => $units,
                'unit' => null,
                'areas' => [],
                'mapping' => [],
                'unmapped' => [],
                'pos' => [],
                'templates' => [],
                'penugasan' => [],
                'pegawai' => [],
                'sesiHariIni' => null,
            ]);
        }

        $areas = PiketArea::where('unit_sekolah_id', $unit->id)->orderBy('urutan')->get();

        $mapping = DB::table('piket_area_kelas')
            ->join('piket_area', 'piket_area.id', '=', 'piket_area_kelas.area_id')
            ->where('piket_area_kelas.unit_sekolah_id', $unit->id)
            ->select('piket_area_kelas.id', 'piket_area_kelas.kelas_label', 'piket_area.nama as area')
            ->orderBy('piket_area.urutan')
            ->orderBy('piket_area_kelas.kelas_label')
            ->get();

        $labels = Jadwal::where('unit_sekolah_id', $unit->id)
            ->whereNotNull('kelas_label')
            ->where('kelas_label', '<>', '')
            ->distinct()
            ->pluck('kelas_label')
            ->merge(DB::table('absen_siswa_sesi')->where('unit_sekolah_id', $unit->id)->distinct()->pluck('class_label'))
            ->unique()
            ->sort()
            ->values();

        $mappedLabels = $mapping->pluck('kelas_label')->all();
        $unmapped = $labels->reject(fn ($l) => in_array($l, $mappedLabels, true))->values();

        $sesiHariIni = PiketSesi::with(['petugas.pegawai:id,nama_lengkap'])
            ->where('unit_sekolah_id', $unit->id)
            ->where('tanggal', now()->toDateString())
            ->orderBy('jam_mulai')
            ->first();

        return Inertia::render('Piket/Settings', [
            'units' => $units,
            'unit' => $unit->only(['id', 'nama', 'singkatan', 'piket_mode']),
            'areas' => $areas,
            'mapping' => $mapping,
            'unmapped' => $unmapped,
            'pos' => PiketPos::where('unit_sekolah_id', $unit->id)->orderBy('jam_mulai')->get(),
            'templates' => PiketItemTemplate::where('unit_sekolah_id', $unit->id)->orderBy('urutan')->get(),
            'penugasan' => PiketPenugasan::with(['pegawai:id,nama_lengkap', 'area:id,nama', 'pos:id,nama'])
                ->where('unit_sekolah_id', $unit->id)
                ->where('aktif', true)
                ->get(),
            'pegawai' => Pegawai::where('status_aktif', 'aktif')
                ->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unit->id))
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap']),
            'sesiHariIni' => $sesiHariIni ? [
                'id' => $sesiHariIni->id,
                'jam' => $sesiHariIni->jam_mulai.'-'.$sesiHariIni->jam_selesai,
                'status' => $sesiHariIni->status,
                'petugas' => $sesiHariIni->petugas->map(fn ($p) => [
                    'nama' => $p->pegawai?->nama_lengkap,
                    'peran' => $p->peran,
                ]),
                'pos_belum' => PiketService::unservedRequiredPos($sesiHariIni),
                'pos' => PiketPos::where('unit_sekolah_id', $unit->id)->where('aktif', true)->get(['id', 'nama', 'is_wajib']),
            ] : null,
        ]);
    }

    public function toggleMode(Request $request)
    {
        $data = $request->validate([
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'mode' => ['required', \Illuminate\Validation\Rule::in(['simple', 'area'])],
        ]);

        if ($data['mode'] === 'area') {
            $punyaArea = \App\Models\PiketArea::where('unit_sekolah_id', $data['unit_sekolah_id'])->exists();
            $punyaTugas = \App\Models\PiketPenugasan::where('unit_sekolah_id', $data['unit_sekolah_id'])->where('aktif', true)->exists();

            if (! $punyaArea || ! $punyaTugas) {
                return back()->with('error', 'Lengkapi area dan penugasan sebelum mengaktifkan mode area.');
            }
        }

        UnitSekolah::whereKey($data['unit_sekolah_id'])->update(['piket_mode' => $data['mode']]);

        return back()->with('message', $data['mode'] === 'area' ? 'Mode area diaktifkan.' : 'Kembali ke mode sederhana.');
    }

    public function saveArea(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:piket_area,id',
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'nama' => 'required|string|max:100',
            'urutan' => 'nullable|integer|min:0|max:99',
        ]);

        PiketArea::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'unit_sekolah_id' => $data['unit_sekolah_id'],
                'nama' => $data['nama'],
                'urutan' => $data['urutan'] ?? 0,
                'aktif' => true,
            ],
        );

        return back()->with('message', 'Area disimpan.');
    }

    public function saveKelas(Request $request)
    {
        $data = $request->validate([
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'area_id' => 'required|integer|exists:piket_area,id',
            'kelas_label' => 'required|string|max:100',
        ]);

        DB::table('piket_area_kelas')->updateOrInsert(
            ['unit_sekolah_id' => $data['unit_sekolah_id'], 'kelas_label' => $data['kelas_label']],
            ['area_id' => $data['area_id'], 'updated_at' => now(), 'created_at' => now()],
        );

        return back()->with('message', 'Kelas dipetakan.');
    }

    public function savePos(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:piket_pos,id',
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'area_id' => 'nullable|integer|exists:piket_area,id',
            'nama' => 'required|string|max:100',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i',
            'is_wajib' => 'boolean',
            'aktif' => 'boolean',
        ]);

        PiketPos::updateOrCreate(
            ['id' => $data['id'] ?? null],
            $data + ['is_wajib' => (bool) ($data['is_wajib'] ?? false), 'aktif' => (bool) ($data['aktif'] ?? true)],
        );

        return back()->with('message', 'Pos disimpan.');
    }

    public function saveItem(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:piket_item_templates,id',
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'kode' => ['required', Rule::in(['cek_absen', 'absen_siswa', 'kelas_kosong', 'buku', 'insiden'])],
            'label' => 'required|string|max:100',
            'seksi' => ['required', Rule::in(['pagi', 'kbm', 'sore', 'bebas'])],
            'scope' => ['required', Rule::in(['global', 'area', 'pos'])],
            'wajib' => 'boolean',
            'butuh_foto' => 'boolean',
            'urutan' => 'nullable|integer|min:0|max:99',
            'aktif' => 'boolean',
        ]);

        PiketItemTemplate::updateOrCreate(
            ['id' => $data['id'] ?? null],
            $data + [
                'tipe' => 'centang',
                'wajib' => (bool) ($data['wajib'] ?? false),
                'butuh_foto' => (bool) ($data['butuh_foto'] ?? false),
                'aktif' => (bool) ($data['aktif'] ?? true),
                'urutan' => $data['urutan'] ?? 0,
            ],
        );

        return back()->with('message', 'Item disimpan.');
    }

    public function savePenugasan(Request $request)
    {
        $data = $request->validate([
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'pegawai_id' => 'required|integer|exists:pegawai,id',
            'area_id' => 'nullable|integer|exists:piket_area,id',
            'pos_id' => 'nullable|integer|exists:piket_pos,id',
            'peran' => ['required', Rule::in(['area', 'gerbang', 'backup'])],
            'tahun_ajaran' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:10',
        ]);

        $jadwal = Jadwal::where('unit_sekolah_id', $data['unit_sekolah_id'])
            ->orderByDesc('id')
            ->first(['tahun_ajaran', 'semester']);

        $tahun = $data['tahun_ajaran'] ?? $jadwal?->tahun_ajaran ?? (date('Y').'/'.(date('Y') + 1));
        $semester = $data['semester'] ?? (string) ($jadwal?->semester ?? '1');

        PiketPenugasan::updateOrCreate(
            [
                'unit_sekolah_id' => $data['unit_sekolah_id'],
                'tahun_ajaran' => $tahun,
                'semester' => $semester,
                'pegawai_id' => $data['pegawai_id'],
            ],
            [
                'area_id' => $data['area_id'] ?? null,
                'pos_id' => $data['pos_id'] ?? null,
                'peran' => $data['peran'],
                'aktif' => true,
            ],
        );

        return back()->with('message', 'Penugasan disimpan.');
    }

    public function hapusPenugasan(PiketPenugasan $penugasan)
    {
        $penugasan->delete();

        return back()->with('message', 'Penugasan dihapus.');
    }

    public function servePos(Request $request, PiketSesi $sesi, PiketPos $pos)
    {
        $this->authorizePosAction($request, $sesi);

        $data = $request->validate([
            'petugas_id' => 'nullable|integer|exists:pegawai,id',
            'alasan' => 'required|string|min:5|max:255',
        ]);

        PiketService::recordPos(
            $sesi,
            $pos,
            (int) ($data['petugas_id'] ?? 0),
            true,
            'Ditandai terlayani oleh admin.',
            'override',
            $request->user()->id,
            $data['alasan'],
        );

        return back()->with('message', 'Pos ditandai terlayani.');
    }

    public function disablePos(Request $request, PiketSesi $sesi, PiketPos $pos)
    {
        $this->authorizePosAction($request, $sesi);

        $data = $request->validate([
            'alasan' => 'required|string|min:5|max:255',
        ]);

        PiketService::markPosUnavailable($sesi, $pos, $request->user()->id, $data['alasan']);

        return back()->with('message', 'Pos dinonaktifkan untuk hari ini.');
    }

    private function authorizePosAction(Request $request, PiketSesi $sesi): void
    {
        $user = $request->user();
        $allowed = $user->can('manage_master_data')
            || ($user->can('view_presensi') && (int) $user->unit_sekolah_id === (int) $sesi->unit_sekolah_id);

        abort_unless($allowed, 403, 'Akses ditolak.');
    }
}
