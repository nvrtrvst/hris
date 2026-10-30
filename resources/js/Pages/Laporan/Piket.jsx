import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { CheckCircle2, ChevronDown, X } from 'lucide-react';

const STATUS_BADGE = {
    submitted: 'bg-emerald-50 text-emerald-700',
    draft: 'bg-amber-50 text-amber-700',
};

const IZIN_BADGE = {
    pending: 'bg-amber-50 text-amber-700',
    disetujui: 'bg-emerald-50 text-emerald-700',
    ditolak: 'bg-rose-50 text-rose-700',
};

const IZIN_LABEL = {
    pending: 'Izin pending',
    disetujui: 'Izin disetujui',
    ditolak: 'Izin ditolak',
};

const ABSEN_STATUS_LABEL = {
    sakit: 'Sakit',
    izin: 'Izin',
    cuti: 'Cuti',
    alpa: 'Alpa',
    hadir: 'Hadir',
    lainnya: 'Lainnya',
};

const EMPTY_IZIN = {
    jenis_izin: 'izin',
    tanggal_mulai: '',
    tanggal_selesai: '',
    alasan: '',
    bukti_foto: null,
    bukti_nama: '',
    dengan_surat_dokter: false,
};

const qualityText = (lap) => {
    if ((lap.pos_belum?.length ?? 0) > 0) return 'Pos belum';
    if ((lap.rounds ?? []).some((r) => !r.selesai_at)) return 'Fase lewat';
    if (lap.perlu_cek > 0) return 'Perlu cek';

    return '-';
};

function IzinBadge({ izin }) {
    if (!izin) return null;

    return (
        <Link
            href={route('pengajuan-izin.index')}
            className={`inline-flex rounded-full px-2 py-1 text-xs font-bold ${IZIN_BADGE[izin.status] || 'bg-slate-100 text-slate-700'}`}
        >
            {IZIN_LABEL[izin.status] || 'Pengajuan izin'}
        </Link>
    );
}

function LaporanMobileCard({ lap, label, openId, setOpenId, canConvert, canUnlock, onConvert, onUnlock }) {
    const open = openId === lap.id;
    const progress = lap.progress ?? {};

    return (
        <div className="p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-mono text-sm font-bold text-text-primary">{lap.tanggal}</p>
                    <p className="truncate text-sm font-semibold text-text-primary">{lap.pegawai?.nama_lengkap || '-'}</p>
                    <p className="truncate text-xs text-text-secondary">{lap.unit || '-'}{lap.area ? ` / ${lap.area}` : ''}</p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    <span className={`inline-flex rounded-full px-2 py-1 text-xs font-bold ${STATUS_BADGE[lap.status] || STATUS_BADGE.draft}`}>
                        {lap.status === 'submitted' ? 'Terkirim' : 'Belum lengkap'}
                    </span>
                    {lap.unlocked_at && <span className="text-xs font-bold text-sky-700">Telat dibuka</span>}
                </div>
            </div>

            <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-text-secondary">
                <span>Item {progress.item_done ?? lap.done}/{progress.item_total ?? lap.total}</span>
                <span>Pos {progress.pos_done ?? 0}/{progress.pos_total ?? 0}</span>
                <span>Fase {progress.round_done ?? 0}/{progress.round_total ?? 0}</span>
                <span>{qualityText(lap)}</span>
            </div>

            <div className="mt-3 flex flex-col gap-2">
                <button
                    type="button"
                    onClick={() => setOpenId(open ? null : lap.id)}
                    aria-expanded={open}
                    className="btn-secondary flex min-h-11 w-full items-center justify-between gap-2"
                >
                    {open ? 'Tutup detail' : 'Detail'}
                    <ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
                </button>
                {canUnlock && lap.status !== 'submitted' && !lap.unlocked_at && (
                    <button
                        type="button"
                        onClick={() => onUnlock(lap)}
                        className="min-h-11 w-full rounded-lg bg-white px-3 text-sm font-bold text-amber-700 ring-1 ring-amber-200 hover:bg-amber-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40"
                    >
                        Buka jalur telat
                    </button>
                )}
            </div>

            {open && (
                <div className="mt-3 border-t border-border pt-3">
                    <LaporanDetail lap={lap} label={label} canConvert={canConvert} onConvert={onConvert} />
                </div>
            )}
        </div>
    );
}

