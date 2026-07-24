import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatRupiah } from '@/Utils/format';
import { PAYROLL_STATUS } from '@/Constants';

export default function Show({ auth, penggajian }) {
    const pendapatan = penggajian.details.filter(d => d.tipe === 'pendapatan');
    const potongan = penggajian.details.filter(d => d.tipe === 'potongan');

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-primary leading-tight">Slip Gaji</h2>}
        >
            <Head title={`Slip Gaji - ${penggajian.pegawai.nama_lengkap}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    
                    <div className="mb-6 flex flex-wrap items-center gap-3">
                        <Link href={route('penggajian.index')} className="text-primary hover:text-primary-light font-medium flex items-center">
                            <svg className="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            Kembali ke Daftar
                        </Link>
                        <div className="flex-1"></div>
                        <button className="bg-primary text-white px-4 py-2 rounded-lg font-medium shadow-md hover:bg-primary-light print:hidden" onClick={() => window.print()}>
                            Cetak Slip
                        </button>
                        {penggajian.status === PAYROLL_STATUS.FINALIZED && (
                            <button
                                className="bg-success text-white px-4 py-2 rounded-lg font-medium shadow-md hover:bg-green-700 print:hidden"
                                onClick={() => {
                                    if (confirm('Tandai slip gaji ini sudah DIBAYAR?')) {
                                        router.post(route('penggajian.mark_paid', penggajian.id));
                                    }
                                }}
                            >
                                Tandai Dibayar
                            </button>
                        )}
                    </div>

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-border print:shadow-none print:border-none p-6 sm:p-10">
                        {/* Header Slip */}
                        <div className="border-b-2 border-gray-800 pb-6 mb-8 flex justify-between items-end">
                            <div>
                                <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">YAYASAN PENDIDIKAN</h1>
                                <p className="text-gray-500 mt-1">Jl. Pendidikan No. 1, Jakarta Selatan</p>
                            </div>
                            <div className="text-right">
                                <h2 className="text-2xl font-bold text-indigo-700 uppercase tracking-widest">SLIP GAJI</h2>
                                <p className="text-gray-600 font-medium mt-1">Periode: {penggajian.periode_bulan}</p>
                            </div>
                        </div>

                        {/* Info Pegawai */}
                        <div className="grid grid-cols-2 gap-8 mb-10 bg-surface p-6 rounded-xl border border-border">
                            <div>
                                <table className="w-full text-sm">
                                    <tbody>
                                        <tr>
                                            <td className="py-1 text-text-secondary w-32">Nama Lengkap</td>
                                            <td className="py-1 font-bold text-primary">: {penggajian.pegawai.nama_lengkap}</td>
                                        </tr>
                                        <tr>
                                            <td className="py-1 text-text-secondary">NIK</td>
                                            <td className="py-1 font-bold text-primary">: {penggajian.pegawai.nik}</td>
                                        </tr>
                                        <tr>
                                            <td className="py-1 text-text-secondary">Status</td>
                                            <td className="py-1 font-bold text-primary uppercase">: {penggajian.pegawai.status_kepegawaian}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div>
                                <table className="w-full text-sm">
                                    <tbody>
                                        <tr>
                                            <td className="py-1 text-text-secondary w-32">Unit Utama</td>
                                            <td className="py-1 font-bold text-primary">: {penggajian.pegawai.units?.find(u => u.pivot.is_primary)?.nama || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td className="py-1 text-text-secondary">Jabatan</td>
                                            <td className="py-1 font-bold text-primary">: {penggajian.pegawai.jabatans.find(j => j.pivot.is_primary)?.nama || '-'}</td>
                                        </tr>
                                        <tr>
                                            <td className="py-1 text-text-secondary">Tanggal Cetak</td>
                                            <td className="py-1 font-bold text-primary">: {new Date(penggajian.tanggal_generate).toLocaleDateString('id-ID')}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Rincian Gaji */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-12 mb-8">
                            {/* Pendapatan */}
                            <div>
                                <h3 className="text-lg font-bold text-green-700 border-b-2 border-green-200 pb-2 mb-4 uppercase tracking-wider">Pendapatan</h3>
                                <table className="w-full text-sm">
                                    <tbody>
                                        {pendapatan.map(p => (
                                            <tr key={p.id}>
                                                <td className="py-2 text-gray-700">{p.nama_komponen}</td>
                                                <td className="py-2 text-right font-medium text-gray-900">{formatRupiah(p.nominal)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t border-gray-200">
                                            <td className="py-3 font-bold text-gray-900">Total Pendapatan</td>
                                            <td className="py-3 text-right font-bold text-green-700 text-base">{formatRupiah(penggajian.total_pendapatan)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {/* Potongan */}
                            <div>
                                <h3 className="text-lg font-bold text-red-700 border-b-2 border-red-200 pb-2 mb-4 uppercase tracking-wider">Potongan</h3>
                                <table className="w-full text-sm">
                                    <tbody>
                                        {potongan.length > 0 ? potongan.map(p => (
                                            <tr key={p.id}>
                                                <td className="py-2 text-gray-700">{p.nama_komponen}</td>
                                                <td className="py-2 text-right font-medium text-gray-900">{formatRupiah(p.nominal)}</td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="2" className="py-2 text-gray-400 italic">Tidak ada potongan</td>
                                            </tr>
                                        )}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t border-gray-200">
                                            <td className="py-3 font-bold text-gray-900">Total Potongan</td>
                                            <td className="py-3 text-right font-bold text-red-700 text-base">{formatRupiah(penggajian.total_potongan)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {/* Take Home Pay */}
                        <div className="bg-indigo-900 rounded-2xl p-8 flex flex-col md:flex-row justify-between items-center text-white mt-12 shadow-inner">
                            <div>
                                <p className="text-indigo-200 text-sm font-semibold uppercase tracking-widest mb-1">Penerimaan Bersih (Take Home Pay)</p>
                                <p className="text-xs text-indigo-300 italic">*Ditransfer ke rekening terdaftar</p>
                            </div>
                            <div className="text-4xl font-extrabold tracking-tight mt-4 md:mt-0">
                                {formatRupiah(penggajian.gaji_bersih)}
                            </div>
                            <p className="text-xs text-indigo-300 italic mt-2">Total Kena Pajak: {formatRupiah(penggajian.total_taxable)}</p>
                        </div>

                        {/* Signatures */}
                        <div className="mt-20 grid grid-cols-2 gap-8 text-center text-sm">
                            <div>
                                <p className="mb-24 text-gray-600">Penerima,</p>
                                <p className="font-bold border-b border-gray-400 inline-block px-8 pb-1">{penggajian.pegawai.nama_lengkap}</p>
                            </div>
                            <div>
                                <p className="mb-24 text-gray-600">Mengetahui, HR Yayasan</p>
                                <p className="font-bold border-b border-gray-400 inline-block px-8 pb-1">Administrator HR</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
