import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import axios from 'axios';
import { formatRupiah } from '@/Utils/format';

export default function RunPayrollWorksheet({ auth, month, year, periode }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [savingId, setSavingId] = useState(null);

    useEffect(() => {
        axios.get(route('penggajian.run.worksheet_data', { month, year }))
            .then(res => {
                setData(res.data);
                setLoading(false);
            })
            .catch(err => {
                alert('Gagal memuat data draft');
                setLoading(false);
            });
    }, [month, year]);

    const addAdHoc = (tipe) => {
        const nama = prompt(`Masukkan nama ${tipe === 'pendapatan' ? 'Pendapatan' : 'Potongan'} baru (Misal: Kasbon, Bonus):`);
        if (!nama) return;

        const newData = data.map(p => {
            return {
                ...p,
                details: [...p.details, {
                    komponen_gaji_id: null,
                    nama_komponen: nama,
                    tipe: tipe,
                    nominal: 0
                }]
            };
        });
        setData(newData);
    };

    const handleCellChange = (penggajianIdx, detailIdx, newNominal) => {
        const newData = [...data];
        newData[penggajianIdx].details[detailIdx].nominal = newNominal === '' ? 0 : parseFloat(newNominal);
        setData(newData);
    };

    const saveRow = (penggajian) => {
        setSavingId(penggajian.id);
        axios.post(route('penggajian.run.worksheet_save', { month, year }), {
            penggajian_id: penggajian.id,
            details: penggajian.details
        }).then(res => {
            setSavingId(null);
        }).catch(err => {
            alert('Gagal menyimpan baris ini!');
            setSavingId(null);
        });
    };

    const allDetailNames = useMemo(() => {
        const names = [];
        data.forEach(p => {
            p.details.forEach(d => {
                if (!names.find(n => n.nama === d.nama_komponen && n.tipe === d.tipe)) {
                    names.push({ nama: d.nama_komponen, tipe: d.tipe });
                }
            });
        });
        return names;
    }, [data]);

    const finalize = () => {
        if(confirm('Kunci Penggajian? Setelah dikunci, data tidak bisa diubah dan slip gaji siap didownload.')) {
            router.post(route('penggajian.run.worksheet_finalize', { month, year }));
        }
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Draft Worksheet" />

            <div className="-m-4 sm:-m-6 lg:-m-8 h-[calc(100vh-4rem)] md:h-[calc(100vh-5rem)] flex flex-col bg-surface pt-6 pb-6 px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col flex-1 min-h-0">

                    <div className="flex justify-between items-center mb-4 shrink-0">
                        <div>
                            <h2 className="text-2xl font-bold text-text-primary">Worksheet Penggajian</h2>
                            <p className="text-sm text-text-muted">Periode: {periode} — Status: <span className="badge-warning uppercase">DRAFT</span></p>
                        </div>
                        <div className="flex space-x-3">
                            <button onClick={() => addAdHoc('pendapatan')} className="btn-secondary btn-sm border-success text-success hover:bg-success-light">
                                + Tambah Pendapatan Khusus
                            </button>
                            <button onClick={() => addAdHoc('potongan')} className="btn-secondary btn-sm border-danger text-danger hover:bg-danger-light">
                                + Tambah Potongan Khusus
                            </button>
                            <button onClick={finalize} className="btn-primary btn-sm">
                                <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                Finalisasi (Kunci)
                            </button>
                        </div>
                    </div>

                    <div className="card flex-1 flex flex-col min-h-0 overflow-hidden">
                        {loading ? (
                            <div className="p-12 text-center text-text-muted flex-1 flex items-center justify-center">Memuat Worksheet...</div>
                        ) : (
                            <div className="overflow-auto flex-1 relative">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface sticky top-0 z-30 shadow-card">
                                        <tr>
                                            <th className="sticky left-0 top-0 bg-surface z-40 px-3 py-2 text-left text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-border border-r shadow-[1px_0_0_0_var(--color-border)]">Pegawai</th>
                                            {allDetailNames.map((col, i) => (
                                                <th key={i} className={`sticky top-0 bg-surface z-30 px-3 py-2 text-right text-[10px] font-bold uppercase tracking-wider border-b border-border border-r min-w-[100px] ${col.tipe === 'pendapatan' ? 'text-success' : 'text-danger'}`}>
                                                    {col.tipe === 'pendapatan' ? '+' : '-'} {col.nama}
                                                </th>
                                            ))}
                                            <th className="sticky right-0 top-0 bg-primary-50/80 z-40 px-3 py-2 text-right text-[10px] font-black text-primary uppercase tracking-wider border-b border-primary/20 border-l shadow-[-1px_0_0_0_rgba(15,61,62,0.1)]">Total Gaji Bersih</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border/50">
                                        {data.map((p, pIdx) => (
                                            <tr key={p.id} className={`group transition-colors ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} hover:bg-primary-50/30`}>
                                                <td className={`sticky left-0 z-10 px-3 py-2 whitespace-nowrap border-r border-border shadow-[1px_0_0_0_var(--color-border)] ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} group-hover:bg-primary-50/30`}>
                                                    <div className="text-[11px] font-bold text-text-primary">{p.pegawai?.nama_lengkap || 'Unknown'}</div>
                                                    <div className="text-[9px] text-text-muted">{p.pegawai?.nip || p.pegawai?.nik || '-'}</div>
                                                    {savingId === p.id && <span className="text-[9px] text-info animate-pulse font-bold">Menyimpan...</span>}
                                                </td>
                                                {allDetailNames.map((col, cIdx) => {
                                                    const detailIdx = p.details.findIndex(d => d.nama_komponen === col.nama && d.tipe === col.tipe);

                                                    if (detailIdx !== -1) {
                                                        const detail = p.details[detailIdx];
                                                        return (
                                                            <td key={cIdx} className="px-2 py-2 whitespace-nowrap border-r border-border relative focus-within:bg-primary-50/30 transition-colors">
                                                                <div className="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                                                    <span className="text-text-muted text-[9px]">Rp</span>
                                                                </div>
                                                                <input
                                                                    type="number"
                                                                    className="input-field w-full text-right pl-5 pr-2 py-1 text-[11px] border-0 border-b border-transparent hover:border-border focus:border-primary focus:ring-0 bg-transparent rounded-none shadow-none"
                                                                    value={detail.nominal}
                                                                    onChange={(e) => handleCellChange(pIdx, detailIdx, e.target.value)}
                                                                    onBlur={() => saveRow(p)}
                                                                />
                                                            </td>
                                                        );
                                                    } else {
                                                        return <td key={cIdx} className="px-2 py-2 border-r border-border text-center text-text-muted text-[9px]">-</td>;
                                                    }
                                                })}
                                                <td className={`sticky right-0 z-10 px-3 py-2 whitespace-nowrap text-right font-black text-primary text-[11px] border-l border-primary/20 shadow-[-1px_0_0_0_rgba(15,61,62,0.1)] ${pIdx % 2 !== 0 ? 'bg-primary-100/40' : 'bg-primary-50/40'} group-hover:bg-primary-100/60`}>
                                                    {formatRupiah(p.gaji_bersih)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
