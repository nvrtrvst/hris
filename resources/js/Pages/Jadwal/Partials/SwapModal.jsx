import React from 'react';
import Modal from '@/Components/Modal';
import { ArrowLeftRight, CheckCircle2 } from 'lucide-react';
import { jenisBadge } from '@/Pages/Jadwal/Partials/jadwalMeta';

export default function SwapModal({
    show,
    onClose,
    onOpenDay,
    onOpenBulk,
    onOpenSlot,
    handleSwap,
    errors,
    jadwalById,
    swapData,
    setSwapData,
    targetPegawaiId,
    setTargetPegawaiId,
    swapCandidates,
    swapLoading,
    swapError,
    candJadwalByPegawai,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <div className="px-6 py-5">
                <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-info-light">
                        <ArrowLeftRight className="h-5 w-5 text-info" />
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-primary">Tukar Jadwal</h3>
                        <p className="text-sm text-text-secondary">Pilih pegawai dan jadwal target untuk ditukar kepemilikannya.</p>
                    </div>
                </div>

                {errors?.conflict && (
                    <div className="mb-4 rounded-card border border-danger/20 bg-danger-light p-3 text-sm text-danger">
                        {errors.conflict}
                    </div>
                )}

                <div className="mb-4">
                    <label className="mb-2 flex items-center gap-2 font-semibold form-label">
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">1</span>
                        Jadwal Asal
                    </label>
                    {(() => {
                        const j = jadwalById.get(swapData.jadwal_asal_id);
                        if (!j) return <div className="rounded-card border border-border bg-surface p-3 text-sm text-text-muted">Pilih jadwal dari matriks (ikon tukar) untuk memulai.</div>;

                        return (
                            <div className="rounded-card border border-primary/20 bg-surface p-3">
                                <div className="text-sm font-bold text-primary">{j.pegawai?.nama_lengkap}</div>
                                <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-text-secondary">
                                    <span>{j.hari}, {j.jam_mulai?.substring(0, 5)}-{j.jam_selesai?.substring(0, 5)}</span>
                                    <span className="font-medium uppercase text-primary/70">{j.jenis_jadwal}</span>
                                    {j.mata_pelajaran?.nama && <span>{j.mata_pelajaran.nama}</span>}
                                    {j.kelas_label && <span>Kls: {j.kelas_label}</span>}
                                    <span>{j.unit_sekolah?.singkatan || '-'}</span>
                                </div>
                            </div>
                        );
                    })()}
                </div>

                <div className="mb-4">
                    <label className="mb-2 flex items-center gap-2 font-semibold form-label">
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-accent text-xs font-bold text-primary-800">2</span>
                        Tukar Dengan Pegawai
                    </label>
                    <select
                        value={targetPegawaiId}
                        onChange={(e) => {
                            setTargetPegawaiId(e.target.value);
                            setSwapData({ ...swapData, jadwal_tujuan_id: '' });
                        }}
                        className="select-field"
                    >
                        <option value="">-- Pilih Pegawai --</option>
                        {(() => {
                            if (swapLoading && !swapCandidates) return <option value="" disabled>Memuat daftar pegawai...</option>;
                            if (swapError && !swapCandidates) return <option value="" disabled>{swapError}</option>;
                            const asal = jadwalById.get(swapData.jadwal_asal_id);
                            const unitId = asal?.unit_sekolah_id;

                            return (swapCandidates?.pegawais || []).filter((p) => {
                                if (asal && asal.pegawai_id === p.id) return false;
                                if (unitId) return (p.unit_ids || []).some((id) => id == unitId);

                                return true;
                            }).map((p) => <option key={p.id} value={p.id}>{p.nama_lengkap}</option>);
                        })()}
                    </select>
                </div>

                {targetPegawaiId && (
                    <div className="mb-4">
                        <label className="mb-2 flex items-center gap-2 font-semibold form-label">
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">3</span>
                            Pilih Jadwal Target
                        </label>
                        <div className="max-h-48 space-y-1.5 overflow-y-auto">
                            {(() => {
                                const asal = jadwalById.get(swapData.jadwal_asal_id);
                                const unitId = asal?.unit_sekolah_id;
                                const targets = (candJadwalByPegawai.get(Number(targetPegawaiId)) || [])
                                    .filter((j) => !unitId || j.unit_sekolah_id == unitId);
                                if (targets.length === 0) {
                                    return <p className="rounded-card border border-border bg-surface p-3 text-sm text-text-muted">Pegawai ini tidak memiliki jadwal.</p>;
                                }

                                return targets.map((j) => (
                                    <button
                                        key={j.id}
                                        type="button"
                                        onClick={() => setSwapData({ ...swapData, jadwal_tujuan_id: j.id })}
                                        className={`w-full cursor-pointer rounded-card border p-3 text-left text-sm transition-colors ${
                                            swapData.jadwal_tujuan_id === j.id
                                                ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                                : 'border-border bg-surface hover:border-primary/40'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <span className="font-bold text-text-primary">{j.hari}, {j.jam_mulai?.substring(0, 5)}-{j.jam_selesai?.substring(0, 5)}</span>
                                                <span className={`ml-2 rounded px-1.5 py-0.5 text-[11px] font-bold ${jenisBadge(j.jenis_jadwal).badge}`}>{j.jenis_jadwal}</span>
                                            </div>
                                            <div className="text-right text-xs text-text-secondary">
                                                {j.mapel_nama && <div>{j.mapel_nama}</div>}
                                                {j.kelas_label && <div>{j.kelas_label}</div>}
                                            </div>
                                        </div>
                                        {swapData.jadwal_tujuan_id === j.id && (
                                            <div className="mt-1 flex items-center gap-1 text-xs font-medium text-primary">
                                                <CheckCircle2 className="h-3 w-3" /> Dipilih
                                            </div>
                                        )}
                                    </button>
                                ));
                            })()}
                        </div>
                    </div>
                )}

                {swapData.jadwal_asal_id && swapData.jadwal_tujuan_id && (() => {
                    const asal = jadwalById.get(swapData.jadwal_asal_id);
                    const tujuan = jadwalById.get(swapData.jadwal_tujuan_id);
                    if (!asal || !tujuan) return null;

                    return (
                        <div className="mb-4 rounded-card border border-info/30 bg-info-light p-3">
                            <p className="mb-2 text-xs font-bold uppercase tracking-wider text-info">Preview Hasil Tukar</p>
                            <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                                <div>
                                    <p className="mb-0.5 text-text-muted">← {asal.pegawai?.nama_lengkap} mendapat:</p>
                                    <p className="font-semibold text-text-primary">{tujuan.hari}, {tujuan.jam_mulai?.substring(0, 5)}-{tujuan.jam_selesai?.substring(0, 5)}</p>
                                </div>
                                <div>
                                    <p className="mb-0.5 text-text-muted">→ {tujuan.pegawai?.nama_lengkap} mendapat:</p>
                                    <p className="font-semibold text-text-primary">{asal.hari}, {asal.jam_mulai?.substring(0, 5)}-{asal.jam_selesai?.substring(0, 5)}</p>
                                </div>
                            </div>
                        </div>
                    );
                })()}

                <div className="flex items-center justify-between gap-3 border-t border-border pt-4">
                    <div className="flex gap-3 text-xs">
                        <button type="button" onClick={onOpenDay} className="font-semibold text-primary hover:underline">
                            Tukar seharian →
                        </button>
                        <button type="button" onClick={onOpenBulk} className="font-semibold text-primary hover:underline">
                            Tukar banyak →
                        </button>
                        <button type="button" onClick={onOpenSlot} className="font-semibold text-primary hover:underline">
                            Tukar slot →
                        </button>
                    </div>
                    <div className="flex gap-3">
                        <button type="button" onClick={onClose} className="btn-secondary">
                            Batal
                        </button>
                        <button onClick={handleSwap} disabled={!swapData.jadwal_tujuan_id} className="btn-primary">
                            <ArrowLeftRight className="mr-1.5 h-4 w-4" />
                            Eksekusi Tukar
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
