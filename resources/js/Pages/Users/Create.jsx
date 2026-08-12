import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, Loader2, Mail, Save, ShieldCheck, User as UserIcon } from 'lucide-react';

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
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {children}
            </div>
        </div>
    );
}

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
            header={<h2 className="page-title">Tambah User Baru</h2>}
        >
            <Head title="Tambah User" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <Link href={route('users.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar User
                    </Link>

                    <form onSubmit={submit} className="space-y-4">
                        <SectionCard Icon={UserIcon} title="Identitas Akun">
                            <Field label="Nama Lengkap" required error={errors.name}>
                                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputClass} required />
                            </Field>
                            <Field label="Email" required error={errors.email}>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={`${inputClass} pl-9`} required />
                                </div>
                            </Field>
                            <Field label="Password" required error={errors.password} hint="Minimal 8 karakter.">
                                <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className={inputClass} required />
                            </Field>
                            <Field label="Konfirmasi Password" required>
                                <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className={inputClass} required />
                            </Field>
                        </SectionCard>

                        <SectionCard Icon={ShieldCheck} title="Role & Unit">
                            <Field label="Role Utama" required error={errors.role}>
                                <select value={data.role} onChange={(e) => setData('role', e.target.value)} className={selectClass}>
                                    {allRoles.map((r) => (
                                        <option key={r.id} value={r.name}>{r.name.toUpperCase()}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Unit Sekolah" error={errors.unit_sekolah_id} hint="Kosongkan jika akun pusat / tanpa unit.">
                                <div className="relative">
                                    <Building2 className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select value={data.unit_sekolah_id} onChange={(e) => setData('unit_sekolah_id', e.target.value)} className={`${selectClass} pl-9`}>
                                        <option value="">Tidak ada (Pusat)</option>
                                        {unitSekolah.map((u) => (
                                            <option key={u.id} value={u.id}>{u.nama}</option>
                                        ))}
                                    </select>
                                </div>
                            </Field>
                        </SectionCard>

                        <div className="card flex items-center justify-end gap-3 p-5">
                            <Link href={route('users.index')} className="btn-secondary">Batal</Link>
                            <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Simpan User</>}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
