import React from 'react';
import { subscribeRouter } from '@/Utils/routerEvents';
import useNowEveryMinute from '@/Utils/useNowEveryMinute';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { id } from 'date-fns/locale/id';
import { avatarTone, initials } from '@/Utils/avatar';
import {
    AlertTriangle,
    AlarmClock,
    CalendarDays,
    CalendarOff,
    CheckCircle2,
    Clock3,
    FileText,
    Filter,
    HeartPulse,
    History,
    Loader2,
    MapPin,
    RotateCcw,
    Search,
    ShieldAlert,
    UserX,
    Users,
    X,
} from 'lucide-react';

const STATUS_META = {
    hadir: { label: 'Hadir', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    telat: { label: 'Terlambat', badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
    sakit: { label: 'Sakit', badge: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-500' },
    izin: { label: 'Izin', badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    cuti: { label: 'Cuti', badge: 'bg-cyan-50 text-cyan-700 border-cyan-200', dot: 'bg-cyan-500' },
    alpa: { label: 'Alpa', badge: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' },
};

const StatusBadge = ({ status }) => {
    const meta = STATUS_META[status] || { label: status, badge: 'bg-gray-50 text-gray-700 border-gray-200', dot: 'bg-gray-400' };

    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border ${meta.badge}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${meta.dot}`} />
            {meta.label}
        </span>
    );
};

const LemburBadge = ({ status }) => {
    const map = {
        pending: 'bg-amber-50 text-amber-700 border-amber-200',
        disetujui: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        ditolak: 'bg-rose-50 text-rose-700 border-rose-200',
    };

    return (
        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border ${map[status] || map.pending}`}>
            <AlarmClock className="h-3 w-3" /> Lembur {status || 'Pending'}
        </span>
    );
};

const StatCard = ({ label, value, Icon, iconBg, iconCls }) => (
    <div className="stat-card group hover:shadow-card-hover transition-shadow">
        <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${iconBg} transition-transform group-hover:scale-105`}>
            <Icon className={`h-5 w-5 ${iconCls}`} />
        </div>
        <div className="min-w-0">
            <p className="text-2xl font-extrabold leading-none text-primary tabular-nums">{value}</p>
            <p className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
        </div>
    </div>
);

const parseDate = (s) => {
    const [y, m, d] = String(s).split('-').map(Number);

    return new Date(y, (m || 1) - 1, d || 1);
};

const kerjaDurasi = (masuk, keluar) => {
    if (!masuk || !keluar) return null;
    const toMin = (x) => {
        const p = String(x).split(':');

        return (+p[0]) * 60 + (+p[1] || 0);
    };
    const m = toMin(keluar) - toMin(masuk);

    return m > 0 ? `${Math.floor(m / 60)}j${String(m % 60).padStart(2, '0')}m` : null;
};

const toMinutes = (hms) => {
    if (!hms) return 0;
    const p = String(hms).split(':');

    return (+p[0]) * 60 + (+p[1] || 0);
};

// Indikator status mengajar — konsisten dengan Dashboard mobile:
// "Mengajar" (berlangsung) selama jam mengajar belum habis, "Selesai"
// setelah jam_selesai lewat. Hanya untuk record yang sudah ada jam_masuk;
// record tanpa absen cukup terbaca dari kolom Status.
// Prop `now` (Date) membuat badge live-update tiap menit via useNowEveryMinute.
const JadwalStatusBadge = ({ p, now }) => {
    if (!p?.jadwal || !p.jam_masuk) return null;
    const current = now || new Date();
    const nowMinutes = current.getHours() * 60 + current.getMinutes();
    // Badge "Mengajar" hanya bermakna untuk record hari ini — record hari
    // sebelumnya/lama selalu sudah selesai, apa pun jam sekarang.
    const todayStr = `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}-${String(current.getDate()).padStart(2, '0')}`;
    const isHariIni = String(p.tanggal || '').startsWith(todayStr);
    const sudahLewat = !isHariIni || toMinutes(p.jadwal.jam_selesai) <= nowMinutes;

    if (sudahLewat) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                Selesai
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700">
            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500" aria-hidden="true" />
            Mengajar
        </span>
    );
};

const fmtDateInput = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export default function Index({ auth, presensis, pegawai, filters = {}, units, stats }) {
    const isAdmin = auth.permissions?.includes('view_presensi');
    const { flash = {} } = usePage().props;
    const [processing, setProcessing] = React.useState(false);
    // Jam "sekarang" yang live — dipakai badge Mengajar/Selesai biar berganti otomatis.
    const now = useNowEveryMinute();

    React.useEffect(() => subscribeRouter({
        start: () => setProcessing(true),
        finish: () => setProcessing(false),
    }), []);

    const [startDate, setStartDate] = React.useState(filters?.start_date || '');
    const [endDate, setEndDate] = React.useState(filters?.end_date || '');
    const [unitId, setUnitId] = React.useState(filters?.unit_id || '');
    const [lemburFilter, setLemburFilter] = React.useState(filters?.lembur_filter || '');
    const [lokasiFilter, setLokasiFilter] = React.useState(filters?.lokasi_filter || '');
    const [suspiciousFilter, setSuspiciousFilter] = React.useState(filters?.suspicious_filter || '');
    const [statusFilter, setStatusFilter] = React.useState(filters?.status_filter || '');
    const [jadwalFilter, setJadwalFilter] = React.useState(filters?.jadwal_filter || '');
    const [jenisFilter, setJenisFilter] = React.useState(filters?.jenis_filter || '');
    const [search, setSearch] = React.useState(filters?.search || '');

    const [confirmStatus, setConfirmStatus] = React.useState(null);
    const [persentaseBayar, setPersentaseBayar] = React.useState(100);
    const [auditModal, setAuditModal] = React.useState({ show: false, loading: false, data: [], presensi: null });
    const [auditPegawai, setAuditPegawai] = React.useState('');
    const [reviewModal, setReviewModal] = React.useState({ show: false, loading: false, data: null });

    const hasFilter = Boolean(search || statusFilter || jadwalFilter || jenisFilter || unitId || lemburFilter || lokasiFilter || suspiciousFilter || startDate || endDate);

    const buildParams = React.useCallback((overrides = {}) => ({
        start_date: startDate,
        end_date: endDate,
        unit_id: unitId,
        lembur_filter: lemburFilter,
        lokasi_filter: lokasiFilter,
        suspicious_filter: suspiciousFilter,
        status_filter: statusFilter,
        jadwal_filter: jadwalFilter,
        jenis_filter: jenisFilter,
        search,
        ...overrides,
    }), [startDate, endDate, unitId, lemburFilter, lokasiFilter, suspiciousFilter, statusFilter, jadwalFilter, jenisFilter, search]);

    const applyFilters = React.useCallback((overrides = {}) => {
        router.get(route('presensi.index'), buildParams(overrides), { preserveState: true, preserveScroll: true });
    }, [buildParams]);

    // Search otomatis dengan debounce
    React.useEffect(() => {
        if (search === (filters?.search || '')) return;
        const timer = setTimeout(() => applyFilters({ search }), 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    React.useEffect(() => {
        if (confirmStatus) setPersentaseBayar(confirmStatus.persentase_bayar_jam ?? 100);
    }, [confirmStatus]);

    const applyPreset = (mode) => {
        const end = new Date();
        const start = new Date(end);
        if (mode === 'bulan') {
            start.setDate(1);
        } else if (mode === '7hari') {
            start.setDate(end.getDate() - 6);
        } else {
            start.setDate(end.getDate());
        }
        applyFilters({ start_date: fmtDateInput(start), end_date: fmtDateInput(end) });
    };

    const resetFilters = () => {
        setStartDate('');
        setEndDate('');
        setUnitId('');
        setLemburFilter('');
        setLokasiFilter('');
        setSuspiciousFilter('');
        setStatusFilter('');
        setJadwalFilter('');
        setJenisFilter('');
        setSearch('');
        router.get(route('presensi.index'), {}, { preserveState: true });
    };

    const openAudit = (p) => {
        setAuditPegawai(p.pegawai?.nama_lengkap || '');
        setAuditModal({ show: true, loading: true, data: [], presensi: null });
        fetch(route('presensi.audit', p.id))
            .then((r) => r.json())
            .then((res) => setAuditModal({ show: true, loading: false, data: res.audits || [], presensi: res.presensi || null }))
            .catch(() => setAuditModal({ show: true, loading: false, data: [], presensi: null }));
    };

    const openReview = (p) => {
        setReviewModal({ show: true, loading: true, data: null });
        fetch(route('presensi.review', p.id))
            .then((r) => r.json())
            .then((res) => setReviewModal({ show: true, loading: false, data: res.presensi || null }))
            .catch(() => setReviewModal({ show: true, loading: false, data: null }));
    };

    const s = stats || { total: 0, hadir: 0, telat: 0, sakit: 0, izin: 0, cuti: 0, alpa: 0, lembur_pending: 0, perlu_review: 0 };

    const statsCards = [
        { label: 'Total', value: s.total, Icon: Users, iconBg: 'bg-primary/10', iconCls: 'text-primary' },
        { label: 'Hadir', value: s.hadir, Icon: CheckCircle2, iconBg: 'bg-emerald-100', iconCls: 'text-emerald-600' },
        { label: 'Terlambat', value: s.telat, Icon: Clock3, iconBg: 'bg-amber-100', iconCls: 'text-amber-600' },
        { label: 'Sakit', value: s.sakit, Icon: HeartPulse, iconBg: 'bg-purple-100', iconCls: 'text-purple-600' },
        { label: 'Izin', value: s.izin, Icon: FileText, iconBg: 'bg-blue-100', iconCls: 'text-blue-600' },
        { label: 'Cuti', value: s.cuti, Icon: CalendarOff, iconBg: 'bg-cyan-100', iconCls: 'text-cyan-600' },
        { label: 'Alpa', value: s.alpa, Icon: UserX, iconBg: 'bg-rose-100', iconCls: 'text-rose-600' },
        { label: 'Lembur Pending', value: s.lembur_pending, Icon: AlarmClock, iconBg: 'bg-orange-100', iconCls: 'text-orange-600' },
        { label: 'Perlu Review', value: s.perlu_review, Icon: ShieldAlert, iconBg: 'bg-red-100', iconCls: 'text-red-600' },
    ];

    const filterSelect = 'select-field text-xs h-9 w-full md:w-auto md:min-w-[150px]';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">{isAdmin ? 'Manajemen Presensi' : 'Riwayat Presensi'}</h2>}
        >
            <Head title="Presensi" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {/* Flash */}
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between gap-3">
                        <div>
                            <h3 className="text-xl font-bold text-primary">
                                {isAdmin ? 'Rekap Kehadiran Pegawai' : `Riwayat Absensi${pegawai ? ` — ${pegawai.nama_lengkap}` : ''}`}
                            </h3>
                            <p className="mt-1 text-sm text-text-secondary">Pantau kehadiran, verifikasi lokasi, dan kelola status presensi.</p>
                        </div>
                        <p className="flex items-center gap-2 text-xs font-medium text-text-secondary">
                            <CalendarDays className="h-4 w-4 text-primary" />
                            {jadwalFilter === 'sedang_berlangsung' ? (
                                <span className="inline-flex items-center gap-1.5 text-amber-700">
                                    <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500" aria-hidden="true" />
                                    Kelas sedang berlangsung (hari ini)
                                </span>
                            ) : startDate || endDate
                                ? `${startDate || '…'} s/d ${endDate || '…'}`
                                : 'Semua periode'}
                        </p>
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {statsCards.map((card) => <StatCard key={card.label} {...card} />)}
                    </div>

                    {/* Filter bar */}
                    <div className="card p-5">
                        <div className="flex flex-col gap-4">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                {isAdmin && (
                                    <>
                                        <div className="relative lg:col-span-2">
                                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                            <input
                                                type="text"
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                placeholder="Cari nama pegawai…"
                                                className="input-field h-9 pl-9 text-xs w-full"
                                            />
                                        </div>
                                        {auth.permissions?.includes('view_all_units') && (
                                            <select className={filterSelect} value={unitId} onChange={(e) => setUnitId(e.target.value)}>
                                                <option value="">Semua Unit</option>
                                                {units?.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                            </select>
                                        )}
                                    </>
                                )}
                                <select className={filterSelect} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                                    <option value="">Semua Status</option>
                                    {Object.entries(STATUS_META).map(([key, m]) => <option key={key} value={key}>{m.label}</option>)}
                                </select>
                                {isAdmin && (
                                    <select className={filterSelect} value={jenisFilter} onChange={(e) => setJenisFilter(e.target.value)}>
                                        <option value="">Semua Jenis</option>
                                        <option value="pendidik">Pendidik (Guru)</option>
                                        <option value="kependidikan">Tenaga Kependidikan</option>
                                    </select>
                                )}
                            </div>

                            {isAdmin && (
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                    <select className={filterSelect} value={jadwalFilter} onChange={(e) => {
                                        setJadwalFilter(e.target.value);
                                        // "Sedang Berlangsung" selalu memaksa tanggal = hari ini;
                                        // bersihkan range tanggal agar tidak konflik jadi kosong.
                                        if (e.target.value === 'sedang_berlangsung') {
                                            setStartDate('');
                                            setEndDate('');
                                        }
                                    }}>
                                        <option value="">Semua Jadwal</option>
                                        <option value="sedang_berlangsung">Sedang Berlangsung</option>
                                    </select>
                                    <select className={filterSelect} value={lemburFilter} onChange={(e) => setLemburFilter(e.target.value)}>
                                        <option value="">Semua Presensi</option>
                                        <option value="lembur_semua">Semua Lembur</option>
                                        <option value="lembur_pending">Lembur Pending</option>
                                        <option value="lembur_disetujui">Lembur Disetujui</option>
                                        <option value="lembur_ditolak">Lembur Ditolak</option>
                                    </select>
                                    <select className={filterSelect} value={lokasiFilter} onChange={(e) => setLokasiFilter(e.target.value)}>
                                        <option value="">Semua Lokasi</option>
                                        <option value="review_semua">Perlu Review (Semua)</option>
                                        <option value="perlu_review">Perlu Review GPS</option>
                                        <option value="pulang_awal">Pulang Awal</option>
                                    </select>
                                    <select className={filterSelect} value={suspiciousFilter} onChange={(e) => setSuspiciousFilter(e.target.value)}>
                                        <option value="">Semua GPS</option>
                                        <option value="1">Posisi Mencurigakan</option>
                                    </select>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-2 border-t border-border/60 pt-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <input type="date" className="input-field text-xs h-9" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
                                    <span className="text-xs text-text-secondary">–</span>
                                    <input type="date" className="input-field text-xs h-9" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
                                </div>
                                <div className="ml-1 flex flex-wrap gap-1.5">
                                    {[{ k: 'hari', label: 'Hari Ini' }, { k: '7hari', label: '7 Hari' }, { k: 'bulan', label: 'Bulan Ini' }].map((p) => (
                                        <button key={p.k} type="button" onClick={() => applyPreset(p.k)} className="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary hover:bg-primary-50">
                                            {p.label}
                                        </button>
                                    ))}
                                </div>
                                <div className="ml-auto flex items-center gap-2">
                                    {hasFilter && (
                                        <button type="button" onClick={resetFilters} className="btn-secondary btn-sm flex items-center gap-1.5">
                                            <RotateCcw className="h-3.5 w-3.5" /> Reset
                                        </button>
                                    )}
                                    <button type="button" onClick={() => applyFilters()} disabled={processing} className="btn-primary btn-sm flex items-center gap-1.5">
                                        {processing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Filter className="h-3.5 w-3.5" />}
                                        Terapkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ─── ADMIN: Table ─── */}
                    {isAdmin ? (
                        <div className="card p-0 overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface/80 sticky top-0 z-10 backdrop-blur-sm">
                                        <tr>
                                            <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Pegawai & Unit</th>
                                            <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Tanggal</th>
                                            <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Jadwal</th>
                                            <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Masuk</th>
                                            <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Keluar</th>
                                            <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Foto</th>
                                            <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Lembur</th>
                                            <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Status</th>
                                            <th className="px-4 py-3.5 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Lokasi & Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className={`divide-y divide-border/50 ${processing ? 'opacity-60 pointer-events-none transition-opacity' : ''}`}>
                                        {presensis.data.map((p) => {
                                            const durasi = kerjaDurasi(p.jam_masuk, p.jam_keluar);
                                            const flagReview = p.lokasi_perlu_review || p.posisi_mencurigakan || p.motion_suspect;
                                            const nama = p.pegawai?.nama_lengkap || '-';

                                            return (
                                                <tr key={p.id} className="group hover:bg-surface/70 transition-colors">
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <div className="flex items-center gap-3">
                                                            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold ${avatarTone(nama)}`}>
                                                                {initials(nama)}
                                                            </span>
                                                            <div className="min-w-0">
                                                                <div className="text-sm font-bold text-primary truncate max-w-[180px]">{nama}</div>
                                                                <div className="text-[11px] text-text-secondary truncate max-w-[180px]">{p.unit_sekolah?.nama || '—'}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <div className="text-sm font-semibold text-primary">{format(parseDate(p.tanggal), 'd MMM yyyy', { locale: id })}</div>
                                                        <div className="text-[11px] text-text-secondary">{format(parseDate(p.tanggal), 'EEEE', { locale: id })}</div>
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {p.jadwal ? (
                                                            <div className="text-xs leading-tight">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="font-semibold text-primary">{p.jadwal.mata_pelajaran?.nama || 'Jadwal'}</span>
                                                                    <JadwalStatusBadge p={p} now={now} />
                                                                </div>
                                                                <div className="text-text-secondary">{p.jadwal.kelas_label || '—'}</div>
                                                                <div className="font-mono text-[11px] text-text-secondary">{p.jadwal.jam_mulai?.substring(0, 5)}–{p.jadwal.jam_selesai?.substring(0, 5)}</div>
                                                            </div>
                                                        ) : p.is_lembur ? (
                                                            <span className="text-xs font-bold uppercase text-orange-600">Lembur</span>
                                                        ) : p.tipe_presensi === 'kantor' ? (
                                                            <span className="text-xs font-semibold text-text-secondary">Kantor</span>
                                                        ) : <span className="text-xs text-text-secondary">—</span>}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {p.jam_masuk ? (
                                                            <div className="flex flex-col">
                                                                <span className="font-mono text-sm font-bold text-primary">{p.jam_masuk.substring(0, 5)}</span>
                                                                {p.jarak_masuk_meter != null && (
                                                                    <span className="text-[10px] text-text-secondary flex items-center gap-0.5"><MapPin className="h-2.5 w-2.5" />{p.jarak_masuk_meter}m</span>
                                                                )}
                                                            </div>
                                                        ) : <span className="text-sm text-text-secondary">—</span>}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {p.jam_keluar ? (
                                                            <div className="flex flex-col">
                                                                <span className="font-mono text-sm font-bold text-primary">{p.jam_keluar.substring(0, 5)}</span>
                                                                {durasi && <span className="text-[10px] font-semibold text-emerald-600">{durasi}</span>}
                                                            </div>
                                                        ) : <span className="text-sm text-text-secondary">—</span>}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <div className="flex gap-1.5">
                                                            {p.foto_masuk_url ? (
                                                                <a href={p.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border hover:ring-2 hover:ring-primary transition-shadow" title="Foto masuk">
                                                                    <img src={p.foto_masuk_url} alt="Masuk" className="h-full w-full object-cover" loading="lazy" />
                                                                </a>
                                                            ) : p.foto_masuk_status ? (
                                                                <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-[8px] font-semibold uppercase text-slate-400">{p.foto_masuk_status}</span>
                                                            ) : null}
                                                            {p.foto_keluar_url ? (
                                                                <a href={p.foto_keluar_url} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border hover:ring-2 hover:ring-primary transition-shadow" title="Foto keluar">
                                                                    <img src={p.foto_keluar_url} alt="Keluar" className="h-full w-full object-cover" loading="lazy" />
                                                                </a>
                                                            ) : p.foto_keluar_status ? (
                                                                <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-[8px] font-semibold uppercase text-slate-400">{p.foto_keluar_status}</span>
                                                            ) : null}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {p.is_lembur ? (
                                                            <div>
                                                                <LemburBadge status={p.lembur_status} />
                                                                {p.lembur_status === 'pending' && (
                                                                    <div className="mt-1.5 flex gap-1">
                                                                        <button
                                                                            onClick={() => router.post(route('presensi.approveLembur', p.id), {}, { preserveState: true })}
                                                                            className="rounded-md bg-emerald-500 px-2 py-1 text-[10px] font-bold text-white transition-colors hover:bg-emerald-600"
                                                                        >
                                                                            Setuju
                                                                        </button>
                                                                        <button
                                                                            onClick={() => router.post(route('presensi.rejectLembur', p.id), {}, { preserveState: true })}
                                                                            className="rounded-md bg-rose-500 px-2 py-1 text-[10px] font-bold text-white transition-colors hover:bg-rose-600"
                                                                        >
                                                                            Tolak
                                                                        </button>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ) : <span className="text-xs text-text-secondary">—</span>}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {auth.permissions?.includes('manage_master_data') ? (
                                                            <select
                                                                className={`cursor-pointer rounded-lg border px-2.5 py-1.5 text-[11px] font-bold uppercase shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/30 ${STATUS_META[p.status]?.badge || 'bg-gray-50 text-gray-700 border-gray-200'}`}
                                                                value={p.status}
                                                                onChange={(e) => setConfirmStatus({ id: p.id, statusLama: p.status, statusBaru: e.target.value })}
                                                            >
                                                                <option value="hadir">Hadir</option>
                                                                <option value="telat">Telat</option>
                                                                <option value="sakit">Sakit</option>
                                                                <option value="izin">Izin</option>
                                                                <option value="cuti">Cuti</option>
                                                                <option value="alpa">Alpa</option>
                                                            </select>
                                                        ) : (
                                                            <StatusBadge status={p.status} />
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap text-right">
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            {flagReview && (
                                                                <button onClick={() => openReview(p)} className="rounded-lg p-1.5 text-amber-600 transition-colors hover:bg-amber-50" title="Detail review anti-spoof">
                                                                    <ShieldAlert className="h-4 w-4" />
                                                                </button>
                                                            )}
                                                            <button onClick={() => openAudit(p)} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary" title="Riwayat perubahan">
                                                                <History className="h-4 w-4" />
                                                            </button>
                                                            {p.jarak_masuk_meter != null && (
                                                                <span className="ml-1 font-mono text-[11px] font-semibold text-text-secondary">{p.jarak_masuk_meter}m</span>
                                                            )}
                                                        </div>
                                                        {(p.lokasi_perlu_review || p.posisi_mencurigakan) && (
                                                            <div className="mt-1 flex justify-end gap-1">
                                                                {p.lokasi_perlu_review && <span className="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[9px] font-bold uppercase text-rose-600"><AlertTriangle className="h-2.5 w-2.5" /> Review</span>}
                                                                {p.posisi_mencurigakan && <span className="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase text-amber-600">Suspek</span>}
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {presensis.data.length === 0 && (
                                            <tr>
                                                <td colSpan="9" className="px-6 py-16">
                                                    <div className="flex flex-col items-center text-center">
                                                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                                            <CalendarDays className="h-8 w-8 text-border" />
                                                        </div>
                                                        <p className="mt-4 text-base font-bold text-primary">Belum ada data presensi</p>
                                                        <p className="mt-1 text-sm text-text-secondary">Coba ubah periode atau bersihkan filter.</p>
                                                        {hasFilter && (
                                                            <button onClick={resetFilters} className="btn-secondary btn-sm mt-4 flex items-center gap-1.5">
                                                                <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {presensis.data.map((p) => (
                                <div key={p.id} className="card p-5 transition-shadow hover:shadow-card-hover">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/5 text-primary">
                                                    <CalendarDays className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <div className="text-sm font-bold text-primary">{format(parseDate(p.tanggal), 'EEEE, d MMMM yyyy', { locale: id })}</div>
                                                    <div className="mt-0.5 flex items-center gap-3 text-xs text-text-secondary">
                                                        <span className="inline-flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Masuk <b className="font-mono text-primary">{p.jam_masuk?.substring(0, 5) || '—'}</b></span>
                                                        <span className="inline-flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-rose-400" /> Keluar <b className="font-mono text-primary">{p.jam_keluar?.substring(0, 5) || '—'}</b></span>
                                                        {p.tipe_presensi === 'mengajar' && (
                                                            <span className="flex items-center gap-2 text-[11px] text-text-muted">
                                                                {p.jadwal?.mata_pelajaran?.nama || ''}
                                                                <JadwalStatusBadge p={p} now={now} />
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={p.status} />
                                            {p.is_lembur && <LemburBadge status={p.lembur_status} />}
                                        </div>
                                    </div>
                                    {(p.foto_masuk_url || p.foto_keluar_url) && (
                                        <div className="mt-3 flex items-center gap-4 border-t border-border pt-3">
                                            {p.foto_masuk_url && (
                                                <a href={p.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-xs text-text-secondary transition-colors hover:text-primary">
                                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Foto Masuk
                                                </a>
                                            )}
                                            {p.foto_keluar_url && (
                                                <a href={p.foto_keluar_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-xs text-text-secondary transition-colors hover:text-primary">
                                                    <span className="h-1.5 w-1.5 rounded-full bg-rose-400" /> Foto Keluar
                                                </a>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))}
                            {presensis.data.length === 0 && (
                                <div className="card p-12 text-center">
                                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                        <CalendarDays className="h-8 w-8 text-border" />
                                    </div>
                                    <p className="mt-4 text-base font-bold text-primary">Belum ada riwayat presensi</p>
                                    <p className="mt-1 text-sm text-text-secondary">Data akan muncul setelah Anda melakukan absen pertama.</p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Pagination */}
                    {presensis.total > 0 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-text-secondary">
                                Menampilkan <b className="text-primary">{presensis.from || 0}</b>–<b className="text-primary">{presensis.to || 0}</b> dari <b className="text-primary">{presensis.total}</b> data
                            </p>
                            <Pagination links={presensis.links} pagination={{ current_page: presensis.current_page, last_page: presensis.last_page }} />
                        </div>
                    )}

                    {/* ─── MODAL: Riwayat Perubahan ─── */}
                    <Modal show={auditModal.show} onClose={() => setAuditModal({ show: false, loading: false, data: [], presensi: null })} maxWidth="lg">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-surface text-primary">
                                        <History className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold text-primary">Riwayat Perubahan</h3>
                                        {auditPegawai && <p className="mt-0.5 text-sm text-text-secondary">{auditPegawai}</p>}
                                    </div>
                                </div>
                                <button onClick={() => setAuditModal({ show: false, loading: false, data: [], presensi: null })} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary">
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                            {auditModal.presensi && (auditModal.presensi.foto_masuk_url || auditModal.presensi.foto_keluar_url) && (
                                <div className="mb-5 rounded-lg bg-surface p-4">
                                    <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Bukti Foto</p>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        {[{ label: 'Foto Masuk', url: auditModal.presensi.foto_masuk_url, status: auditModal.presensi.foto_masuk_status, error: auditModal.presensi.foto_masuk_error, tone: 'bg-emerald-100 text-emerald-700' }, { label: 'Foto Keluar', url: auditModal.presensi.foto_keluar_url, status: auditModal.presensi.foto_keluar_status, error: auditModal.presensi.foto_keluar_error, tone: 'bg-rose-100 text-rose-700' }].map((f, i) => (
                                            f.url && (
                                                <div key={i}>
                                                    <a href={f.url} target="_blank" rel="noopener noreferrer" className="block overflow-hidden rounded-lg border border-border transition-shadow hover:ring-2 hover:ring-primary" title={`${f.label} — buka di tab baru`}>
                                                        <img src={f.url} alt={f.label} className="aspect-[3/4] w-full object-cover" loading="lazy" />
                                                    </a>
                                                    <div className="mt-1.5 flex items-center justify-between px-0.5">
                                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${f.tone}`}>{f.label}</span>
                                                        {f.status && f.status !== 'success' && <span className="text-[10px] font-semibold text-warning">{f.status}{f.error ? ` — ${f.error}` : ''}</span>}
                                                    </div>
                                                </div>
                                            )
                                        ))}
                                    </div>
                                </div>
                            )}
                            {auditModal.loading ? (
                                <div className="space-y-4 py-4">
                                    {[1, 2, 3].map((i) => (
                                        <div key={i} className="flex animate-pulse gap-4">
                                            <div className="mt-2 h-2 w-2 flex-shrink-0 rounded-full bg-border" />
                                            <div className="flex-1 space-y-2">
                                                <div className="h-3 w-24 rounded bg-surface" />
                                                <div className="h-4 w-40 rounded bg-surface" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : auditModal.data.length === 0 ? (
                                <div className="py-12 text-center">
                                    <History className="mx-auto mb-3 h-12 w-12 text-border" />
                                    <p className="text-sm text-text-secondary">Belum ada perubahan.</p>
                                </div>
                            ) : (
                                <div className="relative">
                                    <div className="absolute bottom-2 left-[7px] top-2 w-0.5 bg-border/60" />
                                    <div className="space-y-5">
                                        {auditModal.data.map((a, i) => {
                                            const isStatus = a.field === 'status';
                                            const aksiLabel = a.aksi === 'approve_lembur' ? 'Setujui Lembur'
                                                : a.aksi === 'reject_lembur' ? 'Tolak Lembur'
                                                : a.aksi === 'ubah_status' ? 'Ubah Status'
                                                : a.aksi;
                                            const tone = a.aksi === 'approve_lembur' ? 'bg-emerald-50 text-emerald-700'
                                                : a.aksi === 'reject_lembur' ? 'bg-rose-50 text-rose-700'
                                                : 'bg-amber-50 text-amber-700';
                                            const dotTone = a.aksi === 'approve_lembur' ? 'bg-emerald-400'
                                                : a.aksi === 'reject_lembur' ? 'bg-rose-400'
                                                : 'bg-amber-300';
                                            const badgeOf = (val) => STATUS_META[val]?.badge || 'bg-gray-50 text-gray-700 border-gray-200';

                                            return (
                                                <div key={a.id} className="flex gap-4">
                                                    <div className={`z-10 mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border-2 ${tone} border-current`}>
                                                        <div className={`h-1.5 w-1.5 rounded-full ${dotTone}`} />
                                                    </div>
                                                    <div className="min-w-0 flex-1 pb-1">
                                                        <div className="mb-1 flex items-center gap-2">
                                                            <span className={`inline-flex items-center rounded px-2 py-0.5 text-[11px] font-bold uppercase ${tone}`}>{aksiLabel}</span>
                                                            <span className="text-[11px] text-text-secondary">{format(new Date(a.created_at), 'd MMM HH:mm', { locale: id })}</span>
                                                        </div>
                                                        {isStatus && (
                                                            <div className="mt-1.5 flex items-center gap-2">
                                                                <span className={`inline-flex items-center rounded border px-2 py-0.5 text-xs font-bold ${badgeOf(a.nilai_lama)}`}>{a.nilai_lama?.toUpperCase() || '—'}</span>
                                                                <span className="text-text-secondary">→</span>
                                                                <span className={`inline-flex items-center rounded border px-2 py-0.5 text-xs font-bold ${badgeOf(a.nilai_baru)}`}>{a.nilai_baru?.toUpperCase() || '—'}</span>
                                                            </div>
                                                        )}
                                                        <div className="mt-1 flex items-center gap-1.5">
                                                            <span className="text-[11px] text-text-secondary">{a.user?.name || '-'}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    </Modal>

                    {/* ─── MODAL: Review Anti-Spoof ─── */}
                    <Modal show={reviewModal.show} onClose={() => setReviewModal({ show: false, loading: false, data: null })} maxWidth="lg">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                        <ShieldAlert className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold text-primary">Detail Review Anti-Spoof</h3>
                                        {reviewModal.data && <p className="mt-0.5 text-sm text-text-secondary">{reviewModal.data.pegawai_nama} • {reviewModal.data.tanggal}</p>}
                                    </div>
                                </div>
                                <button onClick={() => setReviewModal({ show: false, loading: false, data: null })} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary">
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                            {reviewModal.loading ? (
                                <div className="space-y-4 py-4">
                                    {[1, 2, 3].map((i) => (
                                        <div key={i} className="flex animate-pulse gap-4">
                                            <div className="flex-1 space-y-2">
                                                <div className="h-3 w-24 rounded bg-surface" />
                                                <div className="h-4 w-40 rounded bg-surface" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : !reviewModal.data ? (
                                <div className="py-12 text-center">
                                    <p className="text-sm text-text-secondary">Data detail tidak tersedia.</p>
                                </div>
                            ) : (() => {
                                const d = reviewModal.data;
                                const flags = [
                                    d.lokasi_perlu_review && { label: 'Lokasi Perlu Review', cls: 'bg-rose-50 text-rose-700 border-rose-200' },
                                    d.posisi_mencurigakan && { label: 'Posisi Mencurigakan', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
                                    d.motion_suspect && { label: 'Motion Suspect (emulator)', cls: 'bg-purple-50 text-purple-700 border-purple-200' },
                                ].filter(Boolean);

                                return (
                                    <div className="space-y-5">
                                        <div className="flex flex-wrap gap-2">
                                            {flags.length ? flags.map((f, i) => (
                                                <span key={i} className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold ${f.cls}`}>{f.label}</span>
                                            )) : <span className="text-xs text-text-secondary">Tidak ada flag aktif.</span>}
                                        </div>

                                        <div className="rounded-lg bg-surface p-4">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Bukti Foto (overlay nama • waktu • lokasi)</p>
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                {[{ label: 'Foto Masuk', url: d.foto_masuk_url, status: d.foto_masuk_status, error: d.foto_masuk_error, tone: 'bg-emerald-100 text-emerald-700' }, { label: 'Foto Keluar', url: d.foto_keluar_url, status: d.foto_keluar_status, error: d.foto_keluar_error, tone: 'bg-rose-100 text-rose-700' }].map((f, i) => (
                                                    <div key={i}>
                                                        {f.url ? (
                                                            <a href={f.url} target="_blank" rel="noopener noreferrer" className="block overflow-hidden rounded-lg border border-border transition-shadow hover:ring-2 hover:ring-primary" title={`${f.label} — buka di tab baru`}>
                                                                <img src={f.url} alt={f.label} className="aspect-[3/4] w-full object-cover" loading="lazy" />
                                                            </a>
                                                        ) : (
                                                            <div className="flex aspect-[3/4] w-full items-center justify-center rounded-lg border border-dashed border-border bg-white">
                                                                <span className="text-xs text-text-secondary">{f.label} tidak tersedia</span>
                                                            </div>
                                                        )}
                                                        <div className="mt-1.5 flex items-center justify-between px-0.5">
                                                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${f.tone}`}>{f.label}</span>
                                                            {f.status && f.status !== 'success' && <span className="text-[10px] font-semibold text-warning">{f.status}{f.error ? ` — ${f.error}` : ''}</span>}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            <p className="mt-2 text-[11px] leading-relaxed text-text-secondary">Foto di-burn-in nama, unit, waktu (HH:mm:ss) dan koordinat saat pengambilan. EXIF GPS (jika tersedia) juga dibandingkan di bawah.</p>
                                        </div>

                                        <div className="rounded-lg bg-surface p-4">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Data GPS</p>
                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div><span className="text-text-secondary">Akurasi:</span> <b className="text-primary">{d.akurasi_masuk ?? '-'}m</b></div>
                                                <div><span className="text-text-secondary">Kecepatan:</span> <b className="text-primary">{d.kecepatan_masuk ?? '-'} m/s</b></div>
                                                <div className="col-span-2"><span className="text-text-secondary">Koordinat:</span> <b className="font-mono text-primary">{d.latitude_masuk ?? '-'}, {d.longitude_masuk ?? '-'}</b></div>
                                                {d.captured_at && <div className="col-span-2"><span className="text-text-secondary">Waktu capture:</span> <b className="text-primary">{new Date(d.captured_at).toLocaleString('id-ID')}</b></div>}
                                            </div>
                                        </div>

                                        <div className="rounded-lg bg-surface p-4">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Trajectory (Awal → A → B)</p>
                                            {d.trajectory?.length ? (
                                                <div className="space-y-2">
                                                    {d.trajectory.map((t, i) => (
                                                        <div key={i} className="flex items-center justify-between text-xs">
                                                            <span className="font-bold uppercase text-primary">{t.label || '?'}</span>
                                                            <span className="font-mono text-text-secondary">{t.lat}, {t.lng}</span>
                                                            <span className="text-text-secondary">akurasi {t.accuracy ?? '-'}m</span>
                                                            <span className="text-text-secondary">{t.captured_at ? new Date(t.captured_at).toLocaleTimeString('id-ID') : '-'}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : <p className="text-xs text-text-secondary">Tidak ada data trajectory (client lama / tidak didukung).</p>}
                                        </div>

                                        <div className="rounded-lg bg-surface p-4">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Motion (Accelerometer)</p>
                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div><span className="text-text-secondary">Sample count:</span> <b className="text-primary">{d.motion_sample_count}</b></div>
                                                <div><span className="text-text-secondary">Varians:</span> <b className="text-primary">{d.motion_variance ?? '-'}</b></div>
                                            </div>
                                            {d.motion_suspect && <p className="mt-2 rounded-lg bg-purple-50 px-3 py-2 text-xs text-purple-600">Varians sangat rendah / nol — indikasi emulator atau device virtual.</p>}
                                        </div>

                                        {d.ip_geo && (
                                            <div className="rounded-lg bg-surface p-4">
                                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">IP Geolocation</p>
                                                <div className="grid grid-cols-2 gap-3 text-sm">
                                                    <div><span className="text-text-secondary">IP:</span> <b className="font-mono text-primary">{d.ip_geo.ip}</b></div>
                                                    <div><span className="text-text-secondary">Lokasi IP:</span> <b className="text-primary">{d.ip_geo.city ? `${d.ip_geo.city}, ${d.ip_geo.country}` : '-'}</b></div>
                                                    <div className="col-span-2"><span className="text-text-secondary">Jarak GPS vs IP:</span> <b className={d.ip_geo.distance_km > 500 ? 'text-danger' : 'text-primary'}>{d.ip_geo.distance_km ?? '-'} km</b></div>
                                                </div>
                                            </div>
                                        )}

                                        {d.exif_meta && (
                                            <div className="rounded-lg bg-surface p-4">
                                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">EXIF Foto</p>
                                                <div className="grid grid-cols-2 gap-3 text-sm">
                                                    <div><span className="text-text-secondary">EXIF GPS:</span> <b className="font-mono text-primary">{d.exif_meta.gps_lat ? `${d.exif_meta.gps_lat.toFixed(5)}, ${d.exif_meta.gps_lng?.toFixed(5)}` : '-'}</b></div>
                                                    <div><span className="text-text-secondary">DateTimeOriginal:</span> <b className="text-primary">{d.exif_meta.datetime_original || '-'}</b></div>
                                                    {d.exif_meta.mismatch && <div className="col-span-2"><span className="font-bold text-danger">⚠ Mismatch {d.exif_meta.mismatch_distance_m}m dengan koordinat reported</span></div>}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })()}
                        </div>
                    </Modal>

                    {/* ─── MODAL: Konfirmasi Ubah Status ─── */}
                    <Modal show={confirmStatus !== null} onClose={() => setConfirmStatus(null)} maxWidth="sm">
                        <div className="p-6">
                            <div className="mb-5 flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                    <AlertTriangle className="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold text-primary">Konfirmasi Ubah Status</h3>
                                    <p className="mt-0.5 text-sm text-text-secondary">Pastikan perubahan status presensi sudah sesuai.</p>
                                </div>
                            </div>
                            <div className="mb-6 rounded-lg bg-surface p-4">
                                <div className="flex items-center justify-between">
                                    <div className="text-center">
                                        <div className="mb-1 text-xs uppercase tracking-wide text-text-secondary">Status Saat Ini</div>
                                        <StatusBadge status={confirmStatus?.statusLama} />
                                    </div>
                                    <span className="mx-4 text-text-secondary">→</span>
                                    <div className="text-center">
                                        <div className="mb-1 text-xs uppercase tracking-wide text-text-secondary">Akan Diubah</div>
                                        <StatusBadge status={confirmStatus?.statusBaru} />
                                    </div>
                                </div>
                                {confirmStatus?.statusBaru === 'sakit' && (
                                    <div className="mt-4 border-t border-border pt-4">
                                        <label className="form-label text-xs">Persentase Bayar Jam Sakit</label>
                                        <div className="mt-1 flex gap-2">
                                            {[0, 50, 100].map((v) => (
                                                <button key={v} type="button"
                                                    onClick={() => setPersentaseBayar(v)}
                                                    className={`rounded-lg border px-4 py-2 text-sm font-semibold transition-colors ${persentaseBayar === v ? 'border-primary bg-primary text-white' : 'border-border bg-white text-text-secondary hover:bg-surface'}`}
                                                >{v}%</button>
                                            ))}
                                        </div>
                                        <p className="form-hint mt-1">Persentase gaji yang tetap dibayarkan untuk hari sakit.</p>
                                    </div>
                                )}
                            </div>
                            <div className="flex justify-end gap-3">
                                <button onClick={() => setConfirmStatus(null)} className="btn-secondary btn-sm min-w-[80px]">Batal</button>
                                <button
                                    onClick={() => {
                                        router.put(route('presensi.update', confirmStatus.id), { status: confirmStatus.statusBaru, persentase_bayar_jam: persentaseBayar }, { preserveState: false });
                                        setConfirmStatus(null);
                                    }}
                                    className="btn-primary btn-sm min-w-[100px]"
                                >
                                    Ya, Ubah
                                </button>
                            </div>
                        </div>
                    </Modal>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
