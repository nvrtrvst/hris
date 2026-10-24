import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
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
        <span className="inline-flex gap-1.5">
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

function LaporanDetail({ lap, label }) {
    const items = lap.items || {};
    const gerbang = items.gerbang;
    const cek = items.cek_absen;
    const kk = items.kelas_kosong;
    const buku = items.buku;
    const insiden = items.insiden;

    return (
        <div className="space-y-3 px-4 py-3 text-sm">
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
                                    <p className="mt-0.5 text-xs text-text-muted">
                                        Cross-check: {e.validasi_catatan}{' '}
                                        <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold ${e.validasi === 'cocok' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'}`}>
                                            {e.validasi === 'cocok' ? 'Cocok' : 'Perlu cek'}
                                        </span>
                                    </p>
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
            {lap.submitted_at && <p className="text-xs text-text-muted">Dikirim {lap.submitted_at}.</p>}
        </div>
    );
}

export default function Piket({ auth, filters, units = [], laporan = [], belum = [], label = {} }) {
    const { flash = {} } = usePage().props;
    const [form, setForm] = useState({
        start_date: filters.start_date,
        end_date: filters.end_date,
        unit_sekolah_id: filters.unit_sekolah_id || '',
        status: filters.status || 'semua',
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
        const params = { start_date: form.start_date, end_date: form.end_date, status: form.status };
        if (form.unit_sekolah_id) params.unit_sekolah_id = form.unit_sekolah_id;
        router.get(route('laporan.piket'), params);
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Laporan Piket</h2>}>
            <Head title="Laporan Piket" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}

                    <form onSubmit={tampilkan} className="page-card p-5">
                        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
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
                            <div className="flex items-end">
                                <button type="submit" className="btn-primary w-full">Tampilkan</button>
                            </div>
                        </div>
                        <p className="form-hint mt-2">Maksimal 31 hari per tampil.</p>
                    </form>

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
                                            <th>Progress</th>
                                            <th>Status</th>
                                            <th>Flag</th>
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
                                                    <td className="whitespace-nowrap">{lap.done}/{lap.total}</td>
                                                    <td>
                                                        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase ${STATUS_BADGE[lap.status] || STATUS_BADGE.draft}`}>
                                                            {lap.status === 'submitted' ? 'Terkirim' : 'Belum lengkap'}
                                                        </span>
                                                        {lap.unlocked_at && <span className="ml-1 inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase text-sky-700">Telat dibuka</span>}
                                                    </td>
                                                    <td>{lap.perlu_cek > 0 ? <span className="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700">Perlu cek {lap.perlu_cek}</span> : <span className="text-text-muted">-</span>}</td>
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
