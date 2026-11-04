import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, Input, PageHeader } from '@/Components/AdminUI';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, Loader2, Plus, Save, Trash2, TrendingUp } from 'lucide-react';
import { formatRupiah } from '@/Utils/format';

const Field = ({ label, required, error, hint, children }) => (
    <div>
        <label className="form-label text-xs">{label} {required && <span className="text-danger">*</span>}</label>
        {children}
        {error && <p className="form-error">{error}</p>}
        {hint && !error && <p className="form-hint">{hint}</p>}
    </div>
);

export default function SkalaMasaBakti({ auth, skalas }) {
    const { data, setData, post, reset, processing, errors, clearErrors } = useForm({
        masa_kerja_tahun: '',
        nominal_gaji: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('skala-masa-bakti.store'), {
            onSuccess: () => {
                reset();
                clearErrors();
            }
        });
    };

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Pengaturan Skala Gaji Masa Bakti</h2>}
        >
            <Head title="Skala Gaji Masa Bakti" />

            <div className="mx-auto w-full max-w-[1400px] space-y-5">
                <PageHeader
                    eyebrow="Penggajian"
                    title="Skala Gaji Masa Bakti"
                    description="Atur nominal gaji pokok berdasarkan bracket masa kerja pegawai."
                    actions={
                        <Link href={route('komponen-gaji.index')} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            <ArrowLeft className="h-4 w-4" /> Kembali
                        </Link>
                    }
                />

                <div className="flex flex-col items-start gap-4 lg:flex-row">
                    {/* Form */}
                    <div className="min-w-0 w-full space-y-4 lg:sticky lg:top-6 lg:w-1/3">
                        <Card className="p-6">
                            <div className="mb-5">
                                <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                        <Plus className="h-4 w-4 text-primary" />
                                    </span>
                                    Tambah Skala
                                </h3>
                                <p className="mt-1.5 text-xs text-text-muted">
                                    Gaji pokok pegawai dihitung otomatis dari bracket masa kerja tertinggi yang ≤ masa kerja pegawai.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-4">
                                <Field label="Masa Kerja (Tahun)" required error={errors.masa_kerja_tahun}>
                                    <Input type="number" value={data.masa_kerja_tahun} onChange={(e) => setData('masa_kerja_tahun', e.target.value)}
                                        placeholder="Contoh: 1" />
                                </Field>
                                <Field label="Nominal Gaji Pokok" required error={errors.nominal_gaji}>
                                    <Input type="number" step="0.01" value={data.nominal_gaji} onChange={(e) => setData('nominal_gaji', e.target.value)}
                                        placeholder="Contoh: 2000000" />
                                </Field>

                                <div className="flex justify-end border-t border-border pt-4">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Simpan Skala</>}
                                    </Button>
                                </div>
                            </form>
                        </Card>
                    </div>

                    {/* Table */}
                    <div className="min-w-0 w-full lg:flex-1">
                        <Card className="overflow-hidden">
                            <div className="flex items-center justify-between border-b border-border bg-surface px-6 py-4">
                                <h3 className="text-base font-extrabold text-text-primary">Daftar Skala Masa Bakti</h3>
                                <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                    <TrendingUp className="h-3 w-3" /> {skalas.length} skala
                                </span>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full table-fixed divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted sm:px-6">Masa Kerja</th>
                                            <th className="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted sm:px-6">Nominal Gaji Pokok</th>
                                            <th className="w-20 px-2 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted sm:w-28 sm:px-6">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border bg-white">
                                        {skalas.length > 0 ? skalas.map((s) => (
                                            <tr key={s.id} className="transition-colors hover:bg-surface">
                                                <td className="whitespace-nowrap px-4 py-4 sm:px-6">
                                                    <div className="flex items-center gap-3">
                                                        <span className="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 sm:flex">
                                                            <CalendarClock className="h-5 w-5 text-primary" />
                                                        </span>
                                                        <p className="text-sm font-bold text-text-primary">{s.masa_kerja_tahun} Tahun</p>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-4 text-sm font-semibold text-text-primary tabular-nums sm:px-6">
                                                    {formatRupiah(s.nominal_gaji)}
                                                </td>
                                                <td className="px-2 py-4 text-right sm:px-6">
                                                    <Button
                                                        variant="danger"
                                                        size="sm"
                                                        className="px-2 sm:px-3"
                                                        onClick={() => {
                                                            if (confirm('Hapus skala ini?')) {
                                                                router.delete(route('skala-masa-bakti.destroy', s.id));
                                                            }
                                                        }}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                        <span className="hidden sm:inline">Hapus</span>
                                                        <span className="sr-only sm:hidden">Hapus skala</span>
                                                    </Button>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="3" className="px-6 py-12 text-center">
                                                    <TrendingUp className="mx-auto h-7 w-7 text-border" />
                                                    <p className="mt-2 text-sm font-semibold text-text-primary">Belum ada skala</p>
                                                    <p className="text-xs text-text-muted">Tambahkan bracket masa kerja pertama untuk perhitungan dinamis masa bakti.</p>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
