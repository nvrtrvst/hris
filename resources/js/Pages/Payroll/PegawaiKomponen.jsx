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

    // Filter pegawais visually based on search (we keep the index synced with the original data array)
    const displayPegawais = pegawais.map((p, originalIndex) => ({ ...p, originalIndex }))
        .filter(p => p.nama_lengkap.toLowerCase().includes(searchTerm.toLowerCase()) || p.nik?.toLowerCase().includes(searchTerm.toLowerCase()));

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-bold text-xl text-primary leading-tight">
                        <Link href={route('komponen-gaji.index')} className="text-gray-400 hover:text-primary mr-2">Komponen /</Link>
                        Penyesuaian Pegawai: {komponen.nama}
                    </h2>
                </div>
            }
        >
            <Head title={`Atur ${komponen.nama}`} />

            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                {/* Excel Actions Card */}
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-border p-6 flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-bold text-primary">Import Massal via Excel</h3>
                        <p className="text-sm text-text-secondary mt-1">Unduh template, isi nominal potongan/tambahan per NIK, lalu upload kembali ke sini.</p>
                    </div>
                    <div className="flex space-x-3 items-center">
                        <a 
                            href={route('komponen-gaji.pegawai.template', komponen.id)}
                            className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            <svg className="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Download Template Excel
                        </a>

                        <form onSubmit={handleImport} className="flex items-center space-x-2">
                            <input 
                                type="file" 
                                accept=".csv, .xlsx, .xls"
                                onChange={e => setImportData('file', e.target.files[0])}
                                className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                            />
                            <PrimaryButton disabled={importing || !importData.file} className="whitespace-nowrap">
                                {importing ? 'Importing...' : 'Upload Excel'}
                            </PrimaryButton>
                        </form>
                    </div>
                </div>

                {importErrors?.file && <div className="text-red-500 text-sm">{importErrors.file}</div>}

                {/* Manual Edit Table */}
                <form onSubmit={handleBatchSave}>
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-border">
                        <div className="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                            <div>
                                <h3 className="text-lg font-bold text-gray-900">Input Manual</h3>
                                <p className="text-sm text-gray-500">Nilai default komponen ini adalah: <strong>{komponen.nilai_default || 0}</strong>. Kosongkan input untuk menggunakan nilai default.</p>
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

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIK</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Pegawai</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nominal Spesifik (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {displayPegawais.map((p) => (
                                        <tr key={p.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-3 whitespace-nowrap text-sm text-gray-500">{p.nik}</td>
                                            <td className="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{p.nama_lengkap}</td>
                                            <td className="px-6 py-3 whitespace-nowrap text-sm text-gray-500">{p.unit}</td>
                                            <td className="px-6 py-3 whitespace-nowrap">
                                                <input
                                                    type="number"
                                                    value={data.pegawai_data[p.originalIndex].nominal}
                                                    onChange={(e) => handleNominalChange(p.originalIndex, e.target.value)}
                                                    placeholder="Gunakan Default"
                                                    className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm w-48"
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="p-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                            <PrimaryButton disabled={processing} className="px-8 py-3">
                                {processing ? 'Menyimpan...' : 'Simpan Semua Perubahan'}
                            </PrimaryButton>
                        </div>
                    </div>
                </form>

            </div>
        </AuthenticatedLayout>
    );
}
