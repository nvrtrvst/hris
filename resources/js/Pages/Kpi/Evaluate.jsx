import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, LockKeyhole } from 'lucide-react';

const RUBRIC_SCORE = { 1: 0, 2: 60, 3: 85, 4: 100 };

const score = (indicator, value) => {
    if (value.tidak_berlaku) return null;
    if (indicator.jenis === 'rubric') return RUBRIC_SCORE[value.level_rubrik] ?? null;

    const actual = indicator.sumber === 'system' ? indicator.nilai_sistem : value.nilai_aktual;
    const target = Number(indicator.target);
    if (actual == null || actual === '' || !Number.isFinite(Number(actual)) || !Number.isFinite(target)) return null;
    if (indicator.arah === 'lower') {
        if (Number(actual) <= target || Number(actual) === 0) return 100;
        return Math.round((target * 10000) / Number(actual)) / 100;
    }
    if (target === 0) return null;
    return Math.round(Math.min(100, (Number(actual) * 10000) / target)) / 100;
};

export default function Evaluate({ auth, assignment, periode, template, indicators = [], evaluation, can_edit = true, can_respond = false }) {
    const existing = evaluation?.items || {};
    const [items, setItems] = React.useState(() => Object.fromEntries(indicators.map((indicator) => [indicator.id, {
        nilai_aktual: existing[indicator.id]?.nilai_aktual ?? '',
        level_rubrik: existing[indicator.id]?.level_rubrik ?? '',
        bukti: existing[indicator.id]?.bukti ?? '',
        catatan: existing[indicator.id]?.catatan ?? '',
        tidak_berlaku: Boolean(existing[indicator.id]?.tidak_berlaku),
        alasan_tidak_berlaku: existing[indicator.id]?.alasan_tidak_berlaku ?? '',
    }])));
    const [ringkasan, setRingkasan] = React.useState(evaluation?.ringkasan || '');
    const [tanggapan, setTanggapan] = React.useState(evaluation?.tanggapan_pegawai || '');
    const [processing, setProcessing] = React.useState(false);
    const { errors = {}, flash = {} } = usePage().props;
    const locked = evaluation?.status === 'finalized' || !can_edit;

    const update = (id, key, value) => setItems((prev) => ({ ...prev, [id]: { ...prev[id], [key]: value } }));
    const scored = indicators.map((indicator) => ({
        score: score(indicator, items[indicator.id] || {}),
        weight: Number(indicator.bobot),
        excluded: Boolean(items[indicator.id]?.tidak_berlaku),
    })).filter((item) => item.score !== null && !item.excluded);
    const weightTotal = scored.reduce((sum, item) => sum + item.weight, 0);
    const previewScore = weightTotal > 0
        ? scored.reduce((sum, item) => sum + item.score * item.weight, 0) / weightTotal
        : null;

    const submit = (finalize) => {
        setProcessing(true);
        router.post(route('kpi.evaluations.store'), {
            pegawai_id: assignment.pegawai_id,
            unit_sekolah_id: assignment.unit_sekolah_id,
            jabatan_id: assignment.jabatan_id,
            periode_mulai: periode.mulai,
            periode_selesai: periode.selesai,
            ringkasan,
            finalize,
            items,
        }, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h1 className="page-title">Penilaian KPI</h1>}>
            <Head title={`KPI - ${assignment.pegawai}`} />
            <div className="min-h-screen bg-surface py-6">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <Link href={route('kpi.index', { periode: 'monthly', tanggal: periode.mulai })} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"><ArrowLeft className="h-4 w-4" />Kembali ke laporan KPI</Link>
                    <section className="rounded-xl border border-border bg-white p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-extrabold text-text-primary">{assignment.pegawai}</h2>
                                <p className="mt-1 text-sm text-text-secondary">{assignment.jabatan} · {assignment.unit}</p>
                                <p className="mt-1 text-xs text-text-muted">Periode {periode.mulai} s/d {periode.selesai} · Template: {template.nama}</p>
                            </div>
                            {locked && <div className="flex flex-wrap items-center gap-2"><span className="inline-flex items-center gap-1.5 rounded-full bg-success-light px-3 py-1 text-xs font-bold text-success"><LockKeyhole className="h-3.5 w-3.5" />Final · {Number(evaluation.nilai_akhir).toFixed(1)}%</span>{auth.permissions?.includes('manage_kpi_templates') && <button type="button" onClick={() => router.post(route('kpi.evaluations.reopen', evaluation.id), {}, { preserveScroll: true })} className="btn-secondary btn-sm">Buka koreksi</button>}</div>}
                        </div>
                    </section>

                    {flash.message && <div role="status" className="rounded-xl border border-success/30 bg-success-light px-4 py-3 text-sm font-semibold text-success">{flash.message}</div>}
                    {Object.keys(errors).length > 0 && <div role="alert" className="rounded-xl border border-danger/30 bg-danger-light px-4 py-3 text-sm font-semibold text-danger">{Object.values(errors).flat().join(' · ')}</div>}

                    {evaluation?.tanggapan_pegawai && <div className="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-text-secondary"><strong className="text-text-primary">Tanggapan pegawai:</strong> {evaluation.tanggapan_pegawai}{evaluation.ditanggapi_pada && <span className="ml-2 text-xs text-text-muted">({evaluation.ditanggapi_pada})</span>}</div>}

                    {indicators.map((indicator) => {
                    const value = items[indicator.id] || {};
                    const rating = score(indicator, value);
                        const rubric = locked ? (evaluation?.items?.[indicator.id]?.rubrik_snapshot || indicator.rubrik) : indicator.rubrik;
                        return (
                            <section key={indicator.id} className="rounded-xl border border-border bg-white p-4 sm:p-5">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <h3 className="font-bold text-text-primary">{indicator.nama}</h3>
                                        {indicator.deskripsi && <p className="mt-1 text-sm text-text-secondary">{indicator.deskripsi}</p>}
                                    </div>
                                    <span className="rounded-full bg-primary/5 px-3 py-1 text-xs font-bold text-primary">Bobot {indicator.bobot}%</span>
                                </div>

                                {indicator.sumber === 'system' ? (
                                    <div className="mt-3 rounded-lg bg-surface px-3 py-2 text-sm text-text-secondary">
                                        Aktual sistem: <strong>{indicator.nilai_sistem == null ? 'Belum ada data' : `${Number(indicator.nilai_sistem).toFixed(1)}${indicator.satuan || ''}`}</strong>
                                        {' · '}Target: {indicator.target}{indicator.satuan || ''}
                                    </div>
                                ) : indicator.jenis === 'rubric' ? (
                                    <label className="form-label mt-3 block text-xs">Penilaian atasan
                                        <select disabled={locked} value={value.level_rubrik} onChange={(e) => update(indicator.id, 'level_rubrik', e.target.value)} className="select-field mt-1 w-full">
                                            <option value="">Pilih tingkat</option>
                                            {Object.entries(rubric || {}).map(([level, label]) => <option key={level} value={level}>{level}. {label}</option>)}
                                        </select>
                                    </label>
                                ) : (
                                    <label className="form-label mt-3 block text-xs">Realisasi
                                        <div className="mt-1 flex items-center gap-2"><input disabled={locked} type="number" min="0" step="0.01" value={value.nilai_aktual} onChange={(e) => update(indicator.id, 'nilai_aktual', e.target.value)} className="input-field max-w-xs" /><span className="text-sm text-text-muted">{indicator.satuan || ''} · target {indicator.target}</span></div>
                                    </label>
                                )}

                                {!locked && <label className="mt-3 flex items-center gap-2 text-xs font-semibold text-text-secondary"><input type="checkbox" checked={value.tidak_berlaku} onChange={(e) => update(indicator.id, 'tidak_berlaku', e.target.checked)} />Tidak berlaku untuk periode ini</label>}
                                {value.tidak_berlaku && <label className="form-label mt-2 block text-xs">Alasan
                                    <textarea disabled={locked} value={value.alasan_tidak_berlaku} onChange={(e) => update(indicator.id, 'alasan_tidak_berlaku', e.target.value)} rows="2" className="input-field mt-1 w-full" />
                                </label>}
                                {indicator.wajib_bukti && !value.tidak_berlaku && <label className="form-label mt-3 block text-xs">Bukti penilaian (wajib)
                                    <textarea disabled={locked} value={value.bukti} onChange={(e) => update(indicator.id, 'bukti', e.target.value)} rows="2" maxLength="4000" className="input-field mt-1 w-full" placeholder="Catatan, hasil observasi, atau referensi dokumen" />
                                </label>}
                                {!locked && <label className="form-label mt-3 block text-xs">Catatan
                                    <textarea value={value.catatan} onChange={(e) => update(indicator.id, 'catatan', e.target.value)} rows="2" maxLength="2000" className="input-field mt-1 w-full" />
                                </label>}
                                <p className="mt-2 text-xs font-semibold text-text-muted">Nilai indikator: {value.tidak_berlaku ? 'Tidak berlaku' : rating == null ? 'Belum dinilai' : `${rating.toFixed(1)}%`}</p>
                            </section>
                        );
                    })}

                    <section className="rounded-xl border border-border bg-white p-4 sm:p-5">
                        <label className="form-label block text-xs">Ringkasan atasan
                            <textarea disabled={locked} value={ringkasan} onChange={(e) => setRingkasan(e.target.value)} rows="3" maxLength="2000" className="input-field mt-1 w-full" placeholder="Sorot capaian dan area pengembangan" />
                        </label>
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                            <div><p className="text-xs text-text-muted">Skor sementara</p><p className="text-2xl font-extrabold tabular-nums text-primary">{previewScore == null ? '—' : `${previewScore.toFixed(1)}%`}</p></div>
                            {!locked && <div className="flex gap-2">
                                <button type="button" disabled={processing} onClick={() => submit(false)} className="btn-secondary">Simpan draft</button>
                                <button type="button" disabled={processing} onClick={() => submit(true)} className="btn-primary inline-flex items-center gap-2">{processing ? 'Menyimpan…' : <><CheckCircle2 className="h-4 w-4" />Finalisasi</>}</button>
                            </div>}
                        </div>
                    </section>

                    {can_respond && <section className="rounded-xl border border-border bg-white p-4 sm:p-5">
                        <label className="form-label block text-xs">Tanggapan Anda
                            <textarea value={tanggapan} onChange={(e) => setTanggapan(e.target.value)} rows="3" maxLength="2000" className="input-field mt-1 w-full" placeholder="Catatan atau klarifikasi terhadap hasil penilaian" />
                        </label>
                        <div className="mt-3 flex justify-end"><button type="button" disabled={processing || tanggapan.trim().length < 3} onClick={() => {
                            setProcessing(true);
                            router.post(route('kpi.evaluations.respond', evaluation.id), { tanggapan_pegawai: tanggapan }, { preserveScroll: true, onFinish: () => setProcessing(false) });
                        }} className="btn-secondary disabled:opacity-50">Kirim tanggapan</button></div>
                    </section>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
