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
            header={<h2 className="font-semibold text-xl text-text-primary leading-tight">{isEdit ? 'Edit Role' : 'Tambah Role Baru'}</h2>}
        >
            <Head title={isEdit ? 'Edit Role' : 'Tambah Role'} />

            <FlashToast flash={flash} />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">

                    <div className="mb-6">
                        <Link href={route('roles.index')} className="link inline-flex items-center">
                            <ArrowLeft className="w-4 h-4 mr-1" />
                            Kembali ke Daftar Role
                        </Link>
                    </div>

                    <div className="page-card">
                        <div className="page-card-header">
                            <div>
                                <h3 className="text-xl font-bold text-text-primary">{isEdit ? `Edit Role: ${role.name.toUpperCase()}` : 'Buat Role Baru'}</h3>
                                <p className="page-subtitle">Tentukan nama peran dan akses bawaan yang akan diberikan kepada akun dengan peran ini.</p>
                            </div>
                        </div>

                        <form onSubmit={submit} className="p-6">

                            <div className="mb-8">
                                <label className="form-label">Nama Role</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="input-field max-w-md"
                                    placeholder="Contoh: keuangan_pusat"
                                    disabled={role?.name === 'superadmin'}
                                    required
                                />
                                {errors.name && <p className="form-error">{errors.name}</p>}
                                <p className="form-hint">Gunakan huruf kecil, pisahkan dengan garis bawah (underscore). Jangan gunakan spasi.</p>
                            </div>

                            <div className="divider"></div>

                            <div>
                                <h4 className="text-md font-bold text-text-primary mb-4">Akses Default Role</h4>

                                <div className="space-y-6">
                                    {Object.keys(allPermissions).map(group => {
                                        const groupPerms = allPermissions[group];
                                        const groupNames = groupPerms.map(p => p.name);
                                        const isAllChecked = groupNames.every(n => data.permissions.includes(n));

                                        return (
                                            <div key={group} className="bg-white rounded-card border border-border overflow-hidden">
                                                <div className="bg-surface px-4 py-3 border-b border-border flex justify-between items-center cursor-pointer hover:bg-gray-100 transition-colors" onClick={() => toggleGroup(groupPerms)}>
                                                    <h5 className="font-semibold text-text-primary capitalize">Modul {group.replace('_', ' ')}</h5>
                                                    <button type="button" className="text-sm font-medium text-primary flex items-center">
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
                                                                    className="h-4 w-4 text-primary border-border rounded focus:ring-primary cursor-pointer"
                                                                />
                                                            </div>
                                                            <div className="ml-3 text-sm">
                                                                <span className="font-medium text-text-secondary group-hover:text-primary transition-colors">{perm.name}</span>
                                                            </div>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })}
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
