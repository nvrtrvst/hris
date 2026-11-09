import React, { useState, useEffect, useRef } from 'react';
import { subscribeRouter } from '@/Utils/routerEvents';
import AdminShell from '@/Layouts/AdminShell';
import Modal from '@/Components/Modal';
import FilterSheet from '@/Components/FilterSheet';
import Pagination from '@/Components/Pagination';
import {
    Avatar,
    Badge,
    Button,
    Card,
    EmptyState,
    FormField,
    Input,
    PageHeader,
    Select,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/AdminUI';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { kepagawaianBadge, STATUS_AKTIF_BADGE } from '@/Utils/statusMeta';
import {
    Building2,
    CalendarClock,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    Loader2,
    Mail,
    MapPin,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Upload,
    UserCheck,
    Users,
    UserX,
    X,
} from 'lucide-react';

export default function Index({ auth, pegawais, stats = {}, filters = {}, unitSekolahs, mataPelajarans, jabatans, isPimpinan = false, perluPilihUnit = false, exportColumns = [], defaultExportColumns = [] }) {
    const isAdminUnit = auth.roles?.includes('admin_unit');
    const [processing, setProcessing] = useState(false);

    useEffect(() => subscribeRouter({
        start: () => setProcessing(true),
        finish: () => setProcessing(false),
    }), []);

    const [search, setSearch] = useState(filters.search || '');
    const [unitSekolahId, setUnitSekolahId] = useState(filters.unit_sekolah_id || '');
    const [mataPelajaranId, setMataPelajaranId] = useState(filters.mata_pelajaran_id || '');
    const [jabatanId, setJabatanId] = useState(filters.jabatan_id || '');
    const [jenisFilter, setJenisFilter] = useState(filters.jenis_filter || '');
    const [showImportModal, setShowImportModal] = useState(false);
    const [showExportModal, setShowExportModal] = useState(false);
    const [exportScope, setExportScope] = useState('filtered');

    const initialExportColumns = React.useMemo(
        () => (defaultExportColumns.length > 0 ? defaultExportColumns : exportColumns.map((column) => column.key)),
        [defaultExportColumns, exportColumns],
    );
    const [selectedColumns, setSelectedColumns] = useState(initialExportColumns);
    const exportGroups = React.useMemo(() => {
        const groups = [];
        const index = new Map();

        exportColumns.forEach((column) => {
            if (!index.has(column.group)) {
                const group = { name: column.group, columns: [] };
                index.set(column.group, group);
                groups.push(group);
            }
            index.get(column.group).columns.push(column);
        });

        return groups;
    }, [exportColumns]);

    const { data: importData, setData: setImportData, post: postImport, processing: importProcessing, errors: importErrors, reset: resetImport } = useForm({
        file: null,
        unit_sekolah_id: isAdminUnit ? auth.user.unit_sekolah_id : '',
        default_password: '',
    });

    const hasFilter = Boolean(search || unitSekolahId || mataPelajaranId || jabatanId || jenisFilter);
    const [filterSheetOpen, setFilterSheetOpen] = useState(false);
    const activeFilterCount = [unitSekolahId, mataPelajaranId, jabatanId, jenisFilter].filter(Boolean).length;

    const toggleExportColumn = (key) => {
        setSelectedColumns((current) => (
            current.includes(key) ? current.filter((item) => item !== key) : [...current, key]
        ));
    };

    const selectAllExportColumns = () => {
        setSelectedColumns(exportColumns.map((column) => column.key));
    };

    const clearExportColumns = () => {
        setSelectedColumns([]);
    };

    const resetExportColumns = () => {
        setSelectedColumns(initialExportColumns);
    };

    const openExportModal = () => {
        setExportScope(hasFilter ? 'filtered' : 'all');
        setShowExportModal(true);
    };

    const downloadExport = (format) => {
        if (selectedColumns.length === 0) return;

        const params = new URLSearchParams();
        params.set('scope', exportScope);
        selectedColumns.forEach((column) => params.append('columns[]', column));

        if (exportScope === 'filtered') {
            if (search) params.set('search', search);
            if (unitSekolahId) params.set('unit_sekolah_id', unitSekolahId);
            if (mataPelajaranId) params.set('mata_pelajaran_id', mataPelajaranId);
            if (jabatanId) params.set('jabatan_id', jabatanId);
            if (jenisFilter) params.set('jenis_filter', jenisFilter);
        }

        // Response file tidak lewat Inertia; gunakan navigasi browser.
        const exportRoute = format === 'pdf' ? 'pegawai.export-pdf' : 'pegawai.export';
        window.location.href = `${route(exportRoute)}?${params.toString()}`;
        setShowExportModal(false);
    };

    const buildParams = (overrides = {}) => ({
        search,
        unit_sekolah_id: unitSekolahId,
        mata_pelajaran_id: mataPelajaranId,
        jabatan_id: jabatanId,
        jenis_filter: jenisFilter,
        ...overrides,
    });

    const applyFilters = (overrides = {}) => {
        router.get(route('pegawai.index'), buildParams(overrides), { preserveState: true, preserveScroll: true });
    };

    // Ref filter terbaru — cegah stale closure saat debounce search tertunda
    // lalu user mengganti unit/mapel/jabatan sebelum timer jalan.
    const latestFiltersRef = useRef({ unitSekolahId, mataPelajaranId, jabatanId });
    latestFiltersRef.current = { unitSekolahId, mataPelajaranId, jabatanId };

    // Search debounce → server
    useEffect(() => {
        if (search === (filters.search || '')) return;
        const timer = setTimeout(() => {
            const { unitSekolahId: u, mataPelajaranId: m, jabatanId: j } = latestFiltersRef.current;
            applyFilters({ search, unit_sekolah_id: u, mata_pelajaran_id: m, jabatan_id: j });
        }, 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const resetFilters = () => {
        setSearch('');
        setUnitSekolahId('');
        setMataPelajaranId('');
        setJabatanId('');
        setJenisFilter('');
        router.get(route('pegawai.index'), {}, { preserveState: true });
    };

    const handleImportSubmit = (e) => {
        e.preventDefault();
        postImport(route('pegawai.import'), {
            onSuccess: () => {
                setShowImportModal(false);
                resetImport();
            },
        });
    };

    const s = stats || { total: 0, aktif: 0, nonaktif: 0, kontrak_berakhir: 0 };

    const filterSelect = 'h-11 w-full text-xs';

    const lokasiLabel = (p) => {
        if (!p.lokasis?.length) return null;
        return p.lokasis.map((l) => {
            const unitNama = p.units?.find((u) => u.id === l.unit_sekolah_id)?.nama || '';
            return unitNama ? `${unitNama} ${l.nama}` : l.nama;
        }).join(', ');
    };

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Manajemen Pegawai</h2>}
        >
            <Head title="Manajemen Pegawai" />

            <div className="mx-auto max-w-7xl space-y-5">
                <PageHeader
                    eyebrow="Kepegawaian"
                    title="Daftar Pegawai"
                    description="Kelola data seluruh pegawai di lingkungan yayasan."
                    actions={
                        <>
                            <a href={route('pegawai.template')} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary shadow-card hover:border-primary/30 hover:text-primary">
                                <Download className="h-3.5 w-3.5" /> Template
                            </a>
                            <Button variant="secondary" size="sm" onClick={openExportModal}>
                                <FileSpreadsheet className="h-3.5 w-3.5" /> Export
                            </Button>
                            <Button variant="secondary" size="sm" onClick={() => setShowImportModal(true)}>
                                <Upload className="h-3.5 w-3.5" /> Import
                            </Button>
                            <Link href={route('pegawai.create')} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-bold text-white shadow-card hover:bg-primary-600">
                                <Plus className="h-3.5 w-3.5" /> Tambah Pegawai
                            </Link>
                        </>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <Card className="p-4">
                        <div className="flex items-start justify-between">
                            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-50 text-primary"><Users className="h-4 w-4" /></span>
                            <span className="text-[10px] font-semibold text-text-muted">Data saat ini</span>
                        </div>
                        <p className="mt-3 text-2xl font-extrabold tracking-tight text-primary">{s.total}</p>
                        <p className="text-[11px] font-medium text-text-secondary">Total Pegawai</p>
                        {s.nonaktif > 0 && <p className="mt-1 text-[10px] text-text-muted">{s.nonaktif} nonaktif</p>}
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-start justify-between">
                            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><UserCheck className="h-4 w-4" /></span>
                            <span className="text-[10px] font-semibold text-text-muted">Status aktif</span>
                        </div>
                        <p className="mt-3 text-2xl font-extrabold tracking-tight text-primary">{s.aktif}</p>
                        <p className="text-[11px] font-medium text-text-secondary">Pegawai Aktif</p>
                    </Card>
                    <Card className="border-amber-200 bg-amber-50/50 p-4">
                        <div className="flex items-start justify-between">
                            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-amber-600"><CalendarClock className="h-4 w-4" /></span>
                            <Badge variant="warning">30 hari</Badge>
                        </div>
                        <p className="mt-3 text-2xl font-extrabold tracking-tight text-primary">{s.kontrak_berakhir}</p>
                        <p className="text-[11px] font-medium text-text-secondary">Kontrak Berakhir</p>
                        {s.kontrak_berakhir > 0 && <p className="mt-1 text-[10px] text-amber-700">Perlu ditinjau</p>}
                    </Card>
                </div>

                    {/* Filter bar */}
                    <FilterSheet
                        open={filterSheetOpen}
                        onOpenChange={setFilterSheetOpen}
                        activeCount={activeFilterCount}
                        onReset={hasFilter ? () => { resetFilters(); setFilterSheetOpen(false); } : undefined}
                    >
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            <div className="relative lg:col-span-2">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                <Input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama / NIK..."
                                    className="h-11 pl-9 text-xs"
                                />
                            </div>
                            <Select
                                className={filterSelect}
                                value={jenisFilter}
                                onChange={(e) => {
                                    setJenisFilter(e.target.value);
                                    applyFilters({ jenis_filter: e.target.value, search });
                                }}
                            >
                                <option value="">Semua Jenis</option>
                                <option value="pendidik">Pendidik (Guru)</option>
                                <option value="kependidikan">Tenaga Kependidikan</option>
                            </Select>
                            {!isAdminUnit && !isPimpinan && (
                                <Select
                                    className={filterSelect}
                                    value={unitSekolahId}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setUnitSekolahId(val);
                                        applyFilters({ unit_sekolah_id: val, search });
                                    }}
                                >
                                    <option value="" disabled>Pilih Unit…</option>
                                    <option value="all">Semua Unit</option>
                                    {unitSekolahs && unitSekolahs.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </Select>
                            )}
                            <Select
                                className={filterSelect}
                                value={jabatanId}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setJabatanId(val);
                                    applyFilters({ jabatan_id: val, search });
                                }}
                            >
                                <option value="">Semua Jabatan</option>
                                {jabatans && jabatans.map((j) => <option key={j.id} value={j.id}>{j.nama}</option>)}
                            </Select>
                            {!isPimpinan && (
                                <Select
                                    className={filterSelect}
                                    value={mataPelajaranId}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setMataPelajaranId(val);
                                        applyFilters({ mata_pelajaran_id: val, search });
                                    }}
                                >
                                    <option value="">Semua Mapel</option>
                                    {mataPelajarans && mataPelajarans.map((m) => <option key={m.id} value={m.id}>{m.nama}</option>)}
                                </Select>
                            )}
                        </div>

                        <div className="flex items-center gap-2 border-t border-border/60 pt-4">
                            {hasFilter && (
                                <Button type="button" variant="secondary" size="sm" onClick={resetFilters}>
                                    <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                </Button>
                            )}
                            <p className="ml-auto text-[11px] text-text-muted">Filter diterapkan otomatis saat dipilih.</p>
                        </div>
                    </FilterSheet>

                    {/* Mobile: Card list */}
                    <div className={`md:hidden space-y-3 transition-opacity ${processing ? 'opacity-60 pointer-events-none' : ''}`}>
                        {pegawais.data.length > 0 ? (
                            pegawais.data.map((pegawai) => {
                                const kep = kepagawaianBadge(pegawai.status_kepegawaian);
                                const unitNames = pegawai.units?.map(u => u.nama).join(', ') || '—';
                                const jabatanNama = [...new Set((pegawai.jabatans || []).map((j) => j.nama))].join(', ') || '—';
                                return (
                                    <Link key={pegawai.id} href={route('pegawai.show', pegawai.id)} className="card block p-4 active:bg-surface/70 transition-colors">
                                        <div className="flex items-start gap-3">
                                            <Avatar src={pegawai.foto_url} name={pegawai.nama_lengkap} className="h-10 w-10" />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-bold text-primary truncate">{pegawai.nama_lengkap}</p>
                                                        <p className="mt-0.5 text-[11px] text-text-secondary truncate">{pegawai.user?.email || pegawai.nuptk || '—'}</p>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-1">
                                                        <Badge className={kep.badge}>{kep.label}</Badge>
                                                    </div>
                                                </div>
                                                <div className="mt-2 flex items-center gap-1.5 text-[11px] text-text-secondary">
                                                    <Building2 className="h-3 w-3 shrink-0 text-primary/50" />
                                                    <span className="truncate">{unitNames} • {jabatanNama}</span>
                                                </div>
                                                {lokasiLabel(pegawai) && (
                                                    <div className="mt-1 flex items-center gap-1 text-[11px] text-slate-500">
                                                        <MapPin className="h-3 w-3 shrink-0" />
                                                        <span className="truncate">{lokasiLabel(pegawai)}</span>
                                                    </div>
                                                )}
                                                <div className="mt-2 flex items-center justify-between">
                                                    <Badge className={`gap-1 ${STATUS_AKTIF_BADGE(pegawai.status_aktif)}`}>
                                                        <span className={`h-1 w-1 rounded-full ${pegawai.status_aktif === 'aktif' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                                        {pegawai.status_aktif}
                                                    </Badge>
                                                    <div className="flex gap-1.5">
                                                        <span className="btn-secondary btn-sm py-1 px-2"><Eye className="h-3 w-3" /> <span className="hidden sm:inline">Detail</span></span>
                                                        <Link href={route('pegawai.edit', pegawai.id)} onClick={(e) => e.stopPropagation()} className="btn-secondary btn-sm py-1 px-2"><Pencil className="h-3 w-3" /> <span className="hidden sm:inline">Edit</span></Link>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })
                        ) : (
                            <Card>
                                <EmptyState
                                    icon={Users}
                                    title={perluPilihUnit ? 'Pilih unit terlebih dahulu' : 'Data pegawai tidak ditemukan'}
                                    description={perluPilihUnit ? 'Daftar pegawai tampil setelah unit dipilih. Pilih "Semua Unit" untuk melihat seluruh yayasan.' : hasFilter ? 'Tidak ada pegawai yang cocok dengan filter aktif.' : 'Tambahkan pegawai pertama untuk memulai.'}
                                    action={hasFilter ? (
                                        <Button variant="secondary" size="sm" onClick={resetFilters}><RotateCcw className="h-3.5 w-3.5" /> Reset Filter</Button>
                                    ) : !perluPilihUnit ? (
                                        <Link href={route('pegawai.create')} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-bold text-white"><Plus className="h-3.5 w-3.5" /> Tambah Pegawai</Link>
                                    ) : null}
                                />
                            </Card>
                        )}
                    </div>

                    {/* Desktop: Table */}
                    <Card className={`hidden overflow-hidden p-0 transition-opacity md:block ${processing ? 'pointer-events-none opacity-60' : ''}`}>
                        <div className="overflow-x-auto">
                            <Table tableClassName="min-w-full divide-y divide-border">
                                <TableHeader className="sticky top-0 z-10 bg-surface/80 backdrop-blur-sm">
                                    <TableRow>
                                        <TableHead className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Pegawai</TableHead>
                                        <TableHead className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Unit & Jabatan</TableHead>
                                        <TableHead className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Status Kepegawaian</TableHead>
                                        <TableHead className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Status Aktif</TableHead>
                                        <TableHead className="px-4 py-3.5 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y divide-border/50">
                                    {pegawais.data.length > 0 ? (
                                        pegawais.data.map((pegawai) => {
                                            const kep = kepagawaianBadge(pegawai.status_kepegawaian);

                                            return (
                                                <TableRow key={pegawai.id} className="group hover:bg-surface/70 transition-colors">
                                                    <TableCell className="px-4 py-3.5 whitespace-nowrap">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar src={pegawai.foto_url} name={pegawai.nama_lengkap} className="h-10 w-10" />
                                                            <div className="min-w-0">
                                                                <div className="text-sm font-bold text-primary truncate max-w-[200px]">{pegawai.nama_lengkap}</div>
                                                                <div className="mt-0.5 flex items-center gap-1 text-[11px] text-text-secondary">
                                                                    <span className="inline-flex items-center gap-0.5 truncate">
                                                                        <Mail className="h-2.5 w-2.5 shrink-0" />
                                                                        {pegawai.user?.email || pegawai.nuptk || '—'}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="px-4 py-3.5">
                                                        {pegawai.units && pegawai.units.length > 0 ? (
                                                            <div className="flex flex-wrap gap-1.5">
                                                                {pegawai.units.map((unit) => {
                                                                    const jabatan = pegawai.jabatans?.find((j) => j.pivot.unit_sekolah_id === unit.id)?.nama;
                                                                    const mapels = pegawai.mapels?.filter((m) => m.pivot.unit_sekolah_id === unit.id) || [];

                                                                    return (
                                                                        <div key={unit.id} className="rounded-lg border border-border bg-surface/50 px-2.5 py-1.5">
                                                                            <div className="flex items-center gap-1.5 text-xs font-bold text-primary">
                                                                                <Building2 className="h-3 w-3 text-primary/60" />
                                                                                {unit.nama}
                                                                            </div>
                                                                            <div className="mt-0.5 text-[11px] text-text-secondary">{jabatan || '-'}</div>
                                                                            {mapels.length > 0 && (
                                                                                <div className="mt-0.5 flex items-center gap-1 text-[11px] font-medium text-blue-600">
                                                                                    <GraduationCap className="h-2.5 w-2.5" />
                                                                                    {mapels.map((m) => m.nama).join(', ')}
                                                                                </div>
                                                                            )}
                                                                            {lokasiLabel(pegawai) && (
                                                                                <div className="mt-0.5 flex items-center gap-1 text-[11px] text-slate-500">
                                                                                    <MapPin className="h-2.5 w-2.5 shrink-0" />
                                                                                    {lokasiLabel(pegawai)}
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-text-secondary">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="px-4 py-3.5 whitespace-nowrap">
                                                        <Badge className={kep.badge}>
                                                            {kep.label}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="px-4 py-3.5 whitespace-nowrap">
                                                        <Badge className={`gap-1.5 ${STATUS_AKTIF_BADGE(pegawai.status_aktif)}`}>
                                                            <span className={`h-1.5 w-1.5 rounded-full ${pegawai.status_aktif === 'aktif' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                                            {pegawai.status_aktif}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="px-4 py-3.5 whitespace-nowrap text-right">
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            <Link href={route('pegawai.show', pegawai.id)} className="btn-secondary btn-sm flex items-center gap-1.5">
                                                                <Eye className="h-3.5 w-3.5" /> Detail
                                                            </Link>
                                                            <Link href={route('pegawai.edit', pegawai.id)} className="btn-secondary btn-sm flex items-center gap-1.5">
                                                                <Pencil className="h-3.5 w-3.5" /> Edit
                                                            </Link>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan="5" className="px-6 py-16">
                                                <EmptyState
                                                    icon={Users}
                                                    title={perluPilihUnit ? 'Pilih unit terlebih dahulu' : 'Data pegawai tidak ditemukan'}
                                                    description={perluPilihUnit ? 'Daftar pegawai tampil setelah unit dipilih. Pilih "Semua Unit" untuk melihat seluruh yayasan.' : hasFilter ? 'Tidak ada pegawai yang cocok dengan kata kunci atau filter aktif.' : 'Tambahkan pegawai pertama untuk memulai.'}
                                                    action={hasFilter ? (
                                                        <Button variant="secondary" size="sm" onClick={resetFilters}><RotateCcw className="h-3.5 w-3.5" /> Reset Filter</Button>
                                                    ) : !perluPilihUnit ? (
                                                        <Link href={route('pegawai.create')} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-bold text-white"><Plus className="h-3.5 w-3.5" /> Tambah Pegawai</Link>
                                                    ) : null}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </Card>

                    {/* Pagination */}
                    {pegawais.total > 0 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-text-secondary">
                                Menampilkan <b className="text-primary">{pegawais.from || 0}</b>–<b className="text-primary">{pegawais.to || 0}</b> dari <b className="text-primary">{pegawais.total}</b> data
                            </p>
                            <Pagination links={pegawais.links} pagination={{ current_page: pegawais.current_page, last_page: pegawais.last_page }} />
                        </div>
                    )}
                </div>

            {/* Modal Import Excel */}
            <Modal show={showImportModal} onClose={() => setShowImportModal(false)} maxWidth="lg">
                <form onSubmit={handleImportSubmit}>
                    <div className="p-6">
                        <div className="mb-5 flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                    <Upload className="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold text-primary">Import Data Pegawai</h3>
                                    <p className="mt-0.5 text-sm text-text-secondary">Upload file Excel untuk menambahkan pegawai massal.</p>
                                </div>
                            </div>
                            <Button type="button" variant="ghost" size="icon" onClick={() => setShowImportModal(false)} aria-label="Tutup">
                                <X className="h-4 w-4" />
                            </Button>
                        </div>

                        <div className="space-y-4">
                            {!isAdminUnit && (
                                <FormField label="Pilih Unit Sekolah" htmlFor="import-unit" required error={importErrors.unit_sekolah_id}>
                                    <Select
                                        id="import-unit"
                                        value={importData.unit_sekolah_id}
                                        onChange={(e) => setImportData('unit_sekolah_id', e.target.value)}
                                        className="h-10 text-xs"
                                    >
                                        <option value="">-- Pilih Unit --</option>
                                        {unitSekolahs && unitSekolahs.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                    </Select>
                                </FormField>
                            )}

                            <FormField label="File Excel (.xlsx)" htmlFor="import-file" required error={importErrors.file}>
                                <Input
                                    id="import-file"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={(e) => setImportData('file', e.target.files[0])}
                                    className="h-auto py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-[11px] file:font-bold file:text-primary hover:file:bg-primary-100"
                                />
                                {importErrors.import && <div className="mt-2 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 whitespace-pre-line">{importErrors.import}</div>}
                                {importErrors[0] && <p className="mt-1 text-[10px] font-semibold text-rose-600">Error pada baris data: {importErrors[0]}</p>}
                            </FormField>

                            <FormField
                                label="Password Default (Opsional)"
                                htmlFor="import-password"
                                error={importErrors.default_password}
                                description="Bila diisi, semua user hasil import memakai password ini dan wajib ganti saat login. Min. 8 karakter."
                            >
                                <Input
                                    id="import-password"
                                    type="text"
                                    value={importData.default_password}
                                    onChange={(e) => setImportData('default_password', e.target.value)}
                                    placeholder="Kosong = pakai NIK masing-masing"
                                    className="text-xs"
                                />
                            </FormField>

                            <div className="flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 p-3 text-xs text-sky-800">
                                <FileSpreadsheet className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>Gunakan template resmi. Kolom Jabatan, Status, Jenjang, dan Unit Sekolah sudah berupa dropdown. Email wajib diisi dan unik. Sistem menolak seluruh data jika ada satu baris saja yang salah format.</span>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t border-border pt-4">
                            <Button type="button" variant="secondary" onClick={() => setShowImportModal(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={importProcessing}>
                                {importProcessing ? <><Loader2 className="h-4 w-4 animate-spin" /> Memproses...</> : <><Upload className="h-4 w-4" /> Upload & Proses</>}
                            </Button>
                        </div>
                    </div>
                </form>
            </Modal>

            <Modal show={showExportModal} onClose={() => setShowExportModal(false)} maxWidth="3xl">
                <div className="p-6">
                    <div className="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <h3 className="text-lg font-bold text-primary">Export Data Pegawai</h3>
                            <p className="mt-1 text-sm text-text-secondary">Pilih cakupan dan kolom yang ingin diunduh.</p>
                        </div>
                        <Button type="button" variant="ghost" size="icon" onClick={() => setShowExportModal(false)} aria-label="Tutup">
                            <X className="h-4 w-4" />
                        </Button>
                    </div>

                    {hasFilter && (
                        <fieldset className="mb-5">
                            <legend className="text-xs font-bold text-text-primary">Cakupan data</legend>
                            <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <label className="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg border border-border px-3 text-sm hover:bg-surface">
                                    <input
                                        type="radio"
                                        name="export-scope"
                                        value="filtered"
                                        checked={exportScope === 'filtered'}
                                        onChange={() => setExportScope('filtered')}
                                        className="h-4 w-4 text-primary focus:ring-primary/25"
                                    />
                                    <span>Sesuai filter saat ini</span>
                                </label>
                                <label className="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg border border-border px-3 text-sm hover:bg-surface">
                                    <input
                                        type="radio"
                                        name="export-scope"
                                        value="all"
                                        checked={exportScope === 'all'}
                                        onChange={() => setExportScope('all')}
                                        className="h-4 w-4 text-primary focus:ring-primary/25"
                                    />
                                    <span>Semua pegawai dalam akses saya</span>
                                </label>
                            </div>
                        </fieldset>
                    )}

                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs font-bold text-text-primary" aria-live="polite">{selectedColumns.length} kolom dipilih</p>
                        <div className="flex items-center gap-3">
                            <button type="button" onClick={selectAllExportColumns} className="text-xs font-bold text-primary hover:underline">
                                Pilih semua
                            </button>
                            <button type="button" onClick={clearExportColumns} className="text-xs font-bold text-text-secondary hover:text-primary">
                                Kosongkan
                            </button>
                            <button type="button" onClick={resetExportColumns} className="inline-flex items-center gap-1 text-xs font-bold text-text-secondary hover:text-primary">
                                <RotateCcw className="h-3.5 w-3.5" /> Reset default
                            </button>
                        </div>
                    </div>

                    <div className="max-h-[55vh] space-y-5 overflow-y-auto pr-1">
                        {exportGroups.map((group) => (
                            <fieldset key={group.name}>
                                <legend className="text-xs font-bold text-text-primary">{group.name}</legend>
                                {group.name === 'Data Sensitif' && (
                                    <p className="mt-0.5 text-xs text-text-secondary">Kolom sensitif hanya tersedia untuk pengguna berwenang.</p>
                                )}
                                <div className="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
                                    {group.columns.map((column) => (
                                        <label
                                            key={column.key}
                                            htmlFor={`export-col-${column.key}`}
                                            className="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg px-2 text-sm text-text-primary hover:bg-surface"
                                        >
                                            <input
                                                id={`export-col-${column.key}`}
                                                type="checkbox"
                                                checked={selectedColumns.includes(column.key)}
                                                onChange={() => toggleExportColumn(column.key)}
                                                className="h-4 w-4 rounded border-border text-primary focus:ring-primary/25"
                                            />
                                            <span>{column.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        ))}
                    </div>

                    {selectedColumns.length === 0 && <p className="mt-2 text-xs font-semibold text-rose-600">Pilih minimal satu kolom.</p>}
                    {selectedColumns.length > 12 && <p className="mt-2 text-[11px] text-text-secondary">PDF dengan banyak kolom akan kecil. Excel lebih nyaman.</p>}

                    <div className="sticky bottom-0 -mx-6 -mb-6 mt-5 flex flex-col gap-3 border-t border-border bg-white px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-[11px] text-text-secondary">PDF maksimal 2000 baris. Untuk data besar gunakan Excel.</p>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Button type="button" variant="secondary" onClick={() => setShowExportModal(false)} className="w-full sm:w-auto">
                                Batal
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => downloadExport('pdf')} disabled={selectedColumns.length === 0} className="w-full sm:w-auto">
                                <FileText className="h-4 w-4" /> Download PDF
                            </Button>
                            <Button type="button" onClick={() => downloadExport('xlsx')} disabled={selectedColumns.length === 0} className="w-full sm:w-auto">
                                <FileSpreadsheet className="h-4 w-4" /> Download Excel
                            </Button>
                        </div>
                    </div>
                </div>
            </Modal>
        </AdminShell>
    );
}
