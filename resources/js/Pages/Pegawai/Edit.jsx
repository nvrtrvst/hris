import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ auth, pegawai, unitSekolahs, jabatans }) {
    const { data, setData, post, processing, errors } = useForm({
        _method: 'put',
        nik: pegawai.nik,
        nip: pegawai.nip || '',
        nama_lengkap: pegawai.nama_lengkap,
        tempat_lahir: pegawai.tempat_lahir,
        tanggal_lahir: pegawai.tanggal_lahir,
        jenis_kelamin: pegawai.jenis_kelamin,
        agama: pegawai.agama,
        status_pernikahan: pegawai.status_pernikahan,
        alamat_ktp: pegawai.alamat_ktp,
        no_hp: pegawai.no_hp,
        status_kepegawaian: pegawai.status_kepegawaian,
        status_aktif: pegawai.status_aktif,
        tanggal_mulai_kerja: pegawai.tanggal_mulai_kerja,
        pendidikan_terakhir: pegawai.pendidikan_terakhir,
        foto: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('pegawai.update', pegawai.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Edit Pegawai: {pegawai.nama_lengkap}</h2>}
        >
            <Head title={`Edit Pegawai - ${pegawai.nama_lengkap}`} />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4">Foto Pegawai</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                                        {pegawai.foto && (
                                            <div>
                                                <p className="text-sm font-medium text-gray-700 mb-2">Foto Saat Ini:</p>
                                                <img src={pegawai.foto} alt="Foto Pegawai" className="w-32 h-32 object-cover rounded-md shadow-sm border" />
                                            </div>
                                        )}
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Upload Foto Baru (Opsional)</label>
                                            <input type="file" onChange={e => setData('foto', e.target.files[0])} className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" accept="image/jpeg, image/png, image/jpg" />
                                            <p className="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah foto.</p>
                                            {errors.foto && <p className="mt-1 text-sm text-red-600">{errors.foto}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Informasi Dasar</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">NIK <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.nik} onChange={e => setData('nik', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.nik && <p className="mt-1 text-sm text-red-600">{errors.nik}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">NIP</label>
                                            <input type="text" value={data.nip} onChange={e => setData('nip', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="NIP (Opsional)" />
                                            {errors.nip && <p className="mt-1 text-sm text-red-600">{errors.nip}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Nama Lengkap <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.nama_lengkap} onChange={e => setData('nama_lengkap', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.nama_lengkap && <p className="mt-1 text-sm text-red-600">{errors.nama_lengkap}</p>}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tempat Lahir <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.tempat_lahir} onChange={e => setData('tempat_lahir', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.tempat_lahir && <p className="mt-1 text-sm text-red-600">{errors.tempat_lahir}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tanggal Lahir <span className="text-red-500">*</span></label>
                                            <input type="date" value={data.tanggal_lahir} onChange={e => setData('tanggal_lahir', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.tanggal_lahir && <p className="mt-1 text-sm text-red-600">{errors.tanggal_lahir}</p>}
                                        </div>
                                    </div>
                                    
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jenis Kelamin <span className="text-red-500">*</span></label>
                                            <select value={data.jenis_kelamin} onChange={e => setData('jenis_kelamin', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Jenis Kelamin</option>
                                                <option value="L">Laki-laki</option>
                                                <option value="P">Perempuan</option>
                                            </select>
                                            {errors.jenis_kelamin && <p className="mt-1 text-sm text-red-600">{errors.jenis_kelamin}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Agama <span className="text-red-500">*</span></label>
                                            <select value={data.agama} onChange={e => setData('agama', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Agama</option>
                                                <option value="Islam">Islam</option>
                                                <option value="Kristen">Kristen</option>
                                                <option value="Katolik">Katolik</option>
                                                <option value="Hindu">Hindu</option>
                                                <option value="Buddha">Buddha</option>
                                                <option value="Konghucu">Konghucu</option>
                                            </select>
                                            {errors.agama && <p className="mt-1 text-sm text-red-600">{errors.agama}</p>}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Pernikahan <span className="text-red-500">*</span></label>
                                            <select value={data.status_pernikahan} onChange={e => setData('status_pernikahan', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Status</option>
                                                <option value="Belum Menikah">Belum Menikah</option>
                                                <option value="Menikah">Menikah</option>
                                                <option value="Cerai Hidup">Cerai Hidup</option>
                                                <option value="Cerai Mati">Cerai Mati</option>
                                            </select>
                                            {errors.status_pernikahan && <p className="mt-1 text-sm text-red-600">{errors.status_pernikahan}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">No. HP <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.no_hp} onChange={e => setData('no_hp', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.no_hp && <p className="mt-1 text-sm text-red-600">{errors.no_hp}</p>}
                                        </div>
                                    </div>

                                    <div className="mt-4">
                                        <label className="block text-sm font-medium text-gray-700">Alamat KTP <span className="text-red-500">*</span></label>
                                        <textarea value={data.alamat_ktp} onChange={e => setData('alamat_ktp', e.target.value)} rows="3" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                        {errors.alamat_ktp && <p className="mt-1 text-sm text-red-600">{errors.alamat_ktp}</p>}
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Status Kepegawaian</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Kepegawaian</label>
                                            <select value={data.status_kepegawaian} onChange={e => setData('status_kepegawaian', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="tetap">Tetap</option>
                                                <option value="kontrak">Kontrak</option>
                                                <option value="honorer">Honorer</option>
                                                <option value="gtt">GTT (Guru Tidak Tetap)</option>
                                            </select>
                                            {errors.status_kepegawaian && <p className="mt-1 text-sm text-red-600">{errors.status_kepegawaian}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Aktif</label>
                                            <select value={data.status_aktif} onChange={e => setData('status_aktif', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="aktif">Aktif</option>
                                                <option value="cuti">Cuti</option>
                                                <option value="nonaktif">Nonaktif</option>
                                                <option value="resign">Resign</option>
                                            </select>
                                            {errors.status_aktif && <p className="mt-1 text-sm text-red-600">{errors.status_aktif}</p>}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tanggal Mulai Kerja <span className="text-red-500">*</span></label>
                                            <input type="date" value={data.tanggal_mulai_kerja} onChange={e => setData('tanggal_mulai_kerja', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.tanggal_mulai_kerja && <p className="mt-1 text-sm text-red-600">{errors.tanggal_mulai_kerja}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Pendidikan Terakhir <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.pendidikan_terakhir} onChange={e => setData('pendidikan_terakhir', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Contoh: S1 Pendidikan Agama Islam" />
                                            {errors.pendidikan_terakhir && <p className="mt-1 text-sm text-red-600">{errors.pendidikan_terakhir}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-end mt-8 border-t pt-6">
                                    <Link href={route('pegawai.show', pegawai.id)} className="text-gray-600 hover:text-gray-900 mr-6 font-medium">Batal</Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 disabled:opacity-50"
                                    >
                                        {processing ? 'Menyimpan...' : 'Perbarui Pegawai'}
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
