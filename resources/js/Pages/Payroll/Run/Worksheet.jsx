import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import axios from 'axios';
import { formatRupiah } from '@/Utils/format';

export default function RunPayrollWorksheet({ auth, month, year, periode }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [savingId, setSavingId] = useState(null);

    // Load Draft Data
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

    // Format Rupiah

    // Add Ad-Hoc Column (One-Time Component)
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

    // Handle Cell Edit
    const handleCellChange = (penggajianIdx, detailIdx, newNominal) => {
        const newData = [...data];
        newData[penggajianIdx].details[detailIdx].nominal = newNominal === '' ? 0 : parseFloat(newNominal);
        
        // Recalculate local totals
        let totalPen = 0;
        let totalPot = 0;
        newData[penggajianIdx].details.forEach(d => {
            if (d.tipe === 'pendapatan') totalPen += parseFloat(d.nominal || 0);
            else totalPot += parseFloat(d.nominal || 0);
        });
        
        newData[penggajianIdx].total_pendapatan = totalPen;
        newData[penggajianIdx].total_potongan = totalPot;
        newData[penggajianIdx].gaji_bersih = totalPen - totalPot;
        
        setData(newData);
    };

    // Auto-Save Row when focus leaves cell
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

    // Extract all unique column names to build the dynamic table header (memoized)
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
            
            <div className="-m-4 sm:-m-6 lg:-m-8 h-[calc(100vh-4rem)] md:h-[calc(100vh-5rem)] flex flex-col bg-gray-50 pt-6 pb-6 px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col flex-1 min-h-0">
                    
                    <div className="flex justify-between items-center mb-4 shrink-0">
                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">Worksheet Penggajian</h2>
                            <p className="text-sm text-gray-500">Periode: {periode} — Status: <span className="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded font-bold text-xs uppercase">DRAFT</span></p>
                        </div>
                        <div className="flex space-x-3">
                            <button onClick={() => addAdHoc('pendapatan')} className="bg-white text-green-700 font-bold px-4 py-2 rounded-lg border border-green-200 hover:bg-green-50 shadow-sm text-sm">
                                + Tambah Pendapatan Khusus
                            </button>
                            <button onClick={() => addAdHoc('potongan')} className="bg-white text-red-700 font-bold px-4 py-2 rounded-lg border border-red-200 hover:bg-red-50 shadow-sm text-sm">
                                + Tambah Potongan Khusus
                            </button>
                            <button onClick={finalize} className="bg-indigo-600 text-white font-bold px-6 py-2 rounded-lg hover:bg-indigo-700 shadow-sm flex items-center">
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                Finalisasi (Kunci)
                            </button>
                        </div>
                    </div>

                    <div className="bg-white shadow-xl sm:rounded-2xl border border-gray-200 flex-1 flex flex-col min-h-0 overflow-hidden">
                        {loading ? (
                            <div className="p-12 text-center text-gray-500 flex-1 flex items-center justify-center">Memuat Worksheet...</div>
                        ) : (
                            <div className="overflow-auto flex-1 relative">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-100 sticky top-0 z-30 shadow-sm">
                                        <tr>
                                            <th className="sticky left-0 top-0 bg-gray-100 z-40 px-3 py-2 text-left text-[10px] font-bold text-gray-700 uppercase tracking-wider border-b border-gray-200 border-r shadow-[1px_0_0_0_#e5e7eb]">Pegawai</th>
                                            {allDetailNames.map((col, i) => (
                                                <th key={i} className={`sticky top-0 bg-gray-100 z-30 px-3 py-2 text-right text-[10px] font-bold uppercase tracking-wider border-b border-gray-200 border-r min-w-[100px] ${col.tipe === 'pendapatan' ? 'text-green-700' : 'text-red-700'}`}>
                                                    {col.tipe === 'pendapatan' ? '+' : '-'} {col.nama}
                                                </th>
                                            ))}
                                            <th className="sticky right-0 top-0 bg-indigo-50 z-40 px-3 py-2 text-right text-[10px] font-black text-indigo-900 uppercase tracking-wider border-b border-indigo-200 border-l shadow-[-1px_0_0_0_#c7d2fe]">Total Gaji Bersih</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {data.map((p, pIdx) => (
                                            <tr key={p.id} className={`group transition-colors ${pIdx % 2 !== 0 ? 'bg-gray-100' : 'bg-white'} hover:bg-blue-50/50`}>
                                                <td className={`sticky left-0 z-10 px-3 py-2 whitespace-nowrap border-r border-gray-200 shadow-[1px_0_0_0_#e5e7eb] ${pIdx % 2 !== 0 ? 'bg-gray-100' : 'bg-white'} group-hover:bg-blue-50/50`}>
                                                    <div className="text-[11px] font-bold text-gray-900">{p.pegawai?.nama_lengkap || 'Unknown'}</div>
                                                    <div className="text-[9px] text-gray-500">{p.pegawai?.nip || p.pegawai?.nik || '-'}</div>
                                                    {savingId === p.id && <span className="text-[9px] text-blue-600 animate-pulse font-bold">Menyimpan...</span>}
                                                </td>
                                                {allDetailNames.map((col, cIdx) => {
                                                    // Find if this pegawai has this detail
                                                    const detailIdx = p.details.findIndex(d => d.nama_komponen === col.nama && d.tipe === col.tipe);
                                                    
                                                    if (detailIdx !== -1) {
                                                        const detail = p.details[detailIdx];
                                                        return (
                                                            <td key={cIdx} className="px-2 py-2 whitespace-nowrap border-r border-gray-200 relative focus-within:bg-indigo-50/30 transition-colors">
                                                                <div className="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                                                    <span className="text-gray-400 text-[9px]">Rp</span>
                                                                </div>
                                                                <input
                                                                    type="number"
                                                                    className="w-full text-right pl-5 pr-2 py-1 border-0 border-b border-transparent hover:border-gray-300 focus:border-indigo-500 focus:ring-0 text-[11px] bg-transparent"
                                                                    value={detail.nominal}
                                                                    onChange={(e) => handleCellChange(pIdx, detailIdx, e.target.value)}
                                                                    onBlur={() => saveRow(p)}
                                                                />
                                                            </td>
                                                        );
                                                    } else {
                                                        return <td key={cIdx} className="px-2 py-2 border-r border-gray-200 text-center text-gray-400 text-[9px]">-</td>;
                                                    }
                                                })}
                                                <td className={`sticky right-0 z-10 px-3 py-2 whitespace-nowrap text-right font-black text-indigo-900 text-[11px] border-l border-indigo-200 shadow-[-1px_0_0_0_#c7d2fe] ${pIdx % 2 !== 0 ? 'bg-indigo-100/40' : 'bg-indigo-50'} group-hover:bg-indigo-100`}>
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
