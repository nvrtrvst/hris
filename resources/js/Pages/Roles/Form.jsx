import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, FormField, Input, PageHeader } from '@/Components/AdminUI';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckSquare, Loader2, Save, ShieldCheck, Square } from 'lucide-react';

export default function Form({ auth, role, rolePermissions, allPermissions }) {
    const isEdit = !!role;

    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name || '',
        permissions: rolePermissions || [],
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
        const newPerms = data.permissions.includes(permissionName)
            ? data.permissions.filter((p) => p !== permissionName)
            : [...data.permissions, permissionName];
        setData('permissions', newPerms);
    };

    const toggleGroup = (groupPermissions) => {
        const groupNames = groupPermissions.map((p) => p.name);
        const allChecked = groupNames.every((name) => data.permissions.includes(name));
        const newPerms = allChecked
            ? data.permissions.filter((p) => !groupNames.includes(p))
            : [...data.permissions, ...groupNames.filter((name) => !data.permissions.includes(name))];
        setData('permissions', newPerms);
    };

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">{isEdit ? 'Edit Role' : 'Tambah Role Baru'}</h2>}
        >
            <Head title={isEdit ? 'Edit Role' : 'Tambah Role'} />

            <div className="mx-auto max-w-4xl space-y-5">
                <Link href={route('roles.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                    <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Role
                </Link>

                <PageHeader
                    eyebrow="Pengaturan"
                    title={isEdit ? `Edit Role: ${role.name.toUpperCase()}` : 'Tambah Role'}
                    description="Tentukan nama peran dan akses default untuk pengguna dengan role ini."
                />

                <form onSubmit={submit} className="space-y-4">
                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <ShieldCheck className="h-4 w-4" />
                            </span>
                            <h2 className="text-sm font-bold text-primary">Identitas Role</h2>
                        </div>

                        <FormField
                            label="Nama Role"
                            htmlFor="name"
                            required
                            error={errors.name}
                            description="Gunakan huruf kecil dan garis bawah. Jangan gunakan spasi."
                        >
                            <Input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="max-w-md"
                                placeholder="Contoh: keuangan_pusat"
                                disabled={role?.name === 'superadmin'}
                                required
                            />
                        </FormField>
                    </Card>

                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <CheckSquare className="h-4 w-4" />
                            </span>
                            <h2 className="text-sm font-bold text-primary">Akses Default Role</h2>
                        </div>

                        <div className="space-y-3">
                            {Object.keys(allPermissions).map((group) => {
                                const groupPerms = allPermissions[group];
                                const groupNames = groupPerms.map((p) => p.name);
                                const isAllChecked = groupNames.every((n) => data.permissions.includes(n));

                                return (
                                    <div key={group} className="overflow-hidden rounded-xl border border-border">
                                        <div className="flex items-center justify-between gap-3 border-b border-border bg-surface px-4 py-3">
                                            <h3 className="text-sm font-bold capitalize text-primary">Modul {group.replace('_', ' ')}</h3>
                                            <button
                                                type="button"
                                                onClick={() => toggleGroup(groupPerms)}
                                                className="flex items-center text-xs font-bold text-primary hover:underline"
                                            >
                                                {isAllChecked ? <CheckSquare className="mr-1 h-4 w-4" /> : <Square className="mr-1 h-4 w-4" />}
                                                {isAllChecked ? 'Batalkan Semua' : 'Pilih Semua'}
                                            </button>
                                        </div>
                                        <div className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 md:grid-cols-3">
                                            {groupPerms.map((perm) => (
                                                <label key={perm.id} className="group flex cursor-pointer items-start">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.permissions.includes(perm.name)}
                                                        onChange={() => togglePermission(perm.name)}
                                                        className="mt-0.5 h-4 w-4 cursor-pointer rounded border-border text-primary focus:ring-primary"
                                                    />
                                                    <span className="ml-2.5 break-words text-xs font-medium text-text-secondary transition-colors group-hover:text-primary">
                                                        {perm.name}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>

                    <div className="flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-white p-4">
                        <Link href={route('roles.index')} className="inline-flex h-10 items-center rounded-lg border border-border bg-white px-4 text-sm font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            Batal
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan...</> : <><Save className="h-4 w-4" /> {isEdit ? 'Simpan Perubahan' : 'Buat Role'}</>}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminShell>
    );
}
