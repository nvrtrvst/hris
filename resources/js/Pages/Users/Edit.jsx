import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, Check } from 'lucide-react';

export default function Edit({ auth, userData, allPermissions, allRoles }) {

    // Convert current permissions and roles to a format easier to work with
    const currentPermissions = userData.permissions.map(p => p.name);
    const currentRole = userData.roles.length > 0 ? userData.roles[0].name : userData.role;

    const { data, setData, put, processing, errors } = useForm({
        role: currentRole || '',
        permissions: currentPermissions || [],
        password: '',
        password_confirmation: '',
    });

    const handlePermissionToggle = (permissionName) => {
        if (data.permissions.includes(permissionName)) {
            setData('permissions', data.permissions.filter(p => p !== permissionName));
        } else {
            setData('permissions', [...data.permissions, permissionName]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('users.update', userData.id));
    };

    // Format permission names for better readability
    const formatPermissionName = (name) => {
        return name.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-text-primary leading-tight">Atur Akses User</h2>}
        >
            <Head title={`Atur Akses: ${userData.name}`} />

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
                                <h3 className="text-xl font-bold text-text-primary">{userData.name}</h3>
                                <p className="page-subtitle">{userData.email} • {userData.unit_sekolah ? userData.unit_sekolah.nama : 'Pusat'}</p>
                            </div>
                        </div>

                        <form onSubmit={submit} className="p-6">

                            <div className="mb-8">
                                <label className="form-label">Role Utama</label>
                                <select
                                    value={data.role}
                                    onChange={e => setData('role', e.target.value)}
                                    className="select-field max-w-md"
                                >
                                    <option value="">Pilih Role...</option>
                                    {allRoles.map(r => (
                                        <option key={r.id} value={r.name}>{r.name.toUpperCase()}</option>
                                    ))}
                                </select>
                                {errors.role && <p className="form-error">{errors.role}</p>}
                                <p className="form-hint">
                                    Mengubah role dapat secara otomatis menyesuaikan hak akses (permission) berdasarkan template role tersebut.
                                </p>
                            </div>

                            <div className="mb-8">
                                <h4 className="section-title">Akses Modul Spesifik (Hak Akses Dinamis)</h4>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {allPermissions.map((permission) => {
                                        const isChecked = data.permissions.includes(permission.name);
                                        return (
                                            <div
                                                key={permission.id}
                                                className={`relative flex items-start p-4 rounded-card border-2 cursor-pointer transition-all ${
                                                    isChecked ? 'border-primary bg-primary-50/50' : 'border-border hover:border-primary'
                                                }`}
                                                onClick={() => handlePermissionToggle(permission.name)}
                                            >
                                                <div className="flex items-center h-5">
                                                    <div className={`w-5 h-5 rounded flex items-center justify-center border transition-colors ${
                                                        isChecked ? 'bg-primary border-primary' : 'border-border bg-white'
                                                    }`}>
                                                        {isChecked && <Check className="w-3.5 h-3.5 text-white" />}
                                                    </div>
                                                </div>
                                                <div className="ml-3 flex-1 select-none">
                                                    <label className="font-bold text-text-primary cursor-pointer text-sm">
                                                        {formatPermissionName(permission.name)}
                                                    </label>
                                                    <p className="text-xs text-text-muted mt-1">Izinkan {userData.name.split(' ')[0]} mengakses fitur ini.</p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="mb-8">
                                <h4 className="section-title">Ubah Kata Sandi (Opsional)</h4>
                                <p className="form-hint mb-4">
                                    Kosongkan jika tidak ingin mengubah kata sandi user ini.
                                </p>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="form-label">Kata Sandi Baru</label>
                                        <input
                                            type="password"
                                            value={data.password}
                                            onChange={e => setData('password', e.target.value)}
                                            className="input-field"
                                            placeholder="Minimal 8 karakter..."
                                        />
                                        {errors.password && <p className="form-error">{errors.password}</p>}
                                    </div>
                                    <div>
                                        <label className="form-label">Konfirmasi Kata Sandi Baru</label>
                                        <input
                                            type="password"
                                            value={data.password_confirmation}
                                            onChange={e => setData('password_confirmation', e.target.value)}
                                            className="input-field"
                                            placeholder="Ketik ulang kata sandi..."
                                        />
                                    </div>
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
                                    Simpan Akses
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
