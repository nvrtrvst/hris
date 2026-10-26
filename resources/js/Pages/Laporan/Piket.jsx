import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { CheckCircle2, ChevronDown } from 'lucide-react';

const STATUS_BADGE = {
    submitted: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    draft: 'bg-amber-50 text-amber-700 border-amber-200',
};

function EntryValidasi({ lapId, entry, idx }) {
    if (entry.verified_at) {
        return (
            <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold ${entry.verified === 'setujui' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'}`}>
                {entry.verified === 'setujui' ? 'Disetujui' : 'Ditolak'}
            </span>
        );
    }
    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            {entry.ada_amanat && (
                <span className={`text-[10px] font-semibold ${entry.tersampaikan ? 'text-emerald-700' : 'text-amber-700'}`}>
                    {entry.tersampaikan ? 'Amanat tersampaikan' : 'Amanat belum tersampaikan'}
                </span>
            )}
            <button
                type="button"
                onClick={() => router.post(route('laporan-piket.validasi', lapId), { entry_index: idx, aksi: 'setujui' }, { preserveScroll: true })}
                className="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
            >
                Setujui
            </button>
            <button
                type="button"
                onClick={() => router.post(route('laporan-piket.validasi', lapId), { entry_index: idx, aksi: 'tolak' }, { preserveScroll: true })}
                className="rounded-lg bg-white px-2.5 py-1 text-[11px] font-bold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/40"
            >
                Tolak
            </button>
        </span>
    );
}

