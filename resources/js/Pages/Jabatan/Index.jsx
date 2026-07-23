import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import Modal from '@/Components/Modal';

export default function Index({ auth, jabatans }) {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        nama: '',
        is_guru: false,
    });

    const openCreate = () => {
        setEditing(null);
        reset();
        setShowModal(true);
    };

    const openEdit = (j) => {
        setEditing(j);
        setData({ nama: j.nama, is_guru: j.is_guru });
        setShowModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editing) {
            put(route('jabatan.update', editing.id), {
                onSuccess: () => { setShowModal(false); reset(); },
            });
        } else {
            post(route('jabatan.store'), {
                onSuccess: () => { setShowModal(false); reset(); },
            });
        }
    };

    const handleDelete = (j) => {
        if (!confirm(`Hapus jabatan "${j.nama}"?`)) return;
        router.delete(route('jabatan.destroy', j.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Kelola Jabatan</h2>}
        >
            <Head title="Jabatan" />
            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <div className="flex justify-between items-center mb-6">
                                <div>
                                    <h3 className="text-xl font-bold text-gray-900">Daftar Jabatan</h3>
                                    <p className="text-gray-500 text-sm mt-1">Kelola jabatan/posisi pegawai di lingkungan yayasan.</p>
                                </div>
                                <button onClick={openCreate}
                                    className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-5 rounded-lg shadow-md transition-all duration-200"
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Tambah Jabatan
                                </button>
                            </div>
                            <div className="overflow-x-auto rounded-xl border border-gray-200">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama Jabatan</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tipe</th>
                                            <th className="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {jabatans.map((j) => (
                                            <tr key={j.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-semibold text-gray-900">{j.nama}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${j.is_guru ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700'}`}>
                                                        {j.is_guru ? 'Guru' : 'Non-Guru'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                    <button onClick={() => openEdit(j)}
                                                        className="text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors font-semibold"
                                                    >Edit</button>
                                                    <button onClick={() => handleDelete(j)}
                                                        className="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors font-semibold"
                                                    >Hapus</button>
                                                </td>
                                            </tr>
                                        ))}
                                        {jabatans.length === 0 && (
                                            <tr>
                                                <td colSpan="3" className="px-6 py-12 text-center text-gray-500">Belum ada data jabatan.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <Modal show={showModal} onClose={() => { setShowModal(false); reset(); }} maxWidth="sm">
                <form onSubmit={handleSubmit} className="p-6">
                    <h3 className="text-lg font-bold text-gray-900 mb-4">
                        {editing ? 'Edit Jabatan' : 'Tambah Jabatan'}
                    </h3>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Nama Jabatan <span className="text-red-500">*</span></label>
                            <input type="text" value={data.nama}
                                onChange={e => setData('nama', e.target.value)}
                                placeholder="cth. Guru Mata Pelajaran"
                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            {errors.nama && <p className="mt-1 text-sm text-red-600">{errors.nama}</p>}
                        </div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" checked={data.is_guru}
                                onChange={e => setData('is_guru', e.target.checked)}
                                className="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div>
                                <span className="text-sm font-medium text-gray-700">Jabatan Guru</span>
                                <p className="text-xs text-gray-500">Centang jika jabatan ini termasuk tenaga pendidik (guru).</p>
                            </div>
                        </label>
                    </div>
                    <div className="flex items-center justify-end gap-3 mt-6 border-t pt-4">
                        <button type="button" onClick={() => { setShowModal(false); reset(); }}
                            className="text-gray-600 hover:text-gray-900 font-medium text-sm"
                        >Batal</button>
                        <button type="submit" disabled={processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg shadow-sm transition-all disabled:opacity-50 text-sm"
                        >
                            {processing ? 'Menyimpan...' : editing ? 'Simpan' : 'Tambah'}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}