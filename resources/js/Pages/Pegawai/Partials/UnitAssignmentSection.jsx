import React from 'react';
import { Building2, Plus, Trash2 } from 'lucide-react';

/**
 * Unit Assignment Subcomponent - Handles unit, jabatan, dan primary flag untuk pegawai.
 * @param {Object[]} units - List unit assignments
 * @param {Function} onUpdate - Callback saat unit update (index, field, value)
 * @param {Function} onAdd - Callback saat tambah unit
 * @param {Function} onRemove - Callback saat remove unit (index)
 * @param {{id: string|number, nama: string}[]} unitSekolahs - Available units
 * @param {{id: string|number, nama: string}[]} jabatans - Available jabatans
 */
export function UnitAssignmentSection({ units, onUpdate, onAdd, onRemove, unitSekolahs, jabatans }) {
    return (
        <div className="card p-6">
            <h3 className="mb-1 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                    <Building2 className="h-4 w-4 text-primary" />
                </span>
                Penugasan Unit &amp; Jabatan
            </h3>
            <p className="mb-5 text-xs text-text-muted">
                Tentukan unit tempat pegawai bertugas beserta jabatannya. Satu unit dapat ditandai <b>Primary</b>.
            </p>
            <div className="space-y-3">
                {units.map((u, i) => (
                    <div key={i} className="flex flex-col gap-3 items-start rounded-xl border border-border bg-surface p-4 sm:flex-row sm:items-center">
                        <div className="w-full flex-1 sm:w-auto">
                            <label className="mb-1 block text-xs sm:hidden">Unit</label>
                            <select
                                value={u.unit_sekolah_id}
                                onChange={(e) => onUpdate(i, 'unit_sekolah_id', e.target.value)}
                                className="select-field"
                            >
                                <option value="">Pilih Unit</option>
                                {unitSekolahs.map((us) => <option key={us.id} value={us.id}>{us.nama}</option>)}
                            </select>
                        </div>
                        <div className="w-full flex-1 sm:w-auto">
                            <label className="mb-1 block text-xs sm:hidden">Jabatan</label>
                            <select
                                value={u.jabatan_id}
                                onChange={(e) => onUpdate(i, 'jabatan_id', e.target.value)}
                                className="select-field"
                            >
                                <option value="">Pilih Jabatan</option>
                                {jabatans.map((j) => <option key={j.id} value={j.id}>{j.nama}</option>)}
                            </select>
                        </div>
                        <label className="inline-flex shrink-0 items-center gap-2 pt-1 text-sm text-text-primary sm:pt-0">
                            <input
                                type="checkbox"
                                checked={!!u.is_primary}
                                onChange={(e) => onUpdate(i, 'is_primary', e.target.checked)}
                                className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                            />
                            Primary
                        </label>
                        <button
                            type="button"
                            onClick={() => onRemove(i)}
                            className="shrink-0 rounded-lg p-1.5 text-danger transition-colors hover:bg-danger-light"
                            title="Hapus penugasan unit"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                ))}
                {units.length === 0 && (
                    <p className="border-2 border-dashed border-border rounded-xl py-6 text-center text-sm text-text-muted">
                        Belum ada penugasan unit.
                    </p>
                )}
            </div>
            <button
                type="button"
                onClick={onAdd}
                className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-primary transition-colors hover:text-primary/70"
            >
                <Plus className="h-4 w-4" /> Tambah Unit
            </button>
        </div>
    );
}
