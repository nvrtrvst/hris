import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FilterSheet from '@/Components/FilterSheet';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import { Head, router, usePage } from '@inertiajs/react';
import { format, subDays } from 'date-fns';
import { id } from 'date-fns/locale/id';
import {
    AlarmClock,
    CheckCircle2,
    Eye,
    Loader2,
    MapPin,
    RotateCcw,
    Search,
    XCircle,
    X,
} from 'lucide-react';

const STATUS_META = {
    pending: { label: 'Pending', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    disetujui: { label: 'Disetujui', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    ditolak: { label: 'Ditolak', badge: 'bg-rose-50 text-rose-700 border-rose-200' },
};

const LemburBadge = ({ status }) => {
    const meta = STATUS_META[status] || STATUS_META.pending;

    return (
        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border ${meta.badge}`}>
            {meta.label}
        </span>
    );
};

// Backend approveLembur/rejectLembur yang cek otorisasi; pimpinan read-only
// tidak ikut menampilkan tombol (sama dengan pola halaman Presensi).
const bolehApproveLembur = (auth) =>
    Boolean(auth?.permissions?.includes('view_presensi')) && !(auth?.roles || []).includes('pimpinan');

const toMinutes = (hms) => {
    if (!hms) return null;
    const [h, m] = String(hms).split(':').map(Number);
    return h * 60 + m;
};

const durasiLembur = (masuk, keluar) => {
    const a = toMinutes(masuk);
    const b = toMinutes(keluar);
    if (a === null || b === null || b <= a) return '—';

    return `${Math.floor((b - a) / 60)}j ${(b - a) % 60}m`;
};

const fmtTanggal = (d) => {
    if (!d) return '—';

    try {
        return format(new Date(d), 'dd MMM yyyy', { locale: id });
    } catch {
        return d;
    }
};

const ROW_CLASS = 'border-b border-border last:border-0 hover:bg-primary-50/40 transition-colors';

const TH_CLASS = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-text-secondary';
const TD_CLASS = 'px-4 py-3.5 whitespace-nowrap text-sm';

export default function Index({ auth, lemburs, counts = {}, filters = {}, units = [] }) {
    const [status, setStatus] = React.useState(filters.lembur_status || '');
    const [unitId, setUnitId] = React.useState(filters.unit_id || '');
    const [search, setSearch] = React.useState(filters.search || '');
    const [startDate, setStartDate] = React.useState(filters.start_date || format(subDays(new Date(), 1), 'yyyy-MM-dd'));
    const [endDate, setEndDate] = React.useState(filters.end_date || '');
    const [filterSheetOpen, setFilterSheetOpen] = React.useState(false);
    const activeFilterCount = [status, unitId, search, endDate].filter(Boolean).length;
    const [detail, setDetail] = React.useState({ show: false, loading: false, data: null, row: null });
    const flash = usePage().props.flash || {};

    const applyFilters = (overrides = {}) => {
        router.get(
            route('lembur.index'),
            {
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                lembur_status: status || undefined,
                unit_id: unitId || undefined,
                search: search || undefined,
                ...overrides,
            },
            { preserveState: true },
        );
    };

    const resetFilters = () => {
        setStatus('');
        setUnitId('');
        setSearch('');
        setStartDate(format(subDays(new Date(), 1), 'yyyy-MM-dd'));
        setEndDate('');
        router.get(route('lembur.index'), {}, { preserveState: true });
    };

    const openDetail = (p) => {
        setDetail({ show: true, loading: true, data: null, row: p });
        fetch(route('presensi.review', p.id))
            .then((r) => r.json())
            .then((res) => setDetail({ show: true, loading: false, data: res.presensi || null, row: p }))
            .catch(() => setDetail({ show: true, loading: false, data: null, row: p }));
    };

    const keputusan = (aksi) => {
        router.post(route(`presensi.${aksi}Lembur`, detail.row.id), {}, { preserveState: true });
        setDetail({ show: false, loading: false, data: null, row: null });
    };

    const allCount = Number(counts.pending || 0) + Number(counts.disetujui || 0) + Number(counts.ditolak || 0);
    const chips = [
        { key: '', label: 'Semua', value: allCount },
        { key: 'pending', label: 'Pending', value: Number(counts.pending || 0) },
        { key: 'disetujui', label: 'Disetujui', value: Number(counts.disetujui || 0) },
        { key: 'ditolak', label: 'Ditolak', value: Number(counts.ditolak || 0) },
    ];

    const bisaApprove = bolehApproveLembur(auth);
    const filterSelect = 'select-field text-xs h-11 w-full';

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Lembur</h2>}>
            <Head title="Lembur" />
            <div className="bg-surface min-h-screen">
                <div className="mx-auto w-full space-y-6">
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Chips status */}
                    <div className="flex flex-wrap gap-2">
                        {chips.map((c) => (
                            <button
                                key={c.label}
                                type="button"
                                onClick={() => {
                                    setStatus(c.key);
                                    applyFilters({ lembur_status: c.key || undefined, page: 1 });
                                }}
                                className={`inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-bold transition-colors ${
                                    status === c.key
                                        ? 'border-primary bg-primary text-white'
                                        : 'border-border bg-white text-text-secondary hover:border-primary/40 hover:text-primary'
                                }`}
                            >
                                {c.key && <AlarmClock className="h-3.5 w-3.5" />}
                                {c.label}
                                <span className={`rounded-full px-1.5 py-0.5 text-[10px] ${status === c.key ? 'bg-white/20' : 'bg-surface'}`}>
                                    {c.value}
                                </span>
                            </button>
                        ))}
                    </div>

                    {/* Filter */}
                    <FilterSheet
                        open={filterSheetOpen}
                        onOpenChange={setFilterSheetOpen}
                        activeCount={activeFilterCount}
                        onReset={() => { resetFilters(); setFilterSheetOpen(false); }}
                        onApply={() => applyFilters()}
                    >
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-text-secondary">Dari Tanggal</label>
                                <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className={filterSelect} />
                            </div>
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-text-secondary">Sampai Tanggal</label>
                                <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className={filterSelect} />
                            </div>
                            {units.length > 0 && (
                                <div>
                                    <label className="mb-1 block text-[11px] font-bold text-text-secondary">Unit</label>
                                    <select value={unitId} onChange={(e) => setUnitId(e.target.value)} className={filterSelect}>
                                        <option value="">Semua Unit</option>
                                        {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-text-secondary">Cari Nama</label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                        placeholder="Nama pegawai..."
                                        className={`${filterSelect} pl-9`}
                                    />
                                </div>
                            </div>

                        </div>
                    </FilterSheet>

                    {/* Tabel */}
                    <div className="overflow-hidden rounded-xl border border-border bg-white">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface">
                                    <tr>
                                        <th className={TH_CLASS}>Tanggal</th>
                                        <th className={TH_CLASS}>Pegawai</th>
                                        <th className="hidden lg:table-cell">Unit</th>
                                        <th className={TH_CLASS}>Jam</th>
                                        <th className={TH_CLASS}>Durasi</th>
                                        <th className={TH_CLASS}>Status</th>
                                        <th className="hidden sm:table-cell">Detail</th>
                                        <th className={TH_CLASS}>&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border bg-white">
                                    {lemburs.data.length === 0 && (
                                        <tr>
                                            <td colSpan={8} className="px-4 py-10 text-center text-sm text-text-secondary">
                                                Tidak ada pengajuan lembur pada filter ini.
                                            </td>
                                        </tr>
                                    )}
                                    {lemburs.data.map((p) => {
                                        return (
                                            <tr key={p.id} className={ROW_CLASS}>
                                                <td className={TD_CLASS}>{fmtTanggal(p.tanggal)}</td>
                                                <td className={`${TD_CLASS} font-semibold`}>{p.pegawai?.nama_lengkap || '—'}</td>
                                                <td className={`${TD_CLASS} hidden lg:table-cell text-text-secondary`}>{p.unit_sekolah?.nama || '—'}</td>
                                                <td className={`${TD_CLASS} tabular-nums`}>
                                                    {p.jam_masuk ? p.jam_masuk.slice(0, 5) : '—'}
                                                    {' → '}
                                                    {p.jam_keluar ? p.jam_keluar.slice(0, 5) : '—'}
                                                </td>
                                                <td className={TD_CLASS}>{durasiLembur(p.jam_masuk, p.jam_keluar)}</td>
                                                <td className={TD_CLASS}><LemburBadge status={p.lembur_status} /></td>
                                                <td className={`${TD_CLASS} hidden sm:table-cell`}>
                                                    <button type="button" onClick={() => openDetail(p)} className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-[11px] font-bold text-text-secondary transition-colors hover:border-primary/40 hover:text-primary">
                                                        <Eye className="h-3.5 w-3.5" /> Buka
                                                    </button>
                                                </td>
                                                <td className={TD_CLASS}>
                                                    {p.lembur_status === 'pending' && bisaApprove && (
                                                        <div className="flex gap-1">
                                                            <button
                                                                type="button"
                                                                onClick={() => router.post(route('presensi.approveLembur', p.id), {}, { preserveState: true })}
                                                                className="inline-flex items-center gap-1 rounded-md bg-emerald-500 px-2.5 py-1.5 text-[11px] font-bold text-white transition-colors hover:bg-emerald-600"
                                                            >
                                                                <CheckCircle2 className="h-3.5 w-3.5" /> Setuju
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => router.post(route('presensi.rejectLembur', p.id), {}, { preserveState: true })}
                                                                className="inline-flex items-center gap-1 rounded-md bg-rose-500 px-2.5 py-1.5 text-[11px] font-bold text-white transition-colors hover:bg-rose-600"
                                                            >
                                                                <XCircle className="h-3.5 w-3.5" /> Tolak
                                                            </button>
                                                        </div>
                                                    )}
                                                    {p.lembur_status !== 'pending' && <span className="text-xs text-text-secondary">—</span>}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <div className="px-4 py-3">
                            <Pagination links={lemburs.links} pagination={lemburs} data={{ start_date: startDate, end_date: endDate, lembur_status: status, unit_id: unitId, search }} />
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal detail anti-spoof / review */}
            <Modal show={detail.show} onClose={() => setDetail({ show: false, loading: false, data: null })} maxWidth="3xl">
                <div className="rounded-xl bg-white p-6">
                    <div className="mb-4 flex items-start justify-between">
                        <div>
                            <h3 className="text-lg font-bold">Detail Lembur</h3>
                            {detail.data && (
                                <p className="mt-0.5 text-sm text-text-secondary">
                                    {detail.data.pegawai_nama} · {fmtTanggal(detail.data.tanggal)} · {format(new Date(detail.data.tanggal), 'EEEE', { locale: id })}
                                </p>
                            )}
                        </div>
                        <button type="button" onClick={() => setDetail({ show: false, loading: false, data: null })} aria-label="Tutup" className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface">
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {detail.loading && (
                        <div className="flex items-center justify-center py-10 text-text-secondary">
                            <Loader2 className="h-6 w-6 animate-spin" />
                        </div>
                    )}

                    {!detail.loading && !detail.data && (
                        <p className="py-10 text-center text-sm text-text-secondary">Gagal memuat detail.</p>
                    )}

                    {!detail.loading && detail.data && (
                        <div className="space-y-4">
                            {!!(detail.data.lokasi_perlu_review || detail.data.posisi_mencurigakan || detail.data.motion_suspect) && (
                                <div className="flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">
                                    <MapPin className="h-4 w-4" /> Lokasi presensi ini perlu review (flag anti-spoof).
                                </div>
                            )}

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5 text-sm">
                                    <p><span className="text-text-secondary">Status presensi:</span> <span className="font-semibold">{detail.data.status}</span></p>
                                    <p><span className="text-text-secondary">Foto masuk:</span> {detail.data.foto_masuk_status || '—'}</p>
                                    {detail.data.akurasi_masuk !== null && <p><span className="text-text-secondary">Akurasi GPS:</span> {detail.data.akurasi_masuk} m</p>}
                                    {detail.data.kecepatan_masuk !== null && <p><span className="text-text-secondary">Kecepatan:</span> {detail.data.kecepatan_masuk}</p>}
                                    {detail.data.latitude_masuk !== null && (
                                        <p className="break-all"><span className="text-text-secondary">Koordinat masuk:</span> {detail.data.latitude_masuk}, {detail.data.longitude_masuk}</p>
                                    )}
                                    {detail.data.captured_at && <p className="text-xs text-text-secondary">Captured: {detail.data.captured_at}</p>}
                                    {detail.data.keterangan && <p className="break-words"><span className="text-text-secondary">Keterangan:</span> {detail.data.keterangan}</p>}
                                </div>
                                <div>
                                    {detail.data.foto_masuk_url ? (
                                        <img src={detail.data.foto_masuk_url} alt="Foto absen masuk" className="max-h-72 w-full rounded-lg object-contain ring-1 ring-border" />
                                    ) : (
                                        <div className="flex h-48 items-center justify-center rounded-lg bg-surface text-xs text-text-secondary">Foto masuk tidak tersedia</div>
                                    )}
                                </div>
                            </div>

                            {detail.row?.lembur_status === 'pending' && bisaApprove && (
                                <div className="flex justify-end gap-2 border-t border-border pt-4">
                                    <button type="button" onClick={() => keputusan('approve')} className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-emerald-600">
                                        <CheckCircle2 className="h-4 w-4" /> Setujui Lembur
                                    </button>
                                    <button type="button" onClick={() => keputusan('reject')} className="inline-flex items-center gap-1.5 rounded-lg bg-rose-500 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-rose-600">
                                        <XCircle className="h-4 w-4" /> Tolak Lembur
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
