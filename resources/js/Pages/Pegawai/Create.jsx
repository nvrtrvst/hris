import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ auth, unitSekolahs, jabatans }) {
    const { data, setData, post, processing, errors } = useForm({
        nik: '',
        nip: '',
        nama_lengkap: '',
        tempat_lahir: '',
        tanggal_lahir: '',
        jenis_kelamin: 'L',
        agama: '',
        status_pernikahan: '',
        alamat_ktp: '',
        no_hp: '',
        status_kepegawaian: 'tetap',
        jatah_cuti_tahunan: 12,
        tanggal_mulai_kerja: '',
        pendidikan_terakhir: '',
        unit_sekolah_id: '',
        jabatan_id: '',
        email: '',
        password: '',
        foto: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('pegawai.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Tambah Pegawai Baru</h2>}
        >
            <Head title="Tambah Pegawai" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
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
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4">Foto Pegawai</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Upload Foto (Opsional)</label>
                                            <input type="file" onChange={e => setData('foto', e.target.files[0])} className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" accept="image/jpeg, image/png, image/jpg" />
                                            {errors.foto && <p className="mt-1 text-sm text-red-600">{errors.foto}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Informasi Dasar</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">NIK (Nomor Induk Kependudukan) <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.nik} onChange={e => setData('nik', e.target.value)} className={`mt-1 block w-full rounded-md shadow-sm sm:text-sm ${errors.nik ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`} placeholder="16 Digit NIK" maxLength={16} />
                                            {errors.nik && <p className="mt-1 text-sm text-red-600">{errors.nik}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">NIP / No Induk Guru</label>
                                            <input type="text" value={data.nip} onChange={e => setData('nip', e.target.value)} className={`mt-1 block w-full rounded-md shadow-sm sm:text-sm ${errors.nip ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`} placeholder="NIP / No Induk (Opsional)" />
                                            {errors.nip && <p className="mt-1 text-sm text-red-600">{errors.nip}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Nama Lengkap <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.nama_lengkap} onChange={e => setData('nama_lengkap', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.nama_lengkap && <p className="mt-1 text-sm text-red-600">{errors.nama_lengkap}</p>}
                                        </div>
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
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jenis Kelamin <span className="text-red-500">*</span></label>
                                            <select value={data.jenis_kelamin} onChange={e => setData('jenis_kelamin', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Kontak & Alamat</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">No. HP (WhatsApp) <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.no_hp} onChange={e => setData('no_hp', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.no_hp && <p className="mt-1 text-sm text-red-600">{errors.no_hp}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Pernikahan <span className="text-red-500">*</span></label>
                                            <select value={data.status_pernikahan} onChange={e => setData('status_pernikahan', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Status</option>
                                                <option value="Belum Kawin">Belum Kawin</option>
                                                <option value="Kawin">Kawin</option>
                                                <option value="Cerai Hidup">Cerai Hidup</option>
                                                <option value="Cerai Mati">Cerai Mati</option>
                                            </select>
                                            {errors.status_pernikahan && <p className="mt-1 text-sm text-red-600">{errors.status_pernikahan}</p>}
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-gray-700">Alamat KTP <span className="text-red-500">*</span></label>
                                            <textarea value={data.alamat_ktp} onChange={e => setData('alamat_ktp', e.target.value)} rows="3" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                            {errors.alamat_ktp && <p className="mt-1 text-sm text-red-600">{errors.alamat_ktp}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Data Kepegawaian & Unit Pertama</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Pendidikan Terakhir <span className="text-red-500">*</span></label>
                                            <select value={data.pendidikan_terakhir} onChange={e => setData('pendidikan_terakhir', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Pendidikan</option>
                                                <option value="SMA/SMK">SMA/SMK</option>
                                                <option value="D3">D3</option>
                                                <option value="D4/S1">D4/S1</option>
                                                <option value="S2">S2</option>
                                                <option value="S3">S3</option>
                                            </select>
                                            {errors.pendidikan_terakhir && <p className="mt-1 text-sm text-red-600">{errors.pendidikan_terakhir}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Kepegawaian <span className="text-red-500">*</span></label>
                                            <select value={data.status_kepegawaian} onChange={e => setData('status_kepegawaian', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="tetap">Tetap</option>
                                                <option value="kontrak">Kontrak</option>
                                                <option value="honorer">Honorer</option>
                                                <option value="gtt">GTT (Guru Tidak Tetap)</option>
                                            </select>
                                            {errors.status_kepegawaian && <p className="mt-1 text-sm text-red-600">{errors.status_kepegawaian}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jatah Cuti Tahunan (Hari) <span className="text-red-500">*</span></label>
                                            <input type="number" min="0" value={data.jatah_cuti_tahunan} onChange={e => setData('jatah_cuti_tahunan', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.jatah_cuti_tahunan && <p className="mt-1 text-sm text-red-600">{errors.jatah_cuti_tahunan}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tanggal Mulai Kerja <span className="text-red-500">*</span></label>
                                            <input type="date" value={data.tanggal_mulai_kerja} onChange={e => setData('tanggal_mulai_kerja', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.tanggal_mulai_kerja && <p className="mt-1 text-sm text-red-600">{errors.tanggal_mulai_kerja}</p>}
                                        </div>
                                        <div></div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Unit Sekolah Utama <span className="text-red-500">*</span></label>
                                            <select value={data.unit_sekolah_id} onChange={e => setData('unit_sekolah_id', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Unit Sekolah</option>
                                                {unitSekolahs.map(unit => (
                                                    <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                                ))}
                                            </select>
                                            {errors.unit_sekolah_id && <p className="mt-1 text-sm text-red-600">{errors.unit_sekolah_id}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jabatan Utama <span className="text-red-500">*</span></label>
                                            <select value={data.jabatan_id} onChange={e => setData('jabatan_id', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Jabatan</option>
                                                {jabatans.map(jab => (
                                                    <option key={jab.id} value={jab.id}>{jab.nama}</option>
                                                ))}
                                            </select>
                                            {errors.jabatan_id && <p className="mt-1 text-sm text-red-600">{errors.jabatan_id}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Akun Login</h3>
                                    <p className="text-sm text-gray-500 mb-4">Jika password dikosongkan, sistem akan otomatis menggunakan NIK sebagai password default.</p>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Email Pegawai <span className="text-red-500">*</span></label>
                                            <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Password (Opsional)</label>
                                            <input type="password" value={data.password} onChange={e => setData('password', e.target.value)} placeholder="Min 8 karakter, kosongkan untuk NIK" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
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
