import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, FormField, Input, PageHeader, Select } from '@/Components/AdminUI';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Briefcase, Info, Loader2, Mail, Phone, Save, User } from 'lucide-react';

const roleOptions = [
    { value: 'pegawai', label: 'Pegawai' },
    { value: 'admin_unit', label: 'Admin Unit (Kepala Sekolah / Kepala TU)' },
    { value: 'pimpinan', label: 'Pimpinan (Read-Only)' },
];

export default function Create({ auth, unitSekolahs, jabatans, statusKepegawaian }) {
    const statusOptions = statusKepegawaian || [];
    const { data, setData, post, processing, errors } = useForm({
        nama_lengkap: '',
        email: '',
        password: '',
        no_hp: '',
        unit_sekolah_id: '',
        jabatan_id: '',
        role: 'pegawai',
        status_kepegawaian: statusOptions[0]?.kode ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('pegawai.store'));
    };

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Tambah Pegawai</h2>}
        >
            <Head title="Tambah Pegawai" />

            <div className="mx-auto max-w-3xl space-y-5">
                <Link href={route('pegawai.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                    <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Pegawai
                </Link>

                <PageHeader
                    eyebrow="Kepegawaian"
                    title="Tambah Pegawai"
                    description="Buat akun dasar. Pegawai melengkapi data pribadi dan finansial saat login pertama."
                />

                <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <Info className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <p className="text-sm leading-relaxed text-amber-800">
                        <b>Data minimal untuk membuat akun.</b> Pegawai akan melengkapi data pribadi dan finansial saat login pertama.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <User className="h-4 w-4" />
                            </span>
                            <h2 className="text-sm font-bold text-primary">Informasi Akun</h2>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Nama Lengkap" htmlFor="nama_lengkap" required error={errors.nama_lengkap}>
                                <Input id="nama_lengkap" type="text" value={data.nama_lengkap} onChange={(e) => setData('nama_lengkap', e.target.value)} placeholder="Nama sesuai KTP" />
                            </FormField>
                            <FormField label="Email" htmlFor="email" required error={errors.email}>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="pl-9" placeholder="nama@yayasan.id" />
                                </div>
                            </FormField>
                            <FormField label="Password" htmlFor="password" error={errors.password} description="Kosongkan untuk auto-generate.">
                                <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                            </FormField>
                            <FormField label="Role Akses" htmlFor="role" required error={errors.role}>
                                <Select id="role" value={data.role} onChange={(e) => setData('role', e.target.value)}>
                                    {roleOptions.map((r) => (
                                        <option key={r.value} value={r.value}>{r.label}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="No. HP" htmlFor="no_hp" required error={errors.no_hp}>
                                <div className="relative">
                                    <Phone className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <Input id="no_hp" type="text" value={data.no_hp} onChange={(e) => setData('no_hp', e.target.value)} className="pl-9" placeholder="08xxxxxxxxxx" />
                                </div>
                            </FormField>
                        </div>
                    </Card>

                    <Card className="p-5 sm:p-6">
                        <div className="mb-5 flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Briefcase className="h-4 w-4" />
                            </span>
                            <h2 className="text-sm font-bold text-primary">Penempatan</h2>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Unit Sekolah" htmlFor="unit_sekolah_id" required error={errors.unit_sekolah_id}>
                                <Select id="unit_sekolah_id" value={data.unit_sekolah_id} onChange={(e) => setData('unit_sekolah_id', e.target.value)}>
                                    <option value="">Pilih Unit</option>
                                    {unitSekolahs.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </Select>
                            </FormField>
                            <FormField label="Jabatan" htmlFor="jabatan_id" required error={errors.jabatan_id}>
                                <Select
                                    id="jabatan_id"
                                    value={data.jabatan_id}
                                    onChange={(e) => {
                                        setData('jabatan_id', e.target.value);
                                        const jab = jabatans.find((j) => String(j.id) === e.target.value);
                                        if (jab?.is_supervisor && data.role === 'pegawai') {
                                            setData('role', 'pimpinan');
                                        }
                                    }}
                                >
                                    <option value="">Pilih Jabatan</option>
                                    {jabatans.map((jab) => <option key={jab.id} value={jab.id}>{jab.nama}</option>)}
                                </Select>
                            </FormField>
                            <FormField label="Status Kepegawaian" htmlFor="status_kepegawaian" required error={errors.status_kepegawaian} className="sm:col-span-2">
                                <Select id="status_kepegawaian" value={data.status_kepegawaian} onChange={(e) => setData('status_kepegawaian', e.target.value)}>
                                    {statusOptions.map((s) => (
                                        <option key={s.kode} value={s.kode}>{s.label}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>
                    </Card>

                    <div className="flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-white p-4">
                        <Link href={route('pegawai.index')} className="inline-flex h-10 items-center rounded-lg border border-border bg-white px-4 text-sm font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            Batal
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan...</> : <><Save className="h-4 w-4" /> Simpan Pegawai</>}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminShell>
    );
}
