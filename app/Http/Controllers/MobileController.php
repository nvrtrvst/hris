<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Presensi;
use App\Services\ImageUploadService;
use App\Traits\CalculatesDistance;
use App\Traits\ResolvesPegawai;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $jadwals = Jadwal::with(['unitSekolah', 'mataPelajaran', 'kelas'])
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

    public function absen()
    {
        $pegawai = $this->getPegawai();
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = Jadwal::with('unitSekolah')
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
            'jadwal_id' => 'required|exists:jadwal,id',
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
        $jadwal = Jadwal::with('unitSekolah')->findOrFail($request->jadwal_id);
        $unit = $jadwal->unitSekolah;

        $distance = $this->calculateDistance($request->latitude, $request->longitude, $unit->latitude, $unit->longitude);

        if ($distance > $unit->radius_meter) {
            return back()->withErrors(['geofence' => "Anda berada di luar jangkauan Unit Sekolah. Jarak Anda: {$distance} meter (Batas: {$unit->radius_meter}m)"]);
        }

        // [ANTISPOOF] Deteksi lokasi palsu (Opsi A: heuristik web)
        $accuracy = $request->filled('accuracy') ? (float) $request->accuracy : null;
        $speed = $request->filled('speed') ? (float) $request->speed : null;
        $mockSuspect = (bool) $request->input('mock_suspect', false);

        if ($accuracy !== null && $accuracy <= 0) {
            return back()->withErrors(['geofence' => 'Akurasi lokasi 0 meter (dicurigai lokasi palsu / mock GPS). Gunakan GPS asli.']);
        }
        if ($accuracy !== null && $accuracy > $unit->radius_meter) {
            return back()->withErrors(['geofence' => "Akurasi GPS buruk ({$accuracy}m > batas {$unit->radius_meter}m). Coba dari tempat terbuka."]);
        }

        $lokasiPerluReview = $mockSuspect || ($accuracy !== null && $accuracy < 10);
        $capturedAt = $request->filled('captured_at') ? Carbon::parse($request->captured_at) : null;

        $imageName = app(ImageUploadService::class)->storeBase64($request->foto, 'presensi');

        DB::transaction(function () use ($request, $pegawai, $jadwal, $unit, $distance, $imageName) {
            $presensi = Presensi::where('pegawai_id', $pegawai->id)
                ->where('jadwal_id', $request->jadwal_id)
                ->where('tanggal', Carbon::today())
                ->lockForUpdate()
                ->first();

            if (! $presensi) {
                $presensi = new Presensi([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $request->jadwal_id,
                    'tanggal' => Carbon::today(),
                ]);
            }

            $presensi->unit_sekolah_id = $unit->id;

            if ($request->tipe === 'masuk') {
                if ($presensi->jam_masuk) {
                    throw ValidationException::withMessages(['conflict' => 'Anda sudah melakukan absen masuk untuk jadwal ini.']);
                }
                $presensi->jam_masuk = Carbon::now()->format('H:i:s');
                $presensi->latitude_masuk = $request->latitude;
                $presensi->longitude_masuk = $request->longitude;
                $presensi->foto_masuk = '/storage/'.$imageName;
                $presensi->jarak_masuk_meter = $distance;
                $presensi->akurasi_masuk = $accuracy;
                $presensi->kecepatan_masuk = $speed;
                $presensi->captured_at = $capturedAt;
                $presensi->lokasi_perlu_review = $lokasiPerluReview;

                $presensi->status = Carbon::now()->format('H:i:s') > $jadwal->jam_mulai ? 'telat' : 'hadir';
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
                $presensi->foto_keluar = '/storage/'.$imageName;
                $presensi->jarak_keluar_meter = $distance;
                $presensi->akurasi_keluar = $accuracy;
                $presensi->kecepatan_keluar = $speed;
                $presensi->lokasi_perlu_review = $presensi->lokasi_perlu_review || $lokasiPerluReview;
            }

            $presensi->save();
        });

        return redirect()->route('mobile.dashboard')->with('message', "Absen {$request->tipe} berhasil dicatat! Jarak: {$distance}m");
    }
}
