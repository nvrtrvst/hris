import React, { useState, useEffect, useMemo, useRef } from 'react';
import { subscribeRouter } from '@/Utils/routerEvents';
import useNowEveryMinute from '@/Utils/useNowEveryMinute';
import usePolling from '@/Utils/usePolling';
import AdminShell from '@/Layouts/AdminShell';
import Dropdown from '@/Components/Dropdown';
import FilterSheet from '@/Components/FilterSheet';
import { Button, Card, Input, PageHeader, Select } from '@/Components/AdminUI';
import { DAYS, durasiMenit, fmtDurasi, jenisBadge, toMinutes } from '@/Pages/Jadwal/Partials/jadwalMeta';
import GenerateModal from '@/Pages/Jadwal/Partials/GenerateModal';
import ImportModal from '@/Pages/Jadwal/Partials/ImportModal';
import RekapModal from '@/Pages/Jadwal/Partials/RekapModal';
import SwapModal from '@/Pages/Jadwal/Partials/SwapModal';
import { SwapBulkModal, SwapDayModal, SwapSlotModal } from '@/Pages/Jadwal/Partials/SwapModals';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarPlus,
    ArrowLeftRight,
    CalendarRange,
    ChevronDown,
    Clock3,
    Info,
    LayoutGrid,
    Loader2,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    Search,
    ShieldCheck,
    Trash2,
    Upload,
    UserRound,
    School,
} from 'lucide-react';

// Indikator status mengajar hari ini — konsisten dengan halaman Presensi & Dashboard:
// "Mengajar" (amber berkedip) selama jam_selesai belum lewat, "Selesai" setelahnya.
// Hanya relevan untuk jadwal HARI INI dengan record presensi mengajar.
const JadwalLiveBadge = ({ jadwal, presensi, now }) => {
    if (!presensi?.jam_masuk) return null;
    const current = now || new Date();
    const todayStr = `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}-${String(current.getDate()).padStart(2, '0')}`;
    const isHariIni = jadwal.hari === DAYS[current.getDay() === 0 ? 6 : current.getDay() - 1];
    if (!isHariIni) return null;
    const selesai = toMinutes(jadwal.jam_selesai) <= current.getHours() * 60 + current.getMinutes();

    if (selesai) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[11px] font-bold text-emerald-700 print:hidden">
                Selesai
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-1.5 py-0.5 text-[11px] font-bold text-amber-700 print:hidden">
            <span className="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true" />
            Mengajar
        </span>
    );
};

