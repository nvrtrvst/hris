import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Show({ auth, pegawai }) {
    const { delete: destroy } = useForm();

    const handleDelete = () => {
        if (confirm('Apakah Anda yakin ingin menonaktifkan pegawai ini?')) {
            // Need to pass alasan_nonaktif, so we can use router.delete or pass data. For simplicity here:
            destroy(route('pegawai.destroy', pegawai.id), { data: { alasan_nonaktif: 'Dinonaktifkan oleh sistem' } });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Detail Pegawai</h2>}
        >
            <Head title={`Detail Pegawai - ${pegawai.nama_lengkap}`} />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                            <div className="flex justify-between items-start mb-8 border-b pb-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0 h-20 w-20">
{pegawai.foto_url ? (
                                             <img className="h-20 w-20 rounded-full object-cover border-4 border-indigo-50" src={pegawai.foto_url} alt="" />
                                        ) : (
                                            <div className="h-20 w-20 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-3xl">
                                                {pegawai.nama_lengkap.charAt(0)}
                                            </div>
                                        )}
                                    </div>
                                    <div className="ml-6">
                                        <h3 className="text-3xl font-bold text-gray-900">{pegawai.nama_lengkap}</h3>
                                        <p className="text-lg text-gray-500 mt-1">NIK: {pegawai.nik} {pegawai.nip && <span className="ml-2 border-l pl-2 border-gray-300">No Induk (NIP): {pegawai.nip}</span>}</p>
                                        <div className="mt-2 flex space-x-2">
                                            <span className="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 uppercase">
                                                {pegawai.status_kepegawaian}
                                            </span>
                                            <span className={`px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${pegawai.status_aktif === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'} uppercase`}>
                                                {pegawai.status_aktif}
                                            </span>
                                            {pegawai.wajib_kantor && (
                                                <span className="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">
                                                    Wajib Kantor
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex space-x-4">
                                    <Link
                                        href={route('pegawai.keuangan', pegawai.id)}
                                        className="bg-indigo-50 text-indigo-600 px-4 py-2 rounded-lg border border-indigo-200 hover:bg-indigo-100 transition-colors font-medium flex items-center"
                                    >
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Keuangan & Gaji
                                    </Link>
                                    <Link
                                        href={route('pegawai.edit', pegawai.id)}
                                        className="bg-amber-50 text-amber-600 px-4 py-2 rounded-lg border border-amber-200 hover:bg-amber-100 transition-colors font-medium"
                                    >
                                        Edit Data
                                    </Link>
                                    <button
                                        onClick={handleDelete}
                                        className="bg-red-50 text-red-600 px-4 py-2 rounded-lg border border-red-200 hover:bg-red-100 transition-colors font-medium"
                                    >
                                        Nonaktifkan
                                    </button>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-12">
                                {/* Data Pribadi */}
                                <div>
                                    <h4 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        Data Pribadi
                                    </h4>
                                    <div className="space-y-4">
                                        <div>
                                            <p className="text-sm text-gray-500">Tempat, Tanggal Lahir</p>
                                            <p className="font-medium text-gray-900">{pegawai.tempat_lahir}, {new Date(pegawai.tanggal_lahir).toLocaleDateString('id-ID')}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500">Jenis Kelamin</p>
                                            <p className="font-medium text-gray-900">{pegawai.jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan'}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500">Agama & Status Pernikahan</p>
                                            <p className="font-medium text-gray-900">{pegawai.agama} - {pegawai.status_pernikahan}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500">Kontak</p>
                                            <p className="font-medium text-gray-900">{pegawai.no_hp} / {pegawai.email || '-'}</p>
                                        </div>
                                        <div className="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                                            <p className="text-sm font-semibold text-emerald-800">Akun Login Mobile</p>
                                            {pegawai.user ? (
                                                <div className="mt-1 space-y-1 text-sm text-emerald-950">
                                                    <p><span className="text-emerald-700">Email:</span> {pegawai.user.email}</p>
                                                    <p><span className="text-emerald-700">Username:</span> {pegawai.user.username || '-'}</p>
                                                    <p className="text-xs text-emerald-700">Login: email atau username di portal mobile.</p>
                                                </div>
                                            ) : (
                                                <p className="mt-1 text-sm text-amber-700">Belum terhubung dengan akun login.</p>
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500">Alamat</p>
                                            <p className="font-medium text-gray-900">{pegawai.alamat_ktp}</p>
                                        </div>
                                    </div>
                                </div>

                                {/* Data Kepegawaian */}
                                <div>
                                    <h4 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        Data Kepegawaian
                                    </h4>
                                    <div className="space-y-4">
                                        <div>
                                            <p className="text-sm text-gray-500">Unit & Jabatan</p>
                                            {pegawai.units && pegawai.units.length > 0 ? (
                                                pegawai.units.map(unit => (
                                                    <p key={unit.id} className="font-medium text-gray-900">
                                                        {unit.nama} <span className="font-normal text-gray-600">- {pegawai.jabatans.find(j => j.pivot.unit_sekolah_id === unit.id)?.nama}</span>
                                                    </p>
                                                ))
                                            ) : (
                                                <p className="text-gray-500">-</p>
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500">Mata Pelajaran (Guru)</p>
                                            {pegawai.mapels && pegawai.mapels.length > 0 ? (
                                                <div className="flex flex-wrap gap-1.5 mt-1">
                                                    {pegawai.mapels.map(mapel => (
                                                        <span key={mapel.id} className="px-2.5 py-1 inline-flex text-xs font-medium rounded-md bg-amber-100 text-amber-800">
                                                            {mapel.nama}
                                                        </span>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-gray-500">Bukan guru / belum ada mapel</p>
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500">Pendidikan Terakhir</p>
                                            <p className="font-medium text-gray-900">{pegawai.pendidikan_terakhir}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500">Mulai Bekerja</p>
                                            <p className="font-medium text-gray-900">{new Date(pegawai.tanggal_mulai_kerja).toLocaleDateString('id-ID')}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500">Jatah Cuti Tahunan</p>
                                            <p className="font-medium text-gray-900">{pegawai.jatah_cuti_tahunan ?? 12} Hari</p>
                                        </div>
                                        <div className="bg-indigo-50 p-3 rounded-md border border-indigo-100">
                                            <p className="text-sm text-indigo-700 font-medium">Sisa Cuti</p>
                                            <p className="font-bold text-indigo-900 text-lg">{pegawai.sisa_cuti} Hari</p>
                                        </div>
                                        {pegawai.tanggal_akhir_kontrak && (
                                            <div>
                                                <p className="text-sm text-gray-500">Akhir Kontrak</p>
                                                <p className="font-medium text-gray-900">{new Date(pegawai.tanggal_akhir_kontrak).toLocaleDateString('id-ID')}</p>
                                            </div>
                                        )}
                                        {pegawai.alasan_nonaktif && (
                                            <div className="bg-red-50 p-3 rounded-md border border-red-100">
                                                <p className="text-sm text-red-600 font-medium">Alasan Nonaktif</p>
                                                <p className="font-medium text-red-900">{pegawai.alasan_nonaktif}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                            
                            {/* Riwayat Perubahan */}
                            <div className="mt-12 border-t pt-8">
                                <h4 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <svg className="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Riwayat Perubahan Data
                                </h4>
                                {pegawai.riwayat && pegawai.riwayat.length > 0 ? (
                                    <div className="space-y-4">
                                        {pegawai.riwayat.map((riw) => (
                                            <div key={riw.id} className="bg-gray-50 p-4 rounded-lg border border-gray-100">
                                                <div className="flex justify-between">
                                                    <p className="font-medium text-gray-900 capitalize">{riw.jenis_perubahan.replace(/_/g, ' ')}</p>
                                                    <p className="text-sm text-gray-500">{new Date(riw.created_at).toLocaleString('id-ID')}</p>
                                                </div>
                                                <p className="text-sm text-gray-600 mt-1">
                                                    Dari: <span className="font-semibold">{riw.nilai_lama || '-'}</span> → Menjadi: <span className="font-semibold">{riw.nilai_baru || '-'}</span>
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-gray-500 text-sm">Belum ada riwayat perubahan.</p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
