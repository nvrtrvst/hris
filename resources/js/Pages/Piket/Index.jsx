import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Settings, ShieldAlert, Clock3 } from 'lucide-react';

export default function PiketMonitor({ auth, tanggal, sessions = [], pos = [], canManage = false }) {
    const { flash = {} } = usePage().props;
    const [koreksi, setKoreksi] = useState({});

    const perlu = sessions.filter((s) => s.perlu_tindakan);
    const lain = sessions.filter((s) => !s.perlu_tindakan);

    const aksi = (sesi, jenis) => {
        const data = koreksi[sesi.id] ?? {};
        if (!data.pos_id || !data.alasan) return;

        router.post(
            route(`piket.sesi.pos.${jenis}`, [sesi.id, data.pos_id]),
            { alasan: data.alasan },
            { preserveScroll: true },
        );
    };

    const RoundRow = ({ round }) => {
        const [alasan, setAlasan] = useState('');
        const selesai = Boolean(round.selesai_at);

        if (selesai) {
            return (
                <p className="text-xs text-text-secondary">
                    Fase {round.putaran}{round.area ? ` · ${round.area}` : ''}: {round.selesai_at} ({round.status})
                </p>
            );
        }

        return (
            <div className="flex flex-col gap-2 rounded-lg border border-border px-3 py-2 sm:flex-row sm:items-center">
                <span className="min-w-0 text-xs">
                    <b>Fase {round.putaran}</b>{round.area ? ` · ${round.area}` : ''} · {round.jendela_mulai}–{round.jendela_selesai}
                </span>
                <input
                    className="input-field h-11 flex-1 text-xs"
                    placeholder="Alasan buka fase (wajib)"
                    aria-label={`Alasan buka fase ${round.putaran}`}
                    value={alasan}
                    onChange={(e) => setAlasan(e.target.value)}
                />
                <button
                    type="button"
                    disabled={alasan.trim().length < 5}
                    onClick={() => router.post(route('piket.round.buka', round.id), { alasan }, { preserveScroll: true })}
                    className="btn-secondary btn-sm"
                >
                    Buka fase
                </button>
            </div>
        );
    };

    const SesionCard = ({ sesi }) => {
        const form = koreksi[sesi.id] ?? { pos_id: '', alasan: '' };
        const setForm = (patch) => setKoreksi({ ...koreksi, [sesi.id]: { ...form, ...patch } });
        const sesiSelesai = sesi.status === 'complete';
        const statusSesi = sesiSelesai ? 'Selesai' : sesi.perlu_tindakan ? 'Perlu tindakan' : 'Berjalan';
        const statusSesiCls = sesiSelesai
            ? 'bg-emerald-100 text-emerald-700'
            : sesi.perlu_tindakan
                ? 'bg-amber-100 text-amber-800'
                : 'bg-sky-100 text-sky-700';

        return (
            <div className="card p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p className="text-sm font-bold text-text-primary">Sesi {sesi.jam}</p>
                        <p className="text-xs text-text-secondary">
                            Status: {sesi.status} · {sesi.petugas.map((p) => `${p.nama} (${p.area || p.peran}, ${p.selesai}/${p.wajib})`).join(' · ')}
                        </p>
                    </div>
                    <span className={`rounded-full px-2 py-1 text-xs font-bold ${statusSesiCls}`}>
                        {statusSesi}
                    </span>
                </div>

                <dl className="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                    <div><dt className="text-text-secondary">Pos belum</dt><dd className="font-semibold">{sesi.pos_belum.length ? sesi.pos_belum.join(', ') : '-'}</dd></div>
                    <div><dt className="text-text-secondary">Fase</dt><dd className="font-semibold">{sesi.round_terlewat} terlewat</dd></div>
                    <div><dt className="text-text-secondary">Temuan</dt><dd className="font-semibold">{sesi.temuan}</dd></div>
                    <div><dt className="text-text-secondary">Insiden</dt><dd className="font-semibold">{sesi.insiden}</dd></div>
                </dl>

                {sesi.rounds.length > 0 && (
                    <div className="mt-3 space-y-2">
                        {sesi.rounds.map((r) => (
                            <RoundRow key={r.id} round={r} />
                        ))}
                    </div>
                )}

                {sesi.pos_belum.length > 0 && (
                    <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
                        <select className="select-field" aria-label="Pilih pos yang belum dilayani" value={form.pos_id} onChange={(e) => setForm({ pos_id: e.target.value })}>
                            <option value="">Pilih pos</option>
                            {pos.filter((p) => p.unit_sekolah_id === sesi.unit && p.is_wajib).map((p) => (
                                <option key={p.id} value={p.id}>{p.nama}</option>
                            ))}
                        </select>
                        <input
                            className="input-field sm:col-span-2"
                            placeholder="Alasan (wajib)"
                            aria-label="Alasan tindakan pos"
                            value={form.alasan}
                            onChange={(e) => setForm({ alasan: e.target.value })}
                        />
                        <div className="flex gap-2">
                            <button type="button" onClick={() => aksi(sesi, 'layani')} className="btn-primary btn-sm flex-1">Layani</button>
                            <button type="button" onClick={() => aksi(sesi, 'nonaktif')} className="btn-secondary btn-sm flex-1">Nonaktif</button>
                        </div>
                    </div>
                )}
            </div>
        );
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Piket</h2>}>
            <Head title="Piket" />

            <div className="mx-auto w-full max-w-5xl space-y-4">
                {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{flash.error}</div>}

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <input
                        type="date"
                        aria-label="Tanggal piket"
                        className="input-field w-auto"
                        value={tanggal}
                        onChange={(e) => router.get(route('piket.index'), { tanggal: e.target.value }, { preserveState: true })}
                    />
                    {canManage && (
                        <Link href={route('piket.settings.index')} className="btn-secondary inline-flex items-center gap-1.5">
                            <Settings className="h-4 w-4" /> Pengaturan
                        </Link>
                    )}
                </div>

                <section className="space-y-2">
                    <h3 className="flex items-center gap-1.5 text-sm font-extrabold uppercase tracking-wide text-primary">
                        <ShieldAlert className="h-4 w-4" /> Perlu tindakan ({perlu.length})
                    </h3>
                    {perlu.length === 0 ? (
                        <div className="card p-4 text-sm text-text-secondary">Tidak ada sesi yang perlu ditindaklanjuti.</div>
                    ) : perlu.map((s) => <SesionCard key={s.id} sesi={s} />)}
                </section>

                <section className="space-y-2">
                    <h3 className="flex items-center gap-1.5 text-sm font-extrabold uppercase tracking-wide text-primary">
                        <Clock3 className="h-4 w-4" /> Semua sesi ({lain.length})
                    </h3>
                    {lain.map((s) => <SesionCard key={s.id} sesi={s} />)}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
