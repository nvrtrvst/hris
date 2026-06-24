<?php

namespace App\Http\Controllers;

use App\Models\Presensi;
use App\Models\Jadwal;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class MobileController extends Controller
{
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * asin(sqrt($a));
        return round($earthRadius * $c);
    }

    private function getPegawai() {
        $pegawai = Pegawai::where('user_id', Auth::id())->first();
        if ($pegawai) {
            return $pegawai;
        }

        // Jika Admin sedang melakukan Simulasi Absen, 
        // kita cari secara cerdas Pegawai mana saja yang *PUNYA* jadwal hari ini,
        // agar dropdown jadwal tidak kosong saat dites.
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = Jadwal::where('hari', $hariIniIndo)->first();
        if ($jadwalHariIni) {
            return Pegawai::find($jadwalHariIni->pegawai_id);
        }

        return Pegawai::first();
    }

    public function dashboard()
    {
        $pegawai = $this->getPegawai();
        
        $presensiHariIni = Presensi::with('unitSekolah')->where('pegawai_id', $pegawai->id)
            ->where('tanggal', Carbon::today())
            ->first();

        // Get jadwal hari ini
        $hariMap = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hariIniIndo = $hariMap[Carbon::now()->format('l')];

        $jadwalHariIni = Jadwal::with('unitSekolah')
            ->where('pegawai_id', $pegawai->id)
            ->where('hari', $hariIniIndo)
            ->get();

        return inertia('Mobile/Dashboard', [
            'pegawai' => $pegawai,
            'presensi' => $presensiHariIni,
            'jadwals' => $jadwalHariIni
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
            'presensiHariIni' => $presensiHariIni
        ]);
    }

    public function storeAbsen(Request $request)
    {
        $request->validate([
            'jadwal_id' => 'required|exists:jadwal,id',
            'tipe' => 'required|in:masuk,keluar',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto' => 'required|string',
        ]);

        $pegawai = $this->getPegawai();
        $jadwal = Jadwal::with('unitSekolah')->findOrFail($request->jadwal_id);
        $unit = $jadwal->unitSekolah;

        $distance = $this->calculateDistance($request->latitude, $request->longitude, $unit->latitude, $unit->longitude);
        
        if ($distance > $unit->radius_meter) {
            return back()->withErrors(['geofence' => "Anda berada di luar jangkauan Unit Sekolah. Jarak Anda: {$distance} meter (Batas: {$unit->radius_meter}m)"]);
        }

        $image = $request->foto;
        $image = str_replace('data:image/jpeg;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $imageName = 'presensi/'.time().'.jpg';
        Storage::disk('public')->put($imageName, base64_decode($image));

        \Illuminate\Support\Facades\DB::transaction(function() use ($request, $pegawai, $jadwal, $unit, $distance, $imageName) {
            $presensi = Presensi::where('pegawai_id', $pegawai->id)
                ->where('jadwal_id', $request->jadwal_id)
                ->where('tanggal', Carbon::today())
                ->lockForUpdate()
                ->first();

            if (!$presensi) {
                $presensi = new Presensi([
                    'pegawai_id' => $pegawai->id,
                    'jadwal_id' => $request->jadwal_id,
                    'tanggal' => Carbon::today(),
                ]);
            }

            $presensi->unit_sekolah_id = $unit->id;

            if ($request->tipe === 'masuk') {
                if ($presensi->jam_masuk) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['conflict' => 'Anda sudah melakukan absen masuk untuk jadwal ini.']);
                }
                $presensi->jam_masuk = Carbon::now()->format('H:i:s');
                $presensi->latitude_masuk = $request->latitude;
                $presensi->longitude_masuk = $request->longitude;
                $presensi->foto_masuk = '/storage/'.$imageName;
                $presensi->jarak_masuk_meter = $distance;
                
                $presensi->status = Carbon::now()->format('H:i:s') > $jadwal->jam_mulai ? 'telat' : 'hadir';
            } else {
                if (!$presensi->exists || !$presensi->jam_masuk) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['conflict' => 'Anda belum absen masuk.']);
                }
                if ($presensi->jam_keluar) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['conflict' => 'Anda sudah melakukan absen keluar.']);
                }
                $presensi->jam_keluar = Carbon::now()->format('H:i:s');
                $presensi->latitude_keluar = $request->latitude;
                $presensi->longitude_keluar = $request->longitude;
                $presensi->foto_keluar = '/storage/'.$imageName;
                $presensi->jarak_keluar_meter = $distance;
            }

            $presensi->save();
        });

        return redirect()->route('mobile.dashboard')->with('message', "Absen {$request->tipe} berhasil dicatat! Jarak: {$distance}m");
    }
}
