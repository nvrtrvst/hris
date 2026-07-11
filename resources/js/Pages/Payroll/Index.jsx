import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, useForm, router } from '@inertiajs/react';

export default function Index({ auth, penggajians, filters }) {
    const { data, setData, post, processing, errors } = useForm({
        periode_bulan: new Date().toLocaleDateString('en-GB', { month: '2-digit', year: 'numeric' }).replace('/', '-'), // e.g. 06-2026
    });

    const [filterPeriode, setFilterPeriode] = useState(filters.periode_bulan || '');

    const handleGenerate = (e) => {
        e.preventDefault();
        if (confirm(`Generate penggajian untuk periode ${data.periode_bulan}? Pastikan data kehadiran sudah valid.`)) {
            post(route('penggajian.generate'));
        }
    };

    const handleFilterChange = (e) => {
        const val = e.target.value;
        setFilterPeriode(val);
        router.get(route('penggajian.index'), { periode_bulan: val }, { preserveState: true });
    };

    const formatRupiah = (angka) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Sistem Penggajian (Payroll)</h2>}
        >
            <Head title="Penggajian" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                    {/* Top Action Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-white p-6 shadow-xl sm:rounded-2xl border border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 className="text-lg font-bold text-gray-900">Konfigurasi Komponen</h3>
                                <p className="text-sm text-gray-500 mt-1">Atur Gaji Pokok, PPh21, BPJS, Tunjangan, Potongan</p>
                            </div>
                            <Link href={route('komponen-gaji.index')} className="bg-white border-2 border-indigo-600 text-indigo-600 hover:bg-indigo-50 font-bold py-2 px-6 rounded-lg transition-colors">
                                Kelola Komponen
                            </Link>
                        </div>

                        <div className="bg-indigo-600 p-6 shadow-xl sm:rounded-2xl border border-indigo-700 flex flex-col sm:flex-row items-center justify-between">
                            <div>
                                <h3 className="text-xl font-bold text-white mb-1">Mulai Proses Penggajian Baru</h3>
                                <p className="text-indigo-200 text-sm">Gunakan Run Payroll Wizard untuk menarik data absensi, jadwal, dan uang kaget bulanan.</p>
                            </div>
                            <Link href={route('penggajian.run')} className="mt-4 sm:mt-0 bg-white text-indigo-600 hover:bg-gray-100 font-bold py-3 px-8 rounded-xl transition-colors shadow-lg">
                                Buka Run Payroll Wizard &rarr;
                            </Link>
                        </div>
                    </div>

                    {/* Data Table */}
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="px-6 py-5 border-b border-gray-200 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <div className="flex items-center space-x-4">
                                <h3 className="text-lg font-bold text-gray-900">Riwayat Penggajian Pegawai</h3>
                            </div>
                            <div className="flex items-center space-x-2">
                                <label className="text-sm text-gray-600">Filter Periode:</label>
                                <input 
                                    type="text" 
                                    placeholder="MM-YYYY" 
                                    value={filterPeriode}
                                    onChange={handleFilterChange}
                                    className="border-gray-300 rounded-md shadow-sm text-sm py-1"
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto w-full">
                            <table className="w-full min-w-max divide-y divide-gray-200">
                                <thead className="bg-white">
                                    <tr>
                                        <th className="px-4 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Periode</th>
                                        <th className="px-4 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Pegawai</th>
                                        <th className="px-4 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Pendapatan</th>
                                        <th className="px-4 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Potongan</th>
                                        <th className="px-4 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Gaji Bersih</th>
                                        <th className="px-4 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-4 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-100">
                                    {penggajians.data.length > 0 ? penggajians.data.map((p) => (
                                        <tr key={p.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                <span className="font-bold text-indigo-900 bg-indigo-50 px-3 py-1 rounded-full text-sm">{p.periode_bulan}</span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="font-bold text-gray-900 truncate max-w-[200px]">{p.pegawai.nama_lengkap}</div>
                                                <div className="text-xs text-gray-500">NIK: {p.pegawai.nik}</div>
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-right font-medium text-green-600">
                                                {formatRupiah(p.total_pendapatan)}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-right font-medium text-red-600">
                                                {formatRupiah(p.total_potongan)}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-right font-bold text-gray-900 text-lg">
                                                {formatRupiah(p.gaji_bersih)}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-center">
                                                <span className={`px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full uppercase ${p.status === 'draft' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'}`}>
                                                    {p.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div className="flex justify-end items-center space-x-2">
                                                    {p.status === 'draft' && auth.user.email === 'admin@yayasan.com' && (
                                                        <>
                                                            <Link 
                                                                href={route('penggajian.destroy', p.id)} 
                                                                method="delete" 
                                                                as="button"
                                                                onClick={(e) => { if(!confirm('Yakin ingin menghapus draft slip gaji ini?')) e.preventDefault(); }}
                                                                className="inline-flex items-center space-x-1.5 px-3 py-1.5 border border-red-500 text-red-600 hover:bg-red-500 hover:text-white rounded-lg text-xs font-bold uppercase tracking-wider transition-all shadow-sm"
                                                            >
                                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </Link>
                                                            <Link 
                                                                href={route('penggajian.finalize', p.id)} 
                                                                method="post" 
                                                                as="button" 
                                                                onClick={(e) => { if(!confirm('Yakin ingin melakukan finalisasi slip gaji ini?')) e.preventDefault(); }}
                                                                className="inline-flex items-center space-x-1.5 px-3 py-1.5 border border-emerald-500 text-emerald-600 hover:bg-emerald-500 hover:text-white rounded-lg text-xs font-bold uppercase tracking-wider transition-all shadow-sm"
                                                            >
                                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
                                                                <span>Finalisasi</span>
                                                            </Link>
                                                        </>
                                                    )}
                                                    <Link 
                                                        href={route('penggajian.show', p.id)} 
                                                        className="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-indigo-600 text-white hover:bg-indigo-700 rounded-lg text-xs font-bold uppercase tracking-wider transition-all shadow-sm"
                                                    >
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                        <span>Slip</span>
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan="7" className="px-6 py-12 text-center text-gray-500">
                                                Belum ada data penggajian. Silakan masukkan periode dan klik "Run Payroll".
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        
                        {penggajians.links && (
                            <div className="p-4 border-t border-gray-200 bg-gray-50">
                                <Pagination links={penggajians.links} />
                            </div>
                        )}
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
