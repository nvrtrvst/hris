import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, useForm, router } from '@inertiajs/react';

export default function Index({ auth, penggajians, filters }) {
    const { data, setData, post, processing, errors } = useForm({
        periode_bulan: new Date().toLocaleDateString('en-GB', { month: '2-digit', year: 'numeric' }).replace('/', '-'),
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
            header={<h2 className="font-semibold text-2xl text-primary leading-tight">Sistem Penggajian (Payroll)</h2>}
        >
            <Head title="Penggajian" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {auth.permissions?.includes('view_payroll') && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="card-hover flex items-center justify-between">
                                <div>
                                    <h3 className="text-lg font-bold text-primary">Konfigurasi Komponen</h3>
                                    <p className="text-sm text-text-secondary mt-1">Atur Gaji Pokok, PPh21, BPJS, Tunjangan, Potongan</p>
                                </div>
                                <Link href={route('komponen-gaji.index')} className="btn-secondary">
                                    Kelola Komponen
                                </Link>
                            </div>
                            <div className="card p-6 flex flex-col sm:flex-row items-center justify-between bg-primary text-white">
                                <div>
                                    <h3 className="text-xl font-bold text-white mb-1">Mulai Proses Penggajian Baru</h3>
                                    <p className="text-primary/60 text-sm">Gunakan Run Payroll Wizard untuk menarik data absensi, jadwal, dan uang kaget bulanan.</p>
                                </div>
                                <Link href={route('penggajian.run')} className="btn-primary bg-white text-primary hover:bg-surface mt-4 sm:mt-0">
                                    Buka Run Payroll Wizard &rarr;
                                </Link>
                            </div>
                        </div>
                    )}

                    <div className="card-table">
                        <div className="px-6 py-5 border-b border-border bg-white/50 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <div className="flex items-center space-x-4">
                                <h3 className="text-lg font-bold text-primary">Riwayat Penggajian Pegawai</h3>
                            </div>
                            <div className="flex items-center space-x-2">
                                <label className="text-sm text-text-secondary">Filter Periode:</label>
                                <input
                                    type="text"
                                    placeholder="MM-YYYY"
                                    value={filterPeriode}
                                    onChange={handleFilterChange}
                                    className="input-field w-auto"
                                />
                            </div>
                        </div>

                        <div className="table-wrap">
                            <table className="table-base">
                                <thead className="bg-white border-b border-border">
                                    <tr>
                                        <th className="px-4 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Periode</th>
                                        <th className="px-4 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Pegawai</th>
                                        <th className="px-4 py-4 text-right text-xs font-bold text-text-secondary uppercase tracking-wider">Pendapatan</th>
                                        <th className="px-4 py-4 text-right text-xs font-bold text-text-secondary uppercase tracking-wider">Potongan</th>
                                        <th className="px-4 py-4 text-right text-xs font-bold text-text-secondary uppercase tracking-wider">Gaji Bersih</th>
                                        <th className="px-4 py-4 text-center text-xs font-bold text-text-secondary uppercase tracking-wider">Status</th>
                                        <th className="px-4 py-4 text-right text-xs font-bold text-text-secondary uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-border/50">
                                    {penggajians.data.length > 0 ? penggajians.data.map((p) => (
                                        <tr key={p.id} className="hover:bg-surface/80 transition-colors">
                                            <td className="px-4 py-4 whitespace-nowrap">
                                                <span className="font-bold text-primary bg-primary/5 px-3 py-1 rounded-full text-sm">{p.periode_bulan}</span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="font-bold text-text-primary truncate max-w-[200px]">{p.pegawai.nama_lengkap}</div>
                                                <div className="text-xs text-text-secondary">NIK: {p.pegawai.nik}</div>
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-right font-medium text-success">
                                                {formatRupiah(p.total_pendapatan)}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-right font-medium text-danger">
                                                {formatRupiah(p.total_potongan)}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-right font-bold text-primary text-lg">
                                                {formatRupiah(p.gaji_bersih)}
                                            </td>
                                            <td className="px-4 py-4 whitespace-nowrap text-center">
                                                <span className={`badge ${p.status === 'draft' ? 'badge-warning' : 'badge-success'}`}>
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
                                                                className="btn-sm btn-danger"
                                                            >
                                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </Link>
                                                            <Link
                                                                href={route('penggajian.finalize', p.id)}
                                                                method="post"
                                                                as="button"
                                                                onClick={(e) => { if(!confirm('Yakin ingin melakukan finalisasi slip gaji ini?')) e.preventDefault(); }}
                                                                className="btn-sm inline-flex items-center gap-1.5 border border-success text-success hover:bg-success hover:text-white rounded-button transition-all duration-150 font-medium"
                                                            >
                                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
                                                                <span>Finalisasi</span>
                                                            </Link>
                                                        </>
                                                    )}
                                                    <Link
                                                        href={route('penggajian.show', p.id)}
                                                        className="btn-sm btn-primary"
                                                    >
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                        <span>Slip</span>
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan="7" className="px-6 py-12 text-center text-text-secondary">
                                                Belum ada data penggajian. Silakan masukkan periode dan klik "Run Payroll".
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {penggajians.links && (
                            <div className="p-4 border-t border-border bg-surface/50">
                                <Pagination links={penggajians.links} />
                            </div>
                        )}
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
