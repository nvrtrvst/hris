<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Models\Jadwal;
use App\Models\Presensi;
use App\Services\ImageUploadService;
use App\Traits\CalculatesDistance;
use App\Traits\ResolvesPegawai;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MobileController extends Controller
{
    use CalculatesDistance, ResolvesPegawai;

    public function dashboard()
    {
        $pegawai = $this->getPegawai();

        $presensiHariIni = Presensi::with('unitSekolah')->where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today())
            ->first();

        $presensiTerbaru = Presensi::with('unitSekolah')
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal', '>=', Carbon::today()->subDays(3))
            ->where('tanggal', '<=', Carbon::today())
            ->orderBy('tanggal', 'desc')
            ->get();

        return inertia('Mobile/Dashboard', [
            'pegawai' => $pegawai,
            'presensi' => $presensiHariIni,
            'presensiSeminggu' => $presensiTerbaru,
        ]);
    }

    public function riwayat(Request $request)
    {
        $pegawai = $this->getPegawai();

        $bulan = $request->input('bulan', Carbon::now()->format('m'));
        $tahun = $request->input('tahun', Carbon::now()->format('Y'));

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

    // Proxy ke API internal app keuangan untuk mengambil daftar siswa per kelas.
    // Kunci API disimpan server-side (tidak dikirim ke browser).
    public function siswaKelas(Request $request)
    {
        $unit = $request->query('unit');
        $tingkat = $request->query('tingkat');
        $kelas = $request->query('kelas');
        $jurusan = $request->query('jurusan');

        if (! $unit || ! $tingkat) {
            return response()->json(['success' => false, 'siswa' => []]);
        }

        $base = config('keuangan.url');
        $key = config('keuangan.key');

        $response = Http::withHeaders(['x-internal-key' => $key])
            ->timeout(8)
            ->get($base.'/api/integration/siswa-by-class', [
                'unit' => $unit,
                'tingkat' => $tingkat,
                'kelas' => $kelas,
                'jurusan' => $jurusan,
            ]);

        if (! $response->successful()) {
            return response()->json(['success' => false, 'siswa' => []]);
        }

        $data = $response->json('data') ?? [];

        return response()->json([
            'success' => true,
            'school_name' => $data['schoolName'] ?? null,
            'class_labels' => $data['classLabels'] ?? [],
            'siswa' => $data['students'] ?? [],
        ]);
    }

    // Proxy ke API internal app keuangan untuk menyimpan absen siswa.
    public function siswaAbsen(Request $request)
    {
        $unit = $request->input('unit');
        $tingkat = $request->input('tingkat');
        $kelas = $request->input('kelas');
        $jurusan = $request->input('jurusan');
        $nis = $request->input('nis');
        $status = $request->input('status');
        $tanggal = $request->input('tanggal');

        if (! $unit || ! $tingkat || ! $nis || ! $status) {
            return response()->json(['success' => false, 'message' => 'Data kurang lengkap.'], 400);
        }

        $base = config('keuangan.url');
        $key = config('keuangan.key');

        $response = Http::withHeaders(['x-internal-key' => $key])
            ->timeout(8)
            ->post($base.'/api/integration/siswa-absen', [
                'unit' => $unit,
                'tingkat' => $tingkat,
                'kelas' => $kelas,
                'jurusan' => $jurusan,
                'nis' => $nis,
                'status' => $status,
                'tanggal' => $tanggal,
            ]);

        if (! $response->successful()) {
            $msg = $response->json('message') ?? 'Gagal menyimpan absen.';

            return response()->json(['success' => false, 'message' => $msg], $response->status());
        }

        return response()->json(['success' => true, 'status' => $response->json('status')]);
    }

    // Proxy batch: kirim semua absen siswa sekaligus ke app keuangan.
    public function siswaAbsenBatch(Request $request)
    {
        $unit = $request->input('unit');
        $tingkat = $request->input('tingkat');
        $kelas = $request->input('kelas');
        $jurusan = $request->input('jurusan');
        $tanggal = $request->input('tanggal');
        $absens = $request->input('absens', []);

        if (! $unit || ! $tingkat || ! is_array($absens) || count($absens) === 0) {
            return response()->json(['success' => false, 'message' => 'Data kurang lengkap.'], 400);
        }

        $base = config('keuangan.url');
        $key = config('keuangan.key');

        $response = Http::withHeaders(['x-internal-key' => $key])
            ->timeout(15)
            ->post($base.'/api/integration/siswa-absen-batch', [
                'unit' => $unit,
                'tingkat' => $tingkat,
                'kelas' => $kelas,
                'jurusan' => $jurusan,
                'tanggal' => $tanggal,
                'absens' => $absens,
            ]);

        if (! $response->successful()) {
            $msg = $response->json('message') ?? 'Gagal menyimpan absen.';

            return response()->json(['success' => false, 'message' => $msg], $response->status());
        }

        return response()->json(['success' => true, 'saved' => $response->json('saved')]);
    }

    public function absen()
    {
        $pegawai = $this->getPegawai();
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
        ]);
    }

    public function storeAbsen(Request $request)
    {
        $request->validate([
            'jadwal_id' => 'nullable|exists:jadwal,id',
            'is_lembur' => 'nullable|boolean',
            'tipe' => 'required|in:masuk,keluar',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'captured_at' => 'nullable|string',
            'mock_suspect' => 'nullable|boolean',
            'foto' => ['required', 'string', 'regex:/^data:image\/\w+;base64,/'],
        ]);

        $pegawai = $this->getPegawai();
        $isLembur = (bool) $request->input('is_lembur', false);

        // Resolve unit: dari jadwal atau primary unit pegawai
        if ($request->jadwal_id) {
            $jadwal = Jadwal::with('unitSekolah')->findOrFail($request->jadwal_id);
            $unit = $jadwal->unitSekolah;
        } elseif ($isLembur) {
            $primaryUnit = $pegawai->units()->first();
            if (! $primaryUnit) {
                $message = PresensiMessages::PEGAWAI_TIDAK_PUNYA_UNIT;

                return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
            }
            $unit = $primaryUnit;
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

        $accuracy = $request->filled('accuracy') ? (float) $request->accuracy : null;
        $speed = $request->filled('speed') ? (float) $request->speed : null;
        $mockSuspect = (bool) $request->input('mock_suspect', false);

        if ($accuracy !== null && $accuracy <= 0) {
            $message = PresensiMessages::GEOFENCE_ACCURACY_ZERO;

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }
        if ($accuracy !== null && $accuracy > $unit->radius_meter) {
            $message = sprintf(PresensiMessages::GEOFENCE_ACCURACY_POOR, $accuracy, $unit->radius_meter);

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
        }

        $lokasiPerluReview = $mockSuspect || ($accuracy !== null && $accuracy < 10);
        $capturedAt = $request->filled('captured_at') ? Carbon::parse($request->captured_at) : null;

        $imageName = app(ImageUploadService::class)->storeBase64($request->foto, $isLembur ? 'presensi/lembur' : 'presensi');

        DB::transaction(function () use ($request, $pegawai, $jadwal, $unit, $distance, $imageName, $isLembur, $accuracy, $speed, $capturedAt, $lokasiPerluReview) {
            // Cari presensi existing
            if ($isLembur) {
                $presensi = Presensi::where('pegawai_id', $pegawai->id)
                    ->where('is_lembur', true)
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

                if ($isLembur) {
                    $presensi->status = 'hadir';
                    $presensi->lembur_status = 'pending';
                } else {
                    $presensi->status = Carbon::now()->format('H:i:s') > $jadwal->jam_mulai ? 'telat' : 'hadir';
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
