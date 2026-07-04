import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Matrix({ auth, pegawais, komponens, unitSekolahs = [] }) {
    // Form state structure:
    // pegawai_data: [
    //   { pegawai_id: 1, komponens: { '1': '500000', '2': '100000' } }
    // ]
    
    const initialData = pegawais.map(p => {
        const compData = {};
        komponens.forEach(k => {
            // Check if this pegawai has this komponen attached
            const pivot = p.komponen_gaji.find(kg => kg.id === k.id);
            if (pivot && pivot.pivot && pivot.pivot.nominal) {
                // Format initial value with dot separator
                compData[k.id] = new Intl.NumberFormat('id-ID').format(pivot.pivot.nominal);
            } else {
                compData[k.id] = '';
            }
        });
        
        return {
            pegawai_id: p.id,
            komponens: compData
        };
    });

    const [data, setData] = useState(initialData);
    const [processing, setProcessing] = useState(false);
    const [search, setSearch] = useState('');
    const [filterUnit, setFilterUnit] = useState('');

    const handleCellChange = (pegawaiIndex, komponenId, value) => {
        // Strip non-digits
        const rawValue = value.replace(/[^0-9]/g, '');
        // Format with dots
        const formatted = rawValue ? new Intl.NumberFormat('id-ID').format(rawValue) : '';
        
        const newData = [...data];
        newData[pegawaiIndex].komponens[komponenId] = formatted;
        setData(newData);
    };

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(route('komponen-gaji.matrix.update'), { pegawai_data: data }, {
            onFinish: () => setProcessing(false)
        });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Matrix Master Gaji Pokok" />
            
            <div className="-m-4 sm:-m-6 lg:-m-8 h-[calc(100vh-4rem)] md:h-[calc(100vh-5rem)] flex flex-col bg-gray-50 pt-6 pb-6 px-4 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                    <div className="flex justify-between items-center mb-4 shrink-0">
                            <div>
                                <Link href={route('komponen-gaji.index')} className="inline-flex items-center text-xs font-medium text-gray-500 hover:text-indigo-600 transition-colors mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    Kembali ke Komponen Gaji
                                </Link>
                                <h2 className="text-2xl font-bold text-gray-900">Matrix Master Gaji Pokok</h2>
                                <p className="text-sm text-gray-500">Atur Gaji Pokok dan Tunjangan Tetap untuk semua pegawai sekaligus.</p>
                            </div>
                            <div className="flex space-x-3 items-center">
                                <input
                                    type="text"
                                    placeholder="Cari Nama / NIP..."
                                    className="border-gray-300 rounded-lg text-sm px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 w-64 shadow-sm"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                />
                                <select
                                    className="border-gray-300 rounded-lg text-sm px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
                                    value={filterUnit}
                                    onChange={e => setFilterUnit(e.target.value)}
                                >
                                    <option value="">Semua Unit Sekolah</option>
                                    {unitSekolahs.map(u => (
                                        <option key={u.id} value={u.id}>{u.nama}</option>
                                    ))}
                                </select>
                                <div className="h-8 w-px bg-gray-300 mx-1"></div>
                                <Link href={route('komponen-gaji.index')} className="bg-white text-gray-700 font-bold px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 shadow-sm text-sm">
                                    Batal
                                </Link>
                                <button type="submit" disabled={processing} className="bg-indigo-600 text-white font-bold px-6 py-2 rounded-lg hover:bg-indigo-700 shadow-sm flex items-center disabled:opacity-50 text-sm">
                                    {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                </button>
                            </div>
                        </div>

                    <div className="bg-white shadow-xl sm:rounded-2xl border border-gray-200 flex-1 flex flex-col min-h-0 overflow-hidden">
                        <div className="overflow-auto flex-1 relative">
                            <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-indigo-50 sticky top-0 z-20 shadow-sm">
                                        <tr>
                                            <th className="sticky left-0 top-0 bg-indigo-50 px-3 py-2 text-left text-[10px] font-bold text-indigo-900 uppercase tracking-wider border-r border-indigo-200 z-30 shadow-[1px_0_0_0_#e0e7ff]">
                                                Nama Pegawai
                                            </th>
                                            {komponens.map(k => (
                                                <th key={k.id} className="sticky top-0 bg-indigo-50 px-3 py-2 text-center text-[10px] font-bold text-indigo-900 uppercase tracking-wider border-r border-indigo-200 min-w-[140px] z-20">
                                                    {k.nama} <br/><span className="text-[9px] text-indigo-500 font-normal">{k.jenis === 'dinamis_masa_bakti' ? 'Skala Masa Bakti (Default)' : `Default: Rp ${Number(k.nilai_default).toLocaleString('id-ID')}`}</span>
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {pegawais.filter(p => {
                                            let match = true;
                                            if (search) {
                                                const s = search.toLowerCase();
                                                match = p.nama_lengkap?.toLowerCase().includes(s) || p.nip?.toLowerCase().includes(s);
                                            }
                                            if (filterUnit && match) {
                                                match = p.units && p.units.some(u => u.id === parseInt(filterUnit));
                                            }
                                            return match;
                                        }).map((p) => {
                                            const pIdx = pegawais.findIndex(orig => orig.id === p.id);
                                            return (
                                            <tr key={p.id} className={`group transition-colors ${pIdx % 2 !== 0 ? 'bg-gray-100' : 'bg-white'} hover:bg-blue-50/50`}>
                                                <td className={`sticky left-0 z-10 px-3 py-2 whitespace-nowrap border-r border-gray-200 shadow-[1px_0_0_0_#e5e7eb] ${pIdx % 2 !== 0 ? 'bg-gray-100' : 'bg-white'} group-hover:bg-blue-50/50`}>
                                                    <div className="text-[11px] font-bold text-gray-900">{p.nama_lengkap}</div>
                                                    <div className="text-[9px] text-gray-500">{p.nip || '-'}</div>
                                                    {p.units && p.units.length > 0 && (
                                                        <div className="text-[9px] text-indigo-500 font-bold mt-0.5 uppercase">{p.units[0].nama}</div>
                                                    )}
                                                </td>
                                                {komponens.map(k => (
                                                    <td key={k.id} className="px-2 py-2 whitespace-nowrap border-r border-gray-200 relative focus-within:bg-indigo-50/30 transition-colors">
                                                        <div className="relative rounded-md shadow-sm">
                                                            <div className="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                                                <span className="text-gray-400 text-[9px]">Rp</span>
                                                            </div>
                                                            <input
                                                                type="text"
                                                                className="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-5 pr-2 py-1 text-[11px] border-gray-300 rounded-md bg-transparent"
                                                                placeholder={new Intl.NumberFormat('id-ID').format(p.dynamic_defaults?.[k.id] ?? k.nilai_default)}
                                                                value={data[pIdx]?.komponens[k.id] || ''}
                                                                onChange={(e) => handleCellChange(pIdx, k.id, e.target.value)}
                                                            />
                                                        </div>
                                                    </td>
                                                ))}
                                            </tr>
                                        )})}
                                        {pegawais.length === 0 && (
                                            <tr>
                                                <td colSpan={komponens.length + 1} className="px-6 py-12 text-center text-gray-500">
                                                    Tidak ada data pegawai yang aktif.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
            </div>
        </AuthenticatedLayout>
    );
}
