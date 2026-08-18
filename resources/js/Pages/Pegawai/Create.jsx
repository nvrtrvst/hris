import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Briefcase, Info, Loader2, Mail, Phone, Save, User } from 'lucide-react';
import { statusKepegawaianLabel } from '@/Utils/pegawaiMeta';

const inputClass = 'input-field';
const selectClass = 'select-field';

const Field = ({ label, required, error, hint, children }) => (
    <div>
        <label className="form-label text-xs">{label} {required && <span className="text-danger">*</span>}</label>
        {children}
        {error && <p className="form-error">{error}</p>}
        {hint && !error && <p className="form-hint">{hint}</p>}
    </div>
);

const SectionCard = ({ Icon, title, children }) => (
    <div className="card p-6">
        <h3 className="mb-5 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                <Icon className="h-4 w-4 text-primary" />
            </span>
            {title}
        </h3>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">{children}</div>
    </div>
);

export default function Create({ auth, unitSekolahs, jabatans, statusKepegawaian }) {
    const { data, setData, post, processing, errors } = useForm({
        nama_lengkap: '',
        email: '',
        password: '',
        no_hp: '',
        unit_sekolah_id: '',
        jabatan_id: '',
        status_kepegawaian: 'tetap',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('pegawai.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Tambah Pegawai</h2>}
        >
            <Head title="Tambah Pegawai" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Link href={route('pegawai.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Pegawai
                    </Link>

                    <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <Info className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                        <p className="text-sm leading-relaxed text-amber-800">
                            <b>Data minimal untuk membuat akun.</b> Pegawai akan melengkapi data pribadi &amp; finansial saat login pertama.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <SectionCard Icon={User} title="Informasi Akun">
                            <Field label="Nama Lengkap" required error={errors.nama_lengkap}>
                                <input type="text" value={data.nama_lengkap} onChange={(e) => setData('nama_lengkap', e.target.value)} className={inputClass} placeholder="Nama sesuai KTP" />
                            </Field>
                            <Field label="Email" required error={errors.email}>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={`${inputClass} pl-9`} placeholder="nama@yayasan.id" />
                                </div>
                            </Field>
                            <Field label="Password" error={errors.password} hint="Kosongkan untuk auto-generate">
                                <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className={inputClass} placeholder="••••••••" />
                            </Field>
                            <Field label="No. HP" required error={errors.no_hp}>
                                <div className="relative">
                                    <Phone className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input type="text" value={data.no_hp} onChange={(e) => setData('no_hp', e.target.value)} className={`${inputClass} pl-9`} placeholder="08xxxxxxxxxx" />
                                </div>
                            </Field>
                        </SectionCard>

                        <SectionCard Icon={Briefcase} title="Penempatan">
                            <Field label="Unit Sekolah" required error={errors.unit_sekolah_id}>
                                <select value={data.unit_sekolah_id} onChange={(e) => setData('unit_sekolah_id', e.target.value)} className={selectClass}>
                                    <option value="">Pilih Unit</option>
                                    {unitSekolahs.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </select>
                            </Field>
                            <Field label="Jabatan" required error={errors.jabatan_id}>
                                <select value={data.jabatan_id} onChange={(e) => setData('jabatan_id', e.target.value)} className={selectClass}>
                                    <option value="">Pilih Jabatan</option>
                                    {jabatans.map((jab) => <option key={jab.id} value={jab.id}>{jab.nama}</option>)}
                                </select>
                            </Field>
                            <div className="sm:col-span-2">
                                <Field label="Status Kepegawaian" required error={errors.status_kepegawaian}>
                                    <select value={data.status_kepegawaian} onChange={(e) => setData('status_kepegawaian', e.target.value)} className={selectClass}>
                                        {(statusKepegawaian || []).map((s) => (
                                            <option key={s} value={s}>{statusKepegawaianLabel(s)}</option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                        </SectionCard>

                        <div className="card flex items-center justify-end gap-3 p-5">
                            <Link href={route('pegawai.index')} className="btn-secondary">Batal</Link>
                            <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Simpan Pegawai</>}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
