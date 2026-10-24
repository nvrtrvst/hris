import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Copy, Save } from 'lucide-react';

export default function Templates({ templates = [], units = [] }) {
    const { auth, flash = {}, errors = {} } = usePage().props;
    const [drafts, setDrafts] = React.useState(() => Object.fromEntries(templates.map((template) => [template.id, {
        nama: template.nama,
        indicators: template.indicators.map((indicator) => ({
            id: indicator.id,
            nama: indicator.nama,
            deskripsi: indicator.deskripsi || '',
            bobot: indicator.bobot,
            target: indicator.target ?? '',
            arah: indicator.arah || '',
            wajib_bukti: Boolean(indicator.wajib_bukti),
            rubrik: indicator.rubrik || {},
        })),
    }])));
    const baseTemplates = templates.filter((template) => template.unit_sekolah_id == null);
    const [override, setOverride] = React.useState({ template_id: '', unit_sekolah_id: '' });

    const updateTemplate = (id, field, value) => setDrafts((prev) => ({ ...prev, [id]: { ...prev[id], [field]: value } }));
    const updateIndicator = (templateId, indicatorId, field, value) => setDrafts((prev) => ({
        ...prev,
        [templateId]: {
            ...prev[templateId],
            indicators: prev[templateId].indicators.map((item) => item.id === indicatorId ? { ...item, [field]: value } : item),
        },
    }));

    const save = (id) => router.put(route('kpi.templates.update', id), drafts[id], { preserveScroll: true });
    const createOverride = (event) => {
        event.preventDefault();
        router.post(route('kpi.templates.override'), override, { preserveScroll: true, onSuccess: () => setOverride({ template_id: '', unit_sekolah_id: '' }) });
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h1 className="page-title">Template KPI</h1>}>
            <Head title="Template KPI" />
            <div className="min-h-screen bg-surface py-6">
                <div className="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <Link href={route('kpi.index')} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"><ArrowLeft className="h-4 w-4" />Kembali ke laporan</Link>
                    <div>
                        <h2 className="text-xl font-extrabold text-text-primary">Template KPI per jabatan</h2>
                        <p className="mt-1 text-sm text-text-muted">Total bobot setiap template harus 100%. Perubahan hanya berlaku untuk evaluasi baru.</p>
                    </div>
                    {flash.message && <div role="status" className="rounded-xl border border-success/30 bg-success-light px-4 py-3 text-sm font-semibold text-success">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-danger/30 bg-danger-light px-4 py-3 text-sm font-semibold text-danger">{flash.error}</div>}
                    {errors.indicators && <div role="alert" className="rounded-xl border border-danger/30 bg-danger-light px-4 py-3 text-sm font-semibold text-danger">{errors.indicators}</div>}

                    <form onSubmit={createOverride} className="grid grid-cols-1 gap-3 rounded-xl border border-border bg-white p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <label className="form-label text-xs">Template dasar
                            <select required value={override.template_id} onChange={(e) => setOverride({ ...override, template_id: e.target.value })} className="select-field mt-1 w-full">
                                <option value="">Pilih jabatan</option>
                                {baseTemplates.map((template) => <option key={template.id} value={template.id}>{template.jabatan?.nama || template.nama}</option>)}
                            </select>
                        </label>
                        <label className="form-label text-xs">Unit untuk penyesuaian
                            <select required value={override.unit_sekolah_id} onChange={(e) => setOverride({ ...override, unit_sekolah_id: e.target.value })} className="select-field mt-1 w-full">
                                <option value="">Pilih unit</option>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                            </select>
                        </label>
                        <button type="submit" className="btn-secondary inline-flex items-center justify-center gap-2"><Copy className="h-4 w-4" />Buat penyesuaian</button>
                    </form>

                    <div className="space-y-4">
                        {templates.map((template) => {
                            const draft = drafts[template.id];
                            const total = draft.indicators.reduce((sum, item) => sum + Number(item.bobot || 0), 0);
                            return (
                                <form key={template.id} onSubmit={(event) => { event.preventDefault(); save(template.id); }} className="overflow-hidden rounded-xl border border-border bg-white">
                                    <div className="flex flex-wrap items-end justify-between gap-3 border-b border-border bg-surface p-4">
                                        <label className="form-label min-w-[16rem] flex-1 text-xs">Nama template
                                            <input value={draft.nama} onChange={(e) => updateTemplate(template.id, 'nama', e.target.value)} className="input-field mt-1 w-full" maxLength={150} />
                                        </label>
                                        <div className="text-right"><p className="text-[11px] font-semibold text-text-muted">{template.jabatan?.nama}{template.unitSekolah ? ` · ${template.unitSekolah.nama}` : ' · Dasar'}</p><p className={`text-sm font-extrabold tabular-nums ${Math.round(total * 100) / 100 === 100 ? 'text-success' : 'text-danger'}`}>Total bobot {total.toFixed(2)}%</p></div>
                                    </div>
                                    <div className="divide-y divide-border">
                                        {draft.indicators.map((item) => (
                                            <div key={item.id} className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-12 sm:items-start">
                                                <label className="form-label text-xs sm:col-span-4">Indikator
                                                    <input value={item.nama} onChange={(e) => updateIndicator(template.id, item.id, 'nama', e.target.value)} className="input-field mt-1 w-full" maxLength={150} />
                                                    <input value={item.deskripsi} onChange={(e) => updateIndicator(template.id, item.id, 'deskripsi', e.target.value)} className="input-field mt-2 w-full" placeholder="Petunjuk penilaian / bukti" maxLength={1000} />
                                                    {Object.keys(item.rubrik).length > 0 && <div className="mt-2 grid grid-cols-1 gap-1.5">
                                                        {[1, 2, 3, 4].map((level) => <input key={level} value={item.rubrik[level] || ''} onChange={(e) => updateIndicator(template.id, item.id, 'rubrik', { ...item.rubrik, [level]: e.target.value })} className="input-field w-full text-xs" placeholder={`Rubrik tingkat ${level}`} maxLength={300} />)}
                                                    </div>}
                                                </label>
                                                <label className="form-label text-xs sm:col-span-2">Bobot (%)
                                                    <input type="number" min="0" max="100" step="0.01" value={item.bobot} onChange={(e) => updateIndicator(template.id, item.id, 'bobot', e.target.value)} className="input-field mt-1 w-full" />
                                                </label>
                                                <label className="form-label text-xs sm:col-span-2">Target
                                                    <input type="number" min="0" step="0.01" value={item.target} onChange={(e) => updateIndicator(template.id, item.id, 'target', e.target.value)} className="input-field mt-1 w-full" placeholder="—" />
                                                </label>
                                                <label className="form-label text-xs sm:col-span-2">Arah
                                                    <select value={item.arah} onChange={(e) => updateIndicator(template.id, item.id, 'arah', e.target.value)} className="select-field mt-1 w-full">
                                                        <option value="">Rubrik</option><option value="higher">Lebih tinggi lebih baik</option><option value="lower">Lebih rendah lebih baik</option>
                                                    </select>
                                                </label>
                                                <label className="flex items-center gap-2 pt-5 text-xs font-semibold text-text-secondary sm:col-span-2"><input type="checkbox" checked={item.wajib_bukti} onChange={(e) => updateIndicator(template.id, item.id, 'wajib_bukti', e.target.checked)} />Bukti wajib</label>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex justify-end border-t border-border p-4"><button disabled={Math.round(total * 100) / 100 !== 100} type="submit" className="btn-primary inline-flex items-center gap-2 disabled:opacity-50"><Save className="h-4 w-4" />Simpan template</button></div>
                                </form>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
