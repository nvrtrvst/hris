import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, KeyRound, Loader2, Save, ShieldCheck, User as UserIcon } from 'lucide-react';
import { avatarTone, initials } from '@/Utils/avatar';

const inputClass = 'input-field';
const selectClass = 'select-field';

const Field = ({ label, required, error, hint, children, className = '' }) => (
    <div className={className}>
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

export default function Edit({ auth, userData, allPermissions, allRoles }) {
    const currentPermissions = userData.permissions.map((p) => p.name);
    const currentRole = userData.roles.length > 0 ? userData.roles[0].name : userData.role;

    const { data, setData, put, processing, errors } = useForm({
        role: currentRole || '',
        permissions: currentPermissions || [],
        password: '',
        password_confirmation: '',
    });

    const handlePermissionToggle = (permissionName) => {
        if (data.permissions.includes(permissionName)) {
            setData('permissions', data.permissions.filter((p) => p !== permissionName));
        } else {
            setData('permissions', [...data.permissions, permissionName]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('users.update', userData.id));
    };

    const formatPermissionName = (name) =>
        name.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Atur Akses User</h2>}
        >
            <Head title={`Atur Akses: ${userData.name}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <Link href={route('users.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar User
                    </Link>

                    {/* Hero user */}
                    <div className="card flex items-center gap-4 p-6">
                        <span className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-lg font-extrabold ${avatarTone(userData.name)}`}>
                            {initials(userData.name)}
                        </span>
                        <div className="min-w-0">
                            <h3 className="truncate text-lg font-extrabold text-text-primary">{userData.name}</h3>
                            <p className="text-sm text-text-muted">{userData.email} • {userData.unit_sekolah ? userData.unit_sekolah.nama : 'Pusat'}</p>
                        </div>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <SectionCard Icon={ShieldCheck} title="Role Utama"
                            description="Mengubah role dapat otomatis menyesuaikan hak akses berdasarkan template role tersebut.">
                            <Field label="Role" required error={errors.role} className="sm:col-span-2">
                                <select value={data.role} onChange={(e) => setData('role', e.target.value)} className={`${selectClass} max-w-md`}>
                                    {allRoles.map((r) => (
                                        <option key={r.id} value={r.name}>{r.name.toUpperCase()}</option>
                                    ))}
                                </select>
                            </Field>
                        </SectionCard>

                        <SectionCard Icon={UserIcon} title="Akses Modul Spesifik (Hak Akses Dinamis)">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {allPermissions.map((permission) => {
                                    const isChecked = data.permissions.includes(permission.name);
                                    return (
                                        <div
                                            key={permission.id}
                                            className={`relative flex cursor-pointer items-start rounded-xl border-2 p-4 transition-all ${isChecked ? 'border-primary bg-primary/5' : 'border-border hover:border-primary'}`}
                                            onClick={() => handlePermissionToggle(permission.name)}
                                        >
                                            <div className="flex h-5 items-center">
                                                <div className={`flex h-5 w-5 items-center justify-center rounded border transition-colors ${isChecked ? 'border-primary bg-primary' : 'border-border bg-white'}`}>
                                                    {isChecked && <Check className="h-3.5 w-3.5 text-white" />}
                                                </div>
                                            </div>
                                            <div className="ml-3 flex-1 select-none">
                                                <p className="text-sm font-bold text-text-primary">{formatPermissionName(permission.name)}</p>
                                                <p className="mt-1 text-xs text-text-muted">Izinkan {userData.name.split(' ')[0]} mengakses fitur ini.</p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </SectionCard>

                        <SectionCard Icon={KeyRound} title="Ubah Kata Sandi (Opsional)"
                            description="Kosongkan jika tidak ingin mengubah kata sandi user ini.">
                            <Field label="Kata Sandi Baru" error={errors.password}>
                                <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)}
                                    className={inputClass} placeholder="Minimal 8 karakter..." />
                            </Field>
                            <Field label="Konfirmasi Kata Sandi Baru">
                                <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)}
                                    className={inputClass} placeholder="Ketik ulang kata sandi..." />
                            </Field>
                        </SectionCard>

                        <div className="card flex items-center justify-end gap-3 p-5">
                            <Link href={route('users.index')} className="btn-secondary">Batal</Link>
                            <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Simpan Akses</>}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
