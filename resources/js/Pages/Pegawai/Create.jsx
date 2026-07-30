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
            header={<h2 className="font-semibold text-2xl text-text-primary leading-tight">Tambah Pegawai</h2>}
        >
            <Head title="Tambah Pegawai" />

            <div className="py-12 bg-surface min-h-screen">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <Link href={route('pegawai.index')} className="inline-flex items-center text-sm font-medium text-text-muted hover:text-primary transition-colors mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Kembali ke Daftar Pegawai
                    </Link>
                    <div className="page-card">
                        <div className="bg-warning-light border border-warning rounded-card p-4 text-sm text-warning mb-6">
                            Data minimal untuk membuat akun. Pegawai akan melengkapi data pribadi &amp; finansial saat login pertama.
                        </div>
                        <form onSubmit={submit} className="form-section">
                            <div>
                                <h3 className="section-title">Informasi Akun</h3>
                                <div className="divider mb-4 mt-0"></div>
                                <div className="form-grid">
                                    <div>
                                        <label className="form-label">Nama Lengkap <span className="text-danger">*</span></label>
                                        <input type="text" value={data.nama_lengkap} onChange={e => setData('nama_lengkap', e.target.value)} className="input-field" />
                                        {errors.nama_lengkap && <p className="form-error">{errors.nama_lengkap}</p>}
                                    </div>
                                    <div>
                                        <label className="form-label">Email <span className="text-danger">*</span></label>
                                        <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className="input-field" />
                                        {errors.email && <p className="form-error">{errors.email}</p>}
                                    </div>
                                    <div>
                                        <label className="form-label">Password</label>
                                        <input type="password" value={data.password} onChange={e => setData('password', e.target.value)} placeholder="Kosongkan = auto-generate" className="input-field" />
                                        {errors.password && <p className="form-error">{errors.password}</p>}
                                    </div>
                                    <div>
                                        <label className="form-label">No. HP <span className="text-danger">*</span></label>
                                        <input type="text" value={data.no_hp} onChange={e => setData('no_hp', e.target.value)} className="input-field" />
                                        {errors.no_hp && <p className="form-error">{errors.no_hp}</p>}
                                    </div>
                                </div>
                            </div>

                            <div className="divider"></div>

                            <div>
                                <h3 className="section-title">Penempatan</h3>
                                <div className="divider mb-4 mt-0"></div>
                                <div className="form-grid">
                                    <div>
                                        <label className="form-label">Unit Sekolah <span className="text-danger">*</span></label>
                                        <select value={data.unit_sekolah_id} onChange={e => setData('unit_sekolah_id', e.target.value)} className="select-field">
                                            <option value="">Pilih Unit</option>
                                            {unitSekolahs.map(unit => (
                                                <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                            ))}
                                        </select>
                                        {errors.unit_sekolah_id && <p className="form-error">{errors.unit_sekolah_id}</p>}
                                    </div>
                                    <div>
                                        <label className="form-label">Jabatan <span className="text-danger">*</span></label>
                                        <select value={data.jabatan_id} onChange={e => setData('jabatan_id', e.target.value)} className="select-field">
                                            <option value="">Pilih Jabatan</option>
                                            {jabatans.map(jab => (
                                                <option key={jab.id} value={jab.id}>{jab.nama}</option>
                                            ))}
                                        </select>
                                        {errors.jabatan_id && <p className="form-error">{errors.jabatan_id}</p>}
                                    </div>
                                    <div>
                                        <label className="form-label">Status Kepegawaian <span className="text-danger">*</span></label>
                                        <select value={data.status_kepegawaian} onChange={e => setData('status_kepegawaian', e.target.value)} className="select-field">
                                            <option value="tetap">Tetap</option>
                                            <option value="kontrak">Kontrak</option>
                                            <option value="honorer">Honorer</option>
                                            <option value="gtt">GTT</option>
                                        </select>
                                        {errors.status_kepegawaian && <p className="form-error">{errors.status_kepegawaian}</p>}
                                    </div>
                                </div>
                            </div>

                            <div className="divider"></div>

                            <div className="flex items-center justify-end gap-3">
                                <Link href={route('pegawai.index')} className="btn-secondary">Batal</Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn-primary"
                                >
                                    {processing ? 'Menyimpan...' : 'Simpan Pegawai'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
