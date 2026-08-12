import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckSquare, KeyRound, Loader2, Save, ShieldCheck, Square } from 'lucide-react';

const inputClass = 'input-field';

const Field = ({ label, required, error, hint, children }) => (
    <div>
        <label className="form-label text-xs">{label} {required && <span className="text-danger">*</span>}</label>
        {children}
        {error && <p className="form-error">{error}</p>}
        {hint && !error && <p className="form-hint">{hint}</p>}
    </div>
);

function SectionCard({ Icon, title, description, children }) {
    return (
        <div className="card p-6">
            <div className="mb-5">
                <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                        <Icon className="h-4 w-4 text-primary" />
                    </span>
                    {title}
                </h3>
                {description && <p className="mt-1.5 text-xs text-text-muted">{description}</p>}
            </div>
            {children}
        </div>
    );
}

export default function Form({ auth, role, rolePermissions, allPermissions, flash }) {
    const isEdit = !!role;

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
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">{isEdit ? 'Edit Role' : 'Tambah Role Baru'}</h2>}
        >
            <Head title={isEdit ? 'Edit Role' : 'Tambah Role'} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <Link href={route('roles.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Role
                    </Link>

                    {flash?.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash?.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    <form onSubmit={submit} className="space-y-4">
                        <SectionCard Icon={ShieldCheck} title={isEdit ? `Edit Role: ${role.name.toUpperCase()}` : 'Buat Role Baru'}
                            description="Tentukan nama peran dan akses bawaan yang akan diberikan kepada akun dengan peran ini.">
                            <Field label="Nama Role" required error={errors.name}
                                hint="Gunakan huruf kecil, pisahkan dengan garis bawah (underscore). Jangan gunakan spasi.">
                                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                    className={`${inputClass} max-w-md`} placeholder="Contoh: keuangan_pusat"
                                    disabled={role?.name === 'superadmin'} required />
                            </Field>
                        </SectionCard>

                        <SectionCard Icon={KeyRound} title="Akses Default Role">
                            <div className="space-y-4">
                                {Object.keys(allPermissions).map((group) => {
                                    const groupPerms = allPermissions[group];
                                    const groupNames = groupPerms.map((p) => p.name);
                                    const isAllChecked = groupNames.every((n) => data.permissions.includes(n));

                                    return (
                                        <div key={group} className="overflow-hidden rounded-xl border border-border">
                                            <div className="flex cursor-pointer items-center justify-between border-b border-border bg-surface px-4 py-3 transition-colors hover:bg-primary/5"
                                                onClick={() => toggleGroup(groupPerms)}>
                                                <h5 className="text-sm font-extrabold capitalize text-text-primary">Modul {group.replace('_', ' ')}</h5>
                                                <button type="button" className="flex items-center text-xs font-semibold text-primary">
                                                    {isAllChecked ? <CheckSquare className="mr-1 h-4 w-4" /> : <Square className="mr-1 h-4 w-4" />}
                                                    {isAllChecked ? 'Batalkan Semua' : 'Pilih Semua'}
                                                </button>
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 md:grid-cols-3">
                                                {groupPerms.map((perm) => (
                                                    <label key={perm.id} className="flex cursor-pointer items-start group">
                                                        <input
                                                            type="checkbox"
                                                            checked={data.permissions.includes(perm.name)}
                                                            onChange={() => togglePermission(perm.name)}
                                                            className="h-4 w-4 cursor-pointer rounded border-border text-primary focus:ring-primary"
                                                        />
                                                        <span className="ml-2.5 text-xs font-medium text-text-secondary transition-colors group-hover:text-primary">
                                                            {perm.name}
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </SectionCard>

                        <div className="card flex items-center justify-end gap-3 p-5">
                            <Link href={route('roles.index')} className="btn-secondary">Batal</Link>
                            <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> {isEdit ? 'Simpan Perubahan' : 'Buat Role'}</>}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
