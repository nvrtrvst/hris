import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Show({ auth, pegawai }) {
    const { delete: destroy } = useForm();

    const handleDelete = () => {
        if (confirm('Apakah Anda yakin ingin menonaktifkan pegawai ini?')) {
            destroy(route('pegawai.destroy', pegawai.id), { data: { alasan_nonaktif: 'Dinonaktifkan oleh sistem' } });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-text-primary leading-tight">Detail Pegawai</h2>}
        >
            <Head title={`Detail Pegawai - ${pegawai.nama_lengkap}`} />

            <div className="py-12 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <Link href={route('pegawai.index')} className="inline-flex items-center text-sm font-medium text-text-muted hover:text-primary transition-colors mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Kembali ke Daftar Pegawai
                    </Link>

                    <div className="page-card p-0">
                        {/* Header Profile */}
                        <div className="p-6 border-b border-border">
                            <div className="flex flex-col sm:flex-row justify-between items-start gap-4">
                                <div className="flex items-center gap-5">
                                    <div className="flex-shrink-0 h-20 w-20">
{pegawai.foto_url ? (
                                             <img className="h-20 w-20 rounded-full object-cover border-4 border-primary-50" src={pegawai.foto_url} alt="" />
                                        ) : (
                                            <div className="h-20 w-20 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold text-3xl">
                                                {pegawai.nama_lengkap.charAt(0)}
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <h3 className="text-2xl font-bold text-text-primary">{pegawai.nama_lengkap}</h3>
                                        <p className="text-sm text-text-muted mt-1">NIK: {pegawai.nik} {pegawai.nip && <span className="ml-2 border-l pl-2 border-border">No Induk (NIP): {pegawai.nip}</span>}</p>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <span className="badge-info uppercase">
                                                {pegawai.status_kepegawaian}
                                            </span>
                                            <span className={`${pegawai.status_aktif === 'aktif' ? 'badge-success' : 'badge-danger'} uppercase`}>
                                                {pegawai.status_aktif}
                                            </span>
                                            {pegawai.wajib_kantor && (
                                                <span className="badge-warning">
                                                    Wajib Kantor
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Link
                                        href={route('pegawai.keuangan', pegawai.id)}
                                        className="btn-secondary btn-sm"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Keuangan & Gaji
                                    </Link>
                                    <Link
                                        href={route('pegawai.edit', pegawai.id)}
                                        className="btn-secondary btn-sm"
                                    >
                                        Edit Data
                                    </Link>
                                    <button
                                        onClick={handleDelete}
                                        className="btn-danger btn-sm"
                                    >
                                        Nonaktifkan
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                            {/* Data Pribadi */}
                            <div>
                                <h4 className="section-title flex items-center gap-2 mb-4">
                                    <svg className="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    Data Pribadi
                                </h4>
                                <div className="space-y-4">
                                    <div>
                                        <p className="text-sm text-text-muted">Tempat, Tanggal Lahir</p>
                                        <p className="font-medium text-text-primary">{pegawai.tempat_lahir}, {new Date(pegawai.tanggal_lahir).toLocaleDateString('id-ID')}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-text-muted">Jenis Kelamin</p>
                                        <p className="font-medium text-text-primary">{pegawai.jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-text-muted">Agama & Status Pernikahan</p>
                                        <p className="font-medium text-text-primary">{pegawai.agama} - {pegawai.status_pernikahan}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-text-muted">Kontak</p>
                                        <p className="font-medium text-text-primary">{pegawai.no_hp} / {pegawai.user?.email || '-'}</p>
                                    </div>
                                    <div className="bg-info-light border border-info rounded-card p-4">
                                        <p className="text-sm font-semibold text-info">Akun Login Mobile</p>
                                        {pegawai.user ? (
                                            <div className="mt-1 space-y-1 text-sm text-text-primary">
                                                <p><span className="text-info">Email:</span> {pegawai.user.email}</p>
                                                <p><span className="text-info">Username:</span> {pegawai.user.username || '-'}</p>
                                                <p className="text-xs text-text-muted">Login: email atau username di portal mobile.</p>
                                            </div>
                                        ) : (
                                            <p className="mt-1 text-sm text-warning">Belum terhubung dengan akun login.</p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-sm text-text-muted">Alamat</p>
                                        <p className="font-medium text-text-primary">{pegawai.alamat_ktp}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Data Kepegawaian */}
                            <div>
                                <h4 className="section-title flex items-center gap-2 mb-4">
                                    <svg className="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    Data Kepegawaian
                                </h4>
                                <div className="space-y-4">
                                    <div>
                                        <p className="text-sm text-text-muted">Unit & Jabatan</p>
                                        {pegawai.units && pegawai.units.length > 0 ? (
                                            pegawai.units.map(unit => (
                                                <p key={unit.id} className="font-medium text-text-primary">
                                                    {unit.nama} <span className="font-normal text-text-muted">- {pegawai.jabatans.find(j => j.pivot.unit_sekolah_id === unit.id)?.nama}</span>
                                                </p>
                                            ))
                                        ) : (
                                            <p className="text-text-muted">-</p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-sm text-text-muted">Mata Pelajaran (Guru)</p>
                                        {pegawai.mapels && pegawai.mapels.length > 0 ? (
                                            <div className="flex flex-wrap gap-1.5 mt-1">
                                                {pegawai.mapels.map(mapel => (
                                                    <span key={mapel.id} className="badge-warning">
                                                        {mapel.nama}
                                                    </span>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-text-muted">Bukan guru / belum ada mapel</p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-sm text-text-muted">Pendidikan Terakhir</p>
                                        <p className="font-medium text-text-primary">{pegawai.pendidikan_terakhir}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-text-muted">Mulai Bekerja</p>
                                        <p className="font-medium text-text-primary">{new Date(pegawai.tanggal_mulai_kerja).toLocaleDateString('id-ID')}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-text-muted">Jatah Cuti Tahunan</p>
                                        <p className="font-medium text-text-primary">{pegawai.jatah_cuti_tahunan ?? 12} Hari</p>
                                    </div>
                                    <div className="bg-primary-50 p-4 rounded-card border border-primary-100">
                                        <p className="text-sm text-primary-700 font-medium">Sisa Cuti</p>
                                        <p className="font-bold text-primary-900 text-lg">{pegawai.sisa_cuti} Hari</p>
                                    </div>
                                    {pegawai.tanggal_akhir_kontrak && (
                                        <div>
                                            <p className="text-sm text-text-muted">Akhir Kontrak</p>
                                            <p className="font-medium text-text-primary">{new Date(pegawai.tanggal_akhir_kontrak).toLocaleDateString('id-ID')}</p>
                                        </div>
                                    )}
                                    {pegawai.alasan_nonaktif && (
                                        <div className="bg-danger-light p-4 rounded-card border border-danger">
                                            <p className="text-sm text-danger font-medium">Alasan Nonaktif</p>
                                            <p className="font-medium text-text-primary">{pegawai.alasan_nonaktif}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Riwayat Perubahan */}
                        <div className="p-6 border-t border-border">
                            <h4 className="section-title flex items-center gap-2 mb-4">
                                <svg className="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Riwayat Perubahan Data
                            </h4>
                            {pegawai.riwayat && pegawai.riwayat.length > 0 ? (
                                <div className="space-y-3">
                                    {pegawai.riwayat.map((riw) => (
                                        <div key={riw.id} className="bg-surface p-4 rounded-card border border-border">
                                            <div className="flex justify-between">
                                                <p className="font-medium text-text-primary capitalize">{riw.jenis_perubahan.replace(/_/g, ' ')}</p>
                                                <p className="text-sm text-text-muted">{new Date(riw.created_at).toLocaleString('id-ID')}</p>
                                            </div>
                                            <p className="text-sm text-text-secondary mt-1">
                                                Dari: <span className="font-semibold">{riw.nilai_lama || '-'}</span> → Menjadi: <span className="font-semibold">{riw.nilai_baru || '-'}</span>
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-text-muted text-sm">Belum ada riwayat perubahan.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
