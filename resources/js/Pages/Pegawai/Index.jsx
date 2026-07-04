import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Index({ auth, pegawais, filters, unitSekolahs, mataPelajarans }) {
    const [search, setSearch] = useState(filters.search || '');
    const [unitSekolahId, setUnitSekolahId] = useState(filters.unit_sekolah_id || '');
    const [mataPelajaranId, setMataPelajaranId] = useState(filters.mata_pelajaran_id || '');
    const [showImportModal, setShowImportModal] = useState(false);

    const { data: importData, setData: setImportData, post: postImport, processing: importProcessing, errors: importErrors, reset: resetImport } = useForm({
        file: null,
        unit_sekolah_id: auth.user.role === 'admin_unit' ? auth.user.unit_sekolah_id : ''
    });

    const handleImportSubmit = (e) => {
        e.preventDefault();
        postImport(route('pegawai.import'), {
            onSuccess: () => {
                setShowImportModal(false);
                resetImport();
            }
        });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('pegawai.index'), { search, unit_sekolah_id: unitSekolahId, mata_pelajaran_id: mataPelajaranId }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Manajemen Pegawai</h2>}
        >
            <Head title="Manajemen Pegawai" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <div className="flex flex-col md:flex-row justify-between md:items-end gap-4 mb-6">
                                <div>
                                    <h3 className="text-xl font-bold text-gray-900">Daftar Pegawai</h3>
                                    <p className="text-sm text-gray-500 mt-1">Kelola data seluruh pegawai di lingkungan yayasan.</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <a
                                        href={route('pegawai.template')}
                                        className="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                                    >
                                        <svg className="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                        Template
                                    </a>
                                    <button
                                        onClick={() => setShowImportModal(true)}
                                        className="inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                                    >
                                        <svg className="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                        Import
                                    </button>
                                    <Link
                                        href={route('pegawai.create')}
                                        className="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                                    >
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Tambah Pegawai
                                    </Link>
                                </div>
                            </div>

                            <form onSubmit={handleSearch} className="mb-8 p-4 bg-gray-50 rounded-xl border border-gray-200 shadow-sm">
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div className="md:col-span-1">
                                        <label className="block text-xs font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Cari Nama / NIK</label>
                                        <div className="relative">
                                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                            </div>
                                            <input
                                                type="text"
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                placeholder="Masukkan kata kunci..."
                                                className="block w-full pl-10 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm"
                                            />
                                        </div>
                                    </div>
                                    {auth.user.role !== 'admin_unit' && (
                                        <div className="md:col-span-1">
                                            <label className="block text-xs font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Unit Sekolah</label>
                                            <select
                                                value={unitSekolahId}
                                                onChange={(e) => setUnitSekolahId(e.target.value)}
                                                className="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm"
                                            >
                                                <option value="">Semua Unit</option>
                                                {unitSekolahs && unitSekolahs.map(unit => (
                                                    <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                                ))}
                                            </select>
                                        </div>
                                    )}
                                    <div className="md:col-span-1">
                                        <label className="block text-xs font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Mata Pelajaran</label>
                                        <select
                                            value={mataPelajaranId}
                                            onChange={(e) => setMataPelajaranId(e.target.value)}
                                            className="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm"
                                        >
                                            <option value="">Semua Mata Pelajaran</option>
                                            {mataPelajarans && mataPelajarans.map(mapel => (
                                                <option key={mapel.id} value={mapel.id}>{mapel.nama}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="md:col-span-1 flex items-end">
                                        <button type="submit" className="w-full bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 hover:text-indigo-700 focus:ring-4 focus:ring-gray-100 font-semibold rounded-lg text-sm px-5 py-2.5 shadow-sm transition-all duration-200">
                                            Terapkan Filter
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama / NIP / NIK</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit & Jabatan</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status Pegawai</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status Aktif</th>
                                            <th className="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {pegawais.data.length > 0 ? (
                                            pegawais.data.map((pegawai) => (
                                                <tr key={pegawai.id} className="hover:bg-gray-50 transition-colors">
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="flex items-center">
                                                            <div className="flex-shrink-0 h-10 w-10">
                                                                {pegawai.foto ? (
                                                                    <img className="h-10 w-10 rounded-full object-cover" src={pegawai.foto} alt="" />
                                                                ) : (
                                                                    <div className="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold">
                                                                        {pegawai.nama_lengkap.charAt(0)}
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="ml-4">
                                                                <div className="text-sm font-medium text-gray-900">{pegawai.nama_lengkap}</div>
                                                                <div className="text-sm text-gray-500">{pegawai.nip ? `NIP: ${pegawai.nip} | ` : ''}NIK: {pegawai.nik}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {pegawai.units && pegawai.units.length > 0 ? (
                                                            pegawai.units.map(unit => (
                                                                <div key={unit.id} className="text-sm text-gray-900 mb-1">
                                                                    <div className="font-medium">{unit.nama}</div>
                                                                    <div className="text-xs text-gray-500">{pegawai.jabatans.find(j => j.pivot.unit_sekolah_id === unit.id)?.nama}</div>
                                                                    {pegawai.mapels && pegawai.mapels.filter(m => m.pivot.unit_sekolah_id === unit.id).length > 0 && (
                                                                        <div className="text-xs text-indigo-600 mt-0.5">
                                                                            Mengajar: {pegawai.mapels.filter(m => m.pivot.unit_sekolah_id === unit.id).map(m => m.nama).join(', ')}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            ))
                                                        ) : (
                                                            <span className="text-sm text-gray-500">-</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 uppercase">
                                                            {pegawai.status_kepegawaian}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${pegawai.status_aktif === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'} uppercase`}>
                                                            {pegawai.status_aktif}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <Link href={route('pegawai.show', pegawai.id)} className="text-indigo-600 hover:text-indigo-900 mr-4 font-semibold">Lihat Detail</Link>
                                                        <Link href={route('pegawai.edit', pegawai.id)} className="text-amber-600 hover:text-amber-900 font-semibold">Edit</Link>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-12 text-center">
                                                    <div className="flex flex-col items-center">
                                                        <svg className="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                                        <p className="text-gray-500 text-lg">Data pegawai tidak ditemukan.</p>
                                                        <p className="text-gray-400 text-sm mt-1">Coba sesuaikan kata kunci pencarian atau tambah data baru.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {pegawais.links && (
                                <div className="mt-8 flex justify-end">
                                    {/* Simplified Pagination for now */}
                                    <div className="flex space-x-1">
                                        {pegawais.links.map((link, index) => (
                                            <Link
                                                key={index}
                                                href={link.url || '#'}
                                                className={`px-4 py-2 border rounded-md text-sm font-medium transition-colors ${link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}

                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Import Excel */}
            {showImportModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onClick={() => setShowImportModal(false)}></div>
                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form onSubmit={handleImportSubmit}>
                                <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <div className="sm:flex sm:items-start">
                                        <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 sm:mx-0 sm:h-10 sm:w-10">
                                            <svg className="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg>
                                        </div>
                                        <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                            <h3 className="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                                Import Data Pegawai
                                            </h3>
                                            <div className="mt-4 space-y-4">
                                                {auth.user.role !== 'admin_unit' && (
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700">Pilih Unit Sekolah <span className="text-red-500">*</span></label>
                                                        <select
                                                            value={importData.unit_sekolah_id}
                                                            onChange={e => setImportData('unit_sekolah_id', e.target.value)}
                                                            className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                                        >
                                                            <option value="">-- Pilih Unit --</option>
                                                            {unitSekolahs && unitSekolahs.map(unit => (
                                                                <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                                            ))}
                                                        </select>
                                                        {importErrors.unit_sekolah_id && <p className="mt-1 text-sm text-red-600">{importErrors.unit_sekolah_id}</p>}
                                                    </div>
                                                )}
                                                
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700">File Excel (.xlsx) <span className="text-red-500">*</span></label>
                                                    <input 
                                                        type="file" 
                                                        accept=".xlsx,.xls,.csv"
                                                        onChange={e => setImportData('file', e.target.files[0])}
                                                        className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                                    />
                                                    {importErrors.file && <p className="mt-1 text-sm text-red-600">{importErrors.file}</p>}
                                                    {importErrors[0] && <p className="mt-1 text-sm text-red-600">Error pada baris data: Silakan periksa file Anda. {importErrors[0]}</p>}
                                                </div>
                                                <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mt-4">
                                                    <p className="text-sm text-blue-700">
                                                        Pastikan file Anda mengikuti format <strong>Template Excel</strong>. Sistem akan menolak seluruh data jika ada satu baris saja yang salah format.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <button 
                                        type="submit" 
                                        disabled={importProcessing}
                                        className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                                    >
                                        {importProcessing ? 'Mengimport...' : 'Upload & Proses'}
                                    </button>
                                    <button 
                                        type="button" 
                                        onClick={() => setShowImportModal(false)}
                                        className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

        </AuthenticatedLayout>
    );
}
