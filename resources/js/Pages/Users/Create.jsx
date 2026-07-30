import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

export default function Create({ auth, allRoles, unitSekolah }) {

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'pegawai',
        unit_sekolah_id: ''
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-text-primary leading-tight">Tambah User Baru</h2>}
        >
            <Head title="Tambah User" />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">

                    <div className="mb-6">
                        <Link href={route('users.index')} className="link inline-flex items-center">
                            <ArrowLeft className="w-4 h-4 mr-1" />
                            Kembali ke Daftar User
                        </Link>
                    </div>

                    <div className="page-card">
                        <div className="page-card-header">
                            <div>
                                <h3 className="text-xl font-bold text-text-primary">Form Tambah Akun</h3>
                                <p className="page-subtitle">Buat akun untuk Pegawai atau Admin baru.</p>
                            </div>
                        </div>

                        <form onSubmit={submit} className="p-6">

                            <div className="form-grid">
                                {/* Name */}
                                <div>
                                    <label className="form-label">Nama Lengkap</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={e => setData('name', e.target.value)}
                                        className="input-field"
                                        required
                                    />
                                    {errors.name && <p className="form-error">{errors.name}</p>}
                                </div>

                                {/* Email */}
                                <div>
                                    <label className="form-label">Email</label>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={e => setData('email', e.target.value)}
                                        className="input-field"
                                        required
                                    />
                                    {errors.email && <p className="form-error">{errors.email}</p>}
                                </div>

                                {/* Password */}
                                <div>
                                    <label className="form-label">Password</label>
                                    <input
                                        type="password"
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        className="input-field"
                                        required
                                    />
                                    {errors.password && <p className="form-error">{errors.password}</p>}
                                </div>

                                {/* Confirm Password */}
                                <div>
                                    <label className="form-label">Konfirmasi Password</label>
                                    <input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={e => setData('password_confirmation', e.target.value)}
                                        className="input-field"
                                        required
                                    />
                                </div>

                                {/* Role */}
                                <div>
                                    <label className="form-label">Role Utama</label>
                                    <select
                                        value={data.role}
                                        onChange={e => setData('role', e.target.value)}
                                        className="select-field"
                                        required
                                    >
                                        <option value="">Pilih Role...</option>
                                        {allRoles.map(r => (
                                            <option key={r.id} value={r.name}>{r.name.toUpperCase()}</option>
                                        ))}
                                    </select>
                                    {errors.role && <p className="form-error">{errors.role}</p>}
                                </div>

                                {/* Unit Sekolah */}
                                <div>
                                    <label className="form-label">Unit Sekolah (Opsional)</label>
                                    <select
                                        value={data.unit_sekolah_id}
                                        onChange={e => setData('unit_sekolah_id', e.target.value)}
                                        className="select-field"
                                    >
                                        <option value="">Tidak ada (Pusat)</option>
                                        {unitSekolah.map(u => (
                                            <option key={u.id} value={u.id}>{u.nama}</option>
                                        ))}
                                    </select>
                                    {errors.unit_sekolah_id && <p className="form-error">{errors.unit_sekolah_id}</p>}
                                </div>
                            </div>

                            <div className="divider"></div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn-primary"
                                >
                                    <Save className="w-4 h-4" />
                                    Simpan User
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
