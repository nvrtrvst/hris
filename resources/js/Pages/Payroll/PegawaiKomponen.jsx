import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link, router } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function PegawaiKomponen({ auth, komponen, pegawais }) {
    const { data, setData, post, processing, errors } = useForm({
        pegawai_data: pegawais.map(p => ({ id: p.id, nominal: p.nominal ?? '' }))
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
        .filter(p => p.nama_lengkap.toLowerCase().includes(searchTerm.toLowerCase()) || p.nik?.toLowerCase().includes(searchTerm.toLowerCase()));

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-bold text-xl text-primary leading-tight">
                        <Link href={route('komponen-gaji.index')} className="text-text-muted hover:text-primary mr-2">Komponen /</Link>
                        Penyesuaian Pegawai: {komponen.nama}
                    </h2>
                </div>
            }
        >
            <Head title={`Atur ${komponen.nama}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    <div className="card p-6 flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-bold text-primary">Import Massal via Excel</h3>
                            <p className="text-sm text-text-secondary mt-1">Unduh template, isi nominal potongan/tambahan per NIK, lalu upload kembali ke sini.</p>
                        </div>
                        <div className="flex space-x-3 items-center">
                            <a
                                href={route('komponen-gaji.pegawai.template', komponen.id)}
                                className="btn-secondary btn-sm"
                            >
                                <svg className="w-4 h-4 mr-1.5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Download Template Excel
                            </a>

                            <form onSubmit={handleImport} className="flex items-center space-x-2">
                                <input
                                    type="file"
                                    accept=".csv, .xlsx, .xls"
                                    onChange={e => setImportData('file', e.target.files[0])}
                                    className="block w-full text-sm text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-input file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary hover:file:bg-primary-100 cursor-pointer"
                                />
                                <PrimaryButton disabled={importing || !importData.file} className="whitespace-nowrap">
                                    {importing ? 'Importing...' : 'Upload Excel'}
                                </PrimaryButton>
                            </form>
                        </div>
                    </div>

                    {importErrors?.file && <div className="text-sm text-danger">{importErrors.file}</div>}

                    <form onSubmit={handleBatchSave}>
                        <div className="card-table">
                            <div className="px-6 py-5 border-b border-border bg-white/50 flex justify-between items-center">
                                <div>
                                    <h3 className="text-lg font-bold text-text-primary">Input Manual</h3>
                                    <p className="text-sm text-text-muted">Nilai default komponen ini adalah: <strong>{komponen.nilai_default || 0}</strong>. Kosongkan input untuk menggunakan nilai default.</p>
                                </div>
                                <div className="w-64">
                                    <TextInput
                                        type="text"
                                        placeholder="Cari nama / NIK..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="w-full"
                                    />
                                </div>
                            </div>

                            <div className="table-wrap">
                                <table className="table-base">
                                    <thead className="bg-surface/50 border-b border-border">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">NIK</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Nama Pegawai</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Unit</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Nominal Spesifik (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border/50">
                                        {displayPegawais.map((p) => (
                                            <tr key={p.id} className="hover:bg-surface/50 transition-colors">
                                                <td className="px-6 py-3 whitespace-nowrap text-sm text-text-muted">{p.nik}</td>
                                                <td className="px-6 py-3 whitespace-nowrap text-sm font-medium text-text-primary">{p.nama_lengkap}</td>
                                                <td className="px-6 py-3 whitespace-nowrap text-sm text-text-muted">{p.unit}</td>
                                                <td className="px-6 py-3 whitespace-nowrap">
                                                    <input
                                                        type="number"
                                                        value={data.pegawai_data[p.originalIndex].nominal}
                                                        onChange={(e) => handleNominalChange(p.originalIndex, e.target.value)}
                                                        placeholder="Gunakan Default"
                                                        className="input-field w-48"
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="p-4 bg-surface/50 border-t border-border flex justify-end">
                                <PrimaryButton disabled={processing} className="px-8 py-3">
                                    {processing ? 'Menyimpan...' : 'Simpan Semua Perubahan'}
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
