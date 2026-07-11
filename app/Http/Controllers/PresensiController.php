<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Services\ImageUploadService;
use App\Traits\CalculatesDistance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PresensiController extends Controller
{
    use CalculatesDistance;

    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_presensi');
        $query = Presensi::with(['unitSekolah', 'pegawai', 'jadwal']);

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($user) {
                $q->forUnit($user->unit_sekolah_id);
            });
        } elseif (! $isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $query->where('pegawai_id', $pegawai->id);
            } else {
                $query->where('id', -1); // Tidak ada data
            }
        }

        if ($request->start_date) {
            $query->where('tanggal', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('tanggal', '<=', $request->end_date);
        }

        if ($request->unit_id && $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($request) {
                $q->where('unit_sekolah_id', $request->unit_id);
            });
        }

        // Filter lembur
        if ($request->lembur_filter === 'lembur_pending') {
            $query->where('is_lembur', true)->where('lembur_status', 'pending');
        } elseif ($request->lembur_filter === 'lembur_disetujui') {
            $query->where('is_lembur', true)->where('lembur_status', 'disetujui');
        } elseif ($request->lembur_filter === 'lembur_ditolak') {
            $query->where('is_lembur', true)->where('lembur_status', 'ditolak');
        } elseif ($request->lembur_filter === 'lembur_semua') {
            $query->where('is_lembur', true);
        }

        $presensis = $query->orderBy('tanggal', 'desc')->paginate(10);
        $presensis->appends($request->only(['start_date', 'end_date', 'unit_id', 'lembur_filter']));

        $units = [];
        if ($user->can('view_all_units')) {
            $units = UnitSekolah::all();
        }

        return inertia('Presensi/Index', [
            'presensis' => $presensis,
            'pegawai' => $isAdmin ? null : ($pegawai ?? null),
            'filters' => $request->only(['start_date', 'end_date', 'unit_id', 'lembur_filter']),
            'units' => $units,
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
        ]);
    }

    public function create()
    {
        $isAdmin = auth()->user() && auth()->user()->can('view_presensi');
        if (! $isAdmin) {
            abort(403, 'Akses ditolak. Presensi hanya bisa dilakukan via Mobile Portal.');
        }

        $pegawai = Pegawai::first(); // Mock user for simulation only
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = Jadwal::with('unitSekolah')
            ->where('pegawai_id', $pegawai->id ?? 0)
            ->where('hari', $hariIniIndo)
            ->get();

        $presensiHariIni = Presensi::where('pegawai_id', $pegawai->id ?? 0)
            ->where('tanggal', Carbon::today())
            ->get();

        return inertia('Presensi/Create', [
            'jadwals' => $jadwalHariIni,
            'presensis' => $presensiHariIni,
            'pegawai' => $pegawai,
        ]);
    }

    public function store(Request $request)
    {
        $isAdmin = auth()->user() && auth()->user()->can('view_presensi');
        if (! $isAdmin) {
            abort(403, 'Akses ditolak. Presensi hanya bisa dilakukan via Mobile Portal.');
        }

        $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'jadwal_id' => 'required|exists:jadwal,id',
            'tipe' => 'required|in:masuk,keluar',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto' => ['required', 'string', 'regex:/^data:image\/\w+;base64,/'],
        ]);

        $jadwal = Jadwal::with('unitSekolah')->findOrFail($request->jadwal_id);
        $unit = $jadwal->unitSekolah;

        // Validasi Geofencing
        $distance = $this->calculateDistance($request->latitude, $request->longitude, $unit->latitude, $unit->longitude);

        if ($distance > $unit->radius_meter) {
            return back()->withErrors(['geofence' => "Anda berada di luar jangkauan Unit Sekolah. Jarak Anda: {$distance} meter (Batas: {$unit->radius_meter}m)"]);
        }

        // Simpan Foto (UUID, hindari path traversal via nama pegawai)
        $pegawai = Pegawai::findOrFail($request->pegawai_id);
        $imageName = app(ImageUploadService::class)->storeBase64($request->foto, 'presensi');

        DB::transaction(function () use ($request, $unit, $distance, $imageName) {
            $presensi = Presensi::where('pegawai_id', $request->pegawai_id)
                ->where('jadwal_id', $request->jadwal_id)
                ->where('tanggal', Carbon::today())
                ->lockForUpdate()
                ->first();

            if (! $presensi) {
                $presensi = new Presensi([
                    'pegawai_id' => $request->pegawai_id,
                    'jadwal_id' => $request->jadwal_id,
                    'tanggal' => Carbon::today(),
                ]);
            }

            $presensi->unit_sekolah_id = $unit->id;

            $jadwal = Jadwal::find($request->jadwal_id);

            if ($request->tipe === 'masuk') {
                if ($presensi->jam_masuk) {
                    throw ValidationException::withMessages(['conflict' => 'Anda sudah melakukan absen masuk untuk jadwal ini.']);
                }
                $presensi->jam_masuk = Carbon::now()->format('H:i:s');
                $presensi->latitude_masuk = $request->latitude;
                $presensi->longitude_masuk = $request->longitude;
                $presensi->foto_masuk = $imageName;
                $presensi->jarak_masuk_meter = $distance;

                // Tentukan status telat
                if (Carbon::now()->format('H:i:s') > $jadwal->jam_mulai) {
                    $presensi->status = 'telat';
                } else {
                    $presensi->status = 'hadir';
                }
            } else {
                if (! $presensi->exists || ! $presensi->jam_masuk) {
                    throw ValidationException::withMessages(['conflict' => 'Anda belum absen masuk.']);
                }
                if ($presensi->jam_keluar) {
                    throw ValidationException::withMessages(['conflict' => 'Anda sudah melakukan absen keluar.']);
                }
                $presensi->jam_keluar = Carbon::now()->format('H:i:s');
                $presensi->latitude_keluar = $request->latitude;
                $presensi->longitude_keluar = $request->longitude;
                $presensi->foto_keluar = $imageName;
                $presensi->jarak_keluar_meter = $distance;
            }

            $presensi->save();
        });

        return redirect()->route('presensi.index')->with('message', 'Presensi berhasil dicatat.');
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_presensi');
        if (! $isAdmin) {
            abort(403);
        }

        $presensi = Presensi::with('pegawai')->findOrFail($id);

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'status' => 'required|in:hadir,telat,alpa',
        ]);

        $presensi->update(['status' => $request->status]);

        return redirect()->back()->with('message', 'Status presensi berhasil diubah menjadi '.strtoupper($request->status));
    }

    public function approveLembur($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        $presensi = Presensi::with('pegawai')->findOrFail($id);
        if (! $presensi->is_lembur || $presensi->lembur_status !== 'pending') {
            return back()->withErrors(['error' => 'Hanya lembur dengan status pending yang bisa disetujui.']);
        }

        if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $presensi->update(['lembur_status' => 'disetujui']);

        return redirect()->back()->with('message', 'Lembur berhasil disetujui.');
    }

    public function rejectLembur($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        $presensi = Presensi::with('pegawai')->findOrFail($id);
        if (! $presensi->is_lembur || $presensi->lembur_status !== 'pending') {
            return back()->withErrors(['error' => 'Hanya lembur dengan status pending yang bisa ditolak.']);
        }

        if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $presensi->update(['lembur_status' => 'ditolak']);

        return redirect()->back()->with('message', 'Lembur ditolak.');
    }
}
