import React from 'react';
import { Building2, Plus, Trash2 } from 'lucide-react';

/**
 * Unit Assignment Subcomponent - Handles unit, jabatan, dan primary flag untuk pegawai.
 * Dikelompokkan per unit: satu unit bisa punya banyak jabatan (rangkap:
 * Guru + Wakasek di unit sama). Submit ke backend tetap flat rows.
 * @param {Object[]} units - List flat assignments {unit_sekolah_id, jabatan_id, is_primary}
 * @param {Function} onAddUnit - Tambah kartu unit kosong
 * @param {Function} onRemoveUnit - Hapus semua baris satu unit (unitId)
 * @param {Function} onUnitChange - Pindah unit (index, unitId baru)
 * @param {Function} onAddJabatan - Tambah baris jabatan di unit (unitId)
 * @param {Function} onUpdateJabatan - Ubah jabatan satu baris (index, jabatanId)
 * @param {Function} onRemoveJabatan - Hapus satu baris jabatan (index)
 * @param {Function} onPrimaryChange - Set primary satu unit (unitId, value)
 * @param {{id: string|number, nama: string}[]} unitSekolahs - Available units
 * @param {{id: string|number, nama: string}[]} jabatans - Available jabatans
 */
export function UnitAssignmentSection({ units, onAddUnit, onRemoveUnit, onUnitChange, onAddJabatan, onUpdateJabatan, onRemoveJabatan, onPrimaryChange, unitSekolahs, jabatans }) {
    const groups = React.useMemo(() => {
        const map = new Map();
        (units || []).forEach((u, i) => {
            const key = u.unit_sekolah_id ? `unit-${u.unit_sekolah_id}` : `blank-${i}`;
            if (!map.has(key)) map.set(key, { key, unitId: u.unit_sekolah_id || '', rows: [] });
            map.get(key).rows.push({ ...u, index: i });
        });
        return [...map.values()];
    }, [units]);

    return (
        <div className="card p-6">
            <h3 className="mb-1 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                    <Building2 className="h-4 w-4 text-primary" />
                </span>
                Penugasan Unit &amp; Jabatan
            </h3>
            <p className="mb-5 text-xs text-text-muted">
                Tentukan unit tempat pegawai bertugas. Satu unit bisa punya lebih dari satu jabatan (rangkap). Satu unit dapat ditandai <b>Primary</b>.
            </p>
            <div className="space-y-3">
                {groups.map((g) => {
                    const unitName = unitSekolahs.find((us) => String(us.id) === String(g.unitId))?.nama;
                    const chosen = g.rows.map((r) => String(r.jabatan_id)).filter(Boolean);
                    const primary = g.rows.length > 0 && g.rows.every((r) => !!r.is_primary);
                    return (
                        <div key={g.key} className="rounded-xl border border-border bg-surface p-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <div className="w-full flex-1 sm:w-auto">
                                    <label className="mb-1 block text-xs sm:hidden">Unit</label>
                                    <select
                                        value={g.unitId}
                                        onChange={(e) => onUnitChange(g.rows[0].index, e.target.value)}
                                        className="select-field"
                                        aria-label="Unit"
                                    >
                                        <option value="">Pilih Unit</option>
                                        {unitSekolahs.map((us) => <option key={us.id} value={us.id}>{us.nama}</option>)}
                                    </select>
                                </div>
                                {g.unitId && (
                                    <label className="inline-flex shrink-0 items-center gap-2 text-sm text-text-primary">
                                        <input
                                            type="checkbox"
                                            checked={primary}
                                            onChange={(e) => onPrimaryChange(g.unitId, e.target.checked)}
                                            className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                        />
                                        Primary
                                    </label>
                                )}
                                {g.unitId && (
                                    <button
                                        type="button"
                                        onClick={() => onRemoveUnit(g.unitId)}
                                        className="shrink-0 rounded-lg p-1.5 text-danger transition-colors hover:bg-danger-light"
                                        title={`Hapus penugasan ${unitName || 'unit'}`}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                            {g.unitId && (
                                <div className="mt-3 space-y-2 border-t border-border pt-3">
                                    {g.rows.map((r) => (
                                        <div key={r.index} className="flex items-center gap-2">
                                            <select
                                                value={r.jabatan_id}
                                                onChange={(e) => onUpdateJabatan(r.index, e.target.value)}
                                                className="select-field flex-1"
                                                aria-label={`Jabatan di ${unitName || 'unit'}`}
                                            >
                                                <option value="">Pilih Jabatan</option>
                                                {jabatans.map((j) => (
                                                    <option key={j.id} value={j.id} disabled={chosen.includes(String(j.id)) && String(r.jabatan_id) !== String(j.id)}>
                                                        {j.nama}
                                                    </option>
                                                ))}
                                            </select>
                                            {g.rows.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => onRemoveJabatan(r.index)}
                                                    className="shrink-0 rounded-lg p-1.5 text-danger transition-colors hover:bg-danger-light"
                                                    title="Hapus jabatan ini"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={() => onAddJabatan(g.unitId)}
                                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary transition-colors hover:text-primary/70"
                                    >
                                        <Plus className="h-3.5 w-3.5" /> Tambah Jabatan
                                    </button>
                                </div>
                            )}
                        </div>
                    );
                })}
                {groups.length === 0 && (
                    <p className="border-2 border-dashed border-border rounded-xl py-6 text-center text-sm text-text-muted">
                        Belum ada penugasan unit.
                    </p>
                )}
            </div>
            <button
                type="button"
                onClick={onAddUnit}
                className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-primary transition-colors hover:text-primary/70"
            >
                <Plus className="h-4 w-4" /> Tambah Unit
            </button>
        </div>
    );
}
