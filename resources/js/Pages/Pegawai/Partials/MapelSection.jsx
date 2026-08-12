import React from 'react';
import { GraduationCap, Plus, Trash2 } from 'lucide-react';

/**
 * Mapel (Mata Pelajaran) Assignment Subcomponent - Handles mapel untuk guru.
 * @param {Object[]} mapels - List mapel assignments
 * @param {Function} onUpdate - Callback saat mapel update (index, field, value)
 * @param {Function} onAdd - Callback saat tambah mapel
 * @param {Function} onRemove - Callback saat remove mapel (index)
 * @param {{id: string|number, nama: string}[]} mapelList - Available mapels
 * @param {{id: string|number, nama: string}[]} unitSekolahs - Available units
 */
export function MapelSection({ mapels, onUpdate, onAdd, onRemove, mapelList, unitSekolahs }) {
    return (
        <div className="card p-6">
            <h3 className="mb-1 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                    <GraduationCap className="h-4 w-4 text-primary" />
                </span>
                Mata Pelajaran (Guru)
            </h3>
            <p className="mb-5 text-xs text-text-muted">
                Untuk guru: tentukan mata pelajaran yang diampu beserta unitnya. Baris yang tidak lengkap akan diabaikan.
            </p>
            <div className="space-y-3">
                {mapels.map((m, i) => (
                    <div key={i} className="flex flex-col gap-3 items-start rounded-xl border border-border bg-surface p-4 sm:flex-row sm:items-center">
                        <div className="w-full flex-1 sm:w-auto">
                            <label className="mb-1 block text-xs sm:hidden">Mata Pelajaran</label>
                            <select
                                value={m.mata_pelajaran_id}
                                onChange={(e) => onUpdate(i, 'mata_pelajaran_id', e.target.value)}
                                className="select-field"
                            >
                                <option value="">Pilih Mata Pelajaran</option>
                                {mapelList.map((mp) => <option key={mp.id} value={mp.id}>{mp.nama}</option>)}
                            </select>
                        </div>
                        <div className="w-full flex-1 sm:w-auto">
                            <label className="mb-1 block text-xs sm:hidden">Unit</label>
                            <select
                                value={m.unit_sekolah_id}
                                onChange={(e) => onUpdate(i, 'unit_sekolah_id', e.target.value)}
                                className="select-field"
                            >
                                <option value="">Pilih Unit</option>
                                {unitSekolahs.map((us) => <option key={us.id} value={us.id}>{us.nama}</option>)}
                            </select>
                        </div>
                        <button
                            type="button"
                            onClick={() => onRemove(i)}
                            className="shrink-0 rounded-lg p-1.5 text-danger transition-colors hover:bg-danger-light self-end sm:self-center"
                            title="Hapus mata pelajaran"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                ))}
                {mapels.length === 0 && (
                    <p className="border-2 border-dashed border-border rounded-xl py-6 text-center text-sm text-text-muted">
                        Bukan guru / belum ada mata pelajaran.
                    </p>
                )}
            </div>
            <button
                type="button"
                onClick={onAdd}
                className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-primary transition-colors hover:text-primary/70"
            >
                <Plus className="h-4 w-4" /> Tambah Mata Pelajaran
            </button>
        </div>
    );
}
