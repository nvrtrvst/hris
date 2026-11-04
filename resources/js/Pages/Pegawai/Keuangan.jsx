import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Badge, Button, Card, EmptyState, Input, PageHeader } from '@/Components/AdminUI';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Banknote, Info, Loader2, Save, User, Wallet } from 'lucide-react';
import { avatarTone, initials } from '@/Utils/avatar';
import { formatRupiah } from '@/Utils/format';

export default function Keuangan({ auth, pegawai, komponens }) {
    const initialKomponens = {};
    (komponens || []).forEach((k) => {
        const pivot = pegawai.komponen_gaji?.find((kg) => kg.id === k.id);
        initialKomponens[k.id] = pivot ? pivot.pivot.nominal : '';
    });

    const { data, setData, post, processing, errors } = useForm({
        komponens: initialKomponens,
    });

    const handleNominalChange = (komponenId, value) => {
        setData('komponens', { ...data.komponens, [komponenId]: value });
    };

    const handleSave = (e) => {
        e.preventDefault();
        post(route('pegawai.keuangan.update', pegawai.id));
    };

    const hasOverride = Object.values(data.komponens).some((v) => v !== '' && v !== null);

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Profil Keuangan: {pegawai.nama_lengkap}</h2>}
        >
            <Head title={`Keuangan - ${pegawai.nama_lengkap}`} />

            <div className="mx-auto max-w-4xl space-y-5">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Link href={route('pegawai.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Pegawai
                    </Link>
                    <Link href={route('pegawai.show', pegawai.id)} className="inline-flex items-center gap-1.5 text-xs font-bold text-primary transition-colors hover:text-primary/70">
                        Lihat Profil Lengkap <ArrowRight className="h-3.5 w-3.5" />
                    </Link>
                </div>

                <PageHeader
                    eyebrow="Kepegawaian"
                    title="Profil Keuangan"
                    description="Atur nominal gaji khusus yang berbeda dari standar yayasan."
                />

                <Card className="p-5 sm:p-6">
                    <div className="flex items-center gap-4">
                        <span className={`flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-lg font-extrabold ${avatarTone(pegawai.nama_lengkap)}`}>
                            {initials(pegawai.nama_lengkap)}
                        </span>
                        <div className="min-w-0">
                            <h2 className="text-xl font-bold text-primary">{pegawai.nama_lengkap}</h2>
                            <p className="mt-0.5 flex items-center gap-1 text-xs text-text-secondary">
                                <User className="h-3 w-3" /> NIP: {pegawai.nuptk || '-'}
                            </p>
                        </div>
                    </div>
                </Card>

                <div className="flex items-start gap-3 rounded-xl border border-info/30 bg-info-light p-4">
                    <Info className="mt-0.5 h-5 w-5 shrink-0 text-info" />
                    <p className="text-sm leading-relaxed text-info">
                        Halaman ini untuk mengeset <b>nominal gaji khusus</b> yang berbeda dari standar yayasan.
                        <span className="mt-0.5 block font-semibold">Biarkan kosong untuk memakai nilai default sistem.</span>
                    </p>
                </div>

                <form onSubmit={handleSave} className="space-y-4">
                    <Card className="p-5 sm:p-6">
                        <div className="mb-4 flex items-center justify-between border-b border-border/60 pb-4">
                            <h2 className="flex items-center gap-2 text-sm font-bold text-primary">
                                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                    <Wallet className="h-4 w-4 text-primary" />
                                </span>
                                Komponen Gaji
                            </h2>
                            {hasOverride && <Badge variant="warning">Ada override khusus</Badge>}
                        </div>

                        {(komponens || []).length === 0 ? (
                            <EmptyState
                                icon={Banknote}
                                title="Belum ada komponen gaji aktif"
                                description="Kelola komponen di menu Konfigurasi Komponen."
                            />
                        ) : (
                            <div className="space-y-3">
                                {(komponens || []).map((k) => {
                                    const isPendapatan = k.tipe === 'pendapatan';

                                    return (
                                        <div key={k.id} className="flex flex-col gap-4 rounded-xl border border-border p-4 transition-colors hover:border-primary/40 hover:bg-surface/60 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className={`h-2 w-2 shrink-0 rounded-full ${isPendapatan ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                                                    <h3 className="text-sm font-bold text-primary">{k.nama}</h3>
                                                    <Badge variant={isPendapatan ? 'success' : 'danger'}>{k.tipe}</Badge>
                                                    <Badge variant="neutral">{String(k.jenis || '').replace(/_/g, ' ')}</Badge>
                                                </div>
                                                <p className="mt-1 text-xs text-text-secondary">
                                                    Default Sistem: <b className="tabular-nums text-primary">{formatRupiah(k.nilai_default || 0)}</b>
                                                </p>
                                            </div>
                                            <div className="w-full shrink-0 sm:w-64">
                                                <div className="relative">
                                                    <div className="pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3">
                                                        <span className="text-xs font-semibold text-text-muted">Rp</span>
                                                    </div>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        value={data.komponens[k.id]}
                                                        onChange={(e) => handleNominalChange(k.id, e.target.value)}
                                                        placeholder="Gunakan default"
                                                        className="pl-9 text-xs"
                                                        aria-label={`Nominal ${k.nama}`}
                                                    />
                                                </div>
                                                {errors?.[`komponens.${k.id}`] && <p className="mt-1 text-xs font-semibold text-rose-600">{errors[`komponens.${k.id}`]}</p>}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </Card>

                    <div className="flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-white p-4">
                        <Link href={route('pegawai.show', pegawai.id)} className="inline-flex h-10 items-center rounded-lg border border-border bg-white px-4 text-sm font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            Batal
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan...</> : <><Save className="h-4 w-4" /> Simpan Profil Keuangan</>}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminShell>
    );
}
