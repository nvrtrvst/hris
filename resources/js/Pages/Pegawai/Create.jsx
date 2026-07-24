import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ auth, unitSekolahs, jabatans }) {
    const { data, setData, post, processing, errors } = useForm({
        nama_lengkap: '',
        email: '',
        password: '',
        no_hp: '',
        unit_sekolah_id: '',
        jabatan_id: '',
        status_kepegawaian: 'tetap',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('pegawai.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Tambah Pegawai</h2>}
        >
            <Head title="Tambah Pegawai" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('pegawai.index')} className="inline-flex items-center text-sm font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Kembali ke Daftar Pegawai
                        </Link>
                    </div>
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <p className="mb-6 rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                                Data minimal untuk membuat akun. Pegawai akan melengkapi data pribadi &amp; finansial saat login pertama.
                            </p>
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4">Informasi Akun</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Nama Lengkap <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.nama_lengkap} onChange={e => setData('nama_lengkap', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.nama_lengkap && <p className="mt-1 text-sm text-red-600">{errors.nama_lengkap}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Email <span className="text-red-500">*</span></label>
                                            <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Password</label>
                                            <input type="password" value={data.password} onChange={e => setData('password', e.target.value)} placeholder="Kosongkan = gunakan email" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">No. HP <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.no_hp} onChange={e => setData('no_hp', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.no_hp && <p className="mt-1 text-sm text-red-600">{errors.no_hp}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4">Penempatan</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Unit Sekolah <span className="text-red-500">*</span></label>
                                            <select value={data.unit_sekolah_id} onChange={e => setData('unit_sekolah_id', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Unit</option>
                                                {unitSekolahs.map(unit => (
                                                    <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                                ))}
                                            </select>
                                            {errors.unit_sekolah_id && <p className="mt-1 text-sm text-red-600">{errors.unit_sekolah_id}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jabatan <span className="text-red-500">*</span></label>
                                            <select value={data.jabatan_id} onChange={e => setData('jabatan_id', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Jabatan</option>
                                                {jabatans.map(jab => (
                                                    <option key={jab.id} value={jab.id}>{jab.nama}</option>
                                                ))}
                                            </select>
                                            {errors.jabatan_id && <p className="mt-1 text-sm text-red-600">{errors.jabatan_id}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Kepegawaian <span className="text-red-500">*</span></label>
                                            <select value={data.status_kepegawaian} onChange={e => setData('status_kepegawaian', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="tetap">Tetap</option>
                                                <option value="kontrak">Kontrak</option>
                                                <option value="honorer">Honorer</option>
                                                <option value="gtt">GTT</option>
                                            </select>
                                            {errors.status_kepegawaian && <p className="mt-1 text-sm text-red-600">{errors.status_kepegawaian}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-end mt-8 border-t pt-6">
                                    <Link href={route('pegawai.index')} className="text-gray-600 hover:text-gray-900 mr-6 font-medium">Batal</Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 disabled:opacity-50"
                                    >
                                        {processing ? 'Menyimpan...' : 'Simpan Pegawai'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
