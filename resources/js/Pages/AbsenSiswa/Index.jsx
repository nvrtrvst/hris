import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FilterSheet from '@/Components/FilterSheet';
import Modal from '@/Components/Modal';
import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { id } from 'date-fns/locale/id';
import { ClipboardList, Search, History, Pencil, Eye, FileText, X } from 'lucide-react';

const STATUS_META = {
    telat: { label: 'Telat', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    izin: { label: 'Izin', badge: 'bg-sky-50 text-sky-700 border-sky-200' },
    sakit: { label: 'Sakit', badge: 'bg-purple-50 text-purple-700 border-purple-200' },
    alpa: { label: 'Alpa', badge: 'bg-rose-50 text-rose-700 border-rose-200' },
    hadir: { label: 'Hadir', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
};

const KPI = [
    { key: 'hadir', label: 'Hadir', cls: 'text-emerald-600' },
    { key: 'telat', label: 'Telat', cls: 'text-amber-600' },
    { key: 'izin', label: 'Izin', cls: 'text-sky-600' },
    { key: 'sakit', label: 'Sakit', cls: 'text-purple-600' },
    { key: 'alpa', label: 'Alpa', cls: 'text-rose-600' },
];

const fmtTanggal = (d) => {
    if (!d) return '-';

    try {
        return format(new Date(`${d}T00:00:00`), 'dd MMM yyyy', { locale: id });
    } catch {
        return d;
    }
};

export default function Index({ auth, sesi = [], belum_diabsen = [], integrasi_ok = true, filters = {}, units = [] }) {
    const [tanggal, setTanggal] = React.useState(filters.tanggal || format(new Date(), 'yyyy-MM-dd'));
    const [unitId, setUnitId] = React.useState(filters.unit_id || '');
    const [cari, setCari] = React.useState('');
    const [filterSheetOpen, setFilterSheetOpen] = React.useState(false);
    const [riwayatSesi, setRiwayatSesi] = React.useState(null);
    const [detailSesi, setDetailSesi] = React.useState(null);
    const [koreksiSesi, setKoreksiSesi] = React.useState(null);
    const [editSiswa, setEditSiswa] = React.useState([]);
    const [alasanEdit, setAlasanEdit] = React.useState('');

    const bukaKoreksi = (s) => {
        setKoreksiSesi(s);
        setEditSiswa((s.students ?? []).map((d) => ({ ...d })));
        setAlasanEdit('');
    };

    const ubahEdit = (nis, patch) => setEditSiswa((prev) => prev.map((d) => (d.nis === nis ? { ...d, ...patch } : d)));

    const simpanKoreksi = (e) => {
        e.preventDefault();
        router.post(route('absen-siswa.koreksi', koreksiSesi.id), {
            alasan: alasanEdit,
            siswa: editSiswa,
        }, {
            preserveScroll: true,
            onSuccess: () => setKoreksiSesi(null),
        });
    };
    const activeFilterCount = [unitId, cari].filter(Boolean).length;

    const apply = (overrides = {}) => {
        router.get(
            route('absen-siswa.index'),
            { tanggal: tanggal || undefined, unit_id: unitId || undefined, ...overrides },
            { preserveState: true, preserveScroll: true },
        );
    };

    const total = React.useMemo(() => sesi.reduce((acc, s) => {
        KPI.forEach(({ key }) => { acc[key] += Number(s.counts[key] || 0); });
        acc.siswa += Number(s.total || 0);

        return acc;
    }, { hadir: 0, telat: 0, izin: 0, sakit: 0, alpa: 0, siswa: 0 }), [sesi]);

    const persenHadir = total.siswa > 0 ? Math.round((total.hadir / total.siswa) * 100) : 0;
    const q = cari.trim().toLowerCase();
    const tampil = q
        ? sesi.filter((s) => s.class_label?.toLowerCase().includes(q) || s.pegawai?.toLowerCase().includes(q))
        : sesi;

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Absen Siswa</h2>}>
            <Head title="Absen Siswa" />
            <div className="bg-surface min-h-screen">
                <div className="mx-auto w-full space-y-6">
                    <FilterSheet
                        open={filterSheetOpen}
                        onOpenChange={setFilterSheetOpen}
                        activeCount={activeFilterCount}
                        onReset={() => { setUnitId(''); setCari(''); apply({ unit_id: undefined }); setFilterSheetOpen(false); }}
                    >
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-text-secondary">Tanggal</label>
                                <input type="date" value={tanggal} onChange={(e) => { setTanggal(e.target.value); apply({ tanggal: e.target.value }); }} className="select-field h-11 w-full text-xs" />
                            </div>
                            {units.length > 0 && (
                                <div>
                                    <label className="mb-1 block text-[11px] font-bold text-text-secondary">Unit</label>
                                    <select value={unitId} onChange={(e) => { setUnitId(e.target.value); apply({ unit_id: e.target.value || undefined }); }} className="select-field h-11 w-full text-xs">
                                        <option value="">Semua Unit</option>
                                        {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-text-secondary">Cari kelas / petugas</label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary" />
                                    <input value={cari} onChange={(e) => setCari(e.target.value)} placeholder="Mis. VII A" className="select-field h-11 w-full pl-9 text-xs" />
                                </div>
                            </div>
                        </div>
                    </FilterSheet>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                        <div className="rounded-xl border border-border bg-white p-3">
                            <p className="text-[11px] font-bold text-text-secondary">Kelas diabsen</p>
                            <p className="mt-1 text-xl font-bold tabular-nums text-text-primary">
                                {sesi.length}
                                <span className="text-sm font-semibold text-text-secondary"> / {sesi.length + belum_diabsen.length}</span>
                            </p>
                        </div>
                        <div className="rounded-xl border border-border bg-white p-3">
                            <p className="text-[11px] font-bold text-text-secondary">Siswa</p>
                            <p className="mt-1 text-xl font-bold tabular-nums text-text-primary">{total.siswa}</p>
                        </div>
                        {KPI.map((k) => (
                            <div key={k.key} className="rounded-xl border border-border bg-white p-3">
                                <p className="text-[11px] font-bold text-text-secondary">{k.label}</p>
                                <p className={`mt-1 text-xl font-bold tabular-nums ${k.cls}`}>{total[k.key]}</p>
                            </div>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs font-semibold text-text-secondary">
                            {fmtTanggal(tanggal)} · kehadiran {persenHadir}%
                        </p>
                        {auth.permissions?.includes('view_absen_siswa') && (
                            <Link
                                href={route('laporan.absen-siswa', {
                                    mode: 'period',
                                    start_date: tanggal,
                                    end_date: tanggal,
                                    unit_id: unitId || undefined,
                                })}
                                className="btn-secondary inline-flex min-h-11 items-center gap-1.5 px-3 text-xs"
                            >
                                <FileText className="h-4 w-4" /> Rekap & Export
                            </Link>
                        )}
                    </div>

                    {!integrasi_ok && (
                        <div role="status" className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                            Data kelas dari aplikasi keuangan tidak tersedia — daftar "belum diabsen" disembunyikan.
                        </div>
                    )}

                    {tampil.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border bg-white px-4 py-10 text-center">
                            <ClipboardList className="mx-auto h-8 w-8 text-text-secondary" />
                            <p className="mt-2 text-sm font-semibold text-text-secondary">Belum ada kelas yang diabsen pada tanggal ini.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                            {tampil.map((s) => (
                                <div key={s.id} className="rounded-xl border border-border bg-white p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-bold text-text-primary">{s.class_label}</p>
                                            <p className="text-xs text-text-secondary">{s.unit || '-'}</p>
                                        </div>
                                        <span className="shrink-0 text-xs font-semibold text-text-secondary">{s.done_at}</span>
                                    </div>

                                    <div className="mt-3 grid grid-cols-5 gap-1 text-center">
                                        {KPI.map((k) => (
                                            <div key={k.key} className="rounded-lg bg-surface py-1.5">
                                                <p className="text-[10px] font-bold text-text-secondary">{k.label}</p>
                                                <p className={`text-sm font-bold tabular-nums ${k.cls}`}>{s.counts[k.key]}</p>
                                            </div>
                                        ))}
                                    </div>

                                    {s.non_hadir.length > 0 ? (
                                        <ul className="mt-3 space-y-1">
                                            {s.non_hadir.map((d) => {
                                                const meta = STATUS_META[d.status] || STATUS_META.alpa;

                                                return (
                                                    <li key={d.nis} className="flex items-start justify-between gap-2 text-xs">
                                                        <span className="min-w-0">
                                                            <span className="font-semibold text-text-primary">{d.nama}</span>
                                                            {d.keterangan && <span className="text-text-secondary"> — {d.keterangan}</span>}
                                                        </span>
                                                        <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold ${meta.badge}`}>{meta.label}</span>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    ) : (
                                        <p className="mt-3 text-xs font-semibold text-emerald-600">Semua siswa hadir.</p>
                                    )}

                                    <div className="mt-3 flex items-center justify-between gap-2 border-t border-border pt-2">
                                        <p className="text-[11px] text-text-secondary">
                                            Diabsen oleh {s.pegawai || '-'} · {s.total} siswa
                                        </p>
                                        <button
                                            type="button"
                                            onClick={() => setDetailSesi(s)}
                                            className="inline-flex min-h-11 shrink-0 items-center gap-1 rounded-lg px-2 text-xs font-bold text-primary hover:bg-primary/5"
                                        >
                                            <Eye className="h-4 w-4" /> Rincian
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {belum_diabsen.length > 0 && (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-text-secondary">Belum diabsen ({belum_diabsen.length})</h3>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                {belum_diabsen.map((b) => (
                                    <div key={`${b.unit}-${b.class_id}`} className="rounded-xl border border-dashed border-border bg-white px-3 py-2.5">
                                        <p className="truncate text-sm font-bold text-text-primary">{b.class_label}</p>
                                        <p className="text-xs text-text-secondary">{b.unit}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <Modal show={detailSesi !== null} onClose={() => setDetailSesi(null)} maxWidth="2xl">
                {detailSesi && (
                    <div className="flex max-h-[90dvh] flex-col">
                        <div className="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
                            <div className="min-w-0">
                                <h3 className="truncate text-base font-bold text-text-primary">Rincian {detailSesi.class_label}</h3>
                                <p className="mt-1 text-xs text-text-secondary">{detailSesi.unit || '-'} · {detailSesi.done_at} · {detailSesi.pegawai || '-'}</p>
                            </div>
                            <button type="button" onClick={() => setDetailSesi(null)} aria-label="Tutup rincian" className="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-text-secondary hover:bg-surface">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="overflow-y-auto px-5 py-4">
                            <ul className="divide-y divide-border/60">
                                {(detailSesi.students ?? detailSesi.non_hadir ?? []).map((student) => {
                                    const meta = STATUS_META[student.status] || STATUS_META.alpa;

                                    return (
                                        <li key={student.nis} className="flex items-center justify-between gap-3 py-2 text-sm">
                                            <span className="min-w-0">
                                                <span className="block break-words font-semibold text-text-primary">{student.nama}</span>
                                                <span className="block font-mono text-xs text-text-secondary">{student.nis}</span>
                                                {student.keterangan && <span className="block text-xs text-text-secondary">{student.keterangan}</span>}
                                            </span>
                                            <span className={`shrink-0 rounded-full border px-2 py-0.5 text-xs font-bold ${meta.badge}`}>{meta.label}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                            {(detailSesi.students ?? detailSesi.non_hadir ?? []).length === 0 && (
                                <p className="py-6 text-center text-sm text-text-secondary">Belum ada data siswa.</p>
                            )}
                        </div>
                    </div>
                )}
            </Modal>

            <Modal show={riwayatSesi !== null} onClose={() => setRiwayatSesi(null)} maxWidth="lg">
                {riwayatSesi && (
                    <div className="p-5">
                        <h3 className="text-base font-bold text-text-primary">Riwayat {riwayatSesi.class_label}</h3>
                        <ul className="mt-3 space-y-3">
                            {(riwayatSesi.riwayat ?? []).map((r, i) => (
                                <li key={i} className="rounded-xl border border-border p-3 text-xs">
                                    <p className="font-semibold text-text-primary">{r.user} · {r.peran} · {r.waktu}</p>
                                    {r.alasan && <p className="mt-1 text-text-secondary">Alasan: {r.alasan}</p>}
                                    <p className="mt-1 text-text-secondary">
                                        H {r.ringkasan?.hadir ?? 0} · T {r.ringkasan?.telat ?? 0} · I {r.ringkasan?.izin ?? 0} · S {r.ringkasan?.sakit ?? 0} · A {r.ringkasan?.alpa ?? 0}
                                    </p>
                                </li>
                            ))}
                            {(riwayatSesi.riwayat ?? []).length === 0 && <li className="text-sm text-text-muted">Belum ada riwayat.</li>}
                        </ul>
                    </div>
                )}
            </Modal>

            <Modal show={koreksiSesi !== null} onClose={() => setKoreksiSesi(null)} maxWidth="2xl">
                {koreksiSesi && (
                    <form onSubmit={simpanKoreksi} className="p-5">
                        <h3 className="text-base font-bold text-text-primary">Koreksi {koreksiSesi.class_label}</h3>
                        <p className="mt-1 text-xs text-text-muted">Perubahan dicatat di riwayat dan wajib alasan.</p>

                        <div className="mt-3 max-h-[50dvh] space-y-2 overflow-y-auto pr-1">
                            {editSiswa.map((d) => (
                                <div key={d.nis} className="rounded-xl border border-border p-2">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="min-w-0 text-xs font-semibold text-text-primary">{d.nama} <span className="font-mono text-text-muted">{d.nis}</span></span>
                                        <select
                                            value={d.status}
                                            onChange={(e) => ubahEdit(d.nis, { status: e.target.value })}
                                            className="select-field h-11 w-32 text-xs"
                                        >
                                            <option value="hadir">Hadir</option>
                                            <option value="telat">Telat</option>
                                            <option value="izin">Izin</option>
                                            <option value="sakit">Sakit</option>
                                            <option value="alpa">Alpa</option>
                                        </select>
                                    </div>
                                    {['izin', 'sakit'].includes(d.status) && (
                                        <input
                                            value={d.keterangan ?? ''}
                                            onChange={(e) => ubahEdit(d.nis, { keterangan: e.target.value })}
                                            placeholder="Keterangan (wajib)"
                                            className="input-field mt-2 h-11 w-full text-xs"
                                        />
                                    )}
                                </div>
                            ))}
                        </div>

                        <label className="mt-3 block">
                            <span className="form-label text-xs">Alasan koreksi</span>
                            <textarea
                                value={alasanEdit}
                                onChange={(e) => setAlasanEdit(e.target.value)}
                                required
                                minLength={5}
                                rows={2}
                                className="input-field w-full"
                                placeholder="Contoh: status sakit dikoreksi setelah surat dokter masuk"
                            />
                        </label>

                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setKoreksiSesi(null)} className="btn-secondary">Batal</button>
                            <button type="submit" className="btn-primary">Simpan koreksi</button>
                        </div>
                    </form>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