function LegacyDetail({ lap, label }) {
    const items = lap.items || {};
    const gerbang = items.gerbang;
    const cek = items.cek_absen;
    const kk = items.kelas_kosong;
    const buku = items.buku;
    const insiden = items.insiden;

    return (
        <div className="space-y-3 text-sm">
            {gerbang?.done_at && (
                <p><span className="font-bold">{label.gerbang}:</span> {gerbang.done_at.substring(0, 5)} ({gerbang.terlambat_count ?? 0} terlambat)</p>
            )}
            {cek && (
                <div>
                    <p className="font-bold">{label.cek_absen}{cek.done_at ? ` (${cek.done_at.substring(0, 5)})` : ''}:</p>
                    {(cek.entries ?? []).length > 0 ? (
                        <ul className="mt-1 list-disc space-y-0.5 pl-5 text-text-secondary">
                            {cek.entries.map((e, i) => <li key={i}>{e.nama_guru} ({e.created_at?.substring(0, 5)}): {e.keterangan}</li>)}
                        </ul>
                    ) : <p className="text-text-muted">Cek awal tercatat.</p>}
                </div>
            )}
            {kk && (
                <div>
                    <p className="font-bold">{label.kelas_kosong}:</p>
                    {kk.nihil ? <p className="text-text-muted">Nihil.</p> : (
                        <ul className="mt-1 space-y-1.5">
                            {(kk.entries ?? []).map((e, i) => (
                                <li key={i} className="rounded-lg bg-surface px-2.5 py-2 ring-1 ring-border">
                                    <p><span className="font-bold">{e.kelas}</span>{e.mapel ? `, ${e.mapel}` : ''} ({e.guru_nama})</p>
                                    {e.ada_amanat && <p className="mt-0.5 text-text-secondary">Amanat: {e.detail_amanat}{e.tersampaikan ? ' (tersampaikan)' : ' (belum tersampaikan)'}</p>}
                                    <div className="mt-1.5"><EntryValidasi lapId={lap.id} entry={e} idx={i} /></div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
            {buku?.done_at && <p><span className="font-bold">{label.buku}:</span> {buku.done_at.substring(0, 5)}</p>}
            {insiden && (
                <div>
                    <p className="font-bold">{label.insiden}:</p>
                    {insiden.nihil ? <p className="text-text-muted">Nihil.</p> : (
                        <ul className="mt-1 list-disc space-y-0.5 pl-5 text-text-secondary">
                            {(insiden.entries ?? []).map((e, i) => <li key={i}>{e.jenis} ({e.created_at?.substring(0, 5)}): {e.tindakan}</li>)}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}

function LaporanDetail({ lap, label }) {
    if (lap.legacy) {
        return (
            <div className="space-y-3 px-4 py-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">Arsip laporan lama</p>
                <LegacyDetail lap={lap} label={label} />
            </div>
        );
    }

    const progress = lap.progress ?? {};
    const items = lap.items || {};
    const kk = items.kelas_kosong;
    const cek = items.cek_absen;
    const buku = items.buku;
    const insiden = items.insiden;

    return (
        <div className="space-y-4 px-4 py-4 text-sm">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-secondary">
                <span className="font-bold text-text-primary">{lap.sesi?.jam || '-'}</span>
                <span>{lap.sesi?.status || '-'}</span>
                {lap.area && <span>{lap.area}</span>}
                {lap.peran && <span className="uppercase">{lap.peran}</span>}
                {lap.sesi?.petugas?.length > 0 && (
                    <span>Petugas: {lap.sesi.petugas.map((t) => `${t.nama}${t.area ? ` (${t.area})` : ''}`).join(' · ')}</span>
                )}
            </div>

            {lap.pos?.length > 0 && (
                <section>
                    <h4 className="text-xs font-bold uppercase tracking-wide text-text-secondary">Pos</h4>
                    <ul className="mt-1.5 space-y-1">
                        {lap.pos.map((pos) => (
                            <li key={pos.id} className="flex flex-wrap items-center gap-x-2">
                                <span className="font-semibold text-text-primary">{pos.nama}</span>
                                {pos.is_wajib && <span className="text-[10px] font-bold uppercase text-rose-600">wajib</span>}
                                <span className="text-text-secondary">
                                    {pos.tercatat
                                        ? `tercatat ${pos.done_at}${pos.nihil ? ' · nihil' : ''}${pos.jumlah != null ? ` · ${pos.jumlah} siswa` : ''}${pos.catatan ? ` · ${pos.catatan}` : ''}`
                                        : 'belum tercatat'}
                                </span>
                                {pos.sumber === 'override' && <span className="text-[10px] font-semibold uppercase text-sky-700">override {pos.override_by || ''}</span>}
                                {pos.sumber === 'nonaktif' && <span className="text-[10px] font-semibold uppercase text-amber-700">dinonaktifkan</span>}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {lap.rounds?.length > 0 && (
                <section>
                    <h4 className="text-xs font-bold uppercase tracking-wide text-text-secondary">Ronde</h4>
                    <ul className="mt-1.5 space-y-1">
                        {lap.rounds.map((r) => (
                            <li key={r.id} className="flex flex-wrap items-center gap-x-2">
                                <span className="font-semibold text-text-primary">Ronde {r.putaran}</span>
                                {r.area && <span className="text-text-secondary">{r.area}</span>}
                                <span className="text-xs text-text-muted">{r.jendela_mulai}–{r.jendela_selesai}</span>
                                <span className={r.selesai_at ? 'text-emerald-700' : 'text-rose-600'}>
                                    {r.selesai_at ? `${r.status} ${r.selesai_at}` : 'belum selesai'}
                                </span>
                                {r.alasan && <span className="text-xs text-text-secondary">· {r.alasan}</span>}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section>
                <h4 className="text-xs font-bold uppercase tracking-wide text-text-secondary">Checklist</h4>
                <div className="mt-1.5 space-y-2">
                    {cek && (
                        <div>
                            <p className="font-semibold text-text-primary">{label.cek_absen}{cek.done_at ? ` (${cek.done_at.substring(0, 5)})` : ''}</p>
                            {(cek.entries ?? []).length > 0 ? (
                                <ul className="mt-0.5 list-disc pl-5 text-text-secondary">
                                    {cek.entries.map((e, i) => <li key={i}>{e.nama_guru} ({e.created_at?.substring(0, 5)}): {e.keterangan}</li>)}
                                </ul>
                            ) : <p className="text-xs text-text-muted">Cek awal tercatat.</p>}
                        </div>
                    )}
                    {kk && (
                        <div>
                            <p className="font-semibold text-text-primary">{label.kelas_kosong}</p>
                            {kk.nihil ? <p className="text-xs text-text-muted">Nihil.</p> : (
                                <ul className="mt-1 space-y-1.5">
                                    {(kk.entries ?? []).map((e, i) => (
                                        <li key={i} className="rounded-lg bg-surface px-2.5 py-2 ring-1 ring-border">
                                            <p><span className="font-bold">{e.kelas}</span>{e.mapel ? `, ${e.mapel}` : ''} ({e.guru_nama})</p>
                                            {e.ada_amanat && <p className="mt-0.5 text-text-secondary">Amanat: {e.detail_amanat}{e.tersampaikan ? ' (tersampaikan)' : ' (belum tersampaikan)'}</p>}
                                            <div className="mt-1.5"><EntryValidasi lapId={lap.id} entry={e} idx={i} /></div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                    {buku?.done_at && <p className="font-semibold text-text-primary">{label.buku}: <span className="font-normal text-text-secondary">{buku.done_at.substring(0, 5)}</span></p>}
                    {insiden && (
                        <div>
                            <p className="font-semibold text-text-primary">{label.insiden}</p>
                            {insiden.nihil ? <p className="text-xs text-text-muted">Nihil.</p> : (
                                <ul className="mt-0.5 list-disc pl-5 text-text-secondary">
                                    {(insiden.entries ?? []).map((e, i) => <li key={i}>{e.jenis} ({e.created_at?.substring(0, 5)}): {e.tindakan}</li>)}
                                </ul>
                            )}
                        </div>
                    )}
                </div>
            </section>

            {lap.absen?.length > 0 && (
                <section>
                    <h4 className="text-xs font-bold uppercase tracking-wide text-text-secondary">Absen siswa</h4>
                    <ul className="mt-1.5 space-y-2">
                        {lap.absen.map((a) => (
                            <li key={a.class_label}>
                                <p className="font-semibold text-text-primary">{a.class_label}</p>
                                <p className="text-xs text-text-secondary">
                                    H {a.counts.hadir} · T {a.counts.telat} · I {a.counts.izin} · S {a.counts.sakit} · A {a.counts.alpa} · {a.total} siswa
                                </p>
                                {a.non_hadir.length > 0 && (
                                    <p className="mt-0.5 text-xs text-text-secondary">
                                        Non-hadir: {a.non_hadir.map((d) => `${d.nama} (${d.status})`).join(', ')}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                    <Link href={route('absen-siswa.index', { tanggal: lap.tanggal, unit_id: lap.unit_id })} className="mt-2 inline-flex text-xs font-bold text-primary underline-offset-2 hover:underline">
                        Lihat rekap absen
                    </Link>
                </section>
            )}

            <div className="flex flex-wrap items-center gap-3 border-t border-border pt-2 text-xs text-text-secondary">
                {lap.submitted_at && <span>Dikirim {lap.submitted_at}</span>}
                <span>Item {progress.item_done}/{progress.item_total} · Pos {progress.pos_done}/{progress.pos_total} · Ronde {progress.round_done}/{progress.round_total}</span>
                {lap.block_reason && <span className="font-semibold text-rose-700">{lap.block_reason}</span>}
                <Link href={route('piket.index', { tanggal: lap.tanggal })} className="ml-auto font-bold text-primary underline-offset-2 hover:underline">
                    Ke Monitoring
                </Link>
            </div>
        </div>
    );
}

export default function Piket({ auth, filters, units = [], areas = [], laporan = [], belum = [], label = {} }) {
    const { flash = {} } = usePage().props;
    const [form, setForm] = useState({
        start_date: filters.start_date,
        end_date: filters.end_date,
        unit_sekolah_id: filters.unit_sekolah_id || '',
        status: filters.status || 'semua',
        area_id: filters.area_id || '',
        kualitas: filters.kualitas || 'semua',
    });
    const [openId, setOpenId] = useState(null);
    const [unlockingId, setUnlockingId] = useState(null);
    const [konfirmasi, setKonfirmasi] = useState(null);

    const bukaJalur = (lap) => {
        setUnlockingId(lap.id);
        router.post(route('laporan.piket.unlock', lap.id), {}, {
            preserveScroll: true,
            onSuccess: () => setKonfirmasi(null),
            onFinish: () => setUnlockingId(null),
        });
    };

    const tampilkan = (e) => {
        e.preventDefault();
        const params = { start_date: form.start_date, end_date: form.end_date, status: form.status, kualitas: form.kualitas };
        if (form.unit_sekolah_id) params.unit_sekolah_id = form.unit_sekolah_id;
        if (form.area_id) params.area_id = form.area_id;
        router.get(route('laporan.piket'), params);
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Laporan Piket</h2>}>
            <Head title="Laporan Piket" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}

                    <form onSubmit={tampilkan} className="page-card p-5">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label className="form-label" htmlFor="f-start">Dari tanggal</label>
                                <input id="f-start" type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} className="input-field" required />
                            </div>
                            <div>
                                <label className="form-label" htmlFor="f-end">Sampai tanggal</label>
                                <input id="f-end" type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} className="input-field" required />
                            </div>
                            {units.length > 1 && (
                                <div>
                                    <label className="form-label" htmlFor="f-unit">Unit</label>
                                    <select id="f-unit" value={form.unit_sekolah_id} onChange={(e) => setForm({ ...form, unit_sekolah_id: e.target.value })} className="select-field">
                                        <option value="">Semua unit</option>
                                        {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="form-label" htmlFor="f-status">Status</label>
                                <select id="f-status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className="select-field">
                                    <option value="semua">Semua</option>
                                    <option value="draft">Belum lengkap</option>
                                    <option value="submitted">Terkirim</option>
                                    <option value="perlu_cek">Perlu cek</option>
                                    <option value="belum">Belum melapor</option>
                                </select>
                            </div>
                            {areas.length > 0 && (

                                <div>

                                    <label className="form-label" htmlFor="f-area">Area</label>

                                    <select id="f-area" value={form.area_id} onChange={(e) => setForm({ ...form, area_id: e.target.value })} className="select-field">

                                        <option value="">Semua area</option>

                                        {areas.map((a) => <option key={a.id} value={a.id}>{a.nama}</option>)}

                                    </select>

                                </div>

                            )}

                            <div>

                                <label className="form-label" htmlFor="f-kualitas">Kualitas</label>

                                <select id="f-kualitas" value={form.kualitas} onChange={(e) => setForm({ ...form, kualitas: e.target.value })} className="select-field">

                                    <option value="semua">Semua</option>

                                    <option value="pos_belum">Pos belum</option>

                                    <option value="ronde_terlewat">Ronde terlewat</option>

                                    <option value="perlu_cek">Perlu cek</option>

                                </select>

                            </div>

                            <div className="flex items-end">

                                <button type="submit" className="btn-primary w-full">Tampilkan</button>

                            </div>
                        </div>
                        <p className="form-hint mt-2">Maksimal 31 hari per tampil.</p>
                    </form>

                    {(() => {


                        const perlu = laporan.filter((lap) => (lap.pos_belum?.length ?? 0) > 0 || (lap.rounds ?? []).some((r) => !r.selesai_at) || lap.perlu_cek > 0);


                        if (perlu.length === 0) return null;



                        return (


                            <div className="page-card p-4">


                                <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Perlu perhatian ({perlu.length})</h3>


                                <ul className="mt-2 divide-y divide-border/60">


                                    {perlu.map((lap) => (


                                        <li key={lap.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-xs">


                                            <span className="min-w-0">


                                                <b>{lap.tanggal}</b> · {lap.pegawai?.nama_lengkap} · {lap.unit}{lap.area ? ` / ${lap.area}` : ''}


                                                <span className="ml-2 text-text-secondary">


                                                    {(lap.pos_belum?.length ?? 0) > 0 && `Pos belum: ${lap.pos_belum.join(', ')} `}


                                                    {(lap.rounds ?? []).some((r) => !r.selesai_at) && 'Ronde terlewat '}


                                                    {lap.perlu_cek > 0 && 'Perlu cek'}


                                                </span>


                                            </span>


                                            <button type="button" onClick={() => setOpenId(lap.id)} className="btn-secondary btn-sm shrink-0">Detail</button>


                                        </li>


                                    ))}


                                </ul>


                            </div>


                        );


                    })()}



                    <div className="page-card card-table">
                        <div className="px-5 py-4 border-b border-border">
                            <h3 className="section-title">Laporan masuk ({laporan.length})</h3>
                            {laporan.length > 0 && (
                                <p className="mt-0.5 text-xs font-semibold text-text-muted">
                                    Terkirim {laporan.filter((lap) => lap.status === 'submitted').length}
                                    {' · '}Belum lengkap {laporan.filter((lap) => lap.status !== 'submitted').length}
                                    {laporan.some((lap) => lap.perlu_cek > 0) && <span className="text-danger">{' · '}Perlu cek {laporan.filter((lap) => lap.perlu_cek > 0).length}</span>}
                                </p>
                            )}
                        </div>
                        {laporan.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-text-muted">Tidak ada laporan pada periode ini. Ubah filter atau periode.</p>
                        ) : (
                            <div className="table-wrap">
                                <table className="table-base">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Petugas</th>
                                            <th>Unit</th>
                                            <th>Item</th>

                                            <th>Pos</th>

                                            <th>Ronde</th>

                                            <th>Status</th>

                                            <th>Kualitas</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {laporan.map((lap) => (
                                            <React.Fragment key={lap.id}>
                                                <tr>
                                                    <td className="whitespace-nowrap font-mono">{lap.tanggal}</td>
                                                    <td className="font-semibold">{lap.pegawai?.nama_lengkap || '-'}</td>
                                                    <td>{lap.unit || '-'}</td>
                                                    <td className="whitespace-nowrap tabular-nums">{lap.progress?.item_done ?? lap.done}/{lap.progress?.item_total ?? lap.total}</td>

                                                    <td className="whitespace-nowrap tabular-nums">{lap.progress ? `${lap.progress.pos_done}/${lap.progress.pos_total}` : '—'}</td>

                                                    <td className="whitespace-nowrap tabular-nums">{lap.progress ? `${lap.progress.round_done}/${lap.progress.round_total}` : '—'}</td>
                                                    <td>
                                                        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase ${STATUS_BADGE[lap.status] || STATUS_BADGE.draft}`}>
                                                            {lap.status === 'submitted' ? 'Terkirim' : 'Belum lengkap'}
                                                        </span>
                                                        {lap.unlocked_at && <span className="ml-1 inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase text-sky-700">Telat dibuka</span>}
                                                    </td>
                                                    <td className="text-xs text-text-secondary">

                                                        {(lap.pos_belum?.length ?? 0) > 0 ? 'Pos belum' : (lap.rounds ?? []).some((r) => !r.selesai_at) ? 'Ronde lewat' : lap.perlu_cek > 0 ? 'Perlu cek' : '—'}

                                                    </td>
                                                    <td>
                                                        <div className="flex items-center gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={() => setOpenId(openId === lap.id ? null : lap.id)}
                                                                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpenId(openId === lap.id ? null : lap.id); } }}
                                                                aria-expanded={openId === lap.id}
                                                                className="inline-flex items-center gap-1 text-xs font-bold text-primary underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                                                            >
                                                                Detail <ChevronDown className={`h-3.5 w-3.5 transition-transform ${openId === lap.id ? 'rotate-180' : ''}`} />
                                                            </button>
                                                            {lap.status !== 'submitted' && auth?.permissions?.includes('view_presensi') && !lap.unlocked_at && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setKonfirmasi(lap)}
                                                                    disabled={unlockingId === lap.id}
                                                                    aria-label={`Buka jalur telat laporan piket ${lap.pegawai?.nama_lengkap || lap.tanggal}`}
                                                                    className="rounded-lg bg-white px-2 py-1 text-[11px] font-bold text-amber-700 ring-1 ring-amber-200 hover:bg-amber-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40 disabled:opacity-50"
                                                                >
                                                                    {unlockingId === lap.id ? '...' : 'Buka jalur telat'}
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                                {openId === lap.id && (
                                                    <tr>
                                                        <td colSpan={7} className="bg-surface/50"><LaporanDetail lap={lap} label={label} /></td>
                                                    </tr>
                                                )}
                                            </React.Fragment>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {belum.length > 0 && (
                        <div className="page-card card-table">
                            <div className="px-5 py-4 border-b border-border">
                                <h3 className="section-title">Belum melapor ({belum.length})</h3>
                            </div>
                            <div className="table-wrap">
                                <table className="table-base">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Petugas</th>
                                            <th>Unit</th>
                                            <th>Jam Piket</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {belum.map((b, i) => (
                                            <tr key={`${b.tanggal}-${b.pegawai?.id}-${i}`}>
                                                <td className="whitespace-nowrap font-mono">{b.tanggal}</td>
                                                <td className="font-semibold">{b.pegawai?.nama_lengkap || '-'}</td>
                                                <td>{b.unit || '-'}</td>
                                                <td className="whitespace-nowrap font-mono">{b.jam}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    <p className="text-xs text-text-muted flex items-center gap-1.5">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Checklist wajib: jaga gerbang, cek guru absen, kelas kosong, buku piket, insiden.
                    </p>
                </div>
            </div>

            <Modal show={konfirmasi !== null} onClose={() => setKonfirmasi(null)} maxWidth="lg">
                {konfirmasi && (
                    <div className="p-6">
                        <div className="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-light px-4 py-3">
                            <div>
                                <p className="font-bold text-text-primary">Buka jalur telat?</p>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Laporan piket <strong>{konfirmasi.pegawai?.nama_lengkap || '-'}</strong> tanggal <strong>{konfirmasi.tanggal}</strong> akan menerima item seksi Pagi meski jendela pagi sudah lewat.
                                </p>
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setKonfirmasi(null)} className="btn-secondary">Batal</button>
                            <button type="button" disabled={unlockingId === konfirmasi.id} onClick={() => bukaJalur(konfirmasi)} className="btn-primary disabled:opacity-50">
                                {unlockingId === konfirmasi.id ? 'Membuka…' : 'Buka jalur telat'}
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
