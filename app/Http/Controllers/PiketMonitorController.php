<?php

namespace App\Http\Controllers;

use App\Models\LaporanPiket;
use App\Models\PiketPos;
use App\Models\PiketSesi;
use App\Services\PiketService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PiketMonitorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('view_presensi'), 403);

        $tanggal = $request->input('tanggal') ?: now()->toDateString();

        $query = PiketSesi::with([
            'unitSekolah:id,nama,piket_mode',
            'petugas.pegawai:id,nama_lengkap',
            'petugas.area:id,nama',
            'petugas.laporan',
            'posLogs',
        ])->whereDate('tanggal', $tanggal);

        if (! $user->can('view_all_units')) {
            $query->where('unit_sekolah_id', $user->unit_sekolah_id);
        }

        $sessions = $query->orderBy('jam_mulai')->get()->map(function (PiketSesi $sesi) {
            $posBelum = PiketService::unservedRequiredPos($sesi);
            $rounds = PiketService::rounds($sesi);
            $isPast = $sesi->tanggal->lt(now()->startOfDay());
            $now = now()->format('H:i');
            $roundTerlewat = collect($rounds)->filter(fn ($r) => $r['selesai_at'] === null
                && ($isPast || ($sesi->tanggal->isToday() && $now > $r['jendela_selesai'])))->count();

            $petugas = $sesi->petugas->map(function ($p) use ($sesi) {
                $required = PiketService::wajibCodes($sesi->unitSekolah, $p);
                $done = count(array_filter($required, fn ($kode) => $p->laporan?->isItemDone($kode)));
                $items = $p->laporan?->items ?? [];
                $batas = Carbon::parse($sesi->tanggal->format('Y-m-d').' '.substr((string) $sesi->jam_selesai, 0, 5));

                return [
                    'nama' => $p->pegawai?->nama_lengkap,
                    'area' => $p->area?->nama,
                    'peran' => $p->peran,
                    'selesai' => $done,
                    'wajib' => count($required),
                    'submitted' => $p->laporan?->status === LaporanPiket::STATUS_SUBMITTED,
                    'submitted_at' => $p->laporan?->submitted_at?->format('H:i'),
                    'terlambat_lapor' => $p->laporan?->status === LaporanPiket::STATUS_SUBMITTED
                        && $p->laporan->submitted_at
                        && $p->laporan->submitted_at->gt($batas),
                    'status' => $p->status,
                    'status_alasan' => $p->status_alasan,
                    'insiden_belum' => collect($items[LaporanPiket::ITEM_INSIDEN]['entries'] ?? [])
                        ->filter(fn ($e) => empty($e['verified_at']))->count(),
                    'temuan' => count($items[LaporanPiket::ITEM_KELAS_KOSONG]['entries'] ?? []),
                    'perlu_cek' => $p->laporan?->perluCekCount() ?? 0,
                ];
            })->values();

            $insidenBelum = $petugas->sum('insiden_belum');
            $temuan = $petugas->sum('temuan');
            $perluCek = $petugas->sum('perlu_cek');
            $tidakHadir = $petugas->where('status', 'tidak_hadir')->count();

            $perluAlasan = [];
            if ($posBelum !== []) {
                $perluAlasan[] = 'Pos belum: '.implode(', ', $posBelum);
            }
            if ($roundTerlewat > 0) {
                $perluAlasan[] = 'Fase terlewat: '.$roundTerlewat;
            }
            if ($insidenBelum > 0) {
                $perluAlasan[] = 'Insiden belum diverifikasi: '.$insidenBelum;
            }
            if ($perluCek > 0) {
                $perluAlasan[] = 'Temuan perlu cek: '.$perluCek;
            }
            if ($tidakHadir > 0) {
                $perluAlasan[] = 'Petugas tidak hadir: '.$tidakHadir;
            }

            return [
                'id' => $sesi->id,
                'unit' => $sesi->unit_sekolah_id,
                'jam' => $sesi->jam_mulai.'-'.$sesi->jam_selesai,
                'status' => $sesi->status,
                'petugas' => $petugas,
                'pos_belum' => $posBelum,
                'rounds' => $rounds,
                'round_terlewat' => $roundTerlewat,
                'insiden' => $insidenBelum,
                'temuan' => $temuan,
                'perlu_cek' => $perluCek,
                'perlu_alasan' => $perluAlasan,
                'perlu_tindakan' => $perluAlasan !== [],
            ];
        });

        return Inertia::render('Piket/Index', [
            'tanggal' => $tanggal,
            'sessions' => $sessions,
            'pos' => PiketPos::where('aktif', true)->get(['id', 'nama', 'is_wajib', 'unit_sekolah_id']),
            'canManage' => $user->can('manage_master_data'),
        ]);
    }
}
