import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';

export default function Index({ auth, mapels }) {
    const { data, setData, post, reset, processing, errors, clearErrors } = useForm({
        nama: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('mata-pelajaran.store'), {
            onSuccess: () => {
                reset();
                clearErrors();
            }
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Referensi Mata Pelajaran</h2>}
        >
            <Head title="Mata Pelajaran" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-[1400px] mx-auto sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-8">

                    {/* Form Section */}
                    <div className="w-full lg:w-1/3">
                        <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100 p-6 sticky top-6">
                            <h3 className="text-lg font-bold text-gray-900 mb-2 border-b pb-2">Tambah Mata Pelajaran</h3>
                            <p className="text-xs text-gray-500 mb-4">
                                Master ini dipakai saat menugaskan mapel ke guru (Pegawai) dan saat membuat Jadwal.
                            </p>

                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Nama Mata Pelajaran</label>
                                    <input type="text" value={data.nama} onChange={e => setData('nama', e.target.value)} placeholder="Contoh: Matematika" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    {errors.nama && <p className="mt-1 text-sm text-red-600">{errors.nama}</p>}
                                </div>

                                <div className="pt-4 flex justify-end space-x-3">
                                    <button type="submit" disabled={processing} className="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">
                                        {processing ? 'Menyimpan...' : 'Simpan'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Table Section */}
                    <div className="w-full lg:w-2/3">
                        <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                            <div className="px-6 py-5 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                                <h3 className="text-lg font-bold text-gray-900">Daftar Mata Pelajaran</h3>
                                <span className="text-sm text-gray-500">{mapels.length} item</span>
                            </div>
                            <div className="p-0 overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Mata Pelajaran</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {mapels.length > 0 ? mapels.map((m) => (
                                            <tr key={m.id} className="hover:bg-gray-50 group">
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-bold text-gray-900">{m.nama}</div>
                                                </td>
                                                <td className="px-6 py-4 text-right text-sm font-medium">
                                                    <button
                                                        onClick={() => {
                                                            if (confirm(`Hapus mata pelajaran "${m.nama}"?`)) {
                                                                router.delete(route('mata-pelajaran.destroy', m.id));
                                                            }
                                                        }}
                                                        className="text-red-600 hover:text-red-900"
                                                    >
                                                        Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="2" className="px-6 py-4 text-center text-sm text-gray-500">Belum ada mata pelajaran.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
