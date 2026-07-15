import React from 'react';

/**
 * Mapel (Mata Pelajaran) Assignment Subcomponent - Handles mapel untuk guru
 * @param {Object[]} mapels - List mapel assignments
 * @param {Function} onUpdate - Callback saat mapel update
 * @param {Function} onAdd - Callback saat tambah mapel
 * @param {Function} onRemove - Callback saat remove mapel
 * @param {{id: string, nama: string}[]} mapelList - Available mapels
 * @param {{id: string, nama: string}[]} unitSekolahs - Available units
 */
export function MapelSection({ mapels, onUpdate, onAdd, onRemove, mapelList, unitSekolahs }) {
    return (
        <div>
            <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Mata Pelajaran (Guru)</h3>
            <p className="text-xs text-gray-500 mb-3">
                Untuk guru: tentukan mata pelajaran yang diampu beserta unitnya. Baris yang tidak lengkap akan diabaikan.
            </p>
            <div className="space-y-3">
                {mapels.map((m, i) => (
                    <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-gray-50 p-3 rounded-lg">
                        <div className="md:col-span-5">
                            <select
                                value={m.mata_pelajaran_id}
                                onChange={(e) => onUpdate(i, 'mata_pelajaran_id', e.target.value)}
                                className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Pilih Mata Pelajaran</option>
                                {mapelList.map((mp) => (
                                    <option key={mp.id} value={mp.id}>{mp.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div className="md:col-span-5">
                            <select
                                value={m.unit_sekolah_id}
                                onChange={(e) => onUpdate(i, 'unit_sekolah_id', e.target.value)}
                                className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Pilih Unit</option>
                                {unitSekolahs.map((us) => (
                                    <option key={us.id} value={us.id}>{us.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div className="md:col-span-2 text-right">
                            <button type="button" onClick={() => onRemove(i)} className="text-red-500 hover:text-red-700 text-sm font-medium">
                                Hapus
                            </button>
                        </div>
                    </div>
                ))}
                {mapels.length === 0 && <p className="text-sm text-gray-500">Bukan guru / belum ada mata pelajaran.</p>}
            </div>
            <button type="button" onClick={onAdd} className="mt-3 inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                + Tambah Mata Pelajaran
            </button>
        </div>
    );
}
