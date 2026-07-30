import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Index({ auth, pegawais, filters, unitSekolahs, mataPelajarans, jabatans }) {
    const [search, setSearch] = useState(filters.search || '');
    const [unitSekolahId, setUnitSekolahId] = useState(filters.unit_sekolah_id || '');
    const [mataPelajaranId, setMataPelajaranId] = useState(filters.mata_pelajaran_id || '');
    const [jabatanId, setJabatanId] = useState(filters.jabatan_id || '');
    const [showImportModal, setShowImportModal] = useState(false);

    const { data: importData, setData: setImportData, post: postImport, processing: importProcessing, errors: importErrors, reset: resetImport } = useForm({
        file: null,
        unit_sekolah_id: auth.roles?.includes('admin_unit') ? auth.user.unit_sekolah_id : ''
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
        router.get(route('pegawai.index'), { search, unit_sekolah_id: unitSekolahId, mata_pelajaran_id: mataPelajaranId, jabatan_id: jabatanId }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-text-primary leading-tight">Manajemen Pegawai</h2>}
        >
            <Head title="Manajemen Pegawai" />

            <div className="py-12 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                    <div className="page-header">
                        <div>
                            <h1 className="page-title">Daftar Pegawai</h1>
                            <p className="page-subtitle">Kelola data seluruh pegawai di lingkungan yayasan.</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <a
                                href={route('pegawai.template')}
                                className="btn-secondary btn-sm"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Template
                            </a>
                            <button
                                onClick={() => setShowImportModal(true)}
                                className="btn-secondary btn-sm"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                Import
                            </button>
                            <Link
                                href={route('pegawai.create')}
                                className="btn-primary"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Tambah Pegawai
                            </Link>
                        </div>
                    </div>

                    <form onSubmit={handleSearch} className="filter-bar p-4 bg-surface rounded-card border border-border">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 w-full">
                            <div className="md:col-span-1">
                                <label className="form-label">Cari Nama / NIK</label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg className="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                    </div>
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Masukkan kata kunci..."
                                        className="input-field pl-10"
                                    />
                                </div>
                            </div>
                            {auth.roles?.includes('admin_unit') === false && (
                                <div className="md:col-span-1">
                                    <label className="form-label">Unit Sekolah</label>
                                    <select
                                        value={unitSekolahId}
                                        onChange={(e) => setUnitSekolahId(e.target.value)}
                                        className="select-field"
                                    >
                                        <option value="">Semua Unit</option>
                                        {unitSekolahs && unitSekolahs.map(unit => (
                                            <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div className="md:col-span-1">
                                <label className="form-label">Mata Pelajaran</label>
                                <select
                                    value={mataPelajaranId}
                                    onChange={(e) => setMataPelajaranId(e.target.value)}
                                    className="select-field"
                                >
                                    <option value="">Semua Mata Pelajaran</option>
                                    {mataPelajarans && mataPelajarans.map(mapel => (
                                        <option key={mapel.id} value={mapel.id}>{mapel.nama}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="md:col-span-1">
                                <label className="form-label">Jabatan</label>
                                <select
                                    value={jabatanId}
                                    onChange={(e) => setJabatanId(e.target.value)}
                                    className="select-field"
                                >
                                    <option value="">Semua Jabatan</option>
                                    {jabatans && jabatans.map(j => (
                                        <option key={j.id} value={j.id}>{j.nama}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="md:col-span-1 flex items-end">
                                <button type="submit" className="btn-secondary w-full">
                                    Terapkan Filter
                                </button>
                            </div>
                        </div>
                    </form>

                    <div className="card-table">
                        <div className="table-wrap">
                            <table className="table-base">
                                <thead className="bg-surface">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Nama / NIP / NIK</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Unit & Jabatan</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Status Pegawai</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Status Aktif</th>
                                        <th className="px-6 py-4 text-right text-xs font-semibold text-text-secondary uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-border">
                                    {pegawais.data.length > 0 ? (
                                        pegawais.data.map((pegawai) => (
                                            <tr key={pegawai.id} className="hover:bg-surface transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0 h-10 w-10">
{pegawai.foto_url ? (
                                                                     <img className="h-10 w-10 rounded-full object-cover" src={pegawai.foto_url} alt="" />
                                                                ) : (
                                                                    <div className="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold">
                                                                        {pegawai.nama_lengkap.charAt(0)}
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="ml-4">
                                                                <div className="text-sm font-medium text-text-primary">{pegawai.nama_lengkap}</div>
                                                                <div className="text-sm text-text-muted">{pegawai.nip ? `NIP: ${pegawai.nip} | ` : ''}NIK: {pegawai.nik}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {pegawai.units && pegawai.units.length > 0 ? (
                                                            pegawai.units.map(unit => (
                                                                <div key={unit.id} className="text-sm text-text-primary mb-1">
                                                                    <div className="font-medium">{unit.nama}</div>
                                                                    <div className="text-xs text-text-muted">{pegawai.jabatans.find(j => j.pivot.unit_sekolah_id === unit.id)?.nama}</div>
                                                                    {pegawai.mapels && pegawai.mapels.filter(m => m.pivot.unit_sekolah_id === unit.id).length > 0 && (
                                                                        <div className="text-xs text-primary mt-0.5">
                                                                            Mengajar: {pegawai.mapels.filter(m => m.pivot.unit_sekolah_id === unit.id).map(m => m.nama).join(', ')}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            ))
                                                        ) : (
                                                            <span className="text-sm text-text-muted">-</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className="badge-info">
                                                            {pegawai.status_kepegawaian}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`${pegawai.status_aktif === 'aktif' ? 'badge-success' : 'badge-danger'} uppercase`}>
                                                            {pegawai.status_aktif}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <Link href={route('pegawai.show', pegawai.id)} className="link mr-4">Lihat Detail</Link>
                                                        <Link href={route('pegawai.edit', pegawai.id)} className="link">Edit</Link>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-12 text-center">
                                                    <div className="empty-state">
                                                        <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                                        <p className="empty-state-title">Data pegawai tidak ditemukan.</p>
                                                        <p className="empty-state-desc">Coba sesuaikan kata kunci pencarian atau tambah data baru.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {pegawais.links && (
                            <div className="mt-6">
                                <Pagination links={pegawais.links} />
                            </div>
                        )}

                </div>
            </div>

            {/* Modal Import Excel */}
            <Modal show={showImportModal} onClose={() => setShowImportModal(false)} maxWidth="lg">
                <form onSubmit={handleImportSubmit}>
                    <div className="modal-content p-0">
                        <div className="p-6">
                            <div className="flex items-start gap-4">
                                <div className="stat-icon bg-warning-light text-warning">
                                    <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                </div>
                                <div className="flex-1">
                                    <h3 className="text-lg font-semibold text-text-primary">
                                        Import Data Pegawai
                                    </h3>
                                    <div className="mt-4 form-section">
                                        {auth.roles?.includes('admin_unit') === false && (
                                            <div>
                                                <label className="form-label">Pilih Unit Sekolah <span className="text-danger">*</span></label>
                                                <select
                                                    value={importData.unit_sekolah_id}
                                                    onChange={e => setImportData('unit_sekolah_id', e.target.value)}
                                                    className="select-field"
                                                >
                                                    <option value="">-- Pilih Unit --</option>
                                                    {unitSekolahs && unitSekolahs.map(unit => (
                                                        <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                                    ))}
                                                </select>
                                                {importErrors.unit_sekolah_id && <p className="form-error">{importErrors.unit_sekolah_id}</p>}
                                            </div>
                                        )}

                                        <div>
                                            <label className="form-label">File Excel (.xlsx) <span className="text-danger">*</span></label>
                                            <input
                                                type="file"
                                                accept=".xlsx,.xls,.csv"
                                                onChange={e => setImportData('file', e.target.files[0])}
                                                className="mt-1 block w-full text-sm text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-button file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary hover:file:bg-primary-100"
                                            />
                                            {importErrors.file && <p className="form-error">{importErrors.file}</p>}
                                            {importErrors[0] && <p className="form-error">Error pada baris data: Silakan periksa file Anda. {importErrors[0]}</p>}
                                        </div>
                                        <div className="bg-info-light border-l-4 border-info p-4 rounded-card">
                                            <p className="text-sm text-info">
                                                Pastikan file Anda mengikuti format <strong>Template Excel</strong>. Sistem akan menolak seluruh data jika ada satu baris saja yang salah format.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="px-6 py-4 bg-surface border-t border-border flex flex-row-reverse gap-3">
                            <button
                                type="submit"
                                disabled={importProcessing}
                                className="btn-primary"
                            >
                                {importProcessing ? 'Mengimport...' : 'Upload & Proses'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setShowImportModal(false)}
                                className="btn-secondary"
                            >
                                Batal
                            </button>
                        </div>
                    </div>
                </form>
            </Modal>

        </AuthenticatedLayout>
    );
}
