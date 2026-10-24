import React from 'react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, MessageSquareText } from 'lucide-react';

const pct = (value) => value == null ? '—' : `${Number(value).toFixed(1)}%`;
const monthLabel = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });

export default function Kpi({ pegawai, bulan_ini, assignments = [] }) {
    const [responses, setResponses] = React.useState(() => Object.fromEntries(
        assignments.flatMap((assignment) => assignment.evaluations.map((evaluation) => [evaluation.id, evaluation.tanggapan_pegawai || ''])),
    ));
    const [sending, setSending] = React.useState(null);
    const { flash = {}, errors = {} } = usePage().props;

    const submitResponse = (id) => {
        setSending(id);
        router.post(route('presensi.kpi.respond', id), { tanggapan_pegawai: responses[id] || '' }, {
            preserveScroll: true,
            onFinish: () => setSending(null),
        });
    };

    return (
        <MobileLayout user={usePage().props.auth.user}>
            <Head title="KPI Saya" />
            <div className="mb-4 px-1">
                <p className="text-xs font-bold uppercase tracking-wide text-primary">Kinerja saya</p>
                <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">KPI Saya</h1>
                <p className="mt-1 text-sm text-slate-600">{pegawai.nama} · progres bulan {monthLabel(bulan_ini.mulai)}</p>
            </div>

            {flash.message && <div role="status" className="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm font-semibold text-emerald-800">{flash.message}</div>}
            {Object.keys(errors).length > 0 && <div role="alert" className="mb-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm font-semibold text-rose-800">{Object.values(errors).flat().join(' · ')}</div>}

            {assignments.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center">
                    <p className="text-sm font-bold text-slate-800">Jabatan belum terhubung.</p>
                    <p className="mt-1 text-xs text-slate-500">Hubungi admin untuk memastikan data unit dan jabatan Anda sudah tercatat.</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {assignments.map((assignment) => (
                        <section key={`${assignment.unit}-${assignment.jabatan}`} className="space-y-2.5">
                            <div className="px-1">
                                <h2 className="text-sm font-extrabold text-slate-900">{assignment.jabatan}</h2>
                                <p className="text-xs text-slate-500">{assignment.unit} · progres {bulan_ini.mulai} s/d {bulan_ini.sampai}</p>
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <div className="rounded-2xl border border-slate-200 bg-white p-3">
                                    <p className="text-[11px] font-semibold text-slate-500">Kehadiran</p>
                                    <p className="mt-1 text-xl font-extrabold tabular-nums text-slate-900">{pct(assignment.attendance_rate)}</p>
                                    <p className="text-[11px] text-slate-500">{assignment.hadir_hari}/{assignment.hari_kerja} hari tugas</p>
                                </div>
                                <div className="rounded-2xl border border-slate-200 bg-white p-3">
                                    <p className="text-[11px] font-semibold text-slate-500">Tepat waktu saat hadir</p>
                                    <p className="mt-1 text-xl font-extrabold tabular-nums text-slate-900">{pct(assignment.punctuality_rate)}</p>
                                </div>
                                {assignment.teaching_jp_terjadwal > 0 && <div className="col-span-2 rounded-2xl border border-slate-200 bg-white p-3">
                                    <p className="text-[11px] font-semibold text-slate-500">Pemenuhan JP mengajar</p>
                                    <div className="mt-1 flex items-end justify-between gap-2"><p className="text-xl font-extrabold tabular-nums text-slate-900">{pct(assignment.teaching_jp_rate)}</p><p className="text-xs tabular-nums text-slate-500">{assignment.teaching_jp_hadir}/{assignment.teaching_jp_terjadwal} JP</p></div>
                                </div>}
                            </div>

                            <div className="space-y-2">
                                <h3 className="px-1 text-xs font-bold uppercase tracking-wide text-slate-500">Evaluasi final</h3>
                                {assignment.evaluations.length === 0 ? (
                                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-xs text-slate-500">Belum ada evaluasi KPI final untuk jabatan ini.</div>
                                ) : assignment.evaluations.map((evaluation) => (
                                    <details key={evaluation.id} className="group rounded-2xl border border-slate-200 bg-white open:shadow-sm">
                                        <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
                                            <span className="min-w-0"><span className="block text-sm font-bold text-slate-800">{monthLabel(evaluation.periode_mulai)}</span><span className="text-xs text-slate-500">{evaluation.items.length} indikator</span></span>
                                            <span className="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-sm font-extrabold tabular-nums text-emerald-800">{Number(evaluation.nilai_akhir).toFixed(1)}%</span>
                                        </summary>
                                        <div className="space-y-3 border-t border-slate-100 px-3 py-3">
                                            {evaluation.ringkasan && <p className="rounded-xl bg-slate-50 p-3 text-sm text-slate-700">{evaluation.ringkasan}</p>}
                                            <ul className="space-y-2">
                                                {evaluation.items.map((item, index) => (
                                                    <li key={`${item.nama}-${index}`} className="rounded-xl bg-slate-50 p-3">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="min-w-0"><p className="text-sm font-bold text-slate-800">{item.nama}</p><p className="mt-0.5 text-[11px] text-slate-500">Bobot {item.bobot}%{item.target != null ? ` · target ${item.target}${item.satuan || ''}` : ''}</p></div>
                                                            <span className="shrink-0 text-sm font-extrabold tabular-nums text-primary">{item.tidak_berlaku ? 'N/A' : item.nilai == null ? '—' : `${Number(item.nilai).toFixed(1)}%`}</span>
                                                        </div>
                                                        {item.aktual != null && <p className="mt-1 text-xs text-slate-600">Realisasi: {item.aktual}{item.satuan || ''}</p>}
                                                        {item.label_rubrik && <p className="mt-1 text-xs text-slate-600">Rubrik {item.level_rubrik}: {item.label_rubrik}</p>}
                                                        {item.alasan_tidak_berlaku && <p className="mt-1 text-xs text-slate-600">Alasan N/A: {item.alasan_tidak_berlaku}</p>}
                                                        {item.bukti && <p className="mt-1 text-xs text-slate-600">Bukti: {item.bukti}</p>}
                                                        {item.catatan && <p className="mt-1 text-xs text-slate-600">Catatan: {item.catatan}</p>}
                                                    </li>
                                                ))}
                                            </ul>

                                            <div className="border-t border-slate-200 pt-3">
                                                <label className="mb-1 flex items-center gap-1.5 text-xs font-bold text-slate-700"><MessageSquareText className="h-3.5 w-3.5" />Tanggapan Anda</label>
                                                <textarea value={responses[evaluation.id] || ''} onChange={(e) => setResponses((prev) => ({ ...prev, [evaluation.id]: e.target.value }))} rows="3" maxLength="2000" className="min-h-20 w-full rounded-xl border-slate-200 text-base focus:border-primary focus:ring-primary" placeholder="Tulis klarifikasi atau tanggapan (min. 3 karakter)" />
                                                <button type="button" disabled={sending === evaluation.id || (responses[evaluation.id] || '').trim().length < 3} onClick={() => submitResponse(evaluation.id)} className="mt-2 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-bold text-white transition active:scale-[0.99] disabled:opacity-50">
                                                    {sending === evaluation.id ? 'Menyimpan…' : <><CheckCircle2 className="h-4 w-4" />Kirim tanggapan</>}
                                                </button>
                                            </div>
                                        </div>
                                    </details>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            )}
        </MobileLayout>
    );
}
