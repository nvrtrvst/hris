import React, { useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, Input, PageHeader, Select } from '@/Components/AdminUI';
import { Head, Link, router } from '@inertiajs/react';
import { Settings, ShieldAlert, Clock3 } from 'lucide-react';

export default function PiketMonitor({ auth, tanggal, sessions = [], pos = [], canManage = false }) {
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
                <Input
                    className="h-11 flex-1 text-xs"
                    placeholder="Alasan buka fase (wajib)"
                    aria-label={`Alasan buka fase ${round.putaran}`}
                    value={alasan}
                    onChange={(e) => setAlasan(e.target.value)}
                />
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={alasan.trim().length < 5}
                    onClick={() => router.post(route('piket.round.buka', round.id), { alasan }, { preserveScroll: true })}
                >
                    Buka fase
                </Button>
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
            <Card className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-sm font-bold text-text-primary">Sesi {sesi.jam}</p>
                        <p className="text-xs text-text-secondary">Status: {sesi.status}</p>
                        <ul className="mt-1 space-y-0.5 text-xs text-text-secondary">
                            {sesi.petugas.map((p) => (
                                <li key={`${p.nama}-${p.area}-${p.peran}`} className="flex flex-wrap items-center gap-x-1.5">
                                    <span className="font-semibold text-text-primary">{p.nama}</span>
                                    <span>({p.area || p.peran}, {p.selesai}/{p.wajib})</span>
                                    <span className={p.submitted ? 'font-semibold text-emerald-700' : 'font-semibold text-amber-700'}>
                                        {p.submitted ? `Terkirim${p.submitted_at ? ` ${p.submitted_at}` : ''}` : 'Belum kirim'}
                                    </span>
                                    {p.terlambat_lapor && (
                                        <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-700">
                                            Terlambat lapor
                                        </span>
                                    )}
                                    {p.status === 'tidak_hadir' && (
                                        <span className="rounded bg-rose-100 px-1.5 py-0.5 text-[11px] font-bold text-rose-700">
                                            Tidak hadir{p.status_alasan ? `: ${p.status_alasan}` : ''}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <span className={`rounded-full px-2 py-1 text-xs font-bold ${statusSesiCls}`}>
                        {statusSesi}
                    </span>
                </div>

                <dl className="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
                    <div><dt className="text-text-secondary">Pos belum</dt><dd className="font-semibold">{sesi.pos_belum.length ? sesi.pos_belum.join(', ') : '-'}</dd></div>
                    <div><dt className="text-text-secondary">Fase</dt><dd className="font-semibold">{sesi.round_terlewat} terlewat</dd></div>
                    <div><dt className="text-text-secondary">Temuan</dt><dd className="font-semibold">{sesi.temuan}</dd></div>
                    <div><dt className="text-text-secondary">Insiden belum verif</dt><dd className="font-semibold">{sesi.insiden}</dd></div>
                    <div><dt className="text-text-secondary">Perlu cek</dt><dd className="font-semibold">{sesi.perlu_cek}</dd></div>
                </dl>

                {sesi.perlu_alasan?.length > 0 && (
                    <ul className="mt-2 list-disc space-y-0.5 pl-4 text-xs text-amber-800">
                        {sesi.perlu_alasan.map((alasan) => <li key={alasan}>{alasan}</li>)}
                    </ul>
                )}

                {sesi.rounds.length > 0 && (
                    <div className="mt-3 space-y-2">
                        {sesi.rounds.map((r) => (
                            <RoundRow key={r.id} round={r} />
                        ))}
                    </div>
                )}

                {sesi.pos_belum.length > 0 && (
                    <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
                        <Select aria-label="Pilih pos yang belum dilayani" value={form.pos_id} onChange={(e) => setForm({ pos_id: e.target.value })}>
                            <option value="">Pilih pos</option>
                            {pos.filter((p) => p.unit_sekolah_id === sesi.unit && p.is_wajib).map((p) => (
                                <option key={p.id} value={p.id}>{p.nama}</option>
                            ))}
                        </Select>
                        <Input
                            className="sm:col-span-2"
                            placeholder="Alasan (wajib)"
                            aria-label="Alasan tindakan pos"
                            value={form.alasan}
                            onChange={(e) => setForm({ alasan: e.target.value })}
                        />
                        <div className="flex gap-2">
                            <Button type="button" size="sm" onClick={() => aksi(sesi, 'layani')} className="flex-1">Layani</Button>
                            <Button type="button" variant="secondary" size="sm" onClick={() => aksi(sesi, 'nonaktif')} className="flex-1">Nonaktif</Button>
                        </div>
                    </div>
                )}
            </Card>
        );
    };

    return (
        <AdminShell user={auth.user} header={<h2 className="page-title">Piket</h2>}>
            <Head title="Piket" />

            <div className="mx-auto w-full max-w-5xl space-y-5">
                <PageHeader
                    eyebrow="Piket"
                    title="Monitoring Piket"
                    description="Pantau sesi piket dan selesaikan sesi yang perlu tindakan."
                />

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Input
                        type="date"
                        aria-label="Tanggal piket"
                        className="w-auto"
                        value={tanggal}
                        onChange={(e) => router.get(route('piket.index'), { tanggal: e.target.value }, { preserveState: true })}
                    />
                    {canManage && (
                        <Link href={route('piket.settings.index')} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            <Settings className="h-4 w-4" /> Pengaturan
                        </Link>
                    )}
                </div>

                <section className="space-y-2">
                    <h3 className="flex items-center gap-1.5 text-sm font-extrabold uppercase tracking-wide text-primary">
                        <ShieldAlert className="h-4 w-4" /> Perlu tindakan ({perlu.length})
                    </h3>
                    {perlu.length === 0 ? (
                        <Card className="p-4 text-sm text-text-secondary">Tidak ada sesi yang perlu ditindaklanjuti.</Card>
                    ) : perlu.map((s) => <SesionCard key={s.id} sesi={s} />)}
                </section>

                <section className="space-y-2">
                    <h3 className="flex items-center gap-1.5 text-sm font-extrabold uppercase tracking-wide text-primary">
                        <Clock3 className="h-4 w-4" /> Semua sesi ({lain.length})
                    </h3>
                    {lain.length === 0 ? (
                        <Card className="p-4 text-sm text-text-secondary">Belum ada sesi piket lain pada tanggal ini.</Card>
                    ) : lain.map((s) => <SesionCard key={s.id} sesi={s} />)}
                </section>
            </div>
        </AdminShell>
    );
}
