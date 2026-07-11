<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Services\ImageUploadService;
use App\Traits\ResolvesPegawai;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MobileIzinController extends Controller
{
    use ResolvesPegawai;

    public function index()
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $pengajuan = PengajuanIzin::where('pegawai_id', $pegawai->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Mobile/Izin/Index', [
            'pengajuan' => $pengajuan,
        ]);
    }

    public function create()
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        return Inertia::render('Mobile/Izin/Create', [
            'pegawai' => $pegawai,
        ]);
    }

    public function store(Request $request)
    {
        $pegawai = $this->getPegawai();
        if (! $pegawai) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'jenis_izin' => 'required|in:sakit,izin,cuti',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'bukti_foto' => ['nullable', 'string', 'regex:/^data:image\/\w+;base64,/'],
        ]);

        if ($request->jenis_izin === 'sakit' && ! $request->bukti_foto) {
            return back()->withErrors(['bukti_foto' => 'Surat keterangan dokter / bukti foto wajib dilampirkan untuk pengajuan Sakit.']);
        }

        if ($request->jenis_izin === 'cuti') {
            $requestedDays = Carbon::parse($request->tanggal_mulai)->diffInDays(Carbon::parse($request->tanggal_selesai)) + 1;
            if ($requestedDays > $pegawai->sisa_cuti) {
                return back()->withErrors(['alasan' => 'Sisa cuti Anda tidak mencukupi. Anda mengajukan '.$requestedDays.' hari, sedangkan sisa cuti: '.$pegawai->sisa_cuti.' hari.']);
            }
        }

        $pengajuan = new PengajuanIzin([
            'pegawai_id' => $pegawai->id,
            'jenis_izin' => $request->jenis_izin,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'alasan' => $request->alasan,
            'status' => 'pending',
        ]);

        if ($request->bukti_foto) {
            $imageName = app(ImageUploadService::class)->storeBase64($request->bukti_foto, 'izin');
            $pengajuan->bukti_foto = $imageName;
        }

        $pengajuan->save();

        return redirect()->route('mobile.izin.index')->with('message', 'Pengajuan berhasil dikirim dan menunggu persetujuan.');
    }
}
