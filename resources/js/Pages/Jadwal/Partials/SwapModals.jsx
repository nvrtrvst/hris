import React from 'react';
import Modal from '@/Components/Modal';
import { ArrowLeftRight, Clock3, Plus } from 'lucide-react';
import { DAYS } from '@/Pages/Jadwal/Partials/jadwalMeta';

export function SwapDayModal({
    show,
    onClose,
    units,
    dayUnit,
    setDayUnit,
    dayA,
    setDayA,
    dayB,
    setDayB,
    dayHari,
    setDayHari,
    daySubmitting,
    handleSwapHari,
    swapCandidates,
    swapLoading,
    swapError,
    errors,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <div className="px-6 py-5">
                <div className="mb-4 flex items-start justify-between">
                    <div>
                        <h3 className="text-lg font-bold text-primary">Tukar Seharian</h3>
                        <p className="text-sm text-text-secondary">Semua jadwal dua pegawai pada hari terpilih bertukar sekaligus.</p>
                    </div>
                    <ArrowLeftRight className="h-5 w-5 shrink-0 text-primary" />
                </div>

                {errors?.conflict && (
                    <div className="mb-4 rounded-card border border-danger/30 bg-danger-light p-3 text-sm text-danger">
                        {errors.conflict}
                    </div>
                )}

                <div className="mb-4">
                    <label className="mb-1.5 block text-xs font-bold uppercase text-text-secondary">Unit Sekolah</label>
                    <select value={dayUnit} onChange={(e) => { setDayUnit(e.target.value); setDayA(''); setDayB(''); }} className="select-field">
                        {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                    </select>
                </div>

                {!swapCandidates && swapLoading && (
                    <p className="py-4 text-center text-sm text-text-muted">Memuat daftar pegawai...</p>
                )}
                {swapError && !swapCandidates && (
                    <p className="py-4 text-center text-sm text-danger">{swapError}</p>
                )}

                {swapCandidates && (
                    <>
                        <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase text-text-secondary">Pegawai A</label>
                                <select value={dayA} onChange={(e) => setDayA(e.target.value)} className="select-field">
                                    <option value="">— Pilih —</option>
                                    {swapCandidates.pegawais
                                        .filter((p) => p.id !== Number(dayB))
                                        .map((p) => <option key={p.id} value={p.id}>{p.nama_lengkap}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase text-text-secondary">Pegawai B</label>
                                <select value={dayB} onChange={(e) => setDayB(e.target.value)} className="select-field">
                                    <option value="">— Pilih —</option>
                                    {swapCandidates.pegawais
                                        .filter((p) => p.id !== Number(dayA))
                                        .map((p) => <option key={p.id} value={p.id}>{p.nama_lengkap}</option>)}
                                </select>
                            </div>
                        </div>

                        <div className="mb-4">
                            <label className="mb-1.5 block text-xs font-bold uppercase text-text-secondary">Hari</label>
                            <div className="flex flex-wrap gap-2">
                                {DAYS.map((h) => {
                                    const checked = dayHari.includes(h);

                                    return (
                                        <button
                                            key={h}
                                            type="button"
                                            onClick={() => setDayHari(checked ? dayHari.filter((x) => x !== h) : [...dayHari, h])}
                                            className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold transition-colors ${
                                                checked ? 'border-primary bg-primary text-white' : 'border-border bg-surface text-text-secondary hover:border-primary/40'
                                            }`}
                                        >
                                            {h}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {dayA && dayB && dayHari.length > 0 && (
                            <div className="mb-4 rounded-card border border-info/30 bg-info-light p-3">
                                <p className="mb-2 text-xs font-bold uppercase tracking-wider text-info">Preview</p>
                                {dayHari.map((h) => {
                                    const jadwals = swapCandidates.jadwals;
                                    const a = jadwals.filter((j) => j.pegawai_id === Number(dayA) && j.hari === h).length;
                                    const b = jadwals.filter((j) => j.pegawai_id === Number(dayB) && j.hari === h).length;

                                    return (
                                        <div key={h} className="flex justify-between text-sm text-text-primary">
                                            <span className="font-semibold">{h}</span>
                                            <span>{a} jadwal ↔ {b} jadwal</span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </>
                )}

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <button type="button" onClick={onClose} className="btn-secondary">Batal</button>
                    <button type="button" onClick={handleSwapHari} disabled={!dayA || !dayB || !dayHari.length || daySubmitting} className="btn-primary">
                        <ArrowLeftRight className="mr-1.5 h-4 w-4" />
                        {daySubmitting ? 'Memproses...' : 'Eksekusi Tukar'}
                    </button>
                </div>
            </div>
        </Modal>
    );
}

export function SwapBulkModal({
    show,
    onClose,
    units,
    bulkUnit,
    setBulkUnit,
    bulkPairs,
    setBulkPairs,
    handleSwapBulk,
    swapCandidates,
    swapLoading,
    swapError,
    errors,
    candJadwalById,
    candNamaById,
    jadwalOptionLabel,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <div className="px-6 py-5">
                <div className="mb-4 flex items-start justify-between">
                    <div>
                        <h3 className="text-lg font-bold text-primary">Tukar Banyak Jadwal</h3>
                        <p className="text-sm text-text-secondary">Beberapa pasangan tukar dalam satu transaksi.</p>
                    </div>
                    <ArrowLeftRight className="h-5 w-5 shrink-0 text-primary" />
                </div>

                {errors?.conflict && (
                    <div className="mb-4 rounded-card border border-danger/30 bg-danger-light p-3 text-sm text-danger">
                        {errors.conflict}
                    </div>
                )}

                <div className="mb-4">
                    <label className="mb-1.5 block text-xs font-bold uppercase text-text-secondary">Unit Sekolah</label>
                    <select value={bulkUnit} onChange={(e) => setBulkUnit(e.target.value)} className="select-field">
                        {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                    </select>
                </div>

                {!swapCandidates && swapLoading && (
                    <p className="py-4 text-center text-sm text-text-muted">Memuat daftar pegawai...</p>
                )}
                {swapError && !swapCandidates && (
                    <p className="py-4 text-center text-sm text-danger">{swapError}</p>
                )}

                {swapCandidates && (
                    <>
                        <div className="mb-4 space-y-3">
                            {bulkPairs.map((pair, idx) => {
                                const usedOther = bulkPairs
                                    .filter((_, i) => i !== idx)
                                    .flatMap((p) => [p.asal, p.tujuan])
                                    .filter(Boolean)
                                    .map(Number);
                                const options = swapCandidates.jadwals.filter((j) => !usedOther.includes(j.id));
                                const asalJ = candJadwalById.get(Number(pair.asal));
                                const tujuanJ = candJadwalById.get(Number(pair.tujuan));

                                return (
                                    <div key={idx} className="rounded-card border border-border bg-surface p-3">
                                        <div className="mb-2 flex items-center justify-between">
                                            <span className="text-xs font-bold text-text-secondary">Pasangan #{idx + 1}</span>
                                            {bulkPairs.length > 1 && (
                                                <button type="button" onClick={() => setBulkPairs(bulkPairs.filter((_, i) => i !== idx))} className="cursor-pointer text-xs font-semibold text-danger hover:underline">
                                                    Hapus
                                                </button>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <select value={pair.asal} onChange={(e) => setBulkPairs(bulkPairs.map((p, i) => (i === idx ? { ...p, asal: e.target.value, tujuan: p.tujuan === e.target.value ? '' : p.tujuan } : p)))} className="select-field text-xs">
                                                <option value="">— Jadwal asal —</option>
                                                {options.map((j) => <option key={j.id} value={j.id}>{jadwalOptionLabel(j)}</option>)}
                                            </select>
                                            <select value={pair.tujuan} onChange={(e) => setBulkPairs(bulkPairs.map((p, i) => (i === idx ? { ...p, tujuan: e.target.value } : p)))} className="select-field text-xs">
                                                <option value="">— Jadwal tujuan —</option>
                                                {options.filter((j) => j.id !== Number(pair.asal)).map((j) => <option key={j.id} value={j.id}>{jadwalOptionLabel(j)}</option>)}
                                            </select>
                                        </div>
                                        {asalJ && tujuanJ && (
                                            <div className="mt-2 rounded border border-info/30 bg-info-light p-2 text-xs">
                                                <span className="text-text-muted">{candNamaById.get(tujuanJ.pegawai_id) || '?'}</span> ←
                                                <span className="font-semibold text-text-primary"> {jadwalOptionLabel(asalJ)}</span>
                                                <span className="mx-2 text-text-muted">│</span>
                                                <span className="text-text-muted">{candNamaById.get(asalJ.pegawai_id) || '?'}</span> ←
                                                <span className="font-semibold text-text-primary"> {jadwalOptionLabel(tujuanJ)}</span>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        {bulkPairs.length < 20 && (
                            <button type="button" onClick={() => setBulkPairs([...bulkPairs, { asal: '', tujuan: '' }])} className="btn-secondary btn-sm mb-4">
                                <Plus className="mr-1.5 h-3.5 w-3.5" /> Tambah Pasangan
                            </button>
                        )}
                    </>
                )}

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <button type="button" onClick={onClose} className="btn-secondary">Batal</button>
                    <button type="button" onClick={handleSwapBulk} disabled={!bulkPairs.some((p) => p.asal && p.tujuan)} className="btn-primary">
                        <ArrowLeftRight className="mr-1.5 h-4 w-4" />
                        Eksekusi Tukar Semua
                    </button>
                </div>
            </div>
        </Modal>
    );
}

export function SwapSlotModal({
    show,
    onClose,
    units,
    slotUnit,
    setSlotUnit,
    slotA,
    setSlotA,
    slotB,
    setSlotB,
    slotSubmitting,
    handleSwapSlot,
    swapCandidates,
    swapLoading,
    swapError,
    errors,
    candJadwalById,
    candNamaById,
    jadwalOptionLabel,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <div className="px-6 py-5">
                <div className="mb-4 flex items-start justify-between">
                    <div>
                        <h3 className="text-lg font-bold text-primary">Tukar Slot Waktu</h3>
                        <p className="text-sm text-text-secondary">Guru, mapel, dan kelas tetap. Yang bertukar hanya hari dan jam pelajaran.</p>
                    </div>
                    <Clock3 className="h-5 w-5 shrink-0 text-primary" />
                </div>

                {errors?.conflict && (
                    <div className="mb-4 rounded-card border border-danger/30 bg-danger-light p-3 text-sm text-danger">
                        {errors.conflict}
                    </div>
                )}

                <div className="mb-4">
                    <label className="mb-1.5 block text-xs font-bold uppercase text-text-secondary">Unit Sekolah</label>
                    <select value={slotUnit} onChange={(e) => { setSlotUnit(e.target.value); setSlotA(''); setSlotB(''); }} className="select-field">
                        {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                    </select>
                </div>

                {!swapCandidates && swapLoading && (
                    <p className="py-4 text-center text-sm text-text-muted">Memuat daftar jadwal...</p>
                )}
                {swapError && !swapCandidates && (
                    <p className="py-4 text-center text-sm text-danger">{swapError}</p>
                )}

                {swapCandidates && (
                    <>
                        <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase text-text-secondary">Jadwal A</label>
                                <select value={slotA} onChange={(e) => setSlotA(e.target.value)} className="select-field text-xs">
                                    <option value="">— Pilih —</option>
                                    {swapCandidates.jadwals
                                        .filter((j) => j.id !== Number(slotB))
                                        .map((j) => <option key={j.id} value={j.id}>{jadwalOptionLabel(j)}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1.5 block text-xs font-bold uppercase text-text-secondary">Jadwal B</label>
                                <select value={slotB} onChange={(e) => setSlotB(e.target.value)} className="select-field text-xs">
                                    <option value="">— Pilih —</option>
                                    {swapCandidates.jadwals
                                        .filter((j) => j.id !== Number(slotA))
                                        .map((j) => <option key={j.id} value={j.id}>{jadwalOptionLabel(j)}</option>)}
                                </select>
                            </div>
                        </div>

                        {slotA && slotB && (() => {
                            const ax = candJadwalById.get(Number(slotA));
                            const bx = candJadwalById.get(Number(slotB));
                            if (!ax || !bx) return null;

                            return (
                                <div className="mb-4 rounded-card border border-info/30 bg-info-light p-3">
                                    <p className="mb-2 text-xs font-bold uppercase tracking-wider text-info">Preview Slot Baru</p>
                                    <div className="space-y-1 text-sm">
                                        <div>
                                            <span className="font-semibold text-text-primary">{candNamaById.get(ax.pegawai_id) || '?'} ({ax.mapel_nama || ax.jenis_jadwal}):</span>{' '}
                                            <span className="text-text-muted">{ax.hari} {String(ax.jam_mulai || '').substring(0, 5)}-{String(ax.jam_selesai || '').substring(0, 5)}</span>
                                            {' → '}
                                            <span className="font-semibold text-text-primary">{bx.hari} {String(bx.jam_mulai || '').substring(0, 5)}-{String(bx.jam_selesai || '').substring(0, 5)}</span>
                                        </div>
                                        <div>
                                            <span className="font-semibold text-text-primary">{candNamaById.get(bx.pegawai_id) || '?'} ({bx.mapel_nama || bx.jenis_jadwal}):</span>{' '}
                                            <span className="text-text-muted">{bx.hari} {String(bx.jam_mulai || '').substring(0, 5)}-{String(bx.jam_selesai || '').substring(0, 5)}</span>
                                            {' → '}
                                            <span className="font-semibold text-text-primary">{ax.hari} {String(ax.jam_mulai || '').substring(0, 5)}-{String(ax.jam_selesai || '').substring(0, 5)}</span>
                                        </div>
                                    </div>
                                </div>
                            );
                        })()}
                    </>
                )}

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <button type="button" onClick={onClose} className="btn-secondary">Batal</button>
                    <button type="button" onClick={handleSwapSlot} disabled={!slotA || !slotB || slotSubmitting} className="btn-primary">
                        <ArrowLeftRight className="mr-1.5 h-4 w-4" />
                        {slotSubmitting ? 'Memproses...' : 'Eksekusi Tukar Slot'}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
