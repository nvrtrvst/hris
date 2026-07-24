<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Models\Jadwal;
use App\Models\Presensi;
use App\Services\ImageUploadService;
use App\Traits\CalculatesDistance;
use App\Traits\ResolvesPegawai;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MobileController extends Controller
{
    use CalculatesDistance, ResolvesPegawai;

    public function dashboard()
    {
        $pegawai = $this->getPegawai();
        $pegawai->load('units');
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $presensiHariIni = Presensi::with('unitSekolah')->where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today())
            ->first();

        $presensiTerbaru = Presensi::with('unitSekolah')
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal', '>=', Carbon::today()->subDays(3))
            ->where('tanggal', '<=', Carbon::today())
            ->orderBy('tanggal', 'desc')
            ->get();

        $jadwalsHariIni = Jadwal::with(['unitSekolah', 'mataPelajaran', 'kelas'])
            ->where('pegawai_id', $pegawai->id)
            ->where('hari', $hariIniIndo)
            ->orderBy('jam_mulai', 'asc')
            ->get();

        return inertia('Mobile/Dashboard', [
            'pegawai' => $pegawai,
            'presensi' => $presensiHariIni,
            'presensiSeminggu' => $presensiTerbaru,
            'jadwalsHariIni' => $jadwalsHariIni,
        ]);
    }

    public function riwayat(Request $request)
    {
        $validated = $request->validate([
            'bulan' => 'nullable|integer|between:1,12',
            'tahun' => 'nullable|integer|between:2020,2100',
        ]);
        $pegawai = $this->getPegawai();

        $bulan = (int) ($validated['bulan'] ?? Carbon::now()->month);
        $tahun = (int) ($validated['tahun'] ?? Carbon::now()->year);

        $presensi = Presensi::with('unitSekolah')
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [
                Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->format('Y-m-d'),
                Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->format('Y-m-d'),
            ])
            ->orderBy('tanggal', 'desc')
            ->get();

        return inertia('Mobile/Riwayat', [
            'pegawai' => $pegawai,
            'presensi' => $presensi,
            'filters' => ['bulan' => $bulan, 'tahun' => $tahun],
        ]);
    }

    public function jadwal()
    {
        $pegawai = $this->getPegawai();

        $jadwals = Jadwal::with(['unitSekolah', 'mataPelajaran', 'kelas', 'kelas.jurusan'])
            ->where('pegawai_id', $pegawai->id)
            ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
            ->orderBy('jam_mulai', 'asc')
            ->get()
            ->groupBy('hari');

        return inertia('Mobile/Jadwal', [
            'pegawai' => $pegawai,
            'jadwalPerHari' => $jadwals,
        ]);
    }

    public function kelasUnit(Request $request)
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|min:1',
        ]);
        [$jadwal, $unit] = $this->resolveOwnedJadwal((int) $validated['jadwal_id']);

        if ($jadwal->kelas_id) {
            return response()->json(['success' => true, 'kelas' => []])->header('Cache-Control', 'no-store');
        }

        $response = $this->keuanganRequest('kelas-by-unit', [
            'unit' => $unit->nama ?: $unit->singkatan,
        ]);

        if (! $response?->successful()) {
            return response()->json(['success' => false, 'kelas' => [], 'message' => 'Data kelas belum tersedia.'], 502)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'success' => true,
            'school_name' => $response->json('data.schoolName'),
            'kelas' => $response->json('data.classes') ?? [],
        ])->header('Cache-Control', 'no-store');
    }

    // Proxy ke API internal app keuangan untuk mengambil daftar siswa per kelas.
    // Kunci API disimpan server-side (tidak dikirim ke browser).
    public function siswaKelas(Request $request)
    {
        $validated = $request->validate($this->studentClassRules());
        [$jadwal, $unit] = $this->resolveOwnedJadwal((int) $validated['jadwal_id']);
        $class = $this->resolveClassPayload($jadwal, $validated);

        $response = $this->keuanganRequest('siswa-by-class', [
            'unit' => $unit->nama ?: $unit->singkatan,
            ...$class,
        ]);

        if (! $response?->successful()) {
            return response()->json(['success' => false, 'siswa' => [], 'message' => 'Data siswa belum tersedia.'], 502)->header('Cache-Control', 'no-store');
        }

        $data = $response->json('data') ?? [];

        return response()->json([
            'success' => true,
            'school_name' => $data['schoolName'] ?? null,
            'class_labels' => $data['classLabels'] ?? [],
            'siswa' => $data['students'] ?? [],
        ])->header('Cache-Control', 'no-store');
    }

    private function resolveOwnedJadwal(int $jadwalId): array
    {
        $pegawai = $this->getPegawai();
        $jadwal = Jadwal::with(['unitSekolah', 'kelas.jurusan'])
            ->whereKey($jadwalId)
            ->where('pegawai_id', $pegawai->id)
            ->firstOrFail();

        abort_unless($jadwal->unitSekolah, 422, 'Unit jadwal tidak tersedia.');

        return [$jadwal, $jadwal->unitSekolah];
    }

    private function studentClassRules(): array
    {
        return [
            'jadwal_id' => 'required|integer|min:1',
            'tingkat' => 'nullable|string|max:20',
            'kelas' => 'nullable|string|max:100',
            'jurusan' => 'nullable|string|max:100',
            'class_id' => 'nullable|string|max:50',
        ];
    }

    private function resolveClassPayload(Jadwal $jadwal, array $validated): array
    {
        if ($jadwal->kelas) {
            return [
                'tingkat' => (string) $jadwal->kelas->tingkat,
                'kelas' => (string) $jadwal->kelas->nama,
                'jurusan' => (string) ($jadwal->kelas->jurusan?->nama ?? ''),
            ];
        }

        if (empty($validated['class_id']) || empty($validated['tingkat']) || empty($validated['kelas'])) {
            throw ValidationException::withMessages(['kelas' => 'Pilih kelas terlebih dahulu.']);
        }

        return [
            'tingkat' => trim($validated['tingkat']),
            'kelas' => trim($validated['kelas']),
            'jurusan' => trim($validated['jurusan'] ?? ''),
            'class_id' => trim($validated['class_id'] ?? ''),
        ];
    }

    private function keuanganRequest(string $endpoint, array $payload, bool $post = false)
    {
        $base = rtrim((string) config('keuangan.url'), '/');
        $key = (string) config('keuangan.key');
        if ($base === '' || $key === '' || $key === 'change-me-in-production') {
            Log::error('Integrasi keuangan belum dikonfigurasi.');

            return null;
        }

        $request = Http::acceptJson()
            ->withHeaders(['x-internal-key' => $key])
            ->connectTimeout(2)
            ->timeout($post ? 15 : 8);

        try {
            return $post
                ? $request->post($base.'/api/integration/'.$endpoint, $payload)
                : $request->get($base.'/api/integration/'.$endpoint, $payload);
        } catch (ConnectionException) {
            Log::warning('Aplikasi keuangan tidak dapat dihubungi.', ['endpoint' => $endpoint]);

            return null;
        }
    }

    public function absen()
    {
        $pegawai = $this->getPegawai();
        $pegawai->load('units');
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = Jadwal::with(['unitSekolah', 'mataPelajaran', 'kelas'])
            ->where('pegawai_id', $pegawai->id)
            ->where('hari', $hariIniIndo)
            ->get();

        $presensiHariIni = Presensi::where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today())
            ->get();

        return inertia('Mobile/Absen', [
            'pegawai' => $pegawai,
            'jadwals' => $jadwalHariIni,
            'presensiHariIni' => $presensiHariIni,
            'officeAttendance' => $pegawai->wajib_kantor && $jadwalHariIni->isEmpty(),
        ]);
    }

    public function storeAbsen(Request $request)
    {
        $request->validate([
            'jadwal_id' => 'nullable|integer|min:1',
            'is_lembur' => 'nullable|boolean',
            'tipe' => 'required|in:masuk,keluar',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'captured_at' => 'nullable|date',
            'mock_suspect' => 'nullable|boolean',
            'pos_a_lat' => 'nullable|numeric|between:-90,90',
            'pos_a_lng' => 'nullable|numeric|between:-180,180',
            'pos_a_accuracy' => 'nullable|numeric|min:0',
            'pos_a_captured_at' => 'nullable|date',
            'foto' => ['required', 'string', 'max:7000000', 'regex:/^data:image\/\w+;base64,/'],
        ]);

        $pegawai = $this->getPegawai();
        $isLembur = (bool) $request->input('is_lembur', false);
        if ($isLembur && $request->filled('jadwal_id')) {
            throw ValidationException::withMessages(['jadwal_id' => 'Jadwal tidak boleh dipilih saat mode lembur.']);
        }
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIni = $hariMap[Carbon::now()->format('l')];
        $tipePresensi = $isLembur ? 'lembur' : ($request->filled('jadwal_id') ? 'mengajar' : 'kantor');

        if ($request->jadwal_id) {
            $jadwal = Jadwal::with('unitSekolah')
                ->whereKey($request->jadwal_id)
                ->where('pegawai_id', $pegawai->id)
                ->where('hari', $hariIni)
                ->first();
            if (! $jadwal) {
                throw ValidationException::withMessages(['jadwal_id' => PresensiMessages::PEMILIH_JADWAL_DULU]);
            }
            $unit = $jadwal->unitSekolah;
        } elseif ($isLembur) {
            $primaryUnit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
            if (! $primaryUnit) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $unit = $primaryUnit;
            $jadwal = null;
        } elseif ($pegawai->wajib_kantor && ! Jadwal::where('pegawai_id', $pegawai->id)->where('hari', $hariIni)->exists()) {
            $unit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
            if (! $unit) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $jadwal = null;
        } else {
            $message = PresensiMessages::PEMILIH_JADWAL_DULU;

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['jadwal_id' => $message]], 422);
        }

        $distance = $this->calculateDistance($request->latitude, $request->longitude, $unit->latitude, $unit->longitude);

        if ($distance > $unit->radius_meter) {
            $message = sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $unit->radius_meter);

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        $accuracy = (float) $request->accuracy;
        $speed = $request->filled('speed') ? (float) $request->speed : null;
        $mockSuspect = (bool) $request->input('mock_suspect', false);

        if ($accuracy !== null && $accuracy <= 0) {
            Log::warning('Percobaan presensi dengan lokasi terindikasi palsu.', [
                'user_id' => $request->user()?->id,
                'pegawai_id' => $pegawai->id,
                'nama_pegawai' => $pegawai->nama_lengkap,
                'ip' => $request->ip(),
            ]);
            $message = PresensiMessages::GEOFENCE_ACCURACY_ZERO;

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }
        if ($accuracy !== null && $accuracy > $unit->radius_meter) {
            $message = sprintf(PresensiMessages::GEOFENCE_ACCURACY_POOR, $accuracy, $unit->radius_meter);

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        $posisiMencurigakan = false;
        if ($request->filled('pos_a_lat') && $request->filled('pos_a_lng')) {
            $jarakAB = $this->calculateDistance($request->pos_a_lat, $request->pos_a_lng, $request->latitude, $request->longitude);
            $waktuAB = null;
            if ($request->filled('pos_a_captured_at') && $request->filled('captured_at')) {
                $waktuAB = Carbon::parse($request->pos_a_captured_at)->diffInSeconds(Carbon::parse($request->captured_at));
            }
            if ($jarakAB < 3 && $waktuAB !== null && $waktuAB > 10) {
                $posisiMencurigakan = true;
            }
            if ($request->filled('pos_a_accuracy') && (string) $request->pos_a_accuracy === (string) $request->accuracy) {
                $posisiMencurigakan = true;
            }
        }

        $lokasiPerluReview = $mockSuspect || ($accuracy !== null && $accuracy < 10);
        $capturedAt = $request->filled('captured_at') ? Carbon::parse($request->captured_at) : null;

        $now = Carbon::now();
        $overlayData = [
            'label' => $isLembur ? 'BUKTI LEMBUR' : 'BUKTI PRESENSI',
            'is_lembur' => $isLembur,
            'pegawai' => $pegawai->nama_lengkap,
            'unit' => $unit->nama,
            'time' => $now->format('H:i:s').' WIB',
            'date' => $now->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'coordinates' => number_format((float) $request->latitude, 6).', '.number_format((float) $request->longitude, 6),
            'accuracy' => number_format($accuracy, 0).'m',
        ];

        $imageName = app(ImageUploadService::class)->storeBase64(
            $request->foto,
            $isLembur ? 'presensi/lembur' : 'presensi',
            $overlayData,
            5 * 1024 * 1024,
            ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap]
        );

        DB::transaction(function () use ($request, $pegawai, $jadwal, $unit, $distance, $imageName, $isLembur, $accuracy, $speed, $capturedAt, $lokasiPerluReview, $posisiMencurigakan, $tipePresensi, $hariIni) {
            // Cari presensi existing
            if ($isLembur) {
                $presensi = Presensi::where('pegawai_id', $pegawai->id)
                    ->where('is_lembur', true)
                    ->where('tanggal', Carbon::today())
                    ->lockForUpdate()
                    ->first();
            } elseif ($tipePresensi === 'kantor') {
                $presensi = Presensi::where('pegawai_id', $pegawai->id)
                    ->where('tipe_presensi', 'kantor')
                    ->where('tanggal', Carbon::today())
                    ->lockForUpdate()
                    ->first();
            } else {
                $presensi = Presensi::where('pegawai_id', $pegawai->id)
                    ->where('jadwal_id', $request->jadwal_id)
                    ->where('tanggal', Carbon::today())
                    ->lockForUpdate()
                    ->first();
            }

            if (! $presensi) {
                $presensi = new Presensi([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $request->jadwal_id,
                    'tanggal' => Carbon::today(),
                ]);
            }

            $presensi->unit_sekolah_id = $unit->id;
            $presensi->is_lembur = $isLembur;
            $presensi->tipe_presensi = $tipePresensi;

            if ($request->tipe === 'masuk') {
                if ($presensi->jam_masuk) {
                    throw ValidationException::withMessages(['conflict' => PresensiMessages::SUDAH_ABSEN_MASUK]);
                }
                $presensi->jam_masuk = Carbon::now()->format('H:i:s');
                $presensi->latitude_masuk = $request->latitude;
                $presensi->longitude_masuk = $request->longitude;
                $presensi->foto_masuk = $imageName;
                $presensi->jarak_masuk_meter = $distance;
                $presensi->akurasi_masuk = $accuracy;
                $presensi->kecepatan_masuk = $speed;
                $presensi->captured_at = $capturedAt;
                $presensi->lokasi_perlu_review = $lokasiPerluReview;
                $presensi->pos_a_lat = $request->filled('pos_a_lat') ? $request->pos_a_lat : null;
                $presensi->pos_a_lng = $request->filled('pos_a_lng') ? $request->pos_a_lng : null;
                $presensi->pos_a_accuracy = $request->filled('pos_a_accuracy') ? $request->pos_a_accuracy : null;
                $presensi->pos_a_captured_at = $request->filled('pos_a_captured_at') ? $request->pos_a_captured_at : null;
                $presensi->posisi_mencurigakan = $posisiMencurigakan;

                if ($isLembur) {
                    $presensi->status = 'hadir';
                    $presensi->lembur_status = 'pending';
                } else {
                    $jamMulai = $tipePresensi === 'kantor' ? $unit->jam_masuk_kantor : $jadwal->jam_mulai;
                    $presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jamMulai);
                }
            } else {
                if (! $presensi->exists || ! $presensi->jam_masuk) {
                    throw ValidationException::withMessages(['conflict' => PresensiMessages::BELUM_ABSEN_MASUK]);
                }
                if ($presensi->jam_keluar) {
                    throw ValidationException::withMessages(['conflict' => PresensiMessages::SUDAH_ABSEN_KELUAR]);
                }
                $presensi->jam_keluar = Carbon::now()->format('H:i:s');
                $presensi->latitude_keluar = $request->latitude;
                $presensi->longitude_keluar = $request->longitude;
                $presensi->foto_keluar = $imageName;
                $presensi->jarak_keluar_meter = $distance;
                $presensi->akurasi_keluar = $accuracy;
                $presensi->kecepatan_keluar = $speed;
                $presensi->pos_a_lat = $request->filled('pos_a_lat') ? $request->pos_a_lat : null;
                $presensi->pos_a_lng = $request->filled('pos_a_lng') ? $request->pos_a_lng : null;
                $presensi->pos_a_accuracy = $request->filled('pos_a_accuracy') ? $request->pos_a_accuracy : null;
                $presensi->pos_a_captured_at = $request->filled('pos_a_captured_at') ? $request->pos_a_captured_at : null;
                $presensi->posisi_mencurigakan = $posisiMencurigakan;

                if (! $isLembur && $tipePresensi === 'mengajar') {
                    $latestSelesai = Jadwal::where('pegawai_id', $pegawai->id)
                        ->where('hari', $hariIni)
                        ->where('jenis_jadwal', '!=', 'lembur')
                        ->max('jam_selesai');
                    if ($latestSelesai) {
                        $batasPulang = Carbon::parse($latestSelesai)->subMinutes(30)->format('H:i:s');
                        if ($presensi->jam_keluar < $batasPulang) {
                            $lokasiPerluReview = true;
                        }
                    }
                } elseif (! $isLembur && $tipePresensi === 'kantor' && $unit->jam_pulang_kantor) {
                    $batasPulang = Carbon::parse($unit->jam_pulang_kantor)->subMinutes(30)->format('H:i:s');
                    if ($presensi->jam_keluar < $batasPulang) {
                        $lokasiPerluReview = true;
                    }
                }

                $presensi->lokasi_perlu_review = $presensi->lokasi_perlu_review || $lokasiPerluReview;
            }

            $presensi->save();
        });

        $label = $isLembur ? PresensiMessages::LABEL_LEMBUR : PresensiMessages::LABEL_ABSEN;
        $successMessage = $request->tipe === 'masuk'
            ? sprintf($isLembur ? PresensiMessages::LEMBUR_MASUK_SUCCESS : PresensiMessages::ABSEN_MASUK_SUCCESS, $distance)
            : sprintf($isLembur ? PresensiMessages::LEMBUR_KELUAR_SUCCESS : PresensiMessages::ABSEN_KELUAR_SUCCESS, $distance);

        return response()->json([
            'success' => true,
            'message' => $successMessage,
        ]);
    }
}
