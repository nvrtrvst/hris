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
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Atur Akses User</h2>}
        >
            <Head title={`Atur Akses: ${userData.name}`} />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    
                    <div className="mb-6">
                        <Link href={route('users.index')} className="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            <ArrowLeft className="w-4 h-4 mr-1" />
                            Kembali ke Daftar User
                        </Link>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                            <div>
                                <h3 className="text-xl font-bold text-gray-900">{userData.name}</h3>
                                <p className="text-sm text-gray-500 mt-0.5">{userData.email} • {userData.unit_sekolah ? userData.unit_sekolah.nama : 'Pusat'}</p>
                            </div>
                        </div>

                        <form onSubmit={submit} className="p-6">
                            
                            <div className="mb-8">
                                <label className="block text-sm font-bold text-gray-700 mb-2">Role Utama</label>
                                <select
                                    value={data.role}
                                    onChange={e => setData('role', e.target.value)}
                                    className="block w-full max-w-md rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Pilih Role...</option>
                                    {allRoles.map(r => (
                                        <option key={r.id} value={r.name}>{r.name.toUpperCase()}</option>
                                    ))}
                                </select>
                                {errors.role && <p className="mt-1 text-sm text-red-600">{errors.role}</p>}
                                <p className="mt-2 text-xs text-gray-500">
                                    Mengubah role dapat secara otomatis menyesuaikan hak akses (permission) berdasarkan template role tersebut.
                                </p>
                            </div>

                            <div className="mb-8">
                                <h4 className="text-sm font-bold text-gray-700 mb-4 border-b pb-2">Akses Modul Spesifik (Hak Akses Dinamis)</h4>
                                
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {allPermissions.map((permission) => {
                                        const isChecked = data.permissions.includes(permission.name);
                                        return (
                                            <div 
                                                key={permission.id} 
                                                className={`relative flex items-start p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                    isChecked ? 'border-indigo-600 bg-indigo-50/50' : 'border-gray-200 hover:border-indigo-300'
                                                }`}
                                                onClick={() => handlePermissionToggle(permission.name)}
                                            >
                                                <div className="flex items-center h-5">
                                                    <div className={`w-5 h-5 rounded flex items-center justify-center border transition-colors ${
                                                        isChecked ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300 bg-white'
                                                    }`}>
                                                        {isChecked && <Check className="w-3.5 h-3.5 text-white" />}
                                                    </div>
                                                </div>
                                                <div className="ml-3 flex-1 select-none">
                                                    <label className="font-bold text-gray-900 cursor-pointer text-sm">
                                                        {formatPermissionName(permission.name)}
                                                    </label>
                                                    <p className="text-xs text-gray-500 mt-1">Izinkan {userData.name.split(' ')[0]} mengakses fitur ini.</p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="mb-8">
                                <h4 className="text-sm font-bold text-gray-700 mb-4 border-b pb-2">Ubah Kata Sandi (Opsional)</h4>
                                <p className="mt-1 text-xs text-gray-500 mb-4">
                                    Kosongkan jika tidak ingin mengubah kata sandi user ini.
                                </p>
                                
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-2">Kata Sandi Baru</label>
                                        <input
                                            type="password"
                                            value={data.password}
                                            onChange={e => setData('password', e.target.value)}
                                            className="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            placeholder="Minimal 8 karakter..."
                                        />
                                        {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-2">Konfirmasi Kata Sandi Baru</label>
                                        <input
                                            type="password"
                                            value={data.password_confirmation}
                                            onChange={e => setData('password_confirmation', e.target.value)}
                                            className="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            placeholder="Ketik ulang kata sandi..."
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end pt-5 border-t border-gray-100">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center px-5 py-2.5 bg-indigo-600 border border-transparent rounded-xl font-semibold text-sm text-white hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm disabled:opacity-50"
                                >
                                    <Save className="w-4 h-4 mr-2" />
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
