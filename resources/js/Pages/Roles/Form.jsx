import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, CheckSquare, Square } from 'lucide-react';
import FlashToast from '@/Components/FlashToast';

export default function Form({ auth, role, rolePermissions, allPermissions, flash }) {
    const isEdit = !!role;
    
    // We expect rolePermissions to be an array of permission names.
    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name || '',
        permissions: rolePermissions || []
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('roles.update', role.id));
        } else {
            post(route('roles.store'));
        }
    };

    const togglePermission = (permissionName) => {
        let newPerms = [...data.permissions];
        if (newPerms.includes(permissionName)) {
            newPerms = newPerms.filter(p => p !== permissionName);
        } else {
            newPerms.push(permissionName);
        }
        setData('permissions', newPerms);
    };

    const toggleGroup = (groupPermissions) => {
        const groupNames = groupPermissions.map(p => p.name);
        const allChecked = groupNames.every(name => data.permissions.includes(name));
        
        let newPerms = [...data.permissions];
        if (allChecked) {
            newPerms = newPerms.filter(p => !groupNames.includes(p));
        } else {
            const toAdd = groupNames.filter(name => !newPerms.includes(name));
            newPerms = [...newPerms, ...toAdd];
        }
        setData('permissions', newPerms);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{isEdit ? 'Edit Role' : 'Tambah Role Baru'}</h2>}
        >
            <Head title={isEdit ? 'Edit Role' : 'Tambah Role'} />
            
            <FlashToast flash={flash} />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    
                    <div className="mb-6">
                        <Link href={route('roles.index')} className="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            <ArrowLeft className="w-4 h-4 mr-1" />
                            Kembali ke Daftar Role
                        </Link>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-6 border-b border-gray-100 bg-gray-50">
                            <h3 className="text-xl font-bold text-gray-900">{isEdit ? `Edit Role: ${role.name.toUpperCase()}` : 'Buat Role Baru'}</h3>
                            <p className="text-sm text-gray-500 mt-1">Tentukan nama peran dan akses bawaan yang akan diberikan kepada akun dengan peran ini.</p>
                        </div>

                        <form onSubmit={submit} className="p-6">
                            
                            <div className="mb-8">
                                <label className="block text-sm font-bold text-gray-700 mb-2">Nama Role</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="block w-full max-w-md rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="Contoh: keuangan_pusat"
                                    disabled={role?.name === 'superadmin'}
                                    required
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                <p className="mt-2 text-xs text-gray-500">Gunakan huruf kecil, pisahkan dengan garis bawah (underscore). Jangan gunakan spasi.</p>
                            </div>

                            <div className="border-t border-gray-100 pt-6">
                                <h4 className="text-md font-bold text-gray-900 mb-4">Akses Default Role</h4>
                                
                                <div className="space-y-6">
                                    {Object.keys(allPermissions).map(group => {
                                        const groupPerms = allPermissions[group];
                                        const groupNames = groupPerms.map(p => p.name);
                                        const isAllChecked = groupNames.every(n => data.permissions.includes(n));
                                        
                                        return (
                                            <div key={group} className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                                <div className="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center cursor-pointer hover:bg-gray-100 transition-colors" onClick={() => toggleGroup(groupPerms)}>
                                                    <h5 className="font-semibold text-gray-800 capitalize">Modul {group.replace('_', ' ')}</h5>
                                                    <button type="button" className="text-sm font-medium text-indigo-600 flex items-center">
                                                        {isAllChecked ? <CheckSquare className="w-4 h-4 mr-1"/> : <Square className="w-4 h-4 mr-1"/>}
                                                        {isAllChecked ? 'Batalkan Semua' : 'Pilih Semua'}
                                                    </button>
                                                </div>
                                                <div className="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                                    {groupPerms.map(perm => (
                                                        <label key={perm.id} className="relative flex items-start cursor-pointer group">
                                                            <div className="flex items-center h-5">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={data.permissions.includes(perm.name)}
                                                                    onChange={() => togglePermission(perm.name)}
                                                                    className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer"
                                                                />
                                                            </div>
                                                            <div className="ml-3 text-sm">
                                                                <span className="font-medium text-gray-700 group-hover:text-indigo-600 transition-colors">{perm.name}</span>
                                                            </div>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex justify-end pt-8 mt-8 border-t border-gray-100">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center px-6 py-2.5 bg-indigo-600 border border-transparent rounded-xl font-semibold text-sm text-white hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm disabled:opacity-50"
                                >
                                    <Save className="w-4 h-4 mr-2" />
                                    {isEdit ? 'Simpan Perubahan' : 'Buat Role'}
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
