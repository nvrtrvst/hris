import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, FormField, Input, PageHeader, Select } from '@/Components/AdminUI';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, Loader2, Mail, Save, ShieldCheck, User as UserIcon } from 'lucide-react';

export default function Create({ auth, allRoles, unitSekolah }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'pegawai',
        unit_sekolah_id: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Tambah User Baru</h2>}
        >
            <Head title="Tambah User" />

            <div className="mx-auto max-w-4xl space-y-5">
                <Link href={route('users.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                    <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar User
                </Link>

                <PageHeader
                    eyebrow="Pengaturan"
                    title="Tambah User"
                    description="Buat akun baru lalu tentukan role dan unit aksesnya."
                />

                <form onSubmit={submit} className="space-y-4">
                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <UserIcon className="h-4 w-4" />
                            </span>
                            <h2 className="text-sm font-bold text-primary">Identitas Akun</h2>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Nama Lengkap" htmlFor="name" required error={errors.name}>
                                <Input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            </FormField>
                            <FormField label="Email" htmlFor="email" required error={errors.email}>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="pl-9" required />
                                </div>
                            </FormField>
                            <FormField label="Password" htmlFor="password" required error={errors.password} description="Minimal 8 karakter.">
                                <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required />
                            </FormField>
                            <FormField label="Konfirmasi Password" htmlFor="password_confirmation" required>
                                <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required />
                            </FormField>
                        </div>
                    </Card>

                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <ShieldCheck className="h-4 w-4" />
                            </span>
                            <h2 className="text-sm font-bold text-primary">Role & Unit</h2>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Role Utama" htmlFor="role" required error={errors.role}>
                                <Select id="role" value={data.role} onChange={(e) => setData('role', e.target.value)}>
                                    {allRoles.map((r) => (
                                        <option key={r.id} value={r.name}>{r.name.toUpperCase()}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Unit Sekolah" htmlFor="unit_sekolah_id" error={errors.unit_sekolah_id} description="Kosongkan jika akun pusat / tanpa unit.">
                                <div className="relative">
                                    <Building2 className="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <Select id="unit_sekolah_id" value={data.unit_sekolah_id} onChange={(e) => setData('unit_sekolah_id', e.target.value)} className="pl-9">
                                        <option value="">Tidak ada (Pusat)</option>
                                        {unitSekolah.map((u) => (
                                            <option key={u.id} value={u.id}>{u.nama}</option>
                                        ))}
                                    </Select>
                                </div>
                            </FormField>
                        </div>
                    </Card>

                    <div className="flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-white p-4">
                        <Link href={route('users.index')} className="inline-flex h-10 items-center rounded-lg border border-border bg-white px-4 text-sm font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            Batal
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan...</> : <><Save className="h-4 w-4" /> Simpan User</>}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminShell>
    );
}
