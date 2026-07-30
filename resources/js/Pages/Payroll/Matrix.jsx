import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Matrix({ auth, pegawais, komponens, unitSekolahs = [] }) {
    const initialData = pegawais.map(p => {
        const compData = {};
        komponens.forEach(k => {
            const pivot = p.komponen_gaji.find(kg => kg.id === k.id);
            if (pivot && pivot.pivot && pivot.pivot.nominal) {
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
        const rawValue = value.replace(/[^0-9]/g, '');
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

            <div className="-m-4 sm:-m-6 lg:-m-8 h-[calc(100vh-4rem)] md:h-[calc(100vh-5rem)] flex flex-col bg-surface pt-6 pb-6 px-4 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                    <div className="flex justify-between items-center mb-4 shrink-0">
                            <div>
                                <Link href={route('komponen-gaji.index')} className="link text-xs mb-2 inline-flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    Kembali ke Komponen Gaji
                                </Link>
                                <h2 className="text-2xl font-bold text-text-primary">Matrix Master Gaji Pokok</h2>
                                <p className="text-sm text-text-muted">Atur Gaji Pokok dan Tunjangan Tetap untuk semua pegawai sekaligus.</p>
                            </div>
                            <div className="flex space-x-3 items-center">
                                <input
                                    type="text"
                                    placeholder="Cari Nama / NIP..."
                                    className="input-field w-64"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                />
                                <select
                                    className="select-field"
                                    value={filterUnit}
                                    onChange={e => setFilterUnit(e.target.value)}
                                >
                                    <option value="">Semua Unit Sekolah</option>
                                    {unitSekolahs.map(u => (
                                        <option key={u.id} value={u.id}>{u.nama}</option>
                                    ))}
                                </select>
                                <div className="h-8 w-px bg-border mx-1"></div>
                                <Link href={route('komponen-gaji.index')} className="btn-secondary btn-sm">
                                    Batal
                                </Link>
                                <button type="submit" disabled={processing} className="btn-primary btn-sm">
                                    {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                </button>
                            </div>
                        </div>

                    <div className="card flex-1 flex flex-col min-h-0 overflow-hidden">
                        <div className="overflow-auto flex-1 relative">
                            <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-primary-50/80 sticky top-0 z-20 shadow-card">
                                        <tr>
                                            <th className="sticky left-0 top-0 bg-primary-50/80 px-3 py-2 text-left text-[10px] font-bold text-primary uppercase tracking-wider border-r border-primary/20 z-30 shadow-[1px_0_0_0_rgba(15,61,62,0.1)]">
                                                Nama Pegawai
                                            </th>
                                            {komponens.map(k => (
                                                <th key={k.id} className="sticky top-0 bg-primary-50/80 px-3 py-2 text-center text-[10px] font-bold text-primary uppercase tracking-wider border-r border-primary/20 min-w-[140px] z-20">
                                                    {k.nama} <br/><span className="text-[9px] text-primary/60 font-normal">{k.jenis === 'dinamis_masa_bakti' ? 'Skala Masa Bakti (Default)' : `Default: Rp ${Number(k.nilai_default).toLocaleString('id-ID')}`}</span>
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border/50">
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
                                            <tr key={p.id} className={`group transition-colors ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} hover:bg-primary-50/30`}>
                                                <td className={`sticky left-0 z-10 px-3 py-2 whitespace-nowrap border-r border-border shadow-[1px_0_0_0_var(--color-border)] ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} group-hover:bg-primary-50/30`}>
                                                    <div className="text-[11px] font-bold text-text-primary">{p.nama_lengkap}</div>
                                                    <div className="text-[9px] text-text-muted">{p.nip || '-'}</div>
                                                    {p.units && p.units.length > 0 && (
                                                        <div className="text-[9px] text-primary font-bold mt-0.5 uppercase">{p.units[0].nama}</div>
                                                    )}
                                                </td>
                                                {komponens.map(k => (
                                                    <td key={k.id} className="px-2 py-2 whitespace-nowrap border-r border-border relative focus-within:bg-primary-50/30 transition-colors">
                                                        <div className="relative rounded-input shadow-card">
                                                            <div className="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                                                <span className="text-text-muted text-[9px]">Rp</span>
                                                            </div>
                                                            <input
                                                                type="text"
                                                                className="focus:ring-primary focus:border-primary block w-full pl-5 pr-2 py-1 text-[11px] border-border rounded-input bg-transparent"
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
                                                <td colSpan={komponens.length + 1} className="px-6 py-12 text-center text-text-muted">
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
