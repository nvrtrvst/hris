import React, { useState, Component, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatCard from '@/Components/StatCard';
import { Head, Link, router } from '@inertiajs/react';
import usePolling from '@/Utils/usePolling';
import { avatarTone, initials } from '@/Utils/avatar';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    BookOpen,
    Building2,
    CalendarDays,
    CalendarClock,
    Megaphone,
    PinIcon,
    CheckCircle2,
    ChevronDown,
    Clock3,
    FileWarning,
    Hourglass,
    School,
    Search,
    TrendingUp,
    UserCheck,
    Users,
    X,
} from 'lucide-react';

class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        this.setState({ error, errorInfo });
        console.error('Dashboard Error:', error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="card p-6">
                    <h2 className="font-semibold text-danger">Terjadi kesalahan di Dashboard.</h2>
                    <button type="button" onClick={() => window.location.reload()} className="btn-secondary btn-sm mt-3">
                        Muat ulang halaman
                    </button>
                    <details className="mt-2 text-sm text-text-muted whitespace-pre-wrap">
                        {this.state.error && this.state.error.toString()}
                        <br />
                        {this.state.errorInfo && this.state.errorInfo.componentStack}
                    </details>
                </div>
            );
        }
        return this.props.children;
    }
}

const STATUS_META = {
    hadir: { label: 'Hadir', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    telat: { label: 'Telat', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    sakit: { label: 'Sakit', badge: 'bg-purple-50 text-purple-700 border-purple-200' },
    izin: { label: 'Izin', badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    cuti: { label: 'Cuti', badge: 'bg-cyan-50 text-cyan-700 border-cyan-200' },
    alpa: { label: 'Alpa', badge: 'bg-rose-50 text-rose-700 border-rose-200' },
};

const StatusBadge = ({ status }) => {
    const meta = STATUS_META[status] || { label: status || '·', badge: 'bg-slate-50 text-slate-600 border-slate-200' };

    return (
        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${meta.badge}`}>
            {meta.label}
        </span>
    );
};

const formatJp = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(value || 0));

const jamPendek = (waktu) => (waktu ? waktu.slice(0, 5) : '—');

/** Satu baris JP tanpa presensi. Unit opsional (disembunyikan di layar sempit). */
function KelasKosongRow({ row, unit }) {
    const mapel = row.mata_pelajaran?.nama || row.pegawaiMapel?.mataPelajaran?.nama || '—';

    return (
        <li className="flex flex-wrap items-center gap-x-2 gap-y-1 py-2">
            <span className="font-mono text-xs font-semibold tabular-nums text-text-primary">
                {jamPendek(row.jam_mulai)}-{jamPendek(row.jam_selesai)}
            </span>
            <span className="text-sm font-semibold text-text-primary">{row.kelas_label || '—'}</span>
            <span className="text-xs text-text-secondary">· {mapel}</span>
            <span className="max-w-[16rem] truncate text-xs text-text-secondary">{row.pegawai?.nama_lengkap || '—'}</span>
            {row.jp > 1 && <span className="badge badge-neutral">{formatJp(row.jp)} JP</span>}
            <span className="ml-auto flex flex-wrap items-center gap-1.5">
                {unit && <span className="hidden text-xs text-text-secondary sm:inline">{unit.singkatan || unit.nama}</span>}
                {row.guru_hadir
                    ? <span className="badge badge-info">Guru sudah di unit</span>
                    : <span className="badge badge-danger">Guru belum hadir</span>}
                {row.status_waktu === 'lewat' && <span className="badge badge-danger">Terlewat</span>}
                {row.status_waktu === 'berjalan' && <span className="badge badge-warning">Berjalan</span>}
            </span>
        </li>
    );
}

/**
 * Kartu "JP belum diabsen": ringkasan JP (bobot JpCalculator, bukan hitung baris),
 * pemisahan lewat/berjalan/belum mulai, dan daftar lengkap yang bisa dibuka di tempat.
 */
function KelasKosongCard({ rows = [], summary = {} }) {
    const [terbuka, setTerbuka] = React.useState(false);
    const [unitId, setUnitId] = React.useState(null);

    const jpTotal = summary.jp_total || 0;
    const jpKosong = summary.jp_kosong || 0;
    const jpTercatat = Math.max(0, jpTotal - jpKosong);
    const persen = jpTotal > 0 ? Math.round((jpTercatat / jpTotal) * 100) : 0;
    const perUnit = summary.per_unit || [];

    const mendesak = React.useMemo(() => rows
        .filter((r) => r.status_waktu !== 'belum_mulai')
        .sort((a, b) => (a.status_waktu === b.status_waktu
            ? (a.jam_mulai || '').localeCompare(b.jam_mulai || '')
            : (a.status_waktu === 'lewat' ? -1 : 1))), [rows]);

    const daftar = React.useMemo(() => rows
        .filter((r) => !unitId || r.unit_sekolah_id === unitId)
        .sort((a, b) => (a.jam_mulai || '').localeCompare(b.jam_mulai || '')), [rows, unitId]);

    const unitDari = (id) => perUnit.find((u) => u.id === id) || null;
    const bukaDaftar = (id = null) => { setUnitId(id); setTerbuka(true); };
    const toggleDaftar = () => {
        if (terbuka) setUnitId(null);
        setTerbuka(!terbuka);
    };

    if (jpTotal <= 0) return null;

    if (summary.hari_libur) {
        return (
            <section className="card px-5 py-4">
                <h3 className="flex items-center gap-2 text-sm font-bold text-text-primary">
                    <CalendarDays className="h-4 w-4 text-text-secondary" aria-hidden="true" /> Hari libur: {summary.hari_libur}
                </h3>
                <p className="mt-1 text-xs text-text-secondary">Tidak ada KBM, jadi JP hari ini tidak dihitung kosong.</p>
            </section>
        );
    }

    if (jpKosong <= 0) {
        return (
            <section className="card border-l-4 border-success px-5 py-4">
                <h3 className="flex items-center gap-2 text-sm font-bold text-text-primary">
                    <CheckCircle2 className="h-4 w-4 text-success" aria-hidden="true" /> Semua {formatJp(jpTotal)} JP sudah tercatat
                </h3>
                <p className="mt-1 text-xs text-text-secondary">Presensi guru untuk setiap JP hari ini lengkap.</p>
            </section>
        );
    }

    const unitTampil = unitId ? [unitId] : perUnit.map((u) => u.id);

    return (
        <section aria-labelledby="kelas-kosong-title" className="card border-l-4 border-warning px-5 py-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 id="kelas-kosong-title" className="flex items-center gap-2 text-sm font-bold text-text-primary">
                        <AlertTriangle className="h-4 w-4 text-warning" aria-hidden="true" /> JP Belum Diabsen
                    </h3>
                    <p className="mt-1 text-xs text-text-secondary">
                        dari {formatJp(jpTotal)} JP terjadwal hari ini · {formatJp(jpTercatat)} JP sudah tercatat
                    </p>
                </div>
                <p className="shrink-0 text-2xl font-extrabold leading-none tabular-nums text-text-primary">
                    {formatJp(jpKosong)} <span className="text-xs font-semibold text-text-secondary">JP</span>
                </p>
            </div>

            <div
                className="mt-3 h-2 w-full overflow-hidden rounded-full bg-border/60"
                role="progressbar"
                aria-valuenow={persen}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={`JP tercatat ${persen} persen`}
            >
                <div className="h-full rounded-full bg-warning motion-safe:transition-all motion-safe:duration-500" style={{ width: `${persen}%` }} />
            </div>

            <div className="mt-3 flex flex-wrap gap-1.5">
                <span className="badge badge-danger">Terlewat {formatJp(summary.jp_lewat)} JP</span>
                <span className="badge badge-warning">Berjalan {formatJp(summary.jp_berjalan)} JP</span>
                <span className="badge badge-neutral">Belum mulai {formatJp(summary.jp_belum_mulai)} JP</span>
            </div>

            {summary.jp_guru_hadir > 0 && (
                <p className="mt-2 text-xs text-text-secondary">
                    {formatJp(summary.jp_guru_hadir)} JP di antaranya gurunya sudah absen harian, tinggal catat JP-nya.
                </p>
            )}

            {mendesak.length > 0 ? (
                <div className="mt-4">
                    <p className="text-xs font-bold uppercase tracking-wider text-text-secondary">
                        Perlu ditindak · {formatJp((summary.jp_lewat || 0) + (summary.jp_berjalan || 0))} JP
                    </p>
                    <ul className="mt-1 divide-y divide-border/60">
                        {mendesak.slice(0, 5).map((row) => (
                            <KelasKosongRow key={row.id} row={row} unit={unitDari(row.unit_sekolah_id)} />
                        ))}
                    </ul>
                    {mendesak.length > 5 && (
                        <p className="text-xs text-text-secondary">+{mendesak.length - 5} JP lagi di daftar lengkap.</p>
                    )}
                </div>
            ) : (
                <p className="mt-4 text-xs text-text-secondary">
                    Belum ada JP yang terlewat. {formatJp(jpKosong)} JP mulai nanti.
                </p>
            )}

            <button
                type="button"
                onClick={toggleDaftar}
                aria-expanded={terbuka}
                aria-controls="kelas-kosong-daftar"
                className="mt-3 inline-flex items-center gap-1.5 rounded-button px-2 py-1.5 text-xs font-bold text-primary hover:bg-primary/5 active:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            >
                {terbuka ? 'Tutup daftar' : `Lihat semua ${formatJp(jpKosong)} JP`}
                <ChevronDown className={`h-4 w-4 transition-transform motion-reduce:transition-none ${terbuka ? 'rotate-180' : ''}`} aria-hidden="true" />
            </button>

            {terbuka && (
                <div id="kelas-kosong-daftar" className="mt-3 border-t border-border/60 pt-3">
                    <div className="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            onClick={() => bukaDaftar(null)}
                            aria-pressed={unitId === null}
                            className={`badge cursor-pointer border focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 ${unitId === null ? 'border-primary bg-primary text-white' : 'border-border bg-white text-text-secondary hover:bg-surface'}`}
                        >
                            Semua {formatJp(jpKosong)} JP
                        </button>
                        {perUnit.map((u) => (
                            <button
                                key={u.id}
                                type="button"
                                onClick={() => bukaDaftar(u.id)}
                                aria-pressed={unitId === u.id}
                                className={`badge cursor-pointer border focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 ${unitId === u.id ? 'border-primary bg-primary text-white' : 'border-border bg-white text-text-secondary hover:bg-surface'}`}
                            >
                                {u.singkatan || u.nama} · {formatJp(u.jp)} JP
                            </button>
                        ))}
                    </div>

                    <div className="mt-2 max-h-80 overflow-y-auto pr-1">
                        {daftar.length === 0 && (
                            <p className="py-3 text-center text-xs text-text-secondary">Tidak ada JP untuk unit ini.</p>
                        )}
                        {unitTampil.map((id) => {
                            const unit = unitDari(id);
                            const baris = daftar.filter((r) => r.unit_sekolah_id === id);
                            if (baris.length === 0) return null;

                            return (
                                <div key={id} className="mb-2 last:mb-0">
                                    <p className="sticky top-0 z-[1] bg-white py-1 text-xs font-bold uppercase tracking-wider text-text-secondary">
                                        {unit?.singkatan || unit?.nama || 'Tanpa unit'} · {formatJp(unit?.jp)} JP
                                    </p>
                                    <ul className="divide-y divide-border/60">
                                        {baris.map((row) => <KelasKosongRow key={row.id} row={row} unit={null} />)}
                                    </ul>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </section>
    );
}

export default function Dashboard(props) {
    return (
        <ErrorBoundary>
            <DashboardContent {...props} />
        </ErrorBoundary>
    );
}

function DashboardContent({ auth, roleType, stats, trends, kontrakBerakhir, jadwalHariIni, presensiHariIni, pengumuman = [], units = [], selectedUnitId = null, selectedUnitNama = null, kelasKosongMengajar = [], kelasKosongSummary = {}, kelasKosongPiket = [], piketHariIni = [] }) {
    const [detailPresensi, setDetailPresensi] = useState(null);
    const [detailPiket, setDetailPiket] = useState(null);
    // Pencarian jadwal: kolom input bebas, filter baru berubah saat Enter (tanpa debounce).
    const [draftCari, setDraftCari] = useState('');
    const [cari, setCari] = useState('');

    // Live polling: refresh data kehadiran/jadwal/presensi tiap 60 detik (partial reload
    // Inertia, hanya prop yang di-`only` dikirim ulang). Berhenti saat tab tidak aktif.
    const isAdmin = roleType === 'Admin';
    usePolling({ enabled: isAdmin, only: ['stats', 'trends', 'jadwalHariIni', 'presensiHariIni', 'kelasKosongMengajar', 'kelasKosongSummary', 'kelasKosongPiket', 'piketHariIni'] });

    // Filter unit (superadmin): reload penuh agar semua angka ter-scope ke unit.
    const setUnitFilter = (e) => {
        const unitId = e.target.value;
        router.get(route('dashboard'), unitId ? { unit_id: unitId } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const presensiMap = useMemo(() => {
        const map = {};
        (presensiHariIni || []).forEach((p) => {
            const key = p.pegawai_id;
            if (!map[key] || p.jam_masuk > (map[key].jam_masuk || '')) map[key] = p;
        });
        return map;
    }, [presensiHariIni]);

    const presensiByJadwal = useMemo(() => {
        const map = {};
        (presensiHariIni || []).forEach((p) => {
            if (p.jadwal_id) map[p.jadwal_id] = p;
        });
        return map;
    }, [presensiHariIni]);

    // Grup jadwal hari ini per guru (seperti tabel /presensi): dalam grup
    // urut jam mulai + mapel; antar grup, guru yang TERAKHIR datang paling atas.
    const jadwalGroups = useMemo(() => {
        const byGuru = new Map();
        (jadwalHariIni || []).forEach((j) => {
            const pid = j.pegawai_id;
            if (!byGuru.has(pid)) byGuru.set(pid, { guru: j.pegawai, items: [] });
            byGuru.get(pid).items.push(j);
        });

        const groups = [...byGuru.values()].map((g) => ({
            ...g,
            items: g.items.sort((a, b) =>
                (a.jam_mulai || '').localeCompare(b.jam_mulai || '')
                || (a.pegawaiMapel?.mataPelajaran?.nama || a.jenis_jadwal || '').localeCompare(b.pegawaiMapel?.mataPelajaran?.nama || b.jenis_jadwal || '')
            ),
            // Jam datang guru = jam_masuk terakhir (presensi apa pun hari ini).
            lastMasuk: presensiMap[g.guru?.id]?.jam_masuk || '',
        }));

        // Prioritas: (1) guru yang SUDAH hadir selalu di atas yang belum,
        // (2) di antara yang hadir, terakhir datang paling atas,
        // (3) yang belum hadir di bawah (di antara mereka, urut nama).
        const rank = (g) => (g.lastMasuk ? 0 : 1);
        return groups.sort((a, b) =>
            rank(a) - rank(b)
            || (b.lastMasuk || '99:99').localeCompare(a.lastMasuk || '99:99')
            || (a.guru?.nama_lengkap || '').localeCompare(b.guru?.nama_lengkap || ''));
    }, [jadwalHariIni, presensiMap]);

    // Filter pencarian di level grup supaya kolom "Harian" (rowSpan) tetap utuh.
    // Grup yang cocok lewat nama guru/unit menampilkan semua JP guru itu; selain itu
    // hanya baris yang cocok (mapel, jenis, kelas, unit, jam).
    const jadwalGroupsCari = useMemo(() => {
        const q = cari.trim().toLowerCase();
        if (!q) return jadwalGroups;

        const cocok = (nilai) => (nilai || '').toLowerCase().includes(q);
        const cocokBaris = (j) => cocok(j.mata_pelajaran?.nama)
            || cocok(j.jenis_jadwal)
            || cocok(j.kelas_label)
            || cocok(j.unit_sekolah?.singkatan)
            || cocok(j.unit_sekolah?.nama)
            || cocok(j.jam_mulai?.slice(0, 5));

        return jadwalGroups.flatMap((g) => {
            const items = cocok(g.guru?.nama_lengkap) ? g.items : g.items.filter(cocokBaris);
            return items.length > 0 ? [{ ...g, items, totalItems: g.items.length }] : [];
        });
    }, [jadwalGroups, cari]);

    const formatRupiah = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(angka || 0);

    const todayString = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const s = stats || {};
    const hasKontrak = (kontrakBerakhir || []).length > 0;
    const chartData = Array.isArray(trends) ? trends : [];
    const totalHadirTrend = chartData.reduce((acc, d) => acc + (d.hadir || 0), 0);
    const maxHadirTrend = chartData.reduce((acc, d) => Math.max(acc, d.hadir || 0), 0);
    const rataHadirTrend = chartData.length > 0 ? Math.round(totalHadirTrend / chartData.length) : 0;

    const pengumumanStatus = (a) => {
        if (!a.published_at) return { label: 'Draft', cls: 'border-slate-200 bg-slate-50 text-slate-600' };
        if (new Date(a.published_at) > new Date()) return { label: 'Terjadwal', cls: 'border-amber-200 bg-amber-50 text-amber-700' };
        return { label: 'Tayang', cls: 'border-emerald-200 bg-emerald-50 text-emerald-700' };
    };
    const formatTgl = (iso) => iso ? new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Belum dijadwalkan';

    const kontrakTone = (sisa) => {
        if (sisa <= 0) return { badge: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Lewat' };
        if (sisa <= 7) return { badge: 'bg-amber-50 text-amber-700 border-amber-200', label: `${sisa} hari` };
        return { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: `${sisa} hari` };
    };

    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={
                <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="page-title">Dashboard {roleType}</h1>
                        <p className="page-subtitle">{todayString}</p>
                    </div>
                    {units.length > 0 && (
                        <div className="relative sm:mb-1 sm:w-64">
                            <Building2 className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                            <select
                                aria-label="Filter unit"
                                value={selectedUnitId || ''}
                                onChange={setUnitFilter}
                                className="select-field h-11 pl-9 text-xs font-semibold"
                            >
                                <option value="">Semua Unit</option>
                                {units.map((u) => (
                                    <option key={u.id} value={u.id}>{u.nama}</option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>
            }
        >
            <Head title={`Dashboard ${roleType}`} />

            <div className="bg-surface min-h-screen">
                <div className="mx-auto w-full space-y-6">
                    {/* Kehadiran Hari Ini, prominent full-width */}
                    <div className="card p-6">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-text-muted">
                            <UserCheck className="h-4 w-4 text-emerald-600" />
                            Kehadiran Hari Ini
                        </div>
                        <div className="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <div className="flex items-baseline gap-3 flex-wrap">
                                    <p className="text-4xl font-extrabold text-success tabular-nums">{s.hadir_percentage || 0}%</p>
                                    <p className="text-sm text-text-secondary">
                                        {s.hadir_hari_ini_count || 0} dari {s.pegawai_total || s.total_pegawai || 0} total
                                        {s.pegawai_dijadwalkan ? <span className="text-text-muted"> • {s.pegawai_dijadwalkan} terjadwal</span> : null}
                                    </p>
                                </div>
                                <div className="mt-3 h-3 w-full max-w-md overflow-hidden rounded-full bg-border/60" role="progressbar" aria-valuenow={s.hadir_percentage || 0} aria-valuemin={0} aria-valuemax={100} aria-label="Kehadiran hari ini">
                                    <div
                                        className="h-full w-full origin-left rounded-full bg-emerald-500 transition-transform duration-500 motion-reduce:transition-none"
                                        style={{ transform: `scaleX(${Math.min(100, s.hadir_percentage || 0) / 100})` }}
                                    />
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {[
                                    { label: 'Hadir', value: s.hadir_hari_ini_count || 0, cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
                                    { label: 'Telat', value: s.telat_count || 0, cls: 'bg-amber-50 text-amber-700 border-amber-200' },
                                    { label: 'Izin', value: s.izin_count || 0, cls: 'bg-blue-50 text-blue-700 border-blue-200' },
                                    { label: 'Sakit', value: s.sakit_count || 0, cls: 'bg-purple-50 text-purple-700 border-purple-200' },
                                    { label: 'Alpa', value: s.alpa_count || 0, cls: 'bg-rose-50 text-rose-700 border-rose-200' },
                                ].map((item) => (
                                    <span key={item.label} className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-bold ${item.cls}`}>
                                        {item.label} <span className="tabular-nums">{item.value}</span>
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Stat cards */}
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                        <StatCard label="Pegawai Aktif" value={s.total_pegawai || 0} Icon={Users} iconBg="bg-primary/10" iconCls="text-primary" />
                        <StatCard label={selectedUnitNama ? `Unit: ${selectedUnitNama}` : 'Unit Sekolah'} value={selectedUnitNama || s.total_unit || 0} Icon={School} iconBg="bg-blue-100" iconCls="text-blue-600" />
                        <StatCard
                            label="Kontrak Berakhir"
                            value={s.kontrak_berakhir_count || 0}
                            Icon={FileWarning}
                            iconBg={s.kontrak_berakhir_count > 0 ? 'bg-amber-100' : 'bg-surface'}
                            iconCls={s.kontrak_berakhir_count > 0 ? 'text-amber-600' : 'text-text-muted'}
                            alert={s.kontrak_berakhir_count > 0}
                        />
                        <StatCard label="Pengajuan Pending" value={s.pengajuan_pending || 0} Icon={Clock3} iconBg={s.pengajuan_pending > 0 ? 'bg-rose-100' : 'bg-surface'} iconCls={s.pengajuan_pending > 0 ? 'text-rose-600' : 'text-text-muted'} alert={s.pengajuan_pending > 0} />
                        {s.guru_mengajar > 0 && (
                            <StatCard
                                label="Guru Over JP"
                                value={`${s.guru_over_jp || 0} / ${s.guru_mengajar}`}
                                sub={`target ${s.target_jp_minggu} JP/minggu`}
                                Icon={BookOpen}
                                iconBg={s.guru_over_jp > 0 ? 'bg-rose-100' : 'bg-surface'}
                                iconCls={s.guru_over_jp > 0 ? 'text-rose-600' : 'text-text-muted'}
                                alert={s.guru_over_jp > 0}
                            />
                        )}
                    </div>

                    {/* Kelas kosong — JP tanpa presensi guru (mengajar), lalu piket */}
                    <KelasKosongCard rows={kelasKosongMengajar} summary={kelasKosongSummary} />
                    {(kelasKosongPiket?.length > 0) && (
                        <div className="card border-l-4 border-info px-5 py-4">
                            <h4 className="flex items-center gap-2 text-sm font-bold text-text-primary">
                                <Clock3 className="h-4 w-4 text-info" aria-hidden="true" /> Piket Kosong — {kelasKosongPiket.length} belum ada petugas
                            </h4>
                            <ul className="mt-1 divide-y divide-border/60">
                                {kelasKosongPiket.slice(0, 5).map((j) => (
                                    <li key={j.id} className="flex flex-wrap items-center gap-x-2 gap-y-1 py-2 text-xs">
                                        <span className="font-mono font-semibold tabular-nums text-text-primary">{jamPendek(j.jam_mulai)}-{jamPendek(j.jam_selesai)}</span>
                                        <span className="text-text-secondary">·</span>
                                        <span className="text-text-primary">{j.pegawai?.nama_lengkap || '—'}</span>
                                        <span className="badge badge-info ml-auto">Piket</span>
                                    </li>
                                ))}
                            </ul>
                            {kelasKosongPiket.length > 5 && <p className="text-xs text-text-secondary">+{kelasKosongPiket.length - 5} lagi</p>}
                        </div>
                    )}
                    {piketHariIni?.length > 0 && kelasKosongPiket?.length === 0 && (
                        <div className="card border-emerald-200 bg-emerald-50 p-4">
                            <h4 className="flex items-center gap-2 text-sm font-bold text-emerald-800">
                                <CheckCircle2 className="h-4 w-4" /> Piket Hari Ini: {piketHariIni.length} petugas terjadwal
                            </h4>
                            <ul className="mt-2 space-y-2">
                                {piketHariIni.map((j) => {
                                    const lap = j.laporan;
                                    const review = (lap?.items?.kelas_kosong?.entries ?? []).map((e, idx) => ({ ...e, idx })).filter((e) => !e.verified_at);
                                    const open = detailPiket === (lap?.id ?? `jadwal-${j.id}`);
                                    return (
                                        <li key={j.id} className="rounded-xl bg-white/70 px-3 py-2">
                                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                                <span className="font-semibold text-emerald-900">
                                                    {j.pegawai?.nama_lengkap}
                                                    <span className="font-normal text-emerald-700"> ({j.jam_mulai?.substring(0, 5)}-{j.jam_selesai?.substring(0, 5)})</span>
                                                </span>
                                                <span className="inline-flex items-center gap-1.5">
                                                    {!lap && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">Belum isi</span>}
                                                    {lap && lap.status === 'submitted' && <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Terkirim</span>}
                                                    {lap && lap.status !== 'submitted' && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">{lap.done}/{lap.total}</span>}
                                                    {lap && lap.perlu_cek > 0 && <span className="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">Perlu cek {lap.perlu_cek}</span>}
                                                </span>
                                            </div>
                                            {review.length > 0 && (
                                                <div className="mt-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetailPiket(open ? null : (lap?.id ?? `jadwal-${j.id}`))}
                                                        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setDetailPiket(open ? null : (lap?.id ?? `jadwal-${j.id}`)); } }}
                                                        aria-expanded={open}
                                                        className="text-[11px] font-bold text-emerald-800 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                                                    >
                                                        {open ? 'Sembunyikan' : `Tinjau ${review.length} entry`}
                                                    </button>
                                                    {open && (
                                                        <ul className="mt-1.5 space-y-1.5">
                                                            {review.map((e) => (
                                                                <li key={e.idx} className="rounded-lg bg-white px-2.5 py-2 text-[11px] text-slate-700 ring-1 ring-slate-200">
                                                                    <p><span className="font-bold">{e.kelas}</span>{e.mapel ? `, ${e.mapel}` : ''} ({e.guru_nama})</p>
                                                                    {e.ada_amanat && <p className="mt-0.5">Amanat: {e.detail_amanat}{e.tersampaikan ? ' (tersampaikan)' : ' (belum tersampaikan)'}</p>}
                                                                    <p className="mt-0.5 text-slate-500">Cross-check: {e.validasi_catatan}</p>
                                                                    <div className="mt-1.5 flex gap-1.5">
                                                                        <button type="button" onClick={() => router.post(route('laporan-piket.validasi', lap.id), { entry_index: e.idx, aksi: 'setujui' }, { preserveScroll: true })} className="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">Setujui</button>
                                                                        <button type="button" onClick={() => router.post(route('laporan-piket.validasi', lap.id), { entry_index: e.idx, aksi: 'tolak' }, { preserveScroll: true })} className="rounded-lg bg-white px-2.5 py-1 text-[11px] font-bold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/40">Tolak</button>
                                                                    </div>
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    )}
                                                </div>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    {/* Jadwal Hari Ini */}
                    <div className="card p-0 overflow-hidden">
                        <div className="flex flex-col gap-3 border-b border-border/60 bg-surface/50 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                            <h3 className="flex flex-wrap items-center gap-2 text-base font-bold text-primary">
                                <CalendarDays className="h-5 w-5 text-primary" />
                                Jadwal Hari Ini
                                <span className="text-xs font-semibold text-text-secondary normal-case">
                                    {new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}
                                </span>
                            </h3>

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-primary/5 px-3 py-1 text-xs font-bold text-primary tabular-nums">
                                    <Users className="h-3.5 w-3.5" aria-hidden="true" />
                                    {cari
                                        ? `${jadwalGroupsCari.length} dari ${jadwalGroups.length} guru`
                                        : `${s.pegawai_dijadwalkan || 0} guru dijadwalkan`}
                                </span>

                                <form
                                    role="search"
                                    onSubmit={(e) => { e.preventDefault(); setCari(draftCari.trim()); }}
                                    className="relative w-full sm:w-72"
                                >
                                    <label htmlFor="cari-jadwal" className="sr-only">Cari jadwal hari ini</label>
                                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary" aria-hidden="true" />
                                    <input
                                        id="cari-jadwal"
                                        type="text"
                                        enterKeyHint="search"
                                        autoComplete="off"
                                        value={draftCari}
                                        onChange={(e) => setDraftCari(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Escape') {
                                                e.preventDefault();
                                                setDraftCari('');
                                                setCari('');
                                            }
                                        }}
                                        placeholder="Cari guru, mapel, kelas, unit, lalu Enter"
                                        className="w-full rounded-button border border-border bg-white py-2 pl-8 pr-8 text-sm text-text-primary placeholder:text-text-secondary hover:border-border-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    {(draftCari || cari) && (
                                        <button
                                            type="button"
                                            onClick={() => { setDraftCari(''); setCari(''); }}
                                            aria-label="Reset pencarian jadwal"
                                            className="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-button p-1 text-text-secondary hover:bg-surface hover:text-text-primary active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                                        >
                                            <X className="h-3.5 w-3.5" aria-hidden="true" />
                                        </button>
                                    )}
                                </form>
                            </div>
                        </div>

                        {cari && (
                            <p role="status" aria-live="polite" className="sr-only">
                                Menampilkan {jadwalGroupsCari.length} dari {jadwalGroups.length} guru.
                            </p>
                        )}

                        {jadwalGroups.length > 0 ? (
                            jadwalGroupsCari.length > 0 ? (
                            <div className="overflow-x-auto lg:max-h-[72vh] lg:overflow-y-auto">
                                <table className="min-w-full divide-y divide-border text-sm">
                                    <thead className="bg-surface/80 sticky top-0 z-10 backdrop-blur-sm">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Jam</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Mapel</th>
                                            <th className="hidden sm:table-cell px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Kelas</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Guru</th>
                                            <th className="hidden md:table-cell px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Unit</th>
                                            <th className="px-4 py-3 text-center text-[11px] font-bold text-text-secondary uppercase tracking-wider">Ngajar</th>
                                            <th className="px-4 py-3 text-center text-[11px] font-bold text-text-secondary uppercase tracking-wider">Harian</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/50">
                                        {jadwalGroupsCari.map((g) => {
                                            const isExpanded = detailPresensi === g.guru?.id;
                                            const harian = presensiMap[g.guru?.id];

                                            return g.items.map((j, idx) => {
                                                const ngajar = presensiByJadwal[j.id];

                                                return (
                                                    <tr
                                                        key={j.id || `${g.guru?.id}-${idx}`}
                                                        tabIndex={0}
                                                        aria-expanded={isExpanded}
                                                        aria-controls="panel-detail-presensi"
                                                        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setDetailPresensi(isExpanded ? null : g.guru?.id); } }}
                                                        onClick={() => setDetailPresensi(isExpanded ? null : g.guru?.id)}
                                                        className={`transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/40 ${isExpanded ? 'bg-surface/70' : 'hover:bg-surface/70'} ${g.items.length > 1 && idx > 0 ? 'border-t-0' : ''}`}
                                                    >
                                                        <td className="px-4 py-3 font-mono text-sm font-semibold text-primary whitespace-nowrap">
                                                            {j.jam_mulai?.substring(0, 5)}–{j.jam_selesai?.substring(0, 5)}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className="font-medium text-text-primary">{j.mata_pelajaran?.nama || j.jenis_jadwal || '-'}</span>
                                                            {j.jenis_jadwal === 'piket' ? (
                                                                <span className="ml-1.5 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Piket</span>
                                                            ) : j.jenis_jadwal !== 'mengajar' ? (
                                                                <span className="ml-1.5 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{j.jenis_jadwal}</span>
                                                            ) : null}
                                                        </td>
                                                        <td className="hidden sm:table-cell px-4 py-3 text-text-secondary">{j.kelas_label || '-'}</td>
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            {idx === 0 ? (
                                                                <div className="flex items-center gap-2">
                                                                    <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold ${avatarTone(g.guru?.nama_lengkap)}`}>
                                                                        {initials(g.guru?.nama_lengkap)}
                                                                    </span>
                                                                    <div className="min-w-0">
                                                                        <span className="font-semibold text-text-primary">{g.guru?.nama_lengkap || '-'}</span>
                                                                        {(g.totalItems ?? g.items.length) > 1 && (
                                                                            <span className="ml-1.5 rounded-full bg-primary/5 px-1.5 py-0.5 text-[10px] font-bold text-primary">{g.totalItems ?? g.items.length} JP</span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <span className="text-xs text-text-muted">↳ lanjutan</span>
                                                            )}
                                                        </td>
                                                        <td className="hidden md:table-cell px-4 py-3 text-xs text-text-muted">{j.unit_sekolah?.singkatan || j.unit_sekolah?.nama || '-'}</td>
                                                        <td className="px-4 py-3 text-center">{ngajar ? <StatusBadge status={ngajar.status} /> : <span className="text-xs text-text-muted">·</span>}</td>
                                                        {idx === 0 ? (
                                                            <td rowSpan={g.items.length} className="px-4 py-3 text-center align-top">
                                                                {harian ? <StatusBadge status={harian.status} /> : <span className="text-xs text-text-muted">·</span>}
                                                            </td>
                                                        ) : null}
                                                    </tr>
                                                );
                                            });
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            ) : (
                                <div className="flex flex-col items-center px-6 py-12 text-center">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface">
                                        <Search className="h-7 w-7 text-text-secondary" aria-hidden="true" />
                                    </div>
                                    <p className="mt-3 text-sm font-bold text-primary">Tidak ada jadwal yang cocok</p>
                                    <p className="mt-1 max-w-sm text-xs text-text-secondary">
                                        Tidak ada guru, mapel, kelas, atau unit yang mengandung &ldquo;{cari}&rdquo;. Coba kata kunci lain atau reset pencarian.
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => { setDraftCari(''); setCari(''); }}
                                        className="mt-4 inline-flex items-center gap-1.5 rounded-button border border-border bg-white px-3 py-2 text-xs font-bold text-primary hover:bg-surface active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                                    >
                                        <X className="h-3.5 w-3.5" aria-hidden="true" /> Reset pencarian
                                    </button>
                                </div>
                            )
                        ) : (
                            <div className="flex flex-col items-center py-12 text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface">
                                    <CalendarDays className="h-7 w-7 text-text-muted" />
                                </div>
                                <p className="mt-3 text-sm font-bold text-primary">Belum ada jadwal untuk hari ini</p>
                                <p className="mt-1 text-xs text-text-secondary">Jadwal mengajar akan tampil di sini.</p>
                            </div>
                        )}

                        {detailPresensi && (() => {
                            const pList = (presensiHariIni || []).filter((pr) => pr.pegawai_id === detailPresensi);
                            if (pList.length === 0) return null;
                            const nama = pList[0]?.pegawai?.nama_lengkap || 'Guru';
                            const harian = presensiMap[detailPresensi];
                            const pNgajar = pList.filter((pr) => pr.jadwal_id);

                            return (
                                <div id="panel-detail-presensi" className="border-t border-border bg-surface/40 px-5 py-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <h4 className="text-sm font-bold text-primary">{nama}</h4>
                                        <button type="button" onClick={() => setDetailPresensi(null)} className="cursor-pointer text-xs font-semibold text-text-muted transition-colors hover:text-primary">
                                            Tutup
                                        </button>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div className="rounded-lg border border-border bg-white p-3">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wider text-text-secondary">Presensi Kantor</p>
                                            <div className="grid grid-cols-2 gap-2 text-xs">
                                                <div>
                                                    <span className="text-text-muted">Masuk</span>
                                                    <p className="font-semibold text-primary">{harian?.jam_masuk?.substring(0, 8) || '·'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-text-muted">Keluar</span>
                                                    <p className="font-semibold text-primary">{harian?.jam_keluar?.substring(0, 8) || '·'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-text-muted">Status</span>
                                                    <p className="mt-0.5">{harian ? <StatusBadge status={harian.status} /> : '·'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-text-muted">Jarak</span>
                                                    <p className="font-semibold">{harian?.jarak_meter != null ? `${harian.jarak_meter}m` : '·'}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="rounded-lg border border-border bg-white p-3">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wider text-text-secondary">Per Jadwal Mengajar</p>
                                            {pNgajar.length > 0 ? pNgajar.slice(0, 3).map((p, i) => (
                                                <div key={i} className="mb-1.5 flex items-center gap-2 text-xs last:mb-0">
                                                    <span className="font-mono text-text-muted">{p.jam_masuk?.substring(0, 8)}–{p.jam_keluar?.substring(0, 8) || '?'}</span>
                                                    <StatusBadge status={p.status} />
                                                </div>
                                            )) : <p className="text-xs text-text-muted">Belum ada absen per jadwal</p>}
                                        </div>
                                    </div>
                                    {harian?.lokasi_perlu_review && (
                                        <div className="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] font-semibold text-amber-700">
                                            <AlertTriangle className="h-3.5 w-3.5" /> Lokasi perlu ditinjau admin
                                        </div>
                                    )}
                                </div>
                            );
                        })()}
                    </div>

                    {/* Tren Kehadiran + Payroll */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div className="card p-6">
                            <h3 className="flex flex-wrap items-center gap-2 text-base font-bold text-primary">
                                <TrendingUp className="h-5 w-5 text-primary" />
                                Tren Kehadiran 7 Hari
                                {chartData.length > 0 && (
                                    <span className="text-xs font-semibold text-text-secondary">
                                        rata-rata {rataHadirTrend} orang/hari
                                    </span>
                                )}
                            </h3>
                            <div className="mt-4">
                                {chartData.length === 0 ? (
                                    <div className="flex h-56 flex-col items-center justify-center text-center">
                                        <TrendingUp className="mb-2 h-8 w-8 text-text-muted" />
                                        <p className="text-sm text-text-secondary">Tidak ada data kehadiran 7 hari terakhir.</p>
                                    </div>
                                ) : (
                                    <>
                                        <div
                                            className="flex h-44 items-end gap-2"
                                            role="img"
                                            aria-label={`Jumlah hadir per hari: ${chartData.map((d) => `${d.day} ${d.hadir}`).join(', ')}`}
                                        >
                                            {chartData.map((d) => (
                                                <div key={d.date || d.day} className="flex h-full flex-1 flex-col items-center justify-end gap-1.5" title={`${d.day} ${d.date || ''}: ${d.hadir} orang`}>
                                                    <span className="text-xs font-bold tabular-nums text-primary">{d.hadir}</span>
                                                    <div
                                                        className="w-full rounded-t-md bg-primary/80"
                                                        style={{ height: `${maxHadirTrend > 0 ? Math.max(4, Math.round(((d.hadir || 0) / maxHadirTrend) * 100)) : 4}%` }}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-2 flex gap-2 border-t border-border/60 pt-2">
                                            {chartData.map((d) => (
                                                <span key={d.date || d.day} className="flex-1 text-center text-[11px] font-semibold text-text-secondary">{d.day?.slice(0, 3)}</span>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="card p-6">
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-text-muted">
                                <Banknote className="h-4 w-4 text-primary" />
                                Payroll Bulan Ini
                            </div>
                            <p className="mt-3 text-3xl font-extrabold text-primary tabular-nums">
                                {s.pengeluaran_gaji > 0 ? formatRupiah(s.pengeluaran_gaji) : 'Rp 0'}
                            </p>
                            <div className="mt-3">
                                {s.is_estimasi_payroll ? (
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-700">
                                        <Hourglass className="h-3.5 w-3.5" /> Estimasi Sementara
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700">
                                        <CheckCircle2 className="h-3.5 w-3.5" /> Sudah Diproses
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Kontrak Berakhir + Pengumuman */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div className="card p-0 overflow-hidden">
                            <div className="flex items-center justify-between border-b border-border/60 bg-surface/50 px-5 py-4">
                                <h3 className="flex items-center gap-2 text-base font-bold text-primary">
                                    <CalendarClock className="h-5 w-5 text-amber-600" />
                                    Kontrak Berakhir (30 Hari)
                                </h3>
                                {hasKontrak && (
                                    <Link href={route('pegawai.index')} className="flex items-center gap-1 text-xs font-bold text-primary transition-colors hover:text-primary/70">
                                        Lihat pegawai <ArrowRight className="h-3.5 w-3.5" />
                                    </Link>
                                )}
                            </div>
                            {hasKontrak ? (
                                <div className="divide-y divide-border/50">
                                    {kontrakBerakhir.slice(0, 5).map((pegawai) => {
                                        const tone = kontrakTone(pegawai.sisa_hari);

                                        return (
                                            <div key={pegawai.id} className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-surface/60">
                                                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold ${avatarTone(pegawai.nama_lengkap)}`}>
                                                    {initials(pegawai.nama_lengkap)}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-sm font-bold text-primary">{pegawai.nama_lengkap}</div>
                                                    <div className="text-[11px] text-text-secondary">{pegawai.unit_nama}</div>
                                                </div>
                                                <div className="shrink-0 text-right">
                                                    <div className="text-xs font-semibold text-text-primary">{pegawai.kontrak_berakhir}</div>
                                                    <span className={`mt-0.5 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold ${tone.badge}`}>
                                                        {pegawai.sisa_hari <= 0 ? 'Lewat' : `Sisa ${tone.label}`}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center py-12 text-center">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface">
                                        <CheckCircle2 className="h-7 w-7 text-emerald-500" />
                                    </div>
                                    <p className="mt-3 text-sm font-bold text-primary">Tidak ada kontrak berakhir</p>
                                    <p className="mt-1 text-xs text-text-secondary">Semua kontrak aman dalam 30 hari ke depan.</p>
                                </div>
                            )}
                        </div>

                        <div className="card p-0 overflow-hidden">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/60 bg-surface/50 px-5 py-4">
                                <h3 className="flex items-center gap-2 text-base font-bold text-primary">
                                    <Megaphone className="h-5 w-5 text-primary" />
                                    Pengumuman Terbaru
                                </h3>
                                <Link href={route('pengumuman.index')} className="text-xs font-semibold text-primary underline-offset-2 hover:underline">
                                    Kelola pengumuman →
                                </Link>
                            </div>
                            {pengumuman.length > 0 ? (
                                <div className="divide-y divide-border/50">
                                    {pengumuman.slice(0, 5).map((a) => {
                                        const st = pengumumanStatus(a);

                                        return (
                                            <div key={a.id} className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-surface/60">
                                                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${a.is_pinned ? 'bg-amber-50 text-amber-600' : 'bg-surface text-text-secondary'}`}>
                                                    {a.is_pinned ? <PinIcon className="h-4 w-4" /> : <Megaphone className="h-4 w-4" />}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-sm font-bold text-primary">{a.title}</div>
                                                    <div className="mt-0.5 text-[11px] text-text-secondary">
                                                        {formatTgl(a.published_at)}{a.unit_nama ? ` · ${a.unit_nama}` : ' · Semua Unit'}
                                                    </div>
                                                </div>
                                                <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold ${st.cls}`}>{st.label}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center py-10 text-center">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface">
                                        <Megaphone className="h-7 w-7 text-text-muted" />
                                    </div>
                                    <p className="mt-3 text-sm font-bold text-primary">Belum ada pengumuman</p>
                                    <Link href={route('pengumuman.index')} className="mt-2 text-xs font-semibold text-primary underline-offset-2 hover:underline">
                                        Buat pengumuman pertama →
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
