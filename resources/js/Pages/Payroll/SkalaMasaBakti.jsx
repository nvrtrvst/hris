import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, Loader2, Plus, Save, Trash2, TrendingUp } from 'lucide-react';
import { formatRupiah } from '@/Utils/format';

const inputClass = 'input-field';

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
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Pengaturan Skala Gaji Masa Bakti</h2>}
        >
            <Head title="Skala Gaji Masa Bakti" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-4 items-start">
                    {/* Form */}
                    <div className="w-full lg:w-1/3 space-y-4 lg:sticky lg:top-6">
                        <Link href={route('komponen-gaji.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary lg:hidden">
                            <ArrowLeft className="h-4 w-4" /> Kembali ke Payroll
                        </Link>
                        <div className="card p-6">
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
                                    <input type="number" value={data.masa_kerja_tahun} onChange={(e) => setData('masa_kerja_tahun', e.target.value)}
                                        placeholder="Contoh: 1" className={inputClass} />
                                </Field>
                                <Field label="Nominal Gaji Pokok" required error={errors.nominal_gaji}>
                                    <input type="number" step="0.01" value={data.nominal_gaji} onChange={(e) => setData('nominal_gaji', e.target.value)}
                                        placeholder="Contoh: 2000000" className={inputClass} />
                                </Field>

                                <div className="flex justify-end border-t border-border pt-4">
                                    <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                        {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Simpan Skala</>}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="w-full lg:w-2/3">
                        <div className="card overflow-hidden">
                            <div className="flex items-center justify-between border-b border-border bg-surface px-6 py-4">
                                <h3 className="text-base font-extrabold text-text-primary">Daftar Skala Masa Bakti</h3>
                                <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                    <TrendingUp className="h-3 w-3" /> {skalas.length} skala
                                </span>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Masa Kerja</th>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nominal Gaji Pokok</th>
                                            <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border bg-white">
                                        {skalas.length > 0 ? skalas.map((s) => (
                                            <tr key={s.id} className="transition-colors hover:bg-surface">
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                                            <CalendarClock className="h-5 w-5 text-primary" />
                                                        </span>
                                                        <p className="text-sm font-bold text-text-primary">{s.masa_kerja_tahun} Tahun</p>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm font-semibold text-text-primary tabular-nums">
                                                    {formatRupiah(s.nominal_gaji)}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    <button
                                                        onClick={() => {
                                                            if (confirm('Hapus skala ini?')) {
                                                                router.delete(route('skala-masa-bakti.destroy', s.id));
                                                            }
                                                        }}
                                                        className="btn-danger btn-sm inline-flex items-center gap-1.5"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                    </button>
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
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
