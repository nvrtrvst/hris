<?php

namespace App\Http\Controllers;

use App\Constants\PresensiMessages;
use App\Events\IzinBaruEvent;
use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PayrollLockHelper;
use App\Models\PengajuanIzin;
use App\Models\User;
use App\Notifications\IzinBaru;
use App\Services\ImageUploadService;
use App\Traits\ResolvesPegawai;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        // Create.jsx menampilkan sisa_cuti — eager-load + append eksplisit (P2).
        $pegawai->loadCutiInfo();

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
            'bukti_foto' => ['nullable', 'string', 'max:'.PresensiMessages::MAX_FOTO_BASE64, 'regex:/^data:image\/\w+;base64,/'],
        ]);

        if ($request->jenis_izin === 'sakit' && ! $request->bukti_foto) {
            return back()->withErrors(['bukti_foto' => 'Surat keterangan dokter / bukti foto wajib dilampirkan untuk pengajuan Sakit.']);
        }

        if ($request->jenis_izin === 'cuti') {
            // sisa_cuti dipakai utk validasi — eager-load pengajuanIzins + append eksplisit (P2).
            $pegawai->loadCutiInfo();
            $requestedDays = Carbon::parse($request->tanggal_mulai)->diffInDays(Carbon::parse($request->tanggal_selesai)) + 1;
            if ($requestedDays > $pegawai->sisa_cuti) {
                return back()->withErrors(['alasan' => 'Sisa cuti Anda tidak mencukupi. Anda mengajukan '.$requestedDays.' hari, sedangkan sisa cuti: '.$pegawai->sisa_cuti.' hari.']);
            }
        }

        // Cek apakah ada tanggal dalam range yang periode payroll-nya sudah dikunci
        $checkDate = Carbon::parse($request->tanggal_mulai);
        $endDate = Carbon::parse($request->tanggal_selesai);
        while ($checkDate->lte($endDate)) {
            if (PayrollLockHelper::isPeriodLocked($pegawai->id, $checkDate)) {
                return back()->withErrors(['tanggal_mulai' => 'Periode penggajian untuk bulan '.$checkDate->format('m-Y').' sudah dikunci. Tidak bisa mengajukan izin untuk tanggal tersebut.']);
            }
            $checkDate->addDay();
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
            $imageName = app(ImageUploadService::class)->storeBase64(
                $request->bukti_foto,
                'izin',
                null,
                PresensiMessages::MAX_FOTO_BYTES,
                ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap]
            );
            $pengajuan->bukti_foto = $imageName;
        }

        try {
            $pengajuan->save();

            $approvers = ApprovalHelper::determineApprovers($pegawai);
            $pengajuan->update([
                'approver_l1_id' => $approvers['l1_id'],
                'approver_l2_id' => $approvers['l2_id'],
            ]);
        } catch (\Throwable $e) {
            // Jangan tinggalkan file bukti foto yang menggantung bila save gagal.
            if (! empty($imageName)) {
                Storage::disk(config('filesystems.presensi_disk', 'presensi'))->delete($imageName);
            }
            throw $e;
        }

        NotificationHelper::sendSafely(User::find($approvers['l1_id']), new IzinBaru($pengajuan));

        // Real-time: beri tahu approver L1 via Reverb (F2b).
        IzinBaruEvent::dispatch($pengajuan);

        return redirect()->route('presensi.izin.index')->with('message', 'Pengajuan berhasil dikirim dan menunggu persetujuan.');
    }
}
