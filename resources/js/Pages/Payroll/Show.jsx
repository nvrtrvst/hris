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

                    <div className="page-header">
                        <Link href={route('penggajian.index')} className="link text-sm flex items-center">
                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            Kembali ke Daftar
                        </Link>
                        <div className="flex items-center gap-3">
                            <button className="btn-primary btn-sm" onClick={() => window.print()}>
                                Cetak Slip
                            </button>
                            {penggajian.status === PAYROLL_STATUS.FINALIZED && (
                                <button
                                    className="btn-sm inline-flex items-center gap-1.5 bg-success text-white rounded-button px-3 py-1.5 text-xs font-medium hover:bg-green-700 transition-all duration-150"
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
                    </div>

                    <div className="card p-6 sm:p-10 print:shadow-none print:border-none">
                        <div className="border-b-2 border-primary/30 pb-6 mb-8 flex justify-between items-end">
                            <div>
                                <h1 className="text-3xl font-extrabold text-text-primary tracking-tight">YAYASAN PENDIDIKAN</h1>
                                <p className="text-text-muted mt-1">Jl. Pendidikan No. 1, Jakarta Selatan</p>
                            </div>
                            <div className="text-right">
                                <h2 className="text-2xl font-bold text-primary uppercase tracking-widest">SLIP GAJI</h2>
                                <p className="text-text-secondary font-medium mt-1">Periode: {penggajian.periode_bulan}</p>
                            </div>
                        </div>

                        <div className="card p-6 rounded-card mb-10">
                            <div className="grid grid-cols-2 gap-8">
                                <div>
                                    <table className="w-full text-sm">
                                        <tbody>
                                            <tr>
                                                <td className="py-1 text-text-secondary w-32">Nama Lengkap</td>
                                                <td className="py-1 font-bold text-text-primary">: {penggajian.pegawai.nama_lengkap}</td>
                                            </tr>
                                            <tr>
                                                <td className="py-1 text-text-secondary">NIK</td>
                                                <td className="py-1 font-bold text-text-primary">: {penggajian.pegawai.nik}</td>
                                            </tr>
                                            <tr>
                                                <td className="py-1 text-text-secondary">Status</td>
                                                <td className="py-1 font-bold text-text-primary uppercase">: {penggajian.pegawai.status_kepegawaian}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div>
                                    <table className="w-full text-sm">
                                        <tbody>
                                            <tr>
                                                <td className="py-1 text-text-secondary w-32">Unit Utama</td>
                                                <td className="py-1 font-bold text-text-primary">: {penggajian.pegawai.units?.find(u => u.pivot.is_primary)?.nama || '-'}</td>
                                            </tr>
                                            <tr>
                                                <td className="py-1 text-text-secondary">Jabatan</td>
                                                <td className="py-1 font-bold text-text-primary">: {penggajian.pegawai.jabatans.find(j => j.pivot.is_primary)?.nama || '-'}</td>
                                            </tr>
                                            <tr>
                                                <td className="py-1 text-text-secondary">Tanggal Cetak</td>
                                                <td className="py-1 font-bold text-text-primary">: {new Date(penggajian.tanggal_generate).toLocaleDateString('id-ID')}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-12 mb-8">
                            <div>
                                <h3 className="text-lg font-bold text-success border-b-2 border-success/20 pb-2 mb-4 uppercase tracking-wider">Pendapatan</h3>
                                <table className="w-full text-sm">
                                    <tbody>
                                        {pendapatan.map(p => (
                                            <tr key={p.id}>
                                                <td className="py-2 text-text-secondary">{p.nama_komponen}</td>
                                                <td className="py-2 text-right font-medium text-text-primary">{formatRupiah(p.nominal)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t border-border">
                                            <td className="py-3 font-bold text-text-primary">Total Pendapatan</td>
                                            <td className="py-3 text-right font-bold text-success text-base">{formatRupiah(penggajian.total_pendapatan)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div>
                                <h3 className="text-lg font-bold text-danger border-b-2 border-danger/20 pb-2 mb-4 uppercase tracking-wider">Potongan</h3>
                                <table className="w-full text-sm">
                                    <tbody>
                                        {potongan.length > 0 ? potongan.map(p => (
                                            <tr key={p.id}>
                                                <td className="py-2 text-text-secondary">{p.nama_komponen}</td>
                                                <td className="py-2 text-right font-medium text-text-primary">{formatRupiah(p.nominal)}</td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="2" className="py-2 text-text-muted italic">Tidak ada potongan</td>
                                            </tr>
                                        )}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t border-border">
                                            <td className="py-3 font-bold text-text-primary">Total Potongan</td>
                                            <td className="py-3 text-right font-bold text-danger text-base">{formatRupiah(penggajian.total_potongan)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div className="card bg-primary p-8 flex flex-col md:flex-row justify-between items-center text-white mt-12">
                            <div>
                                <p className="text-primary-200 text-sm font-semibold uppercase tracking-widest mb-1">Penerimaan Bersih (Take Home Pay)</p>
                                <p className="text-xs text-primary-300 italic">*Ditransfer ke rekening terdaftar</p>
                            </div>
                            <div className="text-4xl font-extrabold tracking-tight mt-4 md:mt-0">
                                {formatRupiah(penggajian.gaji_bersih)}
                            </div>
                            <p className="text-xs text-primary-300 italic mt-2">Total Kena Pajak: {formatRupiah(penggajian.total_taxable)}</p>
                        </div>

                        <div className="mt-20 grid grid-cols-2 gap-8 text-center text-sm">
                            <div>
                                <p className="mb-24 text-text-secondary">Penerima,</p>
                                <p className="font-bold border-b border-border inline-block px-8 pb-1">{penggajian.pegawai.nama_lengkap}</p>
                            </div>
                            <div>
                                <p className="mb-24 text-text-secondary">Mengetahui, HR Yayasan</p>
                                <p className="font-bold border-b border-border inline-block px-8 pb-1">Administrator HR</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
