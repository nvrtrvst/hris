import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, FormField, Input, PageHeader, Select } from '@/Components/AdminUI';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, KeyRound, Loader2, Save, ShieldCheck, User as UserIcon } from 'lucide-react';
import { avatarTone, initials } from '@/Utils/avatar';

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
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Atur Akses User</h2>}
        >
            <Head title={`Atur Akses: ${userData.name}`} />

            <div className="mx-auto max-w-4xl space-y-5">
                <Link href={route('users.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                    <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar User
                </Link>

                <PageHeader
                    eyebrow="Pengaturan"
                    title="Atur Akses User"
                    description="Ubah role dan izin modul untuk akun ini."
                />

                <Card className="flex items-center gap-4 p-5 sm:p-6">
                    <span className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-lg font-extrabold ${avatarTone(userData.name)}`}>
                        {initials(userData.name)}
                    </span>
                    <div className="min-w-0">
                        <h2 className="truncate text-lg font-bold text-primary">{userData.name}</h2>
                        <p className="text-sm text-text-secondary">{userData.email} · {userData.unit_sekolah ? userData.unit_sekolah.nama : 'Pusat'}</p>
                    </div>
                </Card>

                <form onSubmit={submit} className="space-y-4">
                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <ShieldCheck className="h-4 w-4" />
                            </span>
                            <div>
                                <h2 className="text-sm font-bold text-primary">Role Utama</h2>
                                <p className="text-xs text-text-secondary">Perubahan role menyesuaikan hak akses dari template role.</p>
                            </div>
                        </div>

                        <FormField label="Role" htmlFor="role" required error={errors.role}>
                            <Select id="role" value={data.role} onChange={(e) => setData('role', e.target.value)} className="max-w-md">
                                {allRoles.map((r) => (
                                    <option key={r.id} value={r.name}>{r.name.toUpperCase()}</option>
                                ))}
                            </Select>
                        </FormField>
                    </Card>

                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <UserIcon className="h-4 w-4" />
                            </span>
                            <div>
                                <h2 className="text-sm font-bold text-primary">Akses Modul Spesifik</h2>
                                <p className="text-xs text-text-secondary">Centang modul yang boleh diakses akun ini.</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {allPermissions.map((permission) => {
                                const isChecked = data.permissions.includes(permission.name);

                                return (
                                    <button
                                        key={permission.id}
                                        type="button"
                                        onClick={() => handlePermissionToggle(permission.name)}
                                        aria-pressed={isChecked}
                                        className={`flex items-start gap-3 rounded-xl border p-4 text-left transition-colors ${isChecked ? 'border-primary bg-primary/5' : 'border-border bg-white hover:border-primary/40'}`}
                                    >
                                        <span className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border ${isChecked ? 'border-primary bg-primary text-white' : 'border-border bg-white'}`}>
                                            {isChecked && <Check className="h-3.5 w-3.5" />}
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-sm font-bold text-primary">{formatPermissionName(permission.name)}</span>
                                            <span className="mt-0.5 block text-xs text-text-secondary">Izinkan akses ke fitur ini.</span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </Card>

                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <KeyRound className="h-4 w-4" />
                            </span>
                            <div>
                                <h2 className="text-sm font-bold text-primary">Ubah Kata Sandi</h2>
                                <p className="text-xs text-text-secondary">Kosongkan jika tidak ingin mengubah kata sandi.</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Kata Sandi Baru" htmlFor="password" error={errors.password}>
                                <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder="Minimal 8 karakter..." />
                            </FormField>
                            <FormField label="Konfirmasi Kata Sandi" htmlFor="password_confirmation">
                                <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} placeholder="Ketik ulang kata sandi..." />
                            </FormField>
                        </div>
                    </Card>

                    <div className="flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-white p-4">
                        <Link href={route('users.index')} className="inline-flex h-10 items-center rounded-lg border border-border bg-white px-4 text-sm font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            Batal
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan...</> : <><Save className="h-4 w-4" /> Simpan Akses</>}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminShell>
    );
}
