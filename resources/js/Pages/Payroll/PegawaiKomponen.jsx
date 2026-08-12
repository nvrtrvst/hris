import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Download, FileSpreadsheet, Loader2, Save, Search, Users as UsersIcon } from 'lucide-react';

export default function PegawaiKomponen({ auth, komponen, pegawais }) {
    const { data, setData, post, processing } = useForm({
        pegawai_data: pegawais.map((p) => ({ id: p.id, nominal: p.nominal ?? '' }))
    });

    const { data: importData, setData: setImportData, post: postImport, processing: importing, errors: importErrors } = useForm({
        file: null
    });

    const [searchTerm, setSearchTerm] = useState('');

    const handleNominalChange = (index, value) => {
        const newData = [...data.pegawai_data];
        newData[index].nominal = value;
        setData('pegawai_data', newData);
    };

    const handleBatchSave = (e) => {
        e.preventDefault();
        post(route('komponen-gaji.pegawai.batch', komponen.id), {
            preserveScroll: true
        });
    };

    const handleImport = (e) => {
        e.preventDefault();
        postImport(route('komponen-gaji.pegawai.import', komponen.id), {
            preserveScroll: true,
            onSuccess: () => setImportData('file', null),
        });
    };

    const displayPegawais = pegawais.map((p, originalIndex) => ({ ...p, originalIndex }))
        .filter((p) => p.nama_lengkap.toLowerCase().includes(searchTerm.toLowerCase()) || (p.nik_masked || '').toLowerCase().includes(searchTerm.toLowerCase()));

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Penyesuaian Pegawai: {komponen.nama}</h2>}
        >
            <Head title={`Atur ${komponen.nama}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <Link href={route('komponen-gaji.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Komponen Gaji
                    </Link>

                    {/* Import massal */}
                    <div className="card p-6">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-start gap-4">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                    <FileSpreadsheet className="h-5 w-5 text-primary" />
                                </span>
                                <div>
                                    <h3 className="text-base font-extrabold text-text-primary">Import Massal via Excel</h3>
                                    <p className="mt-0.5 text-sm text-text-muted">Unduh template, isi nominal potongan/tambahan per NIK, lalu upload kembali ke sini.</p>
                                    {importErrors?.file && <p className="mt-2 text-sm font-semibold text-danger">{importErrors.file}</p>}
                                </div>
                            </div>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <a href={route('komponen-gaji.pegawai.template', komponen.id)} className="btn-secondary inline-flex items-center justify-center gap-1.5">
                                    <Download className="h-4 w-4" /> Download Template Excel
                                </a>
                                <form onSubmit={handleImport} className="flex items-center gap-2">
                                    <input
                                        type="file"
                                        accept=".csv, .xlsx, .xls"
                                        onChange={(e) => setImportData('file', e.target.files[0])}
                                        className="block w-full max-w-52 text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20"
                                    />
                                    <button type="submit" disabled={importing || !importData.file} className="btn-primary btn-sm inline-flex items-center gap-1.5">
                                        {importing ? <><Loader2 className="h-3.5 w-3.5 animate-spin" /> Import…</> : <><FileSpreadsheet className="h-3.5 w-3.5" /> Upload</>}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {/* Input manual */}
                    <form onSubmit={handleBatchSave}>
                        <div className="card overflow-hidden">
                            <div className="flex flex-col gap-3 border-b border-border bg-surface px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 className="flex items-center gap-2 text-base font-extrabold text-text-primary">
                                        <UsersIcon className="h-4 w-4 text-primary" /> Input Manual
                                    </h3>
                                    <p className="mt-0.5 text-xs text-text-muted">
                                        Nilai default komponen: <strong className="text-text-primary">{komponen.nilai_default || 0}</strong>. Kosongkan input untuk memakai nilai default.
                                    </p>
                                </div>
                                <div className="relative sm:w-64">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input
                                        type="text"
                                        placeholder="Cari nama / NIK..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="input-field pl-9"
                                    />
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">NIK</th>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nama Pegawai</th>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Unit</th>
                                            <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Nominal Spesifik (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border bg-white">
                                        {displayPegawais.map((p) => (
                                            <tr key={p.id} className="transition-colors hover:bg-surface">
                                                <td className="whitespace-nowrap px-6 py-3 font-mono text-xs text-text-muted">{p.nik_masked}</td>
                                                <td className="whitespace-nowrap px-6 py-3 text-sm font-semibold text-text-primary">{p.nama_lengkap}</td>
                                                <td className="whitespace-nowrap px-6 py-3 text-sm text-text-muted">{p.unit}</td>
                                                <td className="whitespace-nowrap px-6 py-3 text-right">
                                                    <input
                                                        type="number"
                                                        value={data.pegawai_data[p.originalIndex].nominal}
                                                        onChange={(e) => handleNominalChange(p.originalIndex, e.target.value)}
                                                        placeholder="Gunakan Default"
                                                        className="input-field w-44 text-right"
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex justify-end border-t border-border bg-surface/50 px-6 py-4">
                                <button type="submit" disabled={processing} className="btn-primary inline-flex items-center gap-2">
                                    {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Simpan Semua Perubahan</>}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
