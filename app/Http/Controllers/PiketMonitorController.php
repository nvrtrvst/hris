<?php

namespace App\Http\Controllers;

use App\Models\LaporanPiket;
use App\Models\PiketItemTemplate;
use App\Models\PiketPos;
use App\Models\PiketSesi;
use App\Services\PiketService;
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
            'petugas.pegawai:id,nama_lengkap',
            'petugas.area:id,nama',
            'petugas.laporan',
            'posLogs',
        ])->whereDate('tanggal', $tanggal);

        if (! $user->can('view_all_units')) {
            $query->where('unit_sekolah_id', $user->unit_sekolah_id);
        }

        $sessions = $query->orderBy('jam_mulai')->get()->map(function (PiketSesi $sesi) {
            $wajib = PiketItemTemplate::where('unit_sekolah_id', $sesi->unit_sekolah_id)->where('aktif', true)->where('wajib', true)->count();
            $posBelum = PiketService::unservedRequiredPos($sesi);
            $rounds = PiketService::rounds($sesi);
            $isPast = $sesi->tanggal->lt(now()->startOfDay());
            $now = now()->format('H:i');
            $roundTerlewat = collect($rounds)->filter(fn ($r) => $r['selesai_at'] === null
                && ($isPast || ($sesi->tanggal->isToday() && $now > $r['jendela_selesai'])))->count();
            $insiden = 0;
            $temuan = 0;

            foreach ($sesi->petugas as $petugas) {
                $items = $petugas->laporan?->items ?? [];
                $insiden += count($items[LaporanPiket::ITEM_INSIDEN]['entries'] ?? []);
                $temuan += count($items[LaporanPiket::ITEM_KELAS_KOSONG]['entries'] ?? []);
            }

            return [
                'id' => $sesi->id,
                'unit' => $sesi->unit_sekolah_id,
                'jam' => $sesi->jam_mulai.'-'.$sesi->jam_selesai,
                'status' => $sesi->status,
                'petugas' => $sesi->petugas->map(fn ($p) => [
                    'nama' => $p->pegawai?->nama_lengkap,
                    'area' => $p->area?->nama,
                    'peran' => $p->peran,
                    'selesai' => $p->laporan?->doneCount() ?? 0,
                    'wajib' => $wajib,
                    'submitted' => $p->laporan?->status === LaporanPiket::STATUS_SUBMITTED,
                ])->values(),
                'pos_belum' => $posBelum,
                'rounds' => $rounds,
                'round_terlewat' => $roundTerlewat,
                'insiden' => $insiden,
                'temuan' => $temuan,
                'perlu_tindakan' => $posBelum !== [] || $roundTerlewat > 0 || $insiden > 0,
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