export default function Index({ auth, jadwals, pegawais, units, mapel, kelasLabels, stats = {}, filters = {}, presensiHariIni = [], canMutateJadwal = false, needsUnitFilter = false, jpMinggu = {}, targetJpMinggu = 24 }) {
    const isAdmin = auth.permissions?.includes('view_jadwal') || auth.permissions?.includes('manage_jadwal');
    const { errors = {} } = usePage().props;
    const [processing, setProcessing] = useState(false);
    const now = useNowEveryMinute();

    // Badge beban mengajar mingguan (JP) vs target 24 JP/minggu.
    const jpBadge = (pegawaiId) => {
        const jp = jpMinggu[pegawaiId];
        if (jp === undefined) return null;
        const warna = jp > targetJpMinggu ? 'bg-red-100 text-red-700' : jp < targetJpMinggu ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700';
        return (
            <span className={`inline-flex rounded font-semibold text-[11px] px-1.5 py-0.5 ${warna}`} title={`Beban mengajar ${jp} dari target ${targetJpMinggu} JP/minggu`}>
                {jp}/{targetJpMinggu} JP
            </span>
        );
    };

    // Overlay "Memuat jadwal…" HANYA untuk navigasi penuh — polling partial reload
    // (only: ['presensiHariIni']) tidak boleh memicu flash overlay tiap 60 detik.
    useEffect(() => subscribeRouter({
        start: (visit) => { if (!visit?.only?.length) setProcessing(true); },
        finish: () => setProcessing(false),
    }), []);

    // Live polling (admin saja): refresh presensi hari ini tiap 60 detik agar badge
    // Mengajar/Selesai berganti otomatis. Berhenti saat tab tidak aktif.
    usePolling({ enabled: isAdmin, only: ['presensiHariIni'] });

    const [unitFilter, setUnitFilter] = useState(filters.unit_sekolah_id || '');
    const [kelasFilter, setKelasFilter] = useState(filters.kelas_label || '');
    const [searchName, setSearchName] = useState(filters.search || '');
    const [jenisFilter, setJenisFilter] = useState(filters.jenis_filter || 'pendidik');
    const [showRekapModal, setShowRekapModal] = useState(false);
    const [viewMode, setViewMode] = useState('matrix');
    const [expandedGuru, setExpandedGuru] = useState(null);

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

    const [showSwapModal, setShowSwapModal] = useState(false);
    const [swapData, setSwapData] = useState({ jadwal_asal_id: '', jadwal_tujuan_id: '' });
    const [targetPegawaiId, setTargetPegawaiId] = useState('');
    const [swapCandidates, setSwapCandidates] = useState(null);
    const [swapLoading, setSwapLoading] = useState(false);
    const [swapError, setSwapError] = useState('');
    const swapCache = useRef({});

    const [showDayModal, setShowDayModal] = useState(false);
    const [dayUnit, setDayUnit] = useState('');
    const [dayA, setDayA] = useState('');
    const [dayB, setDayB] = useState('');
    const [dayHari, setDayHari] = useState([]);
    const [daySubmitting, setDaySubmitting] = useState(false);

    const [showBulkModal, setShowBulkModal] = useState(false);
    const [bulkUnit, setBulkUnit] = useState('');
    const [bulkPairs, setBulkPairs] = useState([{ asal: '', tujuan: '' }]);

    const [showSlotModal, setShowSlotModal] = useState(false);
    const [slotUnit, setSlotUnit] = useState('');
    const [slotA, setSlotA] = useState('');
    const [slotB, setSlotB] = useState('');
    const [slotSubmitting, setSlotSubmitting] = useState(false);

    const [showImportModal, setShowImportModal] = useState(false);
    const [importMode, setImportMode] = useState('excel');
    const { data: importData, setData: setImportData, post: postImport, processing: importProcessing, errors: importErrors, reset: resetImport } = useForm({
        file: null,
        unit_sekolah_id: '',
        tahun_ajaran: '',
        semester: '',
        delete_existing: false,
    });

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

    // Index O(1): jadwal per pegawai, menghindari filter berulang di dalam loop render.
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

    // Chip penanda pegawai tanpa jadwal sama sekali (mis. tendik non-piket).
    const chipTanpaJadwal = (pegawaiId) => {
        if ((jadwalByPegawai.get(pegawaiId) || []).length > 0) return null;

        return (
            <span
                className="inline-flex shrink-0 rounded bg-surface px-1.5 py-0.5 text-[11px] font-semibold text-text-secondary"
                title="Belum ada jadwal di unit ini"
            >
                Tanpa jadwal
            </span>
        );
    };

    // Index kandidat swap — dari endpoint unfiltered, bukan prop matriks.
    const candJadwalByPegawai = useMemo(() => {
        const m = new Map();
        (swapCandidates?.jadwals || []).forEach((j) => {
            if (!m.has(j.pegawai_id)) m.set(j.pegawai_id, []);
            m.get(j.pegawai_id).push(j);
        });

        return m;
    }, [swapCandidates]);

    const candNamaById = useMemo(() => {
        const m = new Map();
        (swapCandidates?.pegawais || []).forEach((p) => m.set(p.id, p.nama_lengkap));

        return m;
    }, [swapCandidates]);

    const candJadwalById = useMemo(() => {
        const m = new Map();
        (swapCandidates?.jadwals || []).forEach((j) => m.set(j.id, j));

        return m;
    }, [swapCandidates]);

    const jadwalOptionLabel = (j) =>
        `${candNamaById.get(j.pegawai_id) || `Pegawai #${j.pegawai_id}`} — ${j.hari} ${String(j.jam_mulai || '').substring(0, 5)}-${String(j.jam_selesai || '').substring(0, 5)} (${j.jenis_jadwal})`;

    // Index O(1): presensi mengajar hari ini per jadwal (badge live di matriks).
    const presensiByJadwal = useMemo(() => {
        const m = new Map();
        (presensiHariIni || []).forEach((p) => {
            if (p.jadwal_id && !m.has(p.jadwal_id)) m.set(p.jadwal_id, p);
        });

        return m;
    }, [presensiHariIni]);

    const hasFilter = Boolean(searchName || unitFilter || kelasFilter || (jenisFilter && jenisFilter !== 'pendidik'));

    // Ref filter terbaru — cegah stale closure saat debounce search tertunda
    // lalu user mengganti unit/kelas sebelum timer jalan.
    const latestFiltersRef = useRef({ unitFilter, kelasFilter, jenisFilter });
    latestFiltersRef.current = { unitFilter, kelasFilter, jenisFilter };

    // Debounce search ke server agar filter konsisten dengan matriks.
    useEffect(() => {
        if (searchName === (filters.search || '')) return;
        const timer = setTimeout(() => {
            const { unitFilter: u, kelasFilter: k, jenisFilter: j } = latestFiltersRef.current;
            router.get(route('jadwal.index'), { unit_sekolah_id: u, kelas_label: k, jenis_filter: j, search: searchName }, { preserveState: true, preserveScroll: true });
        }, 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchName]);

    // Kandidat swap diambil lazy per unit — TIDAK ikut filter search matriks.
    const fetchSwapCandidates = (unitId) => {
        const key = unitId ? `u${unitId}` : 'all';
        if (swapCache.current[key]) {
            setSwapCandidates(swapCache.current[key]);

            return;
        }
        setSwapLoading(true);
        setSwapError('');
        const params = new URLSearchParams();
        if (unitId) params.append('unit_sekolah_id', unitId);
        fetch(`${route('jadwal.swap-candidates')}?${params.toString()}`, { headers: { Accept: 'application/json' } })
            .then((r) => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then((data) => {
                swapCache.current[key] = data;
                setSwapCandidates(data);
            })
            .catch(() => setSwapError('Gagal memuat daftar pegawai. Tutup lalu buka ulang modal.'))
            .finally(() => setSwapLoading(false));
    };

    useEffect(() => {
        if (!showSwapModal) return;
        const asal = jadwalById.get(swapData.jadwal_asal_id);
        // Tanpa filter unit di halaman (superadmin) → kandidat lintas unit, spt skrg.
        fetchSwapCandidates(unitFilter && asal?.unit_sekolah_id ? asal.unit_sekolah_id : null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [showSwapModal, swapData.jadwal_asal_id]);

    useEffect(() => {
        if (!showDayModal || !dayUnit) return;
        fetchSwapCandidates(Number(dayUnit));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [showDayModal, dayUnit]);

    useEffect(() => {
        if (!showBulkModal || !bulkUnit) return;
        fetchSwapCandidates(Number(bulkUnit));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [showBulkModal, bulkUnit]);

    useEffect(() => {
        if (!showSlotModal || !slotUnit) return;
        fetchSwapCandidates(Number(slotUnit));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [showSlotModal, slotUnit]);

    const buildParams = (overrides = {}) => ({
        unit_sekolah_id: unitFilter,
        kelas_label: kelasFilter,
        jenis_filter: jenisFilter,
        search: searchName,
        ...overrides,
    });

    const handleUnitFilterChange = (e) => {
        const value = e.target.value;
        setUnitFilter(value);
        setKelasFilter('');
        router.get(route('jadwal.index'), buildParams({ unit_sekolah_id: value, kelas_label: '' }), { preserveState: true });
    };

    const handleKelasFilterChange = (e) => {
        const value = e.target.value;
        setKelasFilter(value);
        router.get(route('jadwal.index'), buildParams({ kelas_label: value }), { preserveState: true });
    };

    const handleJenisFilterChange = (e) => {
        const value = e.target.value;
        setJenisFilter(value);
        router.get(route('jadwal.index'), buildParams({ jenis_filter: value }), { preserveState: true });
    };

    const resetFilters = () => {
        setSearchName('');
        setUnitFilter('');
        setKelasFilter('');
        setJenisFilter('');
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

    const handleSwapHari = () => {
        if (!dayA || !dayB || !dayHari.length) return;
        setDaySubmitting(true);
        router.post(route('jadwal.swap-hari'), {
            pegawai_a_id: dayA,
            pegawai_b_id: dayB,
            hari: dayHari,
            unit_sekolah_id: dayUnit,
        }, {
            onSuccess: () => {
                setShowDayModal(false);
                setDayA('');
                setDayB('');
                setDayHari([]);
            },
            onFinish: () => setDaySubmitting(false),
        });
    };

    const handleSwapBulk = () => {
        const pairs = bulkPairs.filter((p) => p.asal && p.tujuan);
        if (!pairs.length) return;
        router.post(route('jadwal.swap-bulk'), {
            pairs: pairs.map((p) => ({ jadwal_asal_id: p.asal, jadwal_tujuan_id: p.tujuan })),
        }, {
            onSuccess: () => setShowBulkModal(false),
        });
    };

    const handleSwapSlot = () => {
        if (!slotA || !slotB) return;
        setSlotSubmitting(true);
        router.post(route('jadwal.swap-slot'), {
            jadwal_a_id: slotA,
            jadwal_b_id: slotB,
        }, {
            onSuccess: () => {
                setShowSlotModal(false);
                setSlotA('');
                setSlotB('');
            },
            onFinish: () => setSlotSubmitting(false),
        });
    };

    const handleImportSubmit = (e) => {
        e.preventDefault();
        const url = importMode === 'excel' ? route('jadwal.import') : route('jadwal.import-pdf');
        postImport(url, {
            onSuccess: () => {
                setShowImportModal(false);
                resetImport();
            },
        });
    };

    const s = stats || { total_jadwal: 0, total_mengajar: 0, total_jam_menit: 0, total_pegawai: 0, total_kelas: 0 };

    const summaryItems = [
        { label: 'jadwal', value: s.total_jadwal },
        { label: 'jam/minggu', value: fmtDurasi(s.total_jam_menit) },
        { label: 'pegawai', value: s.total_pegawai },
        { label: 'kelas', value: s.total_kelas },
    ];

    const filterSelect = 'h-10 w-full text-xs';
    const [filterSheetOpen, setFilterSheetOpen] = React.useState(false);
    const activeFilterCount = [unitFilter, jenisFilter, kelasFilter, searchName].filter(Boolean).length;

    const unitSingkatan = (pegawai) => pegawai?.units?.[0]?.singkatan || '-';
    const todayDay = DAYS[now.getDay() === 0 ? 6 : now.getDay() - 1];
    const unitLabel = unitFilter
        ? units.find((unit) => String(unit.id) === String(unitFilter))?.singkatan || 'Unit terpilih'
        : 'Semua unit';
    const jenisLabel = jenisFilter === 'pendidik'
        ? 'Pendidik'
        : jenisFilter === 'kependidikan'
            ? 'Tenaga Kependidikan'
            : 'Semua jenis';
    const contextText = [
        unitLabel,
        jenisLabel,
        kelasFilter ? `Kelas ${kelasFilter}` : null,
        searchName ? `Cari: ${searchName}` : null,
    ].filter(Boolean).join(' · ');

    const actionButtons = (
        <>
            <Dropdown>
                <Dropdown.Trigger>
                    <Button type="button" variant="secondary" size="sm">
                        <CalendarRange className="h-3.5 w-3.5" /> Alat Jadwal <ChevronDown className="h-3.5 w-3.5" />
                    </Button>
                </Dropdown.Trigger>
                <Dropdown.Content align="right" width="48">
                    <a href={route('jadwal.export-pdf', buildParams())} className="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-text-primary hover:bg-surface">
                        <Printer className="h-4 w-4" /> Cetak PDF
                    </a>
                    {canMutateJadwal && (
                        <button type="button" onClick={() => setShowImportModal(true)} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-text-primary hover:bg-surface">
                            <Upload className="h-4 w-4" /> Import Jadwal
                        </button>
                    )}
                    {canMutateJadwal && (
                        <button type="button" onClick={() => { setDayUnit(unitFilter || units[0]?.id || ''); setShowDayModal(true); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-text-primary hover:bg-surface">
                            <ArrowLeftRight className="h-4 w-4" /> Tukar Seharian
                        </button>
                    )}
                    {canMutateJadwal && (
                        <button type="button" onClick={() => { setBulkUnit(unitFilter || units[0]?.id || ''); setBulkPairs([{ asal: '', tujuan: '' }]); setShowBulkModal(true); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-text-primary hover:bg-surface">
                            <ArrowLeftRight className="h-4 w-4" /> Tukar Banyak
                        </button>
                    )}
                    {canMutateJadwal && (
                        <button type="button" onClick={() => { setSlotUnit(unitFilter || units[0]?.id || ''); setSlotA(''); setSlotB(''); setShowSlotModal(true); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-text-primary hover:bg-surface">
                            <Clock3 className="h-4 w-4" /> Tukar Slot
                        </button>
                    )}
                    <button type="button" onClick={() => setShowRekapModal(true)} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-text-primary hover:bg-surface">
                        <CalendarRange className="h-4 w-4" /> Rekap Kelas
                    </button>
                </Dropdown.Content>
            </Dropdown>

            {canMutateJadwal && (
                <Button type="button" variant="secondary" size="sm" onClick={() => setShowGenerateModal(true)}>
                    <CalendarPlus className="h-3.5 w-3.5" /> Generate Otomatis
                </Button>
            )}
            {canMutateJadwal && (
                <Link href={route('jadwal.create')} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-bold text-white transition-colors hover:bg-primary-600">
                    <Plus className="h-3.5 w-3.5" /> Tambah Jadwal
                </Link>
            )}
        </>
    );

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">{isAdmin ? 'Jadwal Pegawai' : 'Jadwal Pribadi'}</h2>}
        >
            <Head title="Jadwal Pegawai" />
            <div className="mx-auto max-w-7xl space-y-5">
                    {isAdmin && (
                        <PageHeader
                            eyebrow="Kepegawaian"
                            title={viewMode === 'matrix' ? 'Jadwal Mingguan' : 'Jadwal Per Guru'}
                            description="Pantau beban mengajar dan antrean kelas tanpa bentrok."
                            actions={
                                <div className="inline-flex rounded-xl border border-border bg-white p-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={viewMode === 'matrix' ? 'primary' : 'ghost'}
                                        onClick={() => setViewMode('matrix')}
                                        aria-pressed={viewMode === 'matrix'}
                                    >
                                        <LayoutGrid className="h-3.5 w-3.5" /> Matriks
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={viewMode === 'guru' ? 'primary' : 'ghost'}
                                        onClick={() => setViewMode('guru')}
                                        aria-pressed={viewMode === 'guru'}
                                    >
                                        <UserRound className="h-3.5 w-3.5" /> Per Guru
                                    </Button>
                                </div>
                            }
                        />
                    )}

                    {!isAdmin && (
                        <PageHeader
                            eyebrow="Kepegawaian"
                            title="Jadwal Saya"
                            description="Jadwal mengajar dan aktivitas yang ditetapkan untuk Anda."
                        />
                    )}

                    {isAdmin && (
                        <>
                            <Card className="px-4 py-3">
                                <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                                    {summaryItems.map(({ label, value }) => (
                                        <div key={label} className="flex items-baseline gap-1.5">
                                            <span className="text-sm font-bold text-primary">{value}</span>
                                            <span className="text-xs text-text-secondary">{label}</span>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                            <p className="flex items-start gap-2 px-1 text-xs leading-relaxed text-text-secondary">
                                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-info" />
                                Penambahan jadwal ditolak otomatis jika bentrok waktu pada hari yang sama untuk pegawai yang sama, termasuk lintas unit.
                            </p>
                        </>
                    )}

                    
                    {isAdmin && (
                        <>
                        <FilterSheet
                            open={filterSheetOpen}
                            onOpenChange={setFilterSheetOpen}
                            activeCount={activeFilterCount}
                            onReset={() => { resetFilters(); setFilterSheetOpen(false); }}
                        >
                            
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                                <div className="relative lg:col-span-2">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <Input
                                        type="text"
                                        value={searchName}
                                        onChange={(e) => setSearchName(e.target.value)}
                                        placeholder="Cari nama pegawai..."
                                        aria-label="Cari nama pegawai"
                                        className="h-10 pl-9 text-xs"
                                    />
                                </div>
                                <Select aria-label="Filter unit" className={filterSelect} value={unitFilter} onChange={handleUnitFilterChange}>
                                    <option value="">Semua Unit</option>
                                    {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </Select>
                                <Select aria-label="Filter jenis pegawai" className={filterSelect} value={jenisFilter} onChange={handleJenisFilterChange}>
                                    <option value="semua">Semua Jenis</option>
                                    <option value="pendidik">Pendidik (Guru)</option>
                                    <option value="kependidikan">Tenaga Kependidikan</option>
                                </Select>
                                <Select aria-label="Filter kelas" className={filterSelect} value={kelasFilter} onChange={handleKelasFilterChange}>
                                    <option value="">Semua Kelas</option>
                                    {kelasLabels.map((kl) => <option key={kl} value={kl}>{kl}</option>)}
                                </Select>
                            </div>

                            <div className="mt-3 hidden items-center justify-between gap-3 border-t border-border pt-3 md:flex">
                                <p className="min-w-0 truncate text-xs text-text-secondary">{contextText}</p>
                                <div className="flex flex-wrap items-center justify-end gap-2">{actionButtons}</div>
                            </div>
                        </FilterSheet>

                        <div className="flex flex-wrap items-center justify-end gap-2 md:hidden print:hidden">
                            {actionButtons}
                        </div>
                        </>
                    )}

                    
                    {isAdmin && needsUnitFilter && (
                        <div className="card p-12 text-center">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                <School className="h-8 w-8 text-border" />
                            </div>
                            <p className="mt-4 text-base font-bold text-primary">Pilih unit terlebih dahulu</p>
                            <p className="mt-1 text-sm text-text-secondary">Data jadwal semua unit tidak dimuat secara default agar halaman tetap ringan. Gunakan filter <span className="font-semibold">Unit</span> di atas untuk menampilkan jadwal.</p>
                        </div>
                    )}

                    
                    {isAdmin && !needsUnitFilter && viewMode === 'guru' && (
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
                                        <div key={pegawai.id} className="card p-4 transition-colors hover:border-primary/30">
                                            <button
                                                type="button"
                                                onClick={() => setExpandedGuru(isExpanded ? null : pegawai.id)}
                                                className="w-full text-left cursor-pointer"
                                            >
                                                <div className="flex items-center justify-between gap-4">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex min-w-0 items-center gap-2">
                                                            <span className="truncate text-sm font-bold text-primary">{pegawai.nama_lengkap}</span>
                                                            <span className="text-xs text-text-muted shrink-0">{unitSingkatan(pegawai)}</span>
                                                            {jpBadge(pegawai.id)}
                                                            {chipTanpaJadwal(pegawai.id)}
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
                                                                <div key={i} className="flex min-w-0 items-center gap-3 px-2 py-1.5 rounded hover:bg-surface text-xs">
                                                                    <span className="font-mono font-semibold text-primary w-24 shrink-0">{j.hari?.substring(0, 3)}, {j.jam_mulai?.substring(0, 5)}-{j.jam_selesai?.substring(0, 5)}</span>
                                                                    <span className="min-w-0 truncate font-medium text-text-primary">{j.mata_pelajaran?.nama || '-'}</span>
                                                                    <span className="shrink-0 text-text-muted">{j.kelas_label || '-'}</span>
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

                    
                    <div className="hidden print:block">
                        <div className="print-header">
                            <h1>JADWAL PEGAWAI</h1>
                            <div className="sub">
                                Unit: {units.find((u) => u.id == unitFilter)?.nama || 'Semua Unit'} &mdash; {filters.tahun_ajaran ? `T.A. ${filters.tahun_ajaran}` : ''}{filters.semester ? ` Semester ${filters.semester}` : ''}
                            </div>
                        </div>
                    </div>

                    
                    {isAdmin && !needsUnitFilter && viewMode === 'matrix' && (
                        <>
                        
                        <div className={`md:hidden space-y-3 transition-opacity ${processing ? 'opacity-60 pointer-events-none' : ''}`}>
                            {pegawais.length > 0 ? pegawais.map((pegawai) => {
                                const pJadwals = jadwalByPegawai.get(pegawai.id) || [];

                                return (
                                    <div key={pegawai.id} className="card p-4">
                                        <div className="mb-3 flex min-w-0 items-center gap-2">
                                            <div className="min-w-0 truncate text-sm font-bold text-gray-900">{pegawai.nama_lengkap}</div>
                                            <span className="text-[11px] text-gray-500">{unitSingkatan(pegawai)}</span>
                                            {jpBadge(pegawai.id)}
                                            {chipTanpaJadwal(pegawai.id)}
                                        </div>
                                        {pJadwals.length === 0 && (
                                            <p className="text-xs text-text-secondary">Belum ada jadwal di unit ini.</p>
                                        )}
                                        <div className="space-y-2">
                                            {DAYS.map((day) => {
                                                const dayJadwals = pJadwals.filter((j) => j.hari === day);
                                                if (dayJadwals.length === 0) return null;

                                                return (
                                                    <div key={day}>
                                                        <div className="text-[11px] font-bold uppercase text-muted mb-1">{day}</div>
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {dayJadwals.map((jadwal) => {
                                                                const meta = jenisBadge(jadwal.jenis_jadwal);

                                                                return (
                                                                    <div key={jadwal.id} className="flex min-w-[148px] flex-col items-center text-center bg-white p-2 rounded-lg shadow-sm border border-gray-200">
                                                                        <span className="text-[11px] font-bold text-primary font-mono">
                                                                            {jadwal.jam_mulai.substring(0, 5)}–{jadwal.jam_selesai.substring(0, 5)}
                                                                        </span>
                                                                        {jadwal.mata_pelajaran?.nama && (
                                                                            <span className="mt-0.5 text-[11px] font-semibold text-gray-700 leading-tight">{jadwal.mata_pelajaran.nama}</span>
                                                                        )}
                                                                        {jadwal.kelas_label && (
                                                                            <span className="text-[11px] text-muted leading-tight">Kls {jadwal.kelas_label}</span>
                                                                        )}
                                                                        <span className={`mt-1 text-[11px] font-bold px-1.5 py-0.5 rounded ${meta.badge}`}>
                                                                            {meta.label}
                                                                        </span>
                                                                        <JadwalLiveBadge
                                                                            jadwal={jadwal}
                                                                            presensi={presensiByJadwal.get(jadwal.id)}
                                                                            now={now}
                                                                        />
                                                                        {canMutateJadwal && (
                                                                            <div className="mt-1.5 flex items-center gap-1 border-t border-gray-100 pt-1.5">
                                                                                <button type="button" onClick={() => router.get(route('jadwal.edit', jadwal.id))} className="flex h-11 w-11 items-center justify-center rounded-lg text-emerald-600 transition-colors active:bg-emerald-50" title="Edit jadwal" aria-label={`Edit jadwal ${jadwal.mata_pelajaran?.nama || ''}`}>
                                                                                    <Pencil className="h-4 w-4" />
                                                                                </button>
                                                                                <button type="button" onClick={() => { setSwapData({ jadwal_asal_id: jadwal.id, jadwal_tujuan_id: '' }); setTargetPegawaiId(''); setShowSwapModal(true); }} className="flex h-11 w-11 items-center justify-center rounded-lg text-blue-600 transition-colors active:bg-blue-50" title="Tukar jadwal" aria-label="Tukar jadwal">
                                                                                    <ArrowLeftRight className="h-4 w-4" />
                                                                                </button>
                                                                                <button type="button" onClick={() => { if (confirm('Hapus jadwal ini?')) { router.delete(route('jadwal.destroy', jadwal.id)); } }} className="flex h-11 w-11 items-center justify-center rounded-lg text-rose-600 transition-colors active:bg-rose-50" title="Hapus jadwal" aria-label="Hapus jadwal">
                                                                                    <Trash2 className="h-4 w-4" />
                                                                                </button>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            }) : (
                                <div className="card p-12 text-center">
                                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface mx-auto">
                                        <CalendarDays className="h-8 w-8 text-border" />
                                    </div>
                                    <p className="mt-4 text-base font-bold text-primary">Tidak ada data pegawai</p>
                                    <p className="mt-1 text-sm text-text-secondary">Coba ubah filter atau kata kunci pencarian.</p>
                                    {hasFilter && (
                                        <button onClick={resetFilters} className="btn-secondary btn-sm mt-4 flex items-center gap-1.5 mx-auto">
                                            <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>

                        
                        <div className={`hidden md:block card p-0 overflow-hidden transition-opacity ${processing ? 'opacity-60 pointer-events-none' : ''}`}>
                        <div className="overflow-x-auto max-h-[72vh] overflow-y-auto print:max-h-none print:overflow-visible">
                                <table className="w-full table-fixed divide-y divide-gray-200 bg-white text-sm">
                                    <thead className="bg-primary text-white sticky top-0 z-20 print:bg-gray-100 print:text-black">
                                        <tr>
                                            <th scope="col" className="w-[16%] min-w-[170px] px-3 py-3.5 text-left text-xs font-bold border-r border-primary/20 print:border-gray-300">
                                                Pegawai
                                            </th>
                                            {DAYS.map((day) => (
                                                <th key={day} scope="col" className={`w-[12%] border-r border-primary/20 px-2 py-3 text-center text-xs font-bold print:text-black print:border-gray-300 ${day === todayDay ? 'bg-primary-800 text-white' : 'text-white/85'}`}>
                                                    {day}
                                                    {day === todayDay && <span className="mt-0.5 block text-[9px] font-semibold text-accent print:hidden">Hari ini</span>}
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
                                                         {jpBadge(pegawai.id)}
                                                        {chipTanpaJadwal(pegawai.id)}
                                                    </div>
                                                </td>
                                                {DAYS.map((day) => {
                                                    const pJadwals = (jadwalByPegawai.get(pegawai.id) || []).filter((j) => j.hari === day);

                                                    return (
                                                        <td key={day} className={`border-r border-gray-100 px-2 py-2 align-top ${day === todayDay ? 'bg-primary-50/50' : 'bg-gray-50/30'}`}>
                                                            <div className="flex flex-col gap-1.5">
                                                                {pJadwals.length > 0 ? pJadwals.map((jadwal) => {
                                                                    const meta = jenisBadge(jadwal.jenis_jadwal);

                                                                    return (
                                                                        <div key={jadwal.id} className="group/card relative flex flex-col items-center rounded-lg border border-gray-200 bg-white p-2 text-center transition-colors hover:border-primary/50 print:border-gray-300">
                                                                            <span className="text-[11px] font-bold text-primary font-mono">
                                                                                {jadwal.jam_mulai.substring(0, 5)}–{jadwal.jam_selesai.substring(0, 5)}
                                                                            </span>
                                                                            {jadwal.mata_pelajaran?.nama && (
                                                                                <span className="mt-0.5 text-[11px] font-semibold text-gray-700 leading-tight">{jadwal.mata_pelajaran.nama}</span>
                                                                            )}
                                                                            {jadwal.kelas_label && (
                                                                                <span className="text-[11px] text-muted leading-tight">Kls {jadwal.kelas_label}</span>
                                                                            )}
                                                                            <span className={`mt-1 text-[11px] font-bold px-1.5 py-0.5 rounded ${meta.badge}`}>
                                                                                {meta.label}
                                                                            </span>
                                                                            <JadwalLiveBadge
                                                                                jadwal={jadwal}
                                                                                presensi={presensiByJadwal.get(jadwal.id)}
                                                                                now={now}
                                                                            />

                                            
                                            {canMutateJadwal && (
                                                <div className="pointer-events-none absolute right-1 top-1 flex items-center gap-0.5 rounded-md border border-border bg-white p-0.5 opacity-0 shadow-sm transition-opacity group-hover/card:pointer-events-auto group-hover/card:opacity-100 group-focus-within/card:pointer-events-auto group-focus-within/card:opacity-100 print:hidden">
                                                    <button
                                                        type="button"
                                                        onClick={() => router.get(route('jadwal.edit', jadwal.id))}
                                                        className="inline-flex h-7 w-7 items-center justify-center rounded text-emerald-700 transition-colors hover:bg-emerald-50"
                                                        title="Edit Jadwal"
                                                        aria-label="Edit jadwal"
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setSwapData({ jadwal_asal_id: jadwal.id, jadwal_tujuan_id: '' });
                                                            setTargetPegawaiId('');
                                                            setShowSwapModal(true);
                                                        }}
                                                        className="inline-flex h-7 w-7 items-center justify-center rounded text-blue-700 transition-colors hover:bg-blue-50"
                                                        title="Tukar Jadwal"
                                                        aria-label="Tukar jadwal"
                                                    >
                                                        <ArrowLeftRight className="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (confirm('Hapus jadwal ini?')) {
                                                                router.delete(route('jadwal.destroy', jadwal.id));
                                                            }
                                                        }}
                                                        className="inline-flex h-7 w-7 items-center justify-center rounded text-rose-700 transition-colors hover:bg-rose-50"
                                                        title="Hapus Jadwal"
                                                        aria-label="Hapus jadwal"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            )}
                                                                         </div>
                                                                    );
                                                                }) : (
                                                                    <div className="text-text-muted text-center py-2 text-xs">-</div>
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
                        </>
                    )}

                    
                    <div className="hidden print:block print-footer">
                        Dicetak oleh: <span className="font-bold">{auth.user.name}</span>
                        {' '}&mdash;{' '}
                        {new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                        <div className="signature-line" />
                    </div>

                    


                    
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
                                            <div key={j.id} className="card p-4 flex items-center gap-4 transition-colors hover:border-primary/30">
                                                <div className="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-primary/5 border border-primary/10">
                                                    <span className="text-[11px] font-bold uppercase text-text-muted">{j.hari.substring(0, 3)}</span>
                                                    <span className="font-mono text-[11px] font-bold text-primary">{j.jam_mulai?.substring(0, 5)}</span>
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-primary truncate">{j.mata_pelajaran?.nama || j.jenis_jadwal}</span>
                                                        <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-bold uppercase ${meta.badge}`}>{meta.label}</span>
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-text-secondary">
                                                        {j.jam_mulai?.substring(0, 5)} – {j.jam_selesai?.substring(0, 5)}
                                                        {j.kelas_label && <> • Kls {j.kelas_label}</>}
                                                    </div>
                                                </div>
                                                <div className="shrink-0 text-right">
                                                    <span className="text-xs font-semibold text-text-secondary">{j.unit_sekolah?.singkatan || '-'}</span>
                                                    {j.unit_sekolah?.nama && <div className="max-w-[140px] truncate text-[11px] text-text-muted">{j.unit_sekolah.nama}</div>}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}

                    {isAdmin && (
                        <GenerateModal
                            show={showGenerateModal}
                            onClose={() => setShowGenerateModal(false)}
                            handleGenerate={handleGenerate}
                            genData={genData}
                            setGenData={setGenData}
                            units={units}
                            selectedUnitPegCount={selectedUnitPegCount}
                            mapel={mapel}
                            errors={errors}
                            generating={generating}
                        />
                    )}

                    {isAdmin && (
                        <SwapModal
                            show={showSwapModal}
                            onClose={() => setShowSwapModal(false)}
                            onOpenDay={() => { setShowSwapModal(false); setDayUnit(unitFilter || units[0]?.id || ''); setShowDayModal(true); }}
                            onOpenBulk={() => { setShowSwapModal(false); setBulkUnit(unitFilter || units[0]?.id || ''); setBulkPairs([{ asal: '', tujuan: '' }]); setShowBulkModal(true); }}
                            onOpenSlot={() => { setShowSwapModal(false); setSlotUnit(unitFilter || units[0]?.id || ''); setSlotA(''); setSlotB(''); setShowSlotModal(true); }}
                            handleSwap={handleSwap}
                            errors={errors}
                            jadwalById={jadwalById}
                            swapData={swapData}
                            setSwapData={setSwapData}
                            targetPegawaiId={targetPegawaiId}
                            setTargetPegawaiId={setTargetPegawaiId}
                            swapCandidates={swapCandidates}
                            swapLoading={swapLoading}
                            swapError={swapError}
                            candJadwalByPegawai={candJadwalByPegawai}
                        />
                    )}

                    {canMutateJadwal && (
                        <SwapDayModal
                            show={showDayModal}
                            onClose={() => setShowDayModal(false)}
                            units={units}
                            dayUnit={dayUnit}
                            setDayUnit={setDayUnit}
                            dayA={dayA}
                            setDayA={setDayA}
                            dayB={dayB}
                            setDayB={setDayB}
                            dayHari={dayHari}
                            setDayHari={setDayHari}
                            daySubmitting={daySubmitting}
                            handleSwapHari={handleSwapHari}
                            swapCandidates={swapCandidates}
                            swapLoading={swapLoading}
                            swapError={swapError}
                            errors={errors}
                        />
                    )}

                    {canMutateJadwal && (
                        <SwapBulkModal
                            show={showBulkModal}
                            onClose={() => setShowBulkModal(false)}
                            units={units}
                            bulkUnit={bulkUnit}
                            setBulkUnit={setBulkUnit}
                            bulkPairs={bulkPairs}
                            setBulkPairs={setBulkPairs}
                            handleSwapBulk={handleSwapBulk}
                            swapCandidates={swapCandidates}
                            swapLoading={swapLoading}
                            swapError={swapError}
                            errors={errors}
                            candJadwalById={candJadwalById}
                            candNamaById={candNamaById}
                            jadwalOptionLabel={jadwalOptionLabel}
                        />
                    )}

                    {canMutateJadwal && (
                        <SwapSlotModal
                            show={showSlotModal}
                            onClose={() => setShowSlotModal(false)}
                            units={units}
                            slotUnit={slotUnit}
                            setSlotUnit={setSlotUnit}
                            slotA={slotA}
                            setSlotA={setSlotA}
                            slotB={slotB}
                            setSlotB={setSlotB}
                            slotSubmitting={slotSubmitting}
                            handleSwapSlot={handleSwapSlot}
                            swapCandidates={swapCandidates}
                            swapLoading={swapLoading}
                            swapError={swapError}
                            errors={errors}
                            candJadwalById={candJadwalById}
                            candNamaById={candNamaById}
                            jadwalOptionLabel={jadwalOptionLabel}
                        />
                    )}

                    {isAdmin && (
                        <RekapModal
                            show={showRekapModal}
                            onClose={() => setShowRekapModal(false)}
                            jadwals={jadwals}
                        />
                    )}

                    {showImportModal && (
                        <ImportModal
                            show={showImportModal}
                            onClose={() => setShowImportModal(false)}
                            handleImportSubmit={handleImportSubmit}
                            importMode={importMode}
                            setImportMode={setImportMode}
                            importData={importData}
                            setImportData={setImportData}
                            importErrors={importErrors}
                            importProcessing={importProcessing}
                            units={units}
                        />
                    )}
            </div>
        </AdminShell>
    );
}

