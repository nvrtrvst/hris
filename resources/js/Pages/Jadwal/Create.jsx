import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ auth, pegawais, units, kelas, mapel }) {
    const { data, setData, post, processing, errors } = useForm({
        pegawai_id: '',
        unit_sekolah_id: '',
        kelas_id: '',
        mata_pelajaran_id: '',
        hari: 'Senin',
        jam_mulai: '',
        jam_selesai: '',
        jenis_jadwal: 'mengajar',
        tahun_ajaran: '2026/2027',
        semester: '1',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('jadwal.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Tambah Jadwal Pegawai</h2>}
        >
            <Head title="Tambah Jadwal" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('jadwal.index')} className="inline-flex items-center text-sm font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Kembali ke Jadwal Mingguan
                        </Link>
                    </div>
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            
                            {errors.conflict && (
                                <div className="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-md">
                                    <div className="flex">
                                        <div className="flex-shrink-0">
                                            <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <p className="text-sm text-red-700 font-medium">
                                                {errors.conflict}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4">Informasi Pegawai & Unit</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-gray-700">Pegawai <span className="text-red-500">*</span></label>
                                            <select value={data.pegawai_id} onChange={e => setData('pegawai_id', e.target.value)} className={`mt-1 block w-full rounded-md shadow-sm sm:text-sm ${errors.pegawai_id ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`}>
                                                <option value="">Pilih Pegawai</option>
                                                {pegawais.map(p => (
                                                    <option key={p.id} value={p.id}>{p.nama_lengkap}</option>
                                                ))}
                                            </select>
                                            {errors.pegawai_id && <p className="mt-1 text-sm text-red-600">{errors.pegawai_id}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Unit Sekolah <span className="text-red-500">*</span></label>
                                            <select value={data.unit_sekolah_id} onChange={e => setData('unit_sekolah_id', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Unit</option>
                                                {units.map(u => (
                                                    <option key={u.id} value={u.id}>{u.nama}</option>
                                                ))}
                                            </select>
                                            {errors.unit_sekolah_id && <p className="mt-1 text-sm text-red-600">{errors.unit_sekolah_id}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jenis Jadwal <span className="text-red-500">*</span></label>
                                            <select value={data.jenis_jadwal} onChange={e => setData('jenis_jadwal', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="mengajar">Mengajar</option>
                                                <option value="piket">Piket</option>
                                                <option value="ekskul">Ekstrakurikuler</option>
                                                <option value="shift_satpam">Shift Satpam</option>
                                                <option value="shift_kebersihan">Shift Kebersihan</option>
                                                <option value="lainnya">Lainnya</option>
                                            </select>
                                            {errors.jenis_jadwal && <p className="mt-1 text-sm text-red-600">{errors.jenis_jadwal}</p>}
                                        </div>
                                    </div>
                                </div>

                                {data.jenis_jadwal === 'mengajar' && (
                                    <div className="bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                                        <h3 className="text-md font-bold text-indigo-900 mb-4">Detail Mengajar</h3>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label className="block text-sm font-medium text-indigo-800">Kelas</label>
                                                <select value={data.kelas_id} onChange={e => setData('kelas_id', e.target.value)} className="mt-1 block w-full border-indigo-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                    <option value="">Pilih Kelas (Opsional)</option>
                                                    {kelas.filter(k => k.unit_sekolah_id == data.unit_sekolah_id || !data.unit_sekolah_id).map(k => (
                                                        <option key={k.id} value={k.id}>{k.tingkat} - {k.nama}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-indigo-800">Mata Pelajaran</label>
                                                <select value={data.mata_pelajaran_id} onChange={e => setData('mata_pelajaran_id', e.target.value)} className="mt-1 block w-full border-indigo-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                    <option value="">Pilih Mapel (Opsional)</option>
                                                    {mapel.map(m => (
                                                        <option key={m.id} value={m.id}>{m.nama}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Waktu Pelaksanaan</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Hari <span className="text-red-500">*</span></label>
                                            <select value={data.hari} onChange={e => setData('hari', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                {['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'].map(h => (
                                                    <option key={h} value={h}>{h}</option>
                                                ))}
                                            </select>
                                            {errors.hari && <p className="mt-1 text-sm text-red-600">{errors.hari}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jam Mulai <span className="text-red-500">*</span></label>
                                            <input type="time" value={data.jam_mulai} onChange={e => setData('jam_mulai', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.jam_mulai && <p className="mt-1 text-sm text-red-600">{errors.jam_mulai}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jam Selesai <span className="text-red-500">*</span></label>
                                            <input type="time" value={data.jam_selesai} onChange={e => setData('jam_selesai', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.jam_selesai && <p className="mt-1 text-sm text-red-600">{errors.jam_selesai}</p>}
                                        </div>
                                        
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tahun Ajaran <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.tahun_ajaran} onChange={e => setData('tahun_ajaran', e.target.value)} placeholder="Contoh: 2026/2027" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.tahun_ajaran && <p className="mt-1 text-sm text-red-600">{errors.tahun_ajaran}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Semester <span className="text-red-500">*</span></label>
                                            <select value={data.semester} onChange={e => setData('semester', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="1">1 (Ganjil)</option>
                                                <option value="2">2 (Genap)</option>
                                            </select>
                                            {errors.semester && <p className="mt-1 text-sm text-red-600">{errors.semester}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-end mt-8 border-t pt-6">
                                    <Link href={route('jadwal.index')} className="text-gray-600 hover:text-gray-900 mr-6 font-medium">Batal</Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 disabled:opacity-50"
                                    >
                                        {processing ? 'Menyimpan...' : 'Simpan Jadwal'}
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