function EntryValidasi({ lapId, entry, idx }) {
    if (entry.verified_at) {
        return (
            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-bold ${entry.verified === 'setujui' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                {entry.verified === 'setujui' ? 'Disetujui' : 'Ditolak'}
            </span>
        );
    }
    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            {entry.ada_amanat && (
                <span className={`text-xs font-semibold ${entry.tersampaikan ? 'text-emerald-700' : 'text-amber-700'}`}>
                    {entry.tersampaikan ? 'Amanat tersampaikan' : 'Amanat belum tersampaikan'}
                </span>
            )}
            <button
                type="button"
                onClick={() => router.post(route('laporan-piket.validasi', lapId), { entry_index: idx, aksi: 'setujui' }, { preserveScroll: true })}
                className="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
            >
                Setujui
            </button>
            <button
                type="button"
                onClick={() => router.post(route('laporan-piket.validasi', lapId), { entry_index: idx, aksi: 'tolak' }, { preserveScroll: true })}
                className="rounded-lg bg-white px-2.5 py-1 text-xs font-bold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/40"
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
                            {cek.entries.map((e, i) => {
                                const konflik = !e.verified_at && (e.konflik_presensi?.perlu_cek || e.validasi === 'perlu_cek');
                                const catatan = e.konflik_presensi?.catatan || e.validasi_catatan;

                                return (
                                    <li key={i}>
                                        {e.nama_guru} ({e.created_at?.substring(0, 5)}): {e.status_absen ? `${ABSEN_STATUS_LABEL[e.status_absen] || e.status_absen} - ` : ''}{e.keterangan}
                                        {konflik && <span className="ml-1 font-bold text-amber-700">Perlu dicek</span>}
                                        {konflik && catatan && <span className="block text-xs text-amber-700">{catatan}</span>}
                                    </li>
                                );
                            })}
                        </ul>
                    ) : <p className="text-text-secondary">Cek awal tercatat.</p>}
                </div>
            )}
            {kk && (
                <div>
                    <p className="font-bold">{label.kelas_kosong}:</p>
                    {kk.nihil ? <p className="text-text-secondary">Nihil.</p> : (
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
                    {insiden.nihil ? <p className="text-text-secondary">Nihil.</p> : (
                        <ul className="mt-1 list-disc space-y-0.5 pl-5 text-text-secondary">
                            {(insiden.entries ?? []).map((e, i) => <li key={i}>{e.jenis} ({e.created_at?.substring(0, 5)}): {e.tindakan}</li>)}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}

function LaporanDetail({ lap, label, canConvert = false, onConvert = () => {} }) {
    if (lap.legacy) {
        return (
            <div className="space-y-3 px-4 py-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Arsip laporan lama</p>
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
                                {pos.is_wajib && <span className="text-xs font-bold uppercase text-rose-600">wajib</span>}
                                <span className="text-text-secondary">
                                    {pos.tercatat
                                        ? `tercatat ${pos.done_at}${pos.nihil ? ' · nihil' : ''}${pos.jumlah != null ? ` · ${pos.jumlah} siswa` : ''}${pos.catatan ? ` · ${pos.catatan}` : ''}`
                                        : 'belum tercatat'}
                                </span>
                                {pos.sumber === 'override' && <span className="text-xs font-semibold uppercase text-sky-700">override {pos.override_by || ''}</span>}
                                {pos.sumber === 'nonaktif' && <span className="text-xs font-semibold uppercase text-amber-700">dinonaktifkan</span>}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {lap.rounds?.length > 0 && (
                <section>
                    <h4 className="text-xs font-bold uppercase tracking-wide text-text-secondary">Fase</h4>
                    <ul className="mt-1.5 space-y-1">
                        {lap.rounds.map((r) => (
                            <li key={r.id} className="flex flex-wrap items-center gap-x-2">
                                <span className="font-semibold text-text-primary">Fase {r.putaran}</span>
                                {r.area && <span className="text-text-secondary">{r.area}</span>}
                                <span className="text-xs text-text-secondary">{r.jendela_mulai}–{r.jendela_selesai}</span>
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
                                <ul className="mt-1 space-y-2 text-text-secondary">
                                    {cek.entries.map((e, i) => {
                                        const konflik = !e.verified_at && (e.konflik_presensi?.perlu_cek || e.validasi === 'perlu_cek');
                                        const catatan = e.konflik_presensi?.catatan || e.validasi_catatan;

                                        return (
                                            <li key={i} className="break-words">
                                                <p>
                                                    {e.nama_guru} ({e.created_at?.substring(0, 5)}): {e.status_absen ? `${ABSEN_STATUS_LABEL[e.status_absen] || e.status_absen} - ` : ''}{e.keterangan}
                                                    {konflik && (
                                                        <span className="ml-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700">
                                                            Perlu dicek
                                                        </span>
                                                    )}
                                                </p>
                                                {konflik && catatan && <p className="mt-0.5 text-xs text-amber-700">{catatan}</p>}
                                                <div className="mt-1 flex flex-wrap items-center gap-2">
                                                    {e.pengajuan_izin ? (
                                                        <IzinBadge izin={e.pengajuan_izin} />
                                                    ) : konflik && e.guru_pegawai_id ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => router.post(route('laporan-piket.validasi', lap.id), { kode: 'cek_absen', entry_index: i, aksi: 'tolak' }, { preserveScroll: true })}
                                                            className="btn-secondary min-h-11 px-3 text-xs"
                                                        >
                                                            Tandai salah catat
                                                        </button>
                                                    ) : canConvert && lap.status === 'submitted' && e.guru_pegawai_id ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => onConvert(lap, i, e)}
                                                            className="btn-secondary min-h-11 w-full px-3 text-xs sm:w-auto"
                                                        >
                                                            Buat pengajuan izin
                                                        </button>
                                                    ) : ! e.guru_pegawai_id ? (
                                                        <span className="text-xs text-text-secondary">Guru manual, belum terhubung ke data pegawai.</span>
                                                    ) : null}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : <p className="text-xs text-text-secondary">Cek awal tercatat.</p>}
                        </div>
                    )}
                    {kk && (
                        <div>
                            <p className="font-semibold text-text-primary">{label.kelas_kosong}</p>
                            {kk.nihil ? <p className="text-xs text-text-secondary">Nihil.</p> : (
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
                            {insiden.nihil ? <p className="text-xs text-text-secondary">Nihil.</p> : (
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
                    <Link href={route('laporan.absen-siswa', { mode: 'daily', tanggal: lap.tanggal, unit_id: lap.unit_id })} className="mt-2 inline-flex text-xs font-bold text-primary underline-offset-2 hover:underline">
                        Lihat rekap absen
                    </Link>
                </section>
            )}

            <div className="flex flex-wrap items-center gap-3 border-t border-border pt-2 text-xs text-text-secondary">
                {lap.submitted_at && <span>Dikirim {lap.submitted_at}</span>}
                <span>Item {progress.item_done}/{progress.item_total} · Pos {progress.pos_done}/{progress.pos_total} · Fase {progress.round_done}/{progress.round_total}</span>
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
    const [izinEntry, setIzinEntry] = useState(null);
    const [izinForm, setIzinForm] = useState(EMPTY_IZIN);
    const [izinError, setIzinError] = useState('');
    const [izinSending, setIzinSending] = useState(false);

    const canConvert = auth?.permissions?.includes('create_izin_from_piket');
    const canUnlock = auth?.permissions?.includes('view_presensi');

    const openIzin = (lap, index, entry) => {
        setIzinError('');
        setIzinForm({
            ...EMPTY_IZIN,
            tanggal_mulai: lap.tanggal,
            tanggal_selesai: lap.tanggal,
            alasan: entry.keterangan ? `Dari laporan piket: ${entry.keterangan}` : '',
        });
        setIzinEntry({ lap, index, entry });
    };

    const closeIzin = () => {
        if (izinSending) return;
        setIzinEntry(null);
        setIzinError('');
        setIzinForm(EMPTY_IZIN);
    };

    const ubahBukti = (event) => {
        const file = event.target.files?.[0];
        if (! file) return;

        if (file.size > 5 * 1024 * 1024) {
            setIzinError('Ukuran bukti maksimal 5 MB.');
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            setIzinForm((prev) => ({ ...prev, bukti_foto: reader.result, bukti_nama: file.name }));
            setIzinError('');
        };
        reader.readAsDataURL(file);
    };

    const simpanIzin = (event) => {
        event.preventDefault();
        if (! izinEntry) return;

        if (izinForm.alasan.trim().length < 10) {
            setIzinError('Alasan minimal 10 karakter.');
            return;
        }
        if (izinForm.jenis_izin === 'sakit' && ! izinForm.bukti_foto) {
            setIzinError('Bukti surat dokter wajib diunggah untuk sakit.');
            return;
        }

        setIzinError('');
        setIzinSending(true);
        router.post(route('laporan-piket.buat-izin', izinEntry.lap.id), {
            entry_index: izinEntry.index,
            jenis_izin: izinForm.jenis_izin,
            tanggal_mulai: izinForm.tanggal_mulai,
            tanggal_selesai: izinForm.tanggal_selesai,
            alasan: izinForm.alasan,
            bukti_foto: izinForm.bukti_foto,
            dengan_surat_dokter: izinForm.dengan_surat_dokter,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIzinEntry(null);
                setIzinForm(EMPTY_IZIN);
            },
            onError: (errors) => setIzinError(Object.values(errors || {}).flat()[0] || 'Gagal membuat pengajuan izin.'),
            onFinish: () => setIzinSending(false),
        });
    };

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

                                    <option value="fase_terlewat">Fase terlewat</option>

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


                                                    {(lap.rounds ?? []).some((r) => !r.selesai_at) && 'Fase terlewat '}


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
                                <p className="mt-0.5 text-xs font-semibold text-text-secondary">
                                    Terkirim {laporan.filter((lap) => lap.status === 'submitted').length}
                                    {' · '}Belum lengkap {laporan.filter((lap) => lap.status !== 'submitted').length}
                                    {laporan.some((lap) => lap.perlu_cek > 0) && <span className="text-danger">{' · '}Perlu cek {laporan.filter((lap) => lap.perlu_cek > 0).length}</span>}
                                </p>
                            )}
                        </div>
                        {laporan.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-text-secondary">Tidak ada laporan pada periode ini. Ubah filter atau periode.</p>
                        ) : (
                            <>
                                <div className="divide-y divide-border lg:hidden">
                                    {laporan.map((lap) => (
                                        <LaporanMobileCard
                                            key={lap.id}
                                            lap={lap}
                                            label={label}
                                            openId={openId}
                                            setOpenId={setOpenId}
                                            canConvert={canConvert}
                                            canUnlock={canUnlock}
                                            onConvert={openIzin}
                                            onUnlock={setKonfirmasi}
                                        />
                                    ))}
                                </div>
                                <div className="hidden table-wrap lg:block">
                                <table className="table-base">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Petugas</th>
                                            <th>Unit</th>
                                            <th>Item</th>

                                            <th>Pos</th>

                                            <th>Fase</th>

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

                                                    <td className="whitespace-nowrap tabular-nums">{lap.progress ? `${lap.progress.pos_done}/${lap.progress.pos_total}` : '-'}</td>

                                                    <td className="whitespace-nowrap tabular-nums">{lap.progress ? `${lap.progress.round_done}/${lap.progress.round_total}` : '-'}</td>
                                                    <td>
                                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold ${STATUS_BADGE[lap.status] || STATUS_BADGE.draft}`}>
                                                            {lap.status === 'submitted' ? 'Terkirim' : 'Belum lengkap'}
                                                        </span>
                                                        {lap.unlocked_at && <span className="ml-1 inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-xs font-bold text-sky-700">Telat dibuka</span>}
                                                    </td>
                                                    <td className="text-xs text-text-secondary">

                                                        {(lap.pos_belum?.length ?? 0) > 0 ? 'Pos belum' : (lap.rounds ?? []).some((r) => !r.selesai_at) ? 'Fase lewat' : lap.perlu_cek > 0 ? 'Perlu cek' : '-'}

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
                                                                    className="rounded-lg bg-white px-2 py-1 text-xs font-bold text-amber-700 ring-1 ring-amber-200 hover:bg-amber-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40 disabled:opacity-50"
                                                                >
                                                                    {unlockingId === lap.id ? '...' : 'Buka jalur telat'}
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                                {openId === lap.id && (
                                                    <tr>
                                                        <td colSpan={9} className="bg-surface/50"><LaporanDetail lap={lap} label={label} canConvert={canConvert} onConvert={openIzin} /></td>
                                                    </tr>
                                                )}
                                            </React.Fragment>
                                        ))}
                                    </tbody>
                                </table>
                                </div>
                            </>
                        )}
                    </div>

                    {belum.length > 0 && (
                        <div className="page-card card-table">
                            <div className="px-5 py-4 border-b border-border">
                                <h3 className="section-title">Belum melapor ({belum.length})</h3>
                            </div>
                            <div className="divide-y divide-border lg:hidden">
                                {belum.map((b, i) => (
                                    <div key={`${b.tanggal}-${b.pegawai?.id}-${i}`} className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="font-mono text-sm font-bold text-text-primary">{b.tanggal}</p>
                                                <p className="truncate text-sm font-semibold text-text-primary">{b.pegawai?.nama_lengkap || '-'}</p>
                                                <p className="truncate text-xs text-text-secondary">{b.unit || '-'}</p>
                                            </div>
                                            <p className="shrink-0 font-mono text-xs font-bold text-text-secondary">{b.jam}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="hidden table-wrap lg:block">
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

                    <p className="text-xs text-text-secondary flex items-center gap-1.5">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Checklist wajib: jaga gerbang, cek guru absen, kelas kosong, buku piket, insiden.
                    </p>
                </div>
            </div>

            <Modal show={izinEntry !== null} onClose={closeIzin} maxWidth="2xl" closeable={!izinSending}>
                {izinEntry && (
                    <form onSubmit={simpanIzin} className="flex max-h-[90dvh] flex-col">
                        <div className="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
                            <div className="min-w-0">
                                <h3 className="text-base font-bold text-text-primary">Buat pengajuan izin</h3>
                                <p className="mt-0.5 break-words text-sm text-text-secondary">
                                    {izinEntry.entry.nama_guru} - {izinEntry.lap.tanggal}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={closeIzin}
                                disabled={izinSending}
                                aria-label="Tutup form pengajuan izin"
                                className="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-text-secondary hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 disabled:opacity-50"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="space-y-4 overflow-y-auto px-5 py-4">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <label htmlFor="izin-jenis" className="form-label">Jenis izin</label>
                                    <select
                                        id="izin-jenis"
                                        value={izinForm.jenis_izin}
                                        onChange={(e) => setIzinForm({ ...izinForm, jenis_izin: e.target.value })}
                                        className="select-field min-h-11 text-base sm:text-sm"
                                    >
                                        <option value="izin">Izin</option>
                                        <option value="sakit">Sakit</option>
                                        <option value="cuti">Cuti</option>
                                    </select>
                                </div>
                                <div>
                                    <label htmlFor="izin-mulai" className="form-label">Tanggal mulai</label>
                                    <input
                                        id="izin-mulai"
                                        type="date"
                                        value={izinForm.tanggal_mulai}
                                        onChange={(e) => setIzinForm({ ...izinForm, tanggal_mulai: e.target.value })}
                                        className="input-field min-h-11 text-base sm:text-sm"
                                        required
                                    />
                                </div>
                                <div>
                                    <label htmlFor="izin-selesai" className="form-label">Tanggal selesai</label>
                                    <input
                                        id="izin-selesai"
                                        type="date"
                                        value={izinForm.tanggal_selesai}
                                        onChange={(e) => setIzinForm({ ...izinForm, tanggal_selesai: e.target.value })}
                                        className="input-field min-h-11 text-base sm:text-sm"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <label htmlFor="izin-alasan" className="form-label">Alasan</label>
                                <textarea
                                    id="izin-alasan"
                                    rows={3}
                                    value={izinForm.alasan}
                                    onChange={(e) => setIzinForm({ ...izinForm, alasan: e.target.value })}
                                    className="input-field min-h-24 text-base sm:text-sm"
                                    maxLength={500}
                                    required
                                />
                            </div>

                            <div>
                                <label htmlFor="izin-bukti" className="form-label">
                                    Bukti surat {izinForm.jenis_izin === 'sakit' ? '(wajib)' : '(opsional)'}
                                </label>
                                <input
                                    id="izin-bukti"
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    onChange={ubahBukti}
                                    className="block min-h-11 w-full rounded-lg border border-border bg-surface p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary"
                                />
                                {izinForm.bukti_nama && <p className="mt-1 break-all text-xs text-text-secondary">{izinForm.bukti_nama}</p>}
                                {izinForm.bukti_foto && (
                                    <img
                                        src={izinForm.bukti_foto}
                                        alt="Pratinjau bukti surat"
                                        className="mt-2 max-h-48 w-full rounded-lg object-contain ring-1 ring-border"
                                    />
                                )}
                            </div>

                            {izinForm.jenis_izin === 'sakit' && (
                                <label className="flex min-h-11 cursor-pointer items-center gap-2 text-sm font-medium text-text-primary">
                                    <input
                                        type="checkbox"
                                        checked={izinForm.dengan_surat_dokter}
                                        onChange={(e) => setIzinForm({ ...izinForm, dengan_surat_dokter: e.target.checked })}
                                        className="h-5 w-5 rounded border-border text-primary focus:ring-primary"
                                    />
                                    Surat dokter sudah diterima
                                </label>
                            )}

                            {izinError && <p role="alert" className="rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{izinError}</p>}
                        </div>

                        <div className="flex flex-col-reverse gap-2 border-t border-border px-5 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:flex-row sm:justify-end sm:pb-4">
                            <button type="button" onClick={closeIzin} disabled={izinSending} className="btn-secondary min-h-11 w-full sm:w-auto">
                                Batal
                            </button>
                            <button type="submit" disabled={izinSending} className="btn-primary min-h-11 w-full sm:w-auto">
                                {izinSending ? 'Menyimpan...' : 'Simpan pengajuan'}
                            </button>
                        </div>
                    </form>
                )}
            </Modal>

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
