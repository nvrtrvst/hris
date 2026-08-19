import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Banknote, Info, Loader2, Save, User, Wallet } from 'lucide-react';
import { avatarTone, initials } from '@/Utils/avatar';
import { formatRupiah } from '@/Utils/format';

export default function Keuangan({ auth, pegawai, komponens }) {
    const { flash = {} } = usePage().props;

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
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Profil Keuangan: {pegawai.nama_lengkap}</h2>}
        >
            <Head title={`Keuangan - ${pegawai.nama_lengkap}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <Link href={route('pegawai.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                            <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Pegawai
                        </Link>
                        <Link href={route('pegawai.show', pegawai.id)} className="inline-flex items-center gap-1.5 text-xs font-bold text-primary transition-colors hover:text-primary/70">
                            Lihat Profil Lengkap <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    {/* Flash */}
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    <form onSubmit={handleSave} className="space-y-4">
                        {/* Hero */}
                        <div className="card p-6">
                            <div className="flex items-center gap-4">
                                <span className={`flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-lg font-extrabold ${avatarTone(pegawai.nama_lengkap)}`}>
                                    {initials(pegawai.nama_lengkap)}
                                </span>
                                <div className="min-w-0">
                                    <h3 className="text-xl font-extrabold text-primary">{pegawai.nama_lengkap}</h3>
                                    <p className="mt-0.5 flex items-center gap-1 text-xs text-text-secondary">
                                        <User className="h-3 w-3" /> NIP: {pegawai.nip || '—'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Info */}
                        <div className="flex items-start gap-3 rounded-xl border border-info/30 bg-info-light p-4">
                            <Info className="mt-0.5 h-5 w-5 shrink-0 text-info" />
                            <p className="text-sm leading-relaxed text-info">
                                Halaman ini untuk mengeset <b>nominal gaji khusus</b> yang berbeda dari standar yayasan.
                                <span className="block mt-0.5 font-semibold">Biarkan kosong untuk memakai nilai default sistem.</span>
                            </p>
                        </div>

                        {/* Komponen */}
                        <div className="card p-5">
                            <div className="mb-4 flex items-center justify-between border-b border-border/60 pb-4">
                                <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                        <Wallet className="h-4 w-4 text-primary" />
                                    </span>
                                    Komponen Gaji
                                </h3>
                                {hasOverride && (
                                    <span className="rounded-full bg-amber-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700 border border-amber-200">
                                        Ada override khusus
                                    </span>
                                )}
                            </div>

                            <div className="space-y-3">
                                {(komponens || []).map((k) => {
                                    const isPendapatan = k.tipe === 'pendapatan';

                                    return (
                                        <div key={k.id} className="flex flex-col gap-4 rounded-xl border border-border p-4 transition-colors hover:border-primary/40 hover:bg-surface/60 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className={`h-2 w-2 shrink-0 rounded-full ${isPendapatan ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                                                    <h4 className="text-sm font-bold text-text-primary">{k.nama}</h4>
                                                    <span className={`rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide ${isPendapatan ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-600'}`}>
                                                        {k.tipe}
                                                    </span>
                                                    <span className="rounded-full bg-surface px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-text-muted">
                                                        {String(k.jenis || '').replace(/_/g, ' ')}
                                                    </span>
                                                </div>
                                                <p className="mt-1 text-xs text-text-secondary">
                                                    Default Sistem: <b className="tabular-nums text-text-primary">{formatRupiah(k.nilai_default || 0)}</b>
                                                </p>
                                            </div>
                                            <div className="w-full sm:w-64 shrink-0">
                                                <div className="relative">
                                                    <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                        <span className="text-xs font-semibold text-text-muted">Rp</span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={data.komponens[k.id]}
                                                        onChange={(e) => handleNominalChange(k.id, e.target.value)}
                                                        placeholder="Gunakan default"
                                                        className="input-field pl-9 text-xs"
                                                    />
                                                </div>
                                                {errors?.[`komponens.${k.id}`] && <p className="form-error">{errors[`komponens.${k.id}`]}</p>}
                                            </div>
                                        </div>
                                    );
                                })}
                                {(!komponens || komponens.length === 0) && (
                                    <div className="flex flex-col items-center py-10 text-center">
                                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface">
                                            <Banknote className="h-7 w-7 text-border" />
                                        </div>
                                        <p className="mt-3 text-sm font-bold text-primary">Belum ada komponen gaji aktif</p>
                                        <p className="mt-1 text-xs text-text-secondary">Kelola komponen di menu Konfigurasi Komponen.</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="card flex items-center justify-end gap-3 p-5">
                            <Link href={route('pegawai.show', pegawai.id)} className="btn-secondary">Batal</Link>
                            <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Simpan Profil Keuangan</>}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
