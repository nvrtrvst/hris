<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaporanTindakLanjutRequest;
use App\Models\LaporanTindakLanjut;
use App\Models\UnitSekolah;
use App\Services\LaporanPengawasanService;
use App\Services\LaporanTindakLanjutAccess;
use Illuminate\Support\Facades\DB;

class LaporanTindakLanjutController extends Controller
{
    public function store(LaporanTindakLanjutRequest $request, LaporanPengawasanService $reports, LaporanTindakLanjutAccess $access)
    {
        $input = $request->validated();
        $user = $request->user();
        abort_unless($access->inUnit($user, $input['unit_sekolah_id']), 403);
        $report = $reports->build(['from' => $input['tanggal'], 'to' => $input['tanggal'], 'unit_sekolah_id' => $input['unit_sekolah_id']], $user, false);
        $finding = collect($report['followups'])->firstWhere('key', $input['finding_key']);
        abort_unless($finding, 422, 'Temuan tidak tersedia. Muat ulang laporan.');
        if (! empty($input['penanggung_jawab_id'])) {
            abort_unless($access->candidates($input['unit_sekolah_id'])->contains('id', $input['penanggung_jawab_id'])
             || ((int) $input['penanggung_jawab_id'] === $user->id && $user->can('view_all_units')), 422, 'Penanggung jawab tidak berwenang di unit ini.');
        }
        abort_if(($input['hasil'] ?? null) === 'alpa_terkonfirmasi' && ($finding['jenis'] !== 'belum_tercatat' || $finding['source_changed']), 422, 'Konfirmasi alpa hanya untuk ketidakhadiran.');
        $record = DB::transaction(function () use ($input, $finding, $user) {
            // Lock the unit so concurrent creation of the same finding is serialized too.
            UnitSekolah::whereKey($input['unit_sekolah_id'])->lockForUpdate()->firstOrFail();
            $record = LaporanTindakLanjut::where('finding_key', $input['finding_key'])->lockForUpdate()->first();
            abort_if((int) ($record?->version ?? 0) !== $input['version'], 409, 'Catatan sudah berubah. Muat ulang sebelum menyimpan.');
            $record ??= new LaporanTindakLanjut([
                'finding_key' => $input['finding_key'], 'pegawai_id' => $finding['pegawai_id'],
                'unit_sekolah_id' => $input['unit_sekolah_id'], 'tanggal' => $finding['tanggal'], 'jenis' => $finding['jenis'],
                'snapshot' => collect($finding)->except(['record', 'candidates'])->all(),
            ]);
            $history = $record->history ?? [];
            $history[] = ['actor' => $user->name, 'actor_id' => $user->id, 'at' => now()->toIso8601String(),
                'status' => $input['status'], 'catatan' => $input['catatan'] ?? null, 'hasil' => $input['status'] === 'selesai' ? $input['hasil'] : null,
                'penanggung_jawab_id' => $input['penanggung_jawab_id'] ?? null];
            $record->fill(collect($input)->only(['status', 'catatan', 'penanggung_jawab_id'])->all());
            $record->hasil = $input['status'] === 'selesai' ? $input['hasil'] : null;
            $record->history = $history;
            $record->version = $input['version'] + 1;
            $record->save();

            return $record;
        });

        return response()->json(['record' => $record]);
    }
}
