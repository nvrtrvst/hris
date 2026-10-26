import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { SlidersHorizontal, Save } from 'lucide-react';

const LABELS = {
    izin: 'Izin',
    cuti: 'Cuti',
    koreksi: 'Koreksi Presensi',
    pulang_cepat: 'Pulang Cepat',
    lembur: 'Lembur',
    tugas_luar: 'Tugas Luar',
};

export default function ApprovalPolicies({ auth, policies }) {
    const { flash } = usePage().props;
    const { data, setData, put, processing, errors } = useForm({
        policies: policies.map((p) => ({
            jenis: p.jenis,
            levels: p.levels,
            second_level_min_days: p.second_level_min_days ?? '',
            sla_days: p.sla_days,
            tembusan_yayasan: !!p.tembusan_yayasan,
        })),
    });

    const setRow = (index, key, value) => {
        const next = data.policies.map((row, i) => (i === index ? { ...row, [key]: value } : row));
        setData('policies', next);
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('approval-policies.update'));
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Aturan Persetujuan</h2>}>
            <Head title="Aturan Persetujuan" />

            <div className="mx-auto w-full max-w-4xl space-y-4">
                {flash?.message && (
                    <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        {flash.message}
                    </div>
                )}

                <form onSubmit={submit} className="card p-4 sm:p-6">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                            <SlidersHorizontal className="h-5 w-5 text-primary" />
                        </span>
                        <div>
                            <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Alur Persetujuan</h3>
                            <p className="text-xs text-text-muted">
                                Tahap 2 hanya dipakai bila jumlah hari pengajuan melebihi ambang. Batas waktu dihitung dalam hari kerja unit.
                            </p>
                        </div>
                    </div>

                    <div className="space-y-4">
                        {data.policies.map((row, index) => (
                            <div key={row.jenis} className="rounded-xl border border-border p-3 sm:p-4">
                                <p className="mb-3 text-sm font-bold text-text-primary">{LABELS[row.jenis] || row.jenis}</p>

                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <label className="block">
                                        <span className="form-label text-xs">Jumlah tahap</span>
                                        <select
                                            className="select-field w-full"
                                            value={row.levels}
                                            onChange={(e) => setRow(index, 'levels', Number(e.target.value))}
                                        >
                                            <option value={1}>1 tahap</option>
                                            <option value={2}>2 tahap</option>
                                        </select>
                                    </label>

                                    <label className="block">
                                        <span className="form-label text-xs">Tahap 2 bila lebih dari (hari)</span>
                                        <input
                                            type="number"
                                            min="1"
                                            max="30"
                                            className="input-field w-full"
                                            value={row.second_level_min_days}
                                            disabled={row.levels < 2}
                                            onChange={(e) => setRow(index, 'second_level_min_days', e.target.value)}
                                        />
                                    </label>

                                    <label className="block">
                                        <span className="form-label text-xs">Batas waktu (hari kerja)</span>
                                        <input
                                            type="number"
                                            min="1"
                                            max="30"
                                            className="input-field w-full"
                                            value={row.sla_days}
                                            onChange={(e) => setRow(index, 'sla_days', Number(e.target.value))}
                                        />
                                    </label>
                                </div>

                                <label className="mt-3 flex items-center gap-2 text-xs font-semibold text-text-secondary">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                        checked={row.tembusan_yayasan}
                                        onChange={(e) => setRow(index, 'tembusan_yayasan', e.target.checked)}
                                    />
                                    Kirim salinan ke Ketua Yayasan
                                </label>

                                {errors[`policies.${index}.sla_days`] && (
                                    <p className="mt-1 text-xs text-danger">{errors[`policies.${index}.sla_days`]}</p>
                                )}
                            </div>
                        ))}
                    </div>

                    <div className="mt-5 flex justify-end">
                        <button type="submit" disabled={processing} className="btn-primary inline-flex items-center gap-2">
                            <Save className="h-4 w-4" /> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
