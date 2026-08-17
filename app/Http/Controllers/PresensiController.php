<?php

namespace App\Http\Controllers;

use App\Helpers\PayrollLockHelper;
use App\Jobs\ProcessPresensiFoto;
use App\Models\AuditPresensi;
use App\Models\Jadwal;
use App\Models\Pegawai;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use App\Traits\CalculatesDistance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PresensiController extends Controller
{
    use CalculatesDistance;

    public function index(Request $request)
    {
        $request->validate([
            'lokasi_filter' => 'nullable|in:perlu_review,pulang_awal,review_semua',
            'suspicious_filter' => 'nullable|boolean',
            'status_filter' => 'nullable|in:hadir,telat,sakit,izin,cuti,alpa',
            'jadwal_filter' => 'nullable|in:sedang_berlangsung',
            'jenis_filter' => 'nullable|in:pendidik,kependidikan',
            'search' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        $isAdmin = $user && $user->can('view_presensi');
        $query = Presensi::with(['unitSekolah', 'pegawai', 'jadwal.mataPelajaran']);

        if (! $isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $query->where('pegawai_id', $pegawai->id);
            } else {
                $query->where('id', -1);
            }
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($user) {
                $q->forUnit($user->unit_sekolah_id);
            });
        }

        if ($request->start_date) {
            $query->where('tanggal', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('tanggal', '<=', $request->end_date);
        }

        if ($request->unit_id && $user->can('view_all_units')) {
            $query->whereHas('pegawai', function ($q) use ($request) {
                $q->whereHas('units', fn ($q2) => $q2->where('unit_sekolah.id', $request->unit_id));
            });
        }

        if ($request->search) {
            $query->whereHas('pegawai', function ($q) use ($request) {
                $q->where('nama_lengkap', 'like', '%'.$request->search.'%');
            });
        }

        // Ringkasan periode (scope + unit + search + tanggal) — bebas dari filter detail
        $stats = $this->presensiStats((clone $query));

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

        if ($request->lokasi_filter === 'perlu_review') {
            $query->where('lokasi_perlu_review', true);
        } elseif ($request->lokasi_filter === 'pulang_awal') {
            $query->where('lokasi_perlu_review', true);
        } elseif ($request->lokasi_filter === 'review_semua') {
            // Gabungan: perlu review ATAU posisi mencurigakan (anti-spoof v2)
            $query->where(function ($q) {
                $q->where('lokasi_perlu_review', true)
                    ->orWhere('posisi_mencurigakan', true);
            });
        }

        if ($request->suspicious_filter) {
            $query->where('posisi_mencurigakan', true);
        }

        if ($request->status_filter) {
            $query->where('status', $request->status_filter);
        }

        // Filter jenis pegawai (Dapodik-style): pendidik = punya jabatan guru (is_guru),
        // tenaga kependidikan = TIDAK punya jabatan guru sama sekali (TU, pustakawan, OB, dll).
        if ($request->jenis_filter === 'pendidik') {
            $query->whereHas('pegawai', fn ($q) => $q->whereHas('jabatans', fn ($q2) => $q2->where('is_guru', true)));
        } elseif ($request->jenis_filter === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->whereDoesntHave('jabatans', fn ($q2) => $q2->where('is_guru', true)));
        }

        // Kelas yang sedang berlangsung SEKARANG: record mengajar hari ini yang
        // sudah absen masuk dan jam jadwal-nya sedang berjalan (jam_mulai <= now < jam_selesai).
        // Konsisten dengan badge "Mengajar" di daftar (Index.jsx).
        // Perbandingan string polos (bukan whereTime) supaya index komposit
        // idx_jadwal_pegawai_hari_jam tetap terpakai — jam selalu tersimpan H:i:s zero-padded.
        if ($request->jadwal_filter === 'sedang_berlangsung') {
            $now = Carbon::now()->format('H:i:s');
            $query->where('tanggal', Carbon::today()->toDateString())
                ->whereNotNull('jadwal_id')
                ->whereNotNull('jam_masuk')
                ->whereHas('jadwal', function ($q) use ($now) {
                    $q->where('jam_mulai', '<=', $now)
                        ->where('jam_selesai', '>', $now);
                });
        }

        $presensis = $query->orderBy('tanggal', 'desc')->paginate(10)->withQueryString();

        $units = [];
        if ($user->can('view_all_units')) {
            $units = UnitSekolah::orderBy('nama')->get();
        }

        return inertia('Presensi/Index', [
            'presensis' => $presensis,
            'pegawai' => $isAdmin ? null : ($pegawai ?? null),
            'filters' => $request->only(['start_date', 'end_date', 'unit_id', 'lembur_filter', 'lokasi_filter', 'suspicious_filter', 'status_filter', 'jadwal_filter', 'jenis_filter', 'search']),
            'units' => $units,
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
            'stats' => $stats,
        ]);
    }

    /**
     * Ringkasan presensi untuk kartu statistik halaman index.
     * Satu query agregat (group by status) + dua count ringan.
     */
    private function presensiStats($query): array
    {
        $byStatus = (clone $query)->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = ['hadir' => 0, 'telat' => 0, 'sakit' => 0, 'izin' => 0, 'cuti' => 0, 'alpa' => 0];
        foreach ($stats as $key => $_) {
            $stats[$key] = (int) ($byStatus[$key] ?? 0);
        }
        $stats['total'] = array_sum($stats);
        $stats['lembur_pending'] = (clone $query)->where('is_lembur', true)->where('lembur_status', 'pending')->count();
        $stats['perlu_review'] = (clone $query)->where('lokasi_perlu_review', true)->count();

        return $stats;
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
            ->where('tanggal', Carbon::today()->toDateString())
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

        $pegawai = Pegawai::findOrFail($request->pegawai_id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->belongsToUnit($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $tempName = Str::uuid()->toString().'.jpg';
        $tempPath = 'temp/'.$tempName;
        $base64Data = $request->foto;
        Storage::disk('local')->put($tempPath, base64_decode(explode(',', $base64Data, 2)[1] ?? ''));

        try {
            $presensi = DB::transaction(function () use ($request, $unit, $distance) {
                $presensi = Presensi::where('pegawai_id', $request->pegawai_id)
                    ->where('jadwal_id', $request->jadwal_id)
                    ->where('tanggal', Carbon::today()->toDateString())
                    ->lockForUpdate()
                    ->first();

                if (! $presensi) {
                    $presensi = new Presensi([
                        'pegawai_id' => $request->pegawai_id,
                        'jadwal_id' => $request->jadwal_id,
                        'tanggal' => Carbon::today()->toDateString(),
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
                    $presensi->foto_masuk_status = 'pending';
                    $presensi->jarak_masuk_meter = $distance;

                    $presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jadwal->jam_mulai, (int) $unit->toleransi_menit);
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
                    $presensi->foto_keluar_status = 'pending';
                    $presensi->jarak_keluar_meter = $distance;
                }

                $presensi->save();

                return $presensi;
            });
        } catch (\Throwable $e) {
            // Jangan tinggalkan sampah temp foto saat transaksi/validasi gagal.
            if (Storage::disk('local')->exists($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }

            throw $e;
        }

        Bus::dispatchSync(new ProcessPresensiFoto(
            $presensi->id,
            $request->tipe,
            $tempPath,
            'presensi',
            null,
            ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap]
        ));

        return redirect()->route('presensi.index')->with('message', 'Presensi berhasil dicatat.');
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $isAdmin = $user && $user->can('view_presensi');
        if (! $isAdmin) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:hadir,telat,alpa,sakit,izin,cuti',
            'persentase_bayar_jam' => 'nullable|integer|min:0|max:100',
        ]);

        $statusLama = null;
        $presensi = DB::transaction(function () use ($id, $request, $user, &$statusLama) {
            $presensi = Presensi::with('pegawai')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                throw ValidationException::withMessages(['error' => 'Periode penggajian sudah dikunci. Tidak bisa mengubah status presensi.']);
            }

            $statusLama = $presensi->status;
            $presensi->status = $request->status;
            if ($request->filled('persentase_bayar_jam')) {
                $presensi->persentase_bayar_jam = (int) $request->persentase_bayar_jam;
            }
            $presensi->save();

            return $presensi;
        });

        AuditPresensi::log($presensi->id, 'ubah_status', 'status', $statusLama, $request->status);

        return redirect()->back()->with('message', 'Status presensi berhasil diubah menjadi '.strtoupper($request->status));
    }

    public function approveLembur($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        // Transaction + lockForUpdate: cegah race dua admin approve/reject bersamaan
        // (TOCTOU antara check status dan update).
        DB::transaction(function () use ($id, $user) {
            $presensi = Presensi::with('pegawai')
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $presensi->is_lembur || $presensi->lembur_status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Hanya lembur dengan status pending yang bisa disetujui.']);
            }

            if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                throw ValidationException::withMessages(['error' => 'Periode penggajian sudah dikunci.']);
            }

            $presensi->update(['lembur_status' => 'disetujui']);

            AuditPresensi::log($presensi->id, 'approve_lembur', 'lembur_status', 'pending', 'disetujui');
        });

        return redirect()->back()->with('message', 'Lembur berhasil disetujui.');
    }

    public function rejectLembur($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_presensi')) {
            abort(403);
        }

        DB::transaction(function () use ($id, $user) {
            $presensi = Presensi::with('pegawai')
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $presensi->is_lembur || $presensi->lembur_status !== 'pending') {
                throw ValidationException::withMessages(['error' => 'Hanya lembur dengan status pending yang bisa ditolak.']);
            }

            if ($user->unit_sekolah_id && ! $user->can('view_all_units') && ! $presensi->pegawai->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                throw ValidationException::withMessages(['error' => 'Periode penggajian sudah dikunci.']);
            }

            $presensi->update(['lembur_status' => 'ditolak']);

            AuditPresensi::log($presensi->id, 'reject_lembur', 'lembur_status', 'pending', 'ditolak');
        });

        return redirect()->back()->with('message', 'Lembur ditolak.');
    }

    public function audit($id)
    {
        if (! auth()->user()?->can('view_presensi')) {
            abort(403);
        }

        $user = auth()->user();
        $presensi = Presensi::with('pegawai:id,nama_lengkap')->findOrFail($id);

        // Scope unit untuk admin unit (anti-IDOR), konsisten dengan reviewDetail
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            if (! $presensi->pegawai?->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }
        }

        $audits = AuditPresensi::where('presensi_id', $id)
            ->with('user:id,name')
            ->latest()
            ->get(['id', 'user_id', 'aksi', 'field', 'nilai_lama', 'nilai_baru', 'created_at']);

        return response()->json([
            'audits' => $audits,
            'presensi' => [
                'pegawai_nama' => $presensi->pegawai?->nama_lengkap,
                'tanggal' => $presensi->tanggal?->format('Y-m-d'),
                'status' => $presensi->status,
                'foto_masuk_url' => $presensi->foto_masuk_url,
                'foto_keluar_url' => $presensi->foto_keluar_url,
                'foto_masuk_status' => $presensi->foto_masuk_status,
                'foto_keluar_status' => $presensi->foto_keluar_status,
                'foto_masuk_error' => $presensi->foto_masuk_error,
                'foto_keluar_error' => $presensi->foto_keluar_error,
            ],
        ]);
    }

    /**
     * Detail anti-spoof untuk review admin (trajectory, motion, IP geo, EXIF).
     * Menampilkan alasan kenapa record di-flag (fitur anti-spoof v2).
     */
    public function reviewDetail($id)
    {
        if (! auth()->user()?->can('view_presensi')) {
            abort(403);
        }

        $user = auth()->user();
        $presensi = Presensi::with('pegawai:id,nama_lengkap')->findOrFail($id);

        // Scope unit untuk admin unit
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            if (! $presensi->pegawai?->belongsToUnit($user->unit_sekolah_id)) {
                abort(403, 'Akses ditolak.');
            }
        }

        $trajectory = $presensi->trajectory_samples;
        $motion = $presensi->motion_samples;
        $motionVariance = null;
        if (is_array($motion) && count($motion) >= 2) {
            $totals = array_map(function ($s) {
                return sqrt((float) ($s['x'] ?? 0) ** 2 + (float) ($s['y'] ?? 0) ** 2 + (float) ($s['z'] ?? 0) ** 2);
            }, array_values($motion));
            $mean = array_sum($totals) / count($totals);
            $motionVariance = round(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $totals)) / count($totals), 6);
        }

        // Ringkas trajectory untuk tampilan (hapus data mentah berat)
        $trajectorySummary = null;
        if (is_array($trajectory)) {
            $trajectorySummary = array_map(function ($p) {
                return [
                    'label' => $p['label'] ?? null,
                    'lat' => $p['lat'] ?? null,
                    'lng' => $p['lng'] ?? null,
                    'accuracy' => $p['accuracy'] ?? null,
                    'captured_at' => $p['captured_at'] ?? null,
                ];
            }, $trajectory);
        }

        return response()->json([
            'presensi' => [
                'id' => $presensi->id,
                'pegawai_nama' => $presensi->pegawai?->nama_lengkap,
                'tanggal' => $presensi->tanggal?->format('Y-m-d'),
                'status' => $presensi->status,
                'latitude_masuk' => $presensi->latitude_masuk,
                'longitude_masuk' => $presensi->longitude_masuk,
                'akurasi_masuk' => $presensi->akurasi_masuk,
                'kecepatan_masuk' => $presensi->kecepatan_masuk,
                'captured_at' => $presensi->captured_at?->toIso8601String(),
                'foto_masuk_url' => $presensi->foto_masuk_url,
                'foto_keluar_url' => $presensi->foto_keluar_url,
                'foto_masuk_status' => $presensi->foto_masuk_status,
                'foto_keluar_status' => $presensi->foto_keluar_status,
                'foto_masuk_error' => $presensi->foto_masuk_error,
                'foto_keluar_error' => $presensi->foto_keluar_error,
                'lokasi_perlu_review' => $presensi->lokasi_perlu_review,
                'posisi_mencurigakan' => $presensi->posisi_mencurigakan,
                'motion_suspect' => $presensi->motion_suspect,
                'trajectory' => $trajectorySummary,
                'motion_sample_count' => is_array($motion) ? count($motion) : 0,
                'motion_variance' => $motionVariance,
                'ip_geo' => $presensi->ip_geo,
                'exif_meta' => $presensi->exif_meta,
            ],
        ]);
    }
}
