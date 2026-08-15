import React, { useState, useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ArrowLeft, Coins, Loader2, Save, Search, X } from 'lucide-react';

const fmtId = (v) => (v === '' || v == null ? '' : new Intl.NumberFormat('id-ID').format(v));

export default function Matrix({ auth, pegawais, komponens, unitSekolahs = [] }) {
    const { flash = {} } = usePage().props;

    // Initial data dihitung SEKALI (lazy initializer) — dulu dihitung ulang di
    // setiap render, termasuk tiap ketikan sel (O(n×k) per keystroke).
    const [data, setData] = useState(() => pegawais.map((p) => {
        const compData = {};
        komponens.forEach((k) => {
            const pivot = p.komponen_gaji.find((kg) => kg.id === k.id);
            compData[k.id] = pivot?.pivot?.nominal ? fmtId(pivot.pivot.nominal) : '';
        });

        return { pegawai_id: p.id, komponens: compData };
    }));

    const [processing, setProcessing] = useState(false);
    const [search, setSearch] = useState('');
    const [filterUnit, setFilterUnit] = useState('');

    // Map id pegawai → index di `data` — ganti findIndex() di dalam render
    // (dulu O(n²) per render saat filter aktif).
    const dataIndexById = useMemo(() => {
        const map = new Map();
        data.forEach((d, i) => map.set(d.pegawai_id, i));

        return map;
    }, [data]);

    const visiblePegawais = useMemo(() => {
        const q = search.trim().toLowerCase();

        return pegawais.filter((p) => {
            if (q) {
                const s = p.nama_lengkap?.toLowerCase() || '';
                const nip = p.nip?.toLowerCase() || '';
                if (!s.includes(q) && !nip.includes(q)) return false;
            }
            if (filterUnit && !p.units?.some((u) => u.id === parseInt(filterUnit, 10))) return false;

            return true;
        });
    }, [pegawais, search, filterUnit]);

    const handleCellChange = (pegawaiIndex, komponenId, value) => {
        const rawValue = value.replace(/[^0-9]/g, '');
        setData((prev) => {
            const next = [...prev];
            next[pegawaiIndex] = { ...next[pegawaiIndex], komponens: { ...next[pegawaiIndex].komponens, [komponenId]: fmtId(rawValue) } };

            return next;
        });
    };

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(route('komponen-gaji.matrix.update'), { pegawai_data: data }, {
            onFinish: () => setProcessing(false),
        });
    };

    const colType = (k) => (k.tipe === 'pendapatan' ? 'pendapatan' : 'potongan');
    const pendapatanCols = komponens.filter((k) => colType(k) === 'pendapatan').length;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Matrix Gaji Pegawai</h2>}
        >
            <Head title="Matrix Gaji Pegawai" />

            <div className="-m-4 sm:-m-6 lg:-m-8 h-[calc(100vh-4rem)] md:h-[calc(100vh-5rem)] flex flex-col bg-surface pt-6 pb-6 px-4 sm:px-6 lg:px-8">
                {flash.message && <div role="status" className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                {flash.error && <div role="alert" className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                    <div className="flex flex-wrap items-start justify-between gap-3 mb-4 shrink-0">
                        <div>
                            <Link href={route('komponen-gaji.index')} className="mb-2 inline-flex items-center gap-1.5 text-xs font-semibold text-text-secondary transition-colors hover:text-primary">
                                <ArrowLeft className="h-3.5 w-3.5" />
                                Kembali ke Komponen Gaji
                            </Link>
                            <h2 className="text-xl font-extrabold text-text-primary">Matrix Gaji Pegawai</h2>
                            <p className="text-sm text-text-muted">Atur nominal gaji & tunjangan semua pegawai sekaligus. Kosongkan = pakai nilai default.</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                <input
                                    type="text"
                                    placeholder="Cari Nama / NIP..."
                                    className="input-field w-52 pl-9"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <select className="select-field" value={filterUnit} onChange={(e) => setFilterUnit(e.target.value)}>
                                <option value="">Semua Unit Sekolah</option>
                                {unitSekolahs.map((u) => (
                                    <option key={u.id} value={u.id}>{u.nama}</option>
                                ))}
                            </select>
                            <Link href={route('komponen-gaji.index')} className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                <X className="h-3.5 w-3.5" /> Batal
                            </Link>
                            <button type="submit" disabled={processing} className="btn-primary btn-sm inline-flex items-center gap-1.5">
                                {processing ? <><Loader2 className="h-3.5 w-3.5 animate-spin" /> Menyimpan…</> : <><Save className="h-3.5 w-3.5" /> Simpan Perubahan</>}
                            </button>
                        </div>
                    </div>

                    <div className="card flex-1 flex flex-col min-h-0 overflow-hidden">
                        <div className="flex items-center gap-3 border-b border-border bg-surface px-4 py-2.5 text-xs text-text-secondary">
                            <span className="inline-flex items-center gap-1"><Coins className="h-3.5 w-3.5 text-primary" /> {komponens.length} kolom komponen</span>
                            <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">+ {pendapatanCols} pendapatan</span>
                            <span className="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-600">− {komponens.length - pendapatanCols} potongan</span>
                            <span className="ml-auto hidden text-[11px] text-text-muted md:inline">Default terlihat di placeholder sel kosong.</span>
                        </div>
                        <div className="overflow-auto flex-1 relative">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-primary-50/80 sticky top-0 z-20 shadow-card">
                                    <tr>
                                        <th className="sticky left-0 top-0 bg-primary-50/80 px-3 py-2 text-left text-[10px] font-bold text-primary uppercase tracking-wider border-r border-primary/20 z-30 shadow-[1px_0_0_0_rgba(15,61,62,0.1)]">
                                            Nama Pegawai
                                        </th>
                                        {komponens.map((k) => (
                                            <th key={k.id} className={`sticky top-0 bg-primary-50/80 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wider border-r border-primary/20 min-w-[140px] z-20 ${colType(k) === 'pendapatan' ? 'text-emerald-700' : 'text-rose-700'}`}>
                                                <span className={colType(k) === 'pendapatan' ? '' : ''}>{colType(k) === 'pendapatan' ? '+' : '−'} {k.nama}</span>
                                                <br />
                                                <span className="text-[9px] text-primary/60 font-normal">
                                                    {k.jenis === 'dinamis_masa_bakti' ? 'Skala Masa Bakti (Default)' : `Default: Rp ${Number(k.nilai_default || 0).toLocaleString('id-ID')}`}
                                                </span>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-border/50">
                                    {visiblePegawais.map((p) => {
                                        const pIdx = dataIndexById.get(p.id);

                                        return (
                                            <tr key={p.id} className={`group transition-colors ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} hover:bg-primary-50/30`}>
                                                <td className={`sticky left-0 z-10 px-3 py-2 whitespace-nowrap border-r border-border shadow-[1px_0_0_0_var(--color-border)] ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} group-hover:bg-primary-50/30`}>
                                                    <div className="text-[11px] font-bold text-text-primary">{p.nama_lengkap}</div>
                                                    <div className="text-[9px] text-text-muted">{p.nip || '-'}</div>
                                                    {p.units && p.units.length > 0 && (
                                                        <div className="text-[9px] text-primary font-bold mt-0.5 uppercase">{p.units[0].nama}</div>
                                                    )}
                                                </td>
                                                {komponens.map((k) => {
                                                    const value = data[pIdx]?.komponens?.[k.id];
                                                    const hasValue = value !== undefined && value !== '';

                                                    return (
                                                        <td key={k.id} className="px-2 py-2 whitespace-nowrap border-r border-border relative focus-within:bg-primary-50/30 transition-colors">
                                                            <div className="relative rounded-input shadow-card">
                                                                <div className="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                                                    <span className="text-text-muted text-[9px]">Rp</span>
                                                                </div>
                                                                <input
                                                                    type="text"
                                                                    inputMode="numeric"
                                                                    className="focus:ring-primary focus:border-primary block w-full pl-5 pr-2 py-1 text-[11px] border-border rounded-input bg-transparent"
                                                                    placeholder={fmtId(p.dynamic_defaults?.[k.id] ?? k.nilai_default)}
                                                                    value={hasValue ? value : ''}
                                                                    onChange={(e) => handleCellChange(pIdx, k.id, e.target.value)}
                                                                />
                                                            </div>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        );
                                    })}
                                    {visiblePegawais.length === 0 && (
                                        <tr>
                                            <td colSpan={komponens.length + 1} className="px-6 py-12 text-center text-text-muted">
                                                Tidak ada pegawai aktif yang cocok dengan filter.
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
