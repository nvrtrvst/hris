import React, { useState, useEffect, useMemo, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    ArrowLeftRight,
    CalendarRange,
    CheckCircle2,
    ChevronDown,
    Clock3,
    GraduationCap,
    Info,
    LayoutGrid,
    Loader2,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    Search,
    ShieldCheck,
    Sparkles,
    Trash2,
    Users,
    UserRound,
} from 'lucide-react';

const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const JENIS_META = {
    mengajar: { badge: 'bg-blue-50 text-blue-700 border-blue-200', label: 'Mengajar' },
    piket: { badge: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Piket' },
    ekskul: { badge: 'bg-purple-50 text-purple-700 border-purple-200', label: 'Ekskul' },
    shift_satpam: { badge: 'bg-slate-100 text-slate-700 border-slate-200', label: 'Shift Satpam' },
    shift_kebersihan: { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Shift Kebersihan' },
    lainnya: { badge: 'bg-gray-100 text-gray-600 border-gray-200', label: 'Lainnya' },
};

const jenisBadge = (jenis) => JENIS_META[jenis] || JENIS_META.lainnya;

const durasiMenit = (mulai, selesai) => {
    const [h1, m1] = String(mulai || '0:0').split(':').map(Number);
    const [h2, m2] = String(selesai || '0:0').split(':').map(Number);

    return Math.max(0, (h2 * 60 + m2) - (h1 * 60 + m1));
};

const fmtDurasi = (menit) => {
    if (!menit) return '0 menit';
    const h = Math.floor(menit / 60);
    const m = menit % 60;

    return h > 0 ? `${h}j ${m}m` : `${m} mnt`;
};

const StatCard = ({ label, value, sub, Icon, iconBg, iconCls }) => (
    <div className="stat-card group hover:shadow-card-hover transition-shadow">
        <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${iconBg} transition-transform group-hover:scale-105`}>
            <Icon className={`h-5 w-5 ${iconCls}`} />
        </div>
        <div className="min-w-0">
            <p className="text-2xl font-extrabold leading-none text-primary tabular-nums">{value}</p>
            <p className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
            {sub && <p className="mt-0.5 text-[10px] text-text-muted truncate">{sub}</p>}
        </div>
    </div>
);

export default function Index({ auth, jadwals, pegawais, units, mapel, kelasLabels, stats = {}, filters = {} }) {
    const isAdmin = auth.permissions?.includes('view_jadwal');
    const { flash = {}, errors = {} } = usePage().props;
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const onStart = () => setProcessing(true);
        const onFinish = () => setProcessing(false);
        router.on('start', onStart);
        router.on('finish', onFinish);

        return () => {
            router.off('start', onStart);
            router.off('finish', onFinish);
        };
    }, []);

    const [unitFilter, setUnitFilter] = useState(filters.unit_sekolah_id || '');
    const [kelasFilter, setKelasFilter] = useState(filters.kelas_label || '');
    const [searchName, setSearchName] = useState(filters.search || '');
    const [showRekapModal, setShowRekapModal] = useState(false);
    const [viewMode, setViewMode] = useState('matrix');
    const [expandedGuru, setExpandedGuru] = useState(null);

    // Modal Generate
    const [showGenerateModal, setShowGenerateModal] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [genData, setGenData] = useState({
        tahun_ajaran: '2026/2027',
        semester: '1',
        unit_sekolah_id: '',
        mata_pelajaran_id: '',
        waktu_mulai: '07:00',
        waktu_selesai: '15:00',
    });

    // Modal Swap
    const [showSwapModal, setShowSwapModal] = useState(false);
    const [swapData, setSwapData] = useState({ jadwal_asal_id: '', jadwal_tujuan_id: '' });
    const [targetPegawaiId, setTargetPegawaiId] = useState('');

    // Hitung jumlah pegawai per unit buat info di modal generate
    const pegawaiCountByUnit = useMemo(() => {
        const acc = {};
        pegawais.forEach((p) => {
            const u = p.units?.[0]?.id || '0';
            acc[u] = (acc[u] || 0) + 1;
        });

        return acc;
    }, [pegawais]);

    const selectedUnitPegCount = genData.unit_sekolah_id
        ? (pegawaiCountByUnit[genData.unit_sekolah_id] || 0)
        : pegawais.length;

    // ── Index O(1): jadwal per pegawai (hemat filter berulang O(n) di dalam loop render) ──
    const jadwalByPegawai = useMemo(() => {
        const m = new Map();
        jadwals.forEach((j) => {
            if (!m.has(j.pegawai_id)) m.set(j.pegawai_id, []);
            m.get(j.pegawai_id).push(j);
        });

        return m;
    }, [jadwals]);

    const jadwalById = useMemo(() => {
        const m = new Map();
        jadwals.forEach((j) => m.set(j.id, j));

        return m;
    }, [jadwals]);

    const hasFilter = Boolean(searchName || unitFilter || kelasFilter);

    // Ref filter terbaru — cegah stale closure saat debounce search tertunda
    // lalu user mengganti unit/kelas sebelum timer jalan.
    const latestFiltersRef = useRef({ unitFilter, kelasFilter });
    latestFiltersRef.current = { unitFilter, kelasFilter };

    // ── Search debounce → server (filter konsisten dengan matriks) ──
    useEffect(() => {
        if (searchName === (filters.search || '')) return;
        const timer = setTimeout(() => {
            const { unitFilter: u, kelasFilter: k } = latestFiltersRef.current;
            router.get(route('jadwal.index'), { unit_sekolah_id: u, kelas_label: k, search: searchName }, { preserveState: true, preserveScroll: true });
        }, 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchName]);

    const handleUnitFilterChange = (e) => {
        const value = e.target.value;
        setUnitFilter(value);
        setKelasFilter('');
        router.get(route('jadwal.index'), { unit_sekolah_id: value, kelas_label: '', search: searchName }, { preserveState: true });
    };

    const handleKelasFilterChange = (e) => {
        const value = e.target.value;
        setKelasFilter(value);
        router.get(route('jadwal.index'), { unit_sekolah_id: unitFilter, kelas_label: value, search: searchName }, { preserveState: true });
    };

    const resetFilters = () => {
        setSearchName('');
        setUnitFilter('');
        setKelasFilter('');
        router.get(route('jadwal.index'), {}, { preserveState: true });
    };

    const handleGenerate = (e) => {
        e.preventDefault();
        setGenerating(true);
        router.post(route('jadwal.generate'), genData, {
            onSuccess: () => {
                setShowGenerateModal(false);
                setGenerating(false);
            },
            onError: () => setGenerating(false),
            onFinish: () => setGenerating(false),
        });
    };

    const handleSwap = (e) => {
        e.preventDefault();
        if (!swapData.jadwal_asal_id || !swapData.jadwal_tujuan_id) {
            alert('Pilih jadwal target terlebih dahulu!');
            return;
        }
        router.post(route('jadwal.swap'), swapData, {
            onSuccess: () => {
                setShowSwapModal(false);
                setSwapData({ jadwal_asal_id: '', jadwal_tujuan_id: '' });
                setTargetPegawaiId('');
            },
        });
    };

    const s = stats || { total_jadwal: 0, total_mengajar: 0, total_jam_menit: 0, total_pegawai: 0, total_kelas: 0 };

    const statCards = [
        { label: 'Total Jadwal', value: s.total_jadwal, Icon: CalendarDays, iconBg: 'bg-primary/10', iconCls: 'text-primary' },
        { label: 'Jadwal Mengajar', value: s.total_mengajar, Icon: BookOpen, iconBg: 'bg-blue-100', iconCls: 'text-blue-600' },
        { label: 'Jam Mengajar', value: fmtDurasi(s.total_jam_menit), sub: 'per minggu', Icon: Clock3, iconBg: 'bg-emerald-100', iconCls: 'text-emerald-600' },
        { label: 'Pegawai', value: s.total_pegawai, sub: 'aktif terfilter', Icon: Users, iconBg: 'bg-amber-100', iconCls: 'text-amber-600' },
        { label: 'Kelas Unik', value: s.total_kelas, Icon: GraduationCap, iconBg: 'bg-purple-100', iconCls: 'text-purple-600' },
    ];

    const filterSelect = 'select-field text-xs h-9 w-full md:w-auto md:min-w-[150px]';

    const unitSingkatan = (pegawai) => pegawai?.units?.[0]?.singkatan || '-';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">{isAdmin ? 'Jadwal Pegawai' : 'Jadwal Pribadi'}</h2>}
        >
            <Head title="Jadwal Pegawai" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {/* Flash */}
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between gap-3">
                        <div>
                            <h3 className="text-xl font-bold text-primary">{isAdmin ? 'Jadwal Mingguan (Matriks)' : 'Jadwal Saya'}</h3>
                            <p className="mt-1 text-sm text-text-secondary">Pantau beban mengajar & antrean kelas semua pegawai dalam satu papan tanpa bentrok.</p>
                        </div>
                        {isAdmin && (
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => setViewMode('matrix')}
                                    className={`btn-sm flex items-center gap-1.5 ${viewMode === 'matrix' ? 'btn-primary' : 'btn-secondary'}`}
                                >
                                    <LayoutGrid className="h-3.5 w-3.5" /> Matriks
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setViewMode('guru')}
                                    className={`btn-sm flex items-center gap-1.5 ${viewMode === 'guru' ? 'btn-primary' : 'btn-secondary'}`}
                                >
                                    <UserRound className="h-3.5 w-3.5" /> Per Guru
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Stats */}
                    {isAdmin && (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            {statCards.map((card) => <StatCard key={card.label} {...card} />)}
                        </div>
                    )}

                    {/* Filter bar */}
                    {isAdmin && (
                        <div className="card p-5">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                                <div className="relative lg:col-span-2">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input
                                        type="text"
                                        value={searchName}
                                        onChange={(e) => setSearchName(e.target.value)}
                                        placeholder="Cari nama pegawai…"
                                        className="input-field h-9 pl-9 text-xs w-full"
                                    />
                                </div>
                                <select className={filterSelect} value={unitFilter} onChange={handleUnitFilterChange}>
                                    <option value="">Semua Unit Sekolah</option>
                                    {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </select>
                                <select className={filterSelect} value={kelasFilter} onChange={handleKelasFilterChange}>
                                    <option value="">Semua Kelas</option>
                                    {kelasLabels.map((kl) => <option key={kl} value={kl}>{kl}</option>)}
                                </select>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 border-t border-border/60 pt-4">
                                {hasFilter && (
                                    <button type="button" onClick={resetFilters} className="btn-secondary btn-sm flex items-center gap-1.5">
                                        <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                    </button>
                                )}
                                <div className="ml-auto flex flex-wrap items-center gap-2 print:hidden">
                                    <button type="button" onClick={() => window.print()} className="btn-secondary btn-sm flex items-center gap-1.5">
                                        <Printer className="h-3.5 w-3.5" /> Cetak PDF
                                    </button>
                                    <button type="button" onClick={() => setShowGenerateModal(true)} className="btn-primary btn-sm bg-accent text-primary-800 hover:bg-yellow-500 flex items-center gap-1.5">
                                        <Sparkles className="h-3.5 w-3.5" /> Generate Otomatis
                                    </button>
                                    <button type="button" onClick={() => setShowRekapModal(true)} className="btn-secondary btn-sm flex items-center gap-1.5">
                                        <CalendarRange className="h-3.5 w-3.5" /> Rekap Kelas
                                    </button>
                                    <Link href={route('jadwal.create')} className="btn-primary btn-sm flex items-center gap-1.5">
                                        <Plus className="h-3.5 w-3.5" /> Tambah Jadwal
                                    </Link>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ─── ADMIN: Per Guru View ─── */}
                    {isAdmin && viewMode === 'guru' && (
                        <div className="space-y-3">
                            {pegawais.length === 0 ? (
                                <div className="card p-12 text-center">
                                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                        <UserRound className="h-8 w-8 text-border" />
                                    </div>
                                    <p className="mt-4 text-base font-bold text-primary">Tidak ada pegawai ditemukan</p>
                                    <p className="mt-1 text-sm text-text-secondary">Coba ubah kata kunci pencarian atau bersihkan filter.</p>
                                    {hasFilter && (
                                        <button onClick={resetFilters} className="btn-secondary btn-sm mt-4 flex items-center gap-1.5">
                                            <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                        </button>
                                    )}
                                </div>
                            ) : (
                                pegawais.map((pegawai) => {
                                    const semua = jadwalByPegawai.get(pegawai.id) || [];
                                    const pJadwals = semua.filter((j) => j.jenis_jadwal === 'mengajar');
                                    const totalMenit = pJadwals.reduce((sum, j) => sum + durasiMenit(j.jam_mulai, j.jam_selesai), 0);
                                    const totalJam = totalMenit / 60;
                                    const kelasCount = new Set(pJadwals.map((j) => j.kelas_label).filter(Boolean)).size;
                                    const selectedUnit = units.find((u) => u.id == (pegawai.units?.[0]?.id || 0));
                                    const maxJam = selectedUnit?.max_jam_minggu || 30;
                                    const pct = Math.min(100, Math.round((totalJam / maxJam) * 100));
                                    const isExpanded = expandedGuru === pegawai.id;

                                    return (
                                        <div key={pegawai.id} className="card p-4 transition-shadow hover:shadow-card-hover">
                                            <button
                                                type="button"
                                                onClick={() => setExpandedGuru(isExpanded ? null : pegawai.id)}
                                                className="w-full text-left cursor-pointer"
                                            >
                                                <div className="flex items-center justify-between gap-4">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-bold text-primary text-sm truncate">{pegawai.nama_lengkap}</span>
                                                            <span className="text-xs text-text-muted shrink-0">{unitSingkatan(pegawai)}</span>
                                                        </div>
                                                        <div className="mt-1 flex items-center gap-2 text-xs text-text-secondary">
                                                            <span>{kelasCount} kelas</span>
                                                            <span>•</span>
                                                            <span>{pJadwals.length} jadwal</span>
                                                        </div>
                                                    </div>
                                                    <div className="shrink-0 text-right min-w-[130px]">
                                                        <div className="text-sm font-bold text-primary">{totalJam.toFixed(1)} / {maxJam} jam</div>
                                                        <div className="mt-1 h-1.5 w-full bg-border rounded-full overflow-hidden">
                                                            <div className={`h-full rounded-full transition-all ${pct >= 100 ? 'bg-danger' : 'bg-primary'}`} style={{ width: `${pct}%` }} />
                                                        </div>
                                                    </div>
                                                    <ChevronDown className={`h-4 w-4 text-text-muted transition-transform shrink-0 ${isExpanded ? 'rotate-180' : ''}`} />
                                                </div>
                                            </button>

                                            {isExpanded && (
                                                <div className="mt-3 pt-3 border-t border-border space-y-1.5">
                                                    {pJadwals.length === 0 ? (
                                                        <p className="text-xs text-text-muted py-2">Belum ada jadwal mengajar.</p>
                                                    ) : (
                                                        (() => {
                                                            const grouped = {};
                                                            pJadwals.forEach((j) => {
                                                                const key = `${j.hari}-${j.jam_mulai}-${j.jam_selesai}`;
                                                                if (!grouped[key]) grouped[key] = { ...j, count: 1 };
                                                            });

                                                            return Object.values(grouped).sort((a, b) => (
                                                                DAYS.indexOf(a.hari) - DAYS.indexOf(b.hari) || a.jam_mulai.localeCompare(b.jam_mulai)
                                                            )).map((j, i) => (
                                                                <div key={i} className="flex items-center gap-3 px-2 py-1.5 rounded hover:bg-surface text-xs">
                                                                    <span className="font-mono font-semibold text-primary w-24 shrink-0">{j.hari?.substring(0, 3)}, {j.jam_mulai?.substring(0, 5)}-{j.jam_selesai?.substring(0, 5)}</span>
                                                                    <span className="font-medium text-text-primary truncate">{j.mata_pelajaran?.nama || '-'}</span>
                                                                    <span className="text-text-muted truncate">{j.kelas_label || '-'}</span>
                                                                </div>
                                                            ));
                                                        })()
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    )}

                    {/* Print Header */}
                    <div className="hidden print:block">
                        <div className="print-header">
                            <h1>JADWAL PEGAWAI</h1>
                            <div className="sub">
                                Unit: {units.find((u) => u.id == unitFilter)?.nama || 'Semua Unit'} &mdash; {filters.tahun_ajaran ? `T.A. ${filters.tahun_ajaran}` : ''}{filters.semester ? ` Semester ${filters.semester}` : ''}
                            </div>
                        </div>
                    </div>

                    {/* ─── ADMIN: Matrix Table Board ─── */}
                    {isAdmin && viewMode === 'matrix' && (
                        <div className={`card p-0 overflow-hidden transition-opacity ${processing ? 'opacity-60 pointer-events-none' : ''}`}>
                        <div className="overflow-x-auto max-h-[72vh] overflow-y-auto print:max-h-none print:overflow-visible">
                                <table className="w-full table-fixed divide-y divide-gray-200 bg-white text-sm">
                                    <thead className="bg-primary text-white sticky top-0 z-20 print:bg-gray-100 print:text-black">
                                        <tr>
                                            <th scope="col" className="w-[16%] min-w-[170px] px-3 py-3.5 text-left font-extrabold uppercase tracking-widest border-r border-primary/20 print:border-gray-300">
                                                Pegawai
                                            </th>
                                            {DAYS.map((day) => (
                                                <th key={day} scope="col" className="w-[12%] px-2 py-3.5 text-center font-extrabold uppercase tracking-widest text-accent print:text-black border-r border-primary/20 print:border-gray-300">
                                                    {day}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className={`divide-y divide-gray-200 bg-white ${processing ? 'opacity-60 pointer-events-none transition-opacity' : ''}`}>
                                        {pegawais.length > 0 ? pegawais.map((pegawai) => (
                                            <tr key={pegawai.id} className="hover:bg-gray-50 transition-colors group">
                                                <td className="px-4 py-3 border-r border-gray-200 sticky left-0 z-10 bg-white group-hover:bg-gray-50 align-top">
                                                    <div className="font-bold text-gray-900 leading-tight">{pegawai.nama_lengkap}</div>
                                                    <div className="mt-1 flex items-center text-[11px] font-medium text-gray-500">
                                                        <span className="inline-flex items-center gap-1">
                                                            <ShieldCheck className="h-3 w-3 text-accent print:hidden" />
                                                            {unitSingkatan(pegawai)}
                                                        </span>
                                                    </div>
                                                </td>
                                                {DAYS.map((day) => {
                                                    const pJadwals = (jadwalByPegawai.get(pegawai.id) || []).filter((j) => j.hari === day);

                                                    return (
                                                        <td key={day} className="px-2 py-2 border-r border-gray-100 align-top bg-gray-50/30">
                                                            <div className="flex flex-col gap-1.5">
                                                                {pJadwals.length > 0 ? pJadwals.map((jadwal) => {
                                                                    const meta = jenisBadge(jadwal.jenis_jadwal);

                                                                    return (
                                                                        <div key={jadwal.id} className="group/card relative flex flex-col items-center text-center bg-white p-2 rounded-lg shadow-sm border border-gray-200 hover:border-primary/50 transition-colors print:shadow-none print:border-gray-300">
                                                                            <span className="text-[11px] font-bold text-primary font-mono">
                                                                                {jadwal.jam_mulai.substring(0, 5)}–{jadwal.jam_selesai.substring(0, 5)}
                                                                            </span>
                                                                            {jadwal.mata_pelajaran?.nama && (
                                                                                <span className="mt-0.5 text-[10px] font-semibold text-gray-700 leading-tight">{jadwal.mata_pelajaran.nama}</span>
                                                                            )}
                                                                            {jadwal.kelas_label && (
                                                                                <span className="text-[9px] text-gray-400 leading-tight">Kls {jadwal.kelas_label}</span>
                                                                            )}
                                                                            <span className={`mt-1 text-[9px] font-bold px-1.5 py-0.5 rounded uppercase border ${meta.badge}`}>
                                                                                {meta.label}
                                                                            </span>

                                                                            {/* Action Overlay */}
                                                                            <div className="absolute inset-0 bg-primary/90 opacity-0 group-hover/card:opacity-100 flex items-center justify-center gap-2 rounded-lg transition-opacity backdrop-blur-sm print:hidden">
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => router.get(route('jadwal.edit', jadwal.id))}
                                                                                    className="text-emerald-300 hover:text-emerald-100 transform hover:scale-110 transition-all cursor-pointer"
                                                                                    title="Edit Jadwal"
                                                                                >
                                                                                    <Pencil className="h-4 w-4" />
                                                                                </button>
                                                                                <div className="w-px h-4 bg-white/30" />
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => {
                                                                                        setSwapData({ jadwal_asal_id: jadwal.id, jadwal_tujuan_id: '' });
                                                                                        setTargetPegawaiId('');
                                                                                        setShowSwapModal(true);
                                                                                    }}
                                                                                    className="text-blue-300 hover:text-blue-100 transform hover:scale-110 transition-all cursor-pointer"
                                                                                    title="Tukar Jadwal"
                                                                                >
                                                                                    <ArrowLeftRight className="h-4 w-4" />
                                                                                </button>
                                                                                <div className="w-px h-4 bg-white/30" />
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => {
                                                                                        if (confirm('Hapus jadwal ini?')) {
                                                                                            router.delete(route('jadwal.destroy', jadwal.id));
                                                                                        }
                                                                                    }}
                                                                                    className="text-red-400 hover:text-red-300 transform hover:scale-110 transition-all cursor-pointer"
                                                                                    title="Hapus Jadwal"
                                                                                >
                                                                                    <Trash2 className="h-4 w-4" />
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    );
                                                                }) : (
                                                                    <div className="text-gray-300 text-center py-2 text-xs">-</div>
                                                                )}
                                                            </div>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="8" className="px-6 py-16">
                                                    <div className="flex flex-col items-center text-center">
                                                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                                            <CalendarDays className="h-8 w-8 text-border" />
                                                        </div>
                                                        <p className="mt-4 text-base font-bold text-primary">Tidak ada data pegawai</p>
                                                        <p className="mt-1 text-sm text-text-secondary">Coba ubah filter atau kata kunci pencarian.</p>
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
                            {processing && (
                                <div className="flex items-center justify-center gap-2 border-t border-border bg-surface/50 py-3 text-xs font-semibold text-text-secondary">
                                    <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" /> Memuat jadwal…
                                </div>
                            )}
                        </div>
                    )}

                    {/* Print Footer */}
                    <div className="hidden print:block print-footer">
                        Dicetak oleh: <span className="font-bold">{auth.user.name}</span>
                        {' '}&mdash;{' '}
                        {new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                        <div className="signature-line" />
                    </div>

                    {/* Info anti-bentrok */}
                    {isAdmin && (
                        <div className="flex items-start gap-3 rounded-xl border border-info/30 bg-info-light p-4">
                            <Info className="mt-0.5 h-5 w-5 shrink-0 text-info" />
                            <p className="text-sm leading-relaxed text-info">
                                Sistem otomatis menolak penambahan jadwal jika terdeteksi bentrok (overlap waktu pada hari yang sama) untuk pegawai yang sama, meskipun di unit berbeda.
                            </p>
                        </div>
                    )}

                    {/* ─── NON-ADMIN: Jadwal Pribadi ─── */}
                    {!isAdmin && (
                        <div className="space-y-3">
                            {jadwals.length === 0 ? (
                                <div className="card p-12 text-center">
                                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                        <CalendarDays className="h-8 w-8 text-border" />
                                    </div>
                                    <p className="mt-4 text-base font-bold text-primary">Belum ada jadwal untuk Anda</p>
                                    <p className="mt-1 text-sm text-text-secondary">Jadwal akan tampil setelah admin menetapkannya.</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    {jadwals.map((j) => {
                                        const meta = jenisBadge(j.jenis_jadwal);

                                        return (
                                            <div key={j.id} className="card p-4 flex items-center gap-4 transition-shadow hover:shadow-card-hover">
                                                <div className="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-primary/5 border border-primary/10">
                                                    <span className="text-[9px] font-bold uppercase text-text-muted">{j.hari.substring(0, 3)}</span>
                                                    <span className="font-mono text-[11px] font-bold text-primary">{j.jam_mulai?.substring(0, 5)}</span>
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-primary truncate">{j.mata_pelajaran?.nama || j.jenis_jadwal}</span>
                                                        <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase ${meta.badge}`}>{meta.label}</span>
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-text-secondary">
                                                        {j.jam_mulai?.substring(0, 5)} – {j.jam_selesai?.substring(0, 5)}
                                                        {j.kelas_label && <> • Kls {j.kelas_label}</>}
                                                    </div>
                                                </div>
                                                <div className="shrink-0 text-right">
                                                    <span className="text-xs font-semibold text-text-secondary">{j.unit_sekolah?.singkatan || '-'}</span>
                                                    {j.unit_sekolah?.nama && <div className="max-w-[140px] truncate text-[10px] text-text-muted">{j.unit_sekolah.nama}</div>}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}

                    {/* ─── MODAL: Generate Otomatis ─── */}
                    {isAdmin && (
                        <Modal show={showGenerateModal} onClose={() => setShowGenerateModal(false)} maxWidth="md">
                            <div className="px-6 py-5">
                                <div className="flex items-center gap-3 mb-4">
                                    <div className="w-10 h-10 rounded-full bg-warning-light flex items-center justify-center shrink-0">
                                        <Sparkles className="w-5 h-5 text-warning" />
                                    </div>
                                    <div>
                                        <h3 className="page-title">Generate Jadwal Otomatis</h3>
                                        <p className="page-subtitle">Algoritma mengisi jadwal mengajar per pegawai tanpa bentrok</p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-2 rounded-xl border border-warning/30 bg-warning-light p-3 text-xs text-warning mb-4">
                                    <Info className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>Jadwal yang <strong>sudah ada tidak akan dihapus</strong>. Generate hanya menambah jadwal baru jika tidak ada bentrok. Anda bisa generate berulang kali.</span>
                                </div>

                                <form onSubmit={handleGenerate} className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="form-label mb-1">Unit Sekolah</label>
                                            <select
                                                value={genData.unit_sekolah_id}
                                                onChange={(e) => setGenData({ ...genData, unit_sekolah_id: e.target.value })}
                                                className="select-field"
                                            >
                                                <option value="">Semua Unit</option>
                                                {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                            </select>
                                            {selectedUnitPegCount > 0 && (
                                                <p className="mt-1 text-xs text-gray-500">≈ {selectedUnitPegCount} pegawai aktif</p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="form-label mb-1">Semester</label>
                                            <select
                                                value={genData.semester}
                                                onChange={(e) => setGenData({ ...genData, semester: e.target.value })}
                                                className="select-field"
                                            >
                                                <option value="1">1 (Ganjil)</option>
                                                <option value="2">2 (Genap)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="form-label mb-1">Mata Pelajaran <span className="text-danger">*</span></label>
                                        <select
                                            value={genData.mata_pelajaran_id}
                                            onChange={(e) => setGenData({ ...genData, mata_pelajaran_id: e.target.value })}
                                            className={`select-field ${errors?.mata_pelajaran_id ? 'input-error' : ''}`}
                                            required
                                        >
                                            <option value="">Pilih Mapel</option>
                                            {mapel.map((m) => <option key={m.id} value={m.id}>{m.nama}</option>)}
                                        </select>
                                        {errors?.mata_pelajaran_id && <p className="form-error">{errors.mata_pelajaran_id}</p>}
                                    </div>

                                    <div>
                                        <label className="form-label mb-1">Tahun Ajaran</label>
                                        <select
                                            value={genData.tahun_ajaran}
                                            onChange={(e) => setGenData({ ...genData, tahun_ajaran: e.target.value })}
                                            className="select-field"
                                        >
                                            {['2024/2025', '2025/2026', '2026/2027', '2027/2028', '2028/2029'].map((ta) => (
                                                <option key={ta} value={ta}>{ta}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="form-label mb-1">Rentang Jam</label>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="relative">
                                                <span className="absolute -top-2 left-3 text-[10px] text-text-muted bg-white px-1">Mulai</span>
                                                <input
                                                    type="time"
                                                    value={genData.waktu_mulai}
                                                    onChange={(e) => setGenData({ ...genData, waktu_mulai: e.target.value })}
                                                    className="input-field pt-2"
                                                />
                                            </div>
                                            <div className="relative">
                                                <span className="absolute -top-2 left-3 text-[10px] text-text-muted bg-white px-1">Selesai</span>
                                                <input
                                                    type="time"
                                                    value={genData.waktu_selesai}
                                                    onChange={(e) => setGenData({ ...genData, waktu_selesai: e.target.value })}
                                                    className="input-field pt-2"
                                                />
                                            </div>
                                        </div>
                                        <p className="form-hint">Blok waktu default: 07:00-09:00, 09:30-11:30, 13:00-15:00. Filter ini mempersempit blok yang digunakan.</p>
                                    </div>

                                    <div className="flex justify-end gap-3 pt-2 border-t border-border">
                                        <button type="button" onClick={() => setShowGenerateModal(false)} disabled={generating} className="btn-secondary">
                                            Batal
                                        </button>
                                        <button type="submit" disabled={generating} className="btn-primary bg-accent text-primary-800 hover:bg-yellow-500">
                                            {generating ? (
                                                <>
                                                    <Loader2 className="-ml-1 mr-2 h-4 w-4 animate-spin text-primary" />
                                                    Memproses…
                                                </>
                                            ) : 'Generate Jadwal'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </Modal>
                    )}

                    {/* ─── MODAL: Tukar Jadwal ─── */}
                    {isAdmin && (
                        <Modal show={showSwapModal} onClose={() => setShowSwapModal(false)} maxWidth="lg">
                            <div className="px-6 py-5">
                                <div className="flex items-center gap-3 mb-5">
                                    <div className="w-10 h-10 rounded-full bg-info-light flex items-center justify-center shrink-0">
                                        <ArrowLeftRight className="w-5 h-5 text-info" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold text-primary">Tukar Jadwal</h3>
                                        <p className="text-sm text-text-secondary">Pilih pegawai dan jadwal target untuk ditukar kepemilikannya.</p>
                                    </div>
                                </div>

                                {errors?.conflict && (
                                    <div className="mb-4 bg-danger-light text-danger p-3 rounded-card text-sm border border-danger/20">
                                        {errors.conflict}
                                    </div>
                                )}

                                {/* Asal */}
                                <div className="mb-4">
                                    <label className="form-label font-semibold mb-2 flex items-center gap-2">
                                        <span className="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center font-bold">1</span>
                                        Jadwal Asal
                                    </label>
                                    {(() => {
                                        const j = jadwalById.get(swapData.jadwal_asal_id);
                                        if (!j) return <div className="p-3 bg-surface border border-border rounded-card text-sm text-text-muted">Pilih jadwal dari matriks (ikon tukar) untuk memulai.</div>;

                                        return (
                                            <div className="p-3 bg-surface border border-primary/20 rounded-card">
                                                <div className="font-bold text-primary text-sm">{j.pegawai?.nama_lengkap}</div>
                                                <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-text-secondary">
                                                    <span>{j.hari}, {j.jam_mulai?.substring(0, 5)}-{j.jam_selesai?.substring(0, 5)}</span>
                                                    <span className="font-medium text-primary/70 uppercase">{j.jenis_jadwal}</span>
                                                    {j.mata_pelajaran?.nama && <span>{j.mata_pelajaran.nama}</span>}
                                                    {j.kelas_label && <span>Kls: {j.kelas_label}</span>}
                                                    <span>{j.unit_sekolah?.singkatan || '-'}</span>
                                                </div>
                                            </div>
                                        );
                                    })()}
                                </div>

                                {/* Target Pegawai */}
                                <div className="mb-4">
                                    <label className="form-label font-semibold mb-2 flex items-center gap-2">
                                        <span className="w-5 h-5 rounded-full bg-accent text-primary-800 text-xs flex items-center justify-center font-bold">2</span>
                                        Tukar Dengan Pegawai
                                    </label>
                                    <select
                                        value={targetPegawaiId}
                                        onChange={(e) => {
                                            setTargetPegawaiId(e.target.value);
                                            setSwapData({ ...swapData, jadwal_tujuan_id: '' });
                                        }}
                                        className="select-field"
                                    >
                                        <option value="">-- Pilih Pegawai --</option>
                                        {(() => {
                                            const asal = jadwalById.get(swapData.jadwal_asal_id);
                                            const unitId = asal?.unit_sekolah_id;

                                            return pegawais.filter((p) => {
                                                if (asal && asal.pegawai_id === p.id) return false;
                                                if (unitId) return p.units?.some((u) => u.id == unitId);

                                                return true;
                                            }).map((p) => <option key={p.id} value={p.id}>{p.nama_lengkap}</option>);
                                        })()}
                                    </select>
                                </div>

                                {/* Target Jadwal */}
                                {targetPegawaiId && (
                                    <div className="mb-4">
                                        <label className="form-label font-semibold mb-2 flex items-center gap-2">
                                            <span className="w-5 h-5 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center font-bold">3</span>
                                            Pilih Jadwal Target
                                        </label>
                                        <div className="space-y-1.5 max-h-48 overflow-y-auto">
                                            {(() => {
                                                const asal = jadwalById.get(swapData.jadwal_asal_id);
                                                const unitId = asal?.unit_sekolah_id;
                                                const targets = (jadwalByPegawai.get(Number(targetPegawaiId)) || [])
                                                    .filter((j) => !unitId || j.unit_sekolah_id == unitId);
                                                if (targets.length === 0) {
                                                    return <p className="p-3 bg-surface border border-border rounded-card text-sm text-text-muted">Pegawai ini tidak memiliki jadwal.</p>;
                                                }

                                                return targets.map((j) => (
                                                    <button
                                                        key={j.id}
                                                        type="button"
                                                        onClick={() => setSwapData({ ...swapData, jadwal_tujuan_id: j.id })}
                                                        className={`w-full text-left p-3 rounded-card border text-sm transition-colors cursor-pointer ${
                                                            swapData.jadwal_tujuan_id === j.id
                                                                ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                                                : 'border-border bg-surface hover:border-primary/40'
                                                        }`}
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <span className="font-bold text-text-primary">{j.hari}, {j.jam_mulai?.substring(0, 5)}-{j.jam_selesai?.substring(0, 5)}</span>
                                                                <span className={`ml-2 text-[10px] font-bold px-1.5 py-0.5 rounded uppercase border ${jenisBadge(j.jenis_jadwal).badge}`}>{j.jenis_jadwal}</span>
                                                            </div>
                                                            <div className="text-xs text-text-secondary text-right">
                                                                {j.mata_pelajaran?.nama && <div>{j.mata_pelajaran.nama}</div>}
                                                                {j.kelas_label && <div>{j.kelas_label}</div>}
                                                            </div>
                                                        </div>
                                                        {swapData.jadwal_tujuan_id === j.id && (
                                                            <div className="mt-1 text-xs text-primary font-medium flex items-center gap-1">
                                                                <CheckCircle2 className="h-3 w-3" /> Dipilih
                                                            </div>
                                                        )}
                                                    </button>
                                                ));
                                            })()}
                                        </div>
                                    </div>
                                )}

                                {/* Preview hasil tukar */}
                                {swapData.jadwal_asal_id && swapData.jadwal_tujuan_id && (() => {
                                    const asal = jadwalById.get(swapData.jadwal_asal_id);
                                    const tujuan = jadwalById.get(swapData.jadwal_tujuan_id);
                                    if (!asal || !tujuan) return null;

                                    return (
                                        <div className="mb-4 p-3 bg-info-light border border-info/30 rounded-card">
                                            <p className="text-xs font-bold text-info uppercase tracking-wider mb-2">Preview Hasil Tukar</p>
                                            <div className="grid grid-cols-2 gap-3 text-xs">
                                                <div>
                                                    <p className="text-text-muted mb-0.5">← {asal.pegawai?.nama_lengkap} mendapat:</p>
                                                    <p className="font-semibold text-text-primary">{tujuan.hari}, {tujuan.jam_mulai?.substring(0, 5)}-{tujuan.jam_selesai?.substring(0, 5)}</p>
                                                </div>
                                                <div>
                                                    <p className="text-text-muted mb-0.5">→ {tujuan.pegawai?.nama_lengkap} mendapat:</p>
                                                    <p className="font-semibold text-text-primary">{asal.hari}, {asal.jam_mulai?.substring(0, 5)}-{asal.jam_selesai?.substring(0, 5)}</p>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })()}

                                <div className="flex justify-end gap-3 pt-4 border-t border-border">
                                    <button type="button" onClick={() => setShowSwapModal(false)} className="btn-secondary">
                                        Batal
                                    </button>
                                    <button
                                        onClick={handleSwap}
                                        disabled={!swapData.jadwal_tujuan_id}
                                        className="btn-primary"
                                    >
                                        <ArrowLeftRight className="h-4 w-4 mr-1.5" />
                                        Eksekusi Tukar
                                    </button>
                                </div>
                            </div>
                        </Modal>
                    )}

                    {/* ─── MODAL: Rekap Kelas ─── */}
                    {isAdmin && (
                        <Modal show={showRekapModal} onClose={() => setShowRekapModal(false)} maxWidth="2xl">
                            <div className="px-6 py-5 max-h-[90vh] overflow-y-auto">
                                <div className="flex items-center gap-3 mb-5">
                                    <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                                        <CalendarRange className="w-5 h-5 text-primary" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold text-primary">Rekap Kelas</h3>
                                        <p className="text-sm text-text-secondary">Mapel, guru, dan total jam per kelas.</p>
                                    </div>
                                </div>

                                {(() => {
                                    const grouped = {};
                                    jadwals.filter((j) => j.kelas_label && j.jenis_jadwal === 'mengajar').forEach((j) => {
                                        if (!grouped[j.kelas_label]) grouped[j.kelas_label] = {};
                                        const mapelId = j.mata_pelajaran?.id || 0;
                                        const mapelName = j.mata_pelajaran?.nama || 'Tanpa Mapel';
                                        if (!grouped[j.kelas_label][mapelId]) {
                                            grouped[j.kelas_label][mapelId] = { nama: mapelName, guru: {} };
                                        }
                                        const pegNama = j.pegawai?.nama_lengkap || 'Tanpa Nama';
                                        const durasi = durasiMenit(j.jam_mulai, j.jam_selesai);
                                        grouped[j.kelas_label][mapelId].guru[pegNama] = (grouped[j.kelas_label][mapelId].guru[pegNama] || 0) + durasi;
                                    });

                                    const entries = Object.entries(grouped).sort((a, b) => a[0].localeCompare(b[0]));
                                    if (entries.length === 0) {
                                        return <div className="p-6 text-center text-text-muted">Belum ada jadwal mengajar dengan kelas.</div>;
                                    }

                                    return (
                                        <div className="space-y-6">
                                            {entries.map(([kelas, mapels]) => {
                                                const mapelEntries = Object.entries(mapels);
                                                const totalJam = mapelEntries.reduce((sum, [, m]) => sum + Object.values(m.guru).reduce((a, b) => a + b, 0), 0);

                                                return (
                                                    <div key={kelas} className="bg-surface border border-border rounded-card overflow-hidden">
                                                        <div className="px-4 py-3 bg-primary/5 border-b border-border flex items-center justify-between">
                                                            <h4 className="font-bold text-primary text-sm">Kls {kelas}</h4>
                                                            <span className="text-xs text-text-muted">{fmtDurasi(totalJam)} / {mapelEntries.length} mapel</span>
                                                        </div>
                                                        <div className="divide-y divide-border">
                                                            {mapelEntries.sort((a, b) => a[1].nama.localeCompare(b[1].nama)).map(([mapelId, m]) => (
                                                                <div key={mapelId} className="px-4 py-2.5">
                                                                    <div className="flex items-center justify-between">
                                                                        <span className="font-medium text-sm text-text-primary">{m.nama}</span>
                                                                        <span className="text-xs text-text-muted">{fmtDurasi(Object.values(m.guru).reduce((a, b) => a + b, 0))}</span>
                                                                    </div>
                                                                    <div className="mt-1 flex flex-wrap gap-1.5">
                                                                        {Object.entries(m.guru).sort((a, b) => b[1] - a[1]).map(([guru, menit]) => (
                                                                            <span key={guru} className="inline-flex items-center gap-1 text-xs bg-white border border-border px-2 py-0.5 rounded-full">
                                                                                <span className="text-text-primary">{guru}</span>
                                                                                <span className="text-text-muted">{menit}m</span>
                                                                            </span>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    );
                                })()}

                                <div className="mt-6 pt-4 border-t border-border flex justify-end">
                                    <button type="button" onClick={() => setShowRekapModal(false)} className="btn-secondary">
                                        Tutup
                                    </button>
                                </div>
                            </div>
                        </Modal>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
