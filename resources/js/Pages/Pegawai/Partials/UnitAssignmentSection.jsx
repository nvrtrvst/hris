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
            <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Penugasan Unit & Jabatan</h3>
            <p className="text-xs text-gray-500 mb-3">
                Tentukan unit tempat pegawai bertugas beserta jabatannya. Satu unit dapat ditandai <b>Primary</b>.
            </p>
            <div className="space-y-3">
                {units.map((u, i) => (
                    <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-gray-50 p-3 rounded-lg">
                        <div className="md:col-span-5">
                            <select
                                value={u.unit_sekolah_id}
                                onChange={(e) => onUpdate(i, 'unit_sekolah_id', e.target.value)}
                                className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
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
                                className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Pilih Jabatan</option>
                                {jabatans.map((j) => (
                                    <option key={j.id} value={j.id}>{j.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div className="md:col-span-2 flex items-center">
                            <label className="inline-flex items-center text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={!!u.is_primary}
                                    onChange={(e) => onUpdate(i, 'is_primary', e.target.checked)}
                                    className="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Primary
                            </label>
                        </div>
                        <div className="md:col-span-1 text-right">
                            <button type="button" onClick={() => onRemove(i)} className="text-red-500 hover:text-red-700 text-sm font-medium">
                                Hapus
                            </button>
                        </div>
                    </div>
                ))}
                {units.length === 0 && <p className="text-sm text-gray-500">Belum ada penugasan unit.</p>}
            </div>
            <button type="button" onClick={onAdd} className="mt-3 inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                + Tambah Unit
            </button>
        </div>
    );
}
