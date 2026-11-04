import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, PageHeader } from '@/Components/AdminUI';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Printer } from 'lucide-react';
import { formatRupiah } from '@/Utils/format';
import { PAYROLL_STATUS } from '@/Constants';

export default function Show({ auth, penggajian }) {
    const pendapatan = penggajian.details.filter(d => d.tipe === 'pendapatan');
    const potongan = penggajian.details.filter(d => d.tipe === 'potongan');

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Slip Gaji</h2>}
        >
            <Head title={`Slip Gaji - ${penggajian.pegawai.nama_lengkap}`} />

            <div className="mx-auto w-full max-w-4xl space-y-5">
                <PageHeader
                    className="print:hidden"
                    eyebrow="Penggajian"
                    title="Slip Gaji"
                    description={`Periode ${penggajian.periode_bulan} · ${penggajian.pegawai.nama_lengkap}`}
                    actions={
                        <>
                            <Link href={route('penggajian.index')} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                <ArrowLeft className="h-4 w-4" /> Kembali
                            </Link>
                            <Button variant="secondary" onClick={() => window.print()}>
                                <Printer className="h-4 w-4" /> Cetak Slip
                            </Button>
                            {penggajian.status === PAYROLL_STATUS.FINALIZED && (
                                <Button
                                    onClick={() => {
                                        if (confirm('Tandai slip gaji ini sudah DIBAYAR?')) {
                                            router.post(route('penggajian.mark_paid', penggajian.id));
                                        }
                                    }}
                                >
                                    <CheckCircle2 className="h-4 w-4" /> Tandai Dibayar
                                </Button>
                            )}
                        </>
                    }
                />

                    <div className="card p-6 sm:p-10 print:shadow-none print:border-none">
                        <div className="mb-8 flex flex-col gap-3 border-b-2 border-primary/30 pb-6 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p className="text-3xl font-extrabold tracking-tight text-text-primary">YAYASAN PENDIDIKAN</p>
                                <p className="text-text-muted mt-1">Jl. Pendidikan No. 1, Jakarta Selatan</p>
                            </div>
                            <div className="text-right">
                                <h2 className="text-2xl font-bold text-primary uppercase tracking-widest">SLIP GAJI</h2>
                                <p className="text-text-secondary font-medium mt-1">Periode: {penggajian.periode_bulan}</p>
                            </div>
                        </div>

                        <div className="card p-6 rounded-card mb-10">
                            <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
                                <div>
                                    <div className="overflow-x-auto"><table className="w-full text-sm">
                                        <tbody>
                                            <tr>
                                                <td className="py-1 text-text-secondary w-32">Nama Lengkap</td>
                                                <td className="py-1 font-bold text-text-primary">: {penggajian.pegawai.nama_lengkap}</td>
                                            </tr>
                                            <tr>
                                                <td className="py-1 text-text-secondary">NIP</td>
                                                <td className="py-1 font-bold text-text-primary">: {penggajian.pegawai.nuptk || '—'}</td>
                                            </tr>
                                            <tr>
                                                <td className="py-1 text-text-secondary">Status</td>
                                                <td className="py-1 font-bold text-text-primary uppercase">: {penggajian.pegawai.statusRef?.label || penggajian.pegawai.status_kepegawaian}</td>
                                            </tr>
                                        </tbody>
                                    </table></div>
                                </div>
                                <div>
                                    <div className="overflow-x-auto"><table className="w-full text-sm">
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
                                    </table></div>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-12 mb-8">
                            <div>
                                <h3 className="text-lg font-bold text-success border-b-2 border-success/20 pb-2 mb-4 uppercase tracking-wider">Pendapatan</h3>
                                <div className="overflow-x-auto"><table className="w-full text-sm">
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
                                </table></div>
                            </div>

                            <div>
                                <h3 className="text-lg font-bold text-danger border-b-2 border-danger/20 pb-2 mb-4 uppercase tracking-wider">Potongan</h3>
                                <div className="overflow-x-auto"><table className="w-full text-sm">
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
                                </table></div>
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
        </AdminShell>
    );
}
