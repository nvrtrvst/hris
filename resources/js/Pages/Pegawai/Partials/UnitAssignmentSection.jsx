import React from 'react';

/**
 * Unit Assignment Subcomponent - Handles unit, jabatan, dan primary flag untuk pegawai
 * @param {Object[]} units - List unit assignments
 * @param {Function} onUpdate - Callback saat unit update
 * @param {Function} onAdd - Callback saat tambah unit
 * @param {Function} onRemove - Callback saat remove unit
 * @param {{id: string, nama: string}[]} unitSekolahs - Available units
 * @param {{id: string, nama: string}[]} jabatans - Available jabatans
 */
export function UnitAssignmentSection({ units, onUpdate, onAdd, onRemove, unitSekolahs, jabatans }) {
    return (
        <div>
            <h3 className="section-title">Penugasan Unit & Jabatan</h3>
            <p className="text-xs text-text-muted mb-3">
                Tentukan unit tempat pegawai bertugas beserta jabatannya. Satu unit dapat ditandai <b>Primary</b>.
            </p>
            <div className="space-y-3">
                {units.map((u, i) => (
                    <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-surface p-3 rounded-card border border-border">
                        <div className="md:col-span-5">
                            <select
                                value={u.unit_sekolah_id}
                                onChange={(e) => onUpdate(i, 'unit_sekolah_id', e.target.value)}
                                className="select-field"
                            >
                                <option value="">Pilih Unit</option>
                                {unitSekolahs.map((us) => (
                                    <option key={us.id} value={us.id}>{us.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div className="md:col-span-4">
                            <select
                                value={u.jabatan_id}
                                onChange={(e) => onUpdate(i, 'jabatan_id', e.target.value)}
                                className="select-field"
                            >
                                <option value="">Pilih Jabatan</option>
                                {jabatans.map((j) => (
                                    <option key={j.id} value={j.id}>{j.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div className="md:col-span-2 flex items-center">
                            <label className="inline-flex items-center text-sm text-text-primary">
                                <input
                                    type="checkbox"
                                    checked={!!u.is_primary}
                                    onChange={(e) => onUpdate(i, 'is_primary', e.target.checked)}
                                    className="mr-2 rounded border-border text-primary focus:ring-primary"
                                />
                                Primary
                            </label>
                        </div>
                        <div className="md:col-span-1 text-right">
                            <button type="button" onClick={() => onRemove(i)} className="text-danger hover:text-danger text-sm font-medium">
                                Hapus
                            </button>
                        </div>
                    </div>
                ))}
                {units.length === 0 && <p className="text-sm text-text-muted">Belum ada penugasan unit.</p>}
            </div>
            <button type="button" onClick={onAdd} className="mt-3 inline-flex items-center text-sm font-semibold text-primary hover:text-primary-600">
                + Tambah Unit
            </button>
        </div>
    );
}
