import React, { useState, useEffect, useRef } from 'react';
import { subscribeRouter } from '@/Utils/routerEvents';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { avatarTone, initials } from '@/Utils/avatar';
import { kepagawaianBadge, STATUS_AKTIF_BADGE } from '@/Utils/statusMeta';
import {
    Briefcase,
    Building2,
    CalendarClock,
    Download,
    Eye,
    FileSpreadsheet,
    GraduationCap,
    IdCard,
    Loader2,
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

const StatCard = ({ label, value, sub, Icon, iconBg, iconCls, alert }) => (
    <div className="stat-card group hover:shadow-card-hover transition-shadow">
        <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${iconBg} transition-transform group-hover:scale-105`}>
            <Icon className={`h-5 w-5 ${iconCls}`} />
        </div>
        <div className="min-w-0">
            <p className={`text-2xl font-extrabold leading-none tabular-nums ${alert ? 'text-danger' : 'text-primary'}`}>{value}</p>
            <p className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
            {sub && <p className="mt-0.5 text-[10px] text-text-muted">{sub}</p>}
        </div>
    </div>
);

const UserAvatar = ({ pegawai }) => (
    pegawai.foto_url ? (
        <img className="h-10 w-10 rounded-full object-cover" src={pegawai.foto_url} alt="" loading="lazy" />
    ) : (
        <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-extrabold ${avatarTone(pegawai.nama_lengkap)}`}>
            {initials(pegawai.nama_lengkap)}
        </span>
    )
);

export default function Index({ auth, pegawais, stats = {}, filters = {}, unitSekolahs, mataPelajarans, jabatans }) {
    const isAdminUnit = auth.roles?.includes('admin_unit');
    const { flash = {} } = usePage().props;
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

    const { data: importData, setData: setImportData, post: postImport, processing: importProcessing, errors: importErrors, reset: resetImport } = useForm({
        file: null,
        unit_sekolah_id: isAdminUnit ? auth.user.unit_sekolah_id : '',
    });

    const hasFilter = Boolean(search || unitSekolahId || mataPelajaranId || jabatanId || jenisFilter);

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

    const statCards = [
        { label: 'Total Pegawai', value: s.total, Icon: Users, iconBg: 'bg-primary/10', iconCls: 'text-primary' },
        { label: 'Aktif', value: s.aktif, Icon: UserCheck, iconBg: 'bg-emerald-100', iconCls: 'text-emerald-600' },
        { label: 'Non-Aktif', value: s.nonaktif, Icon: UserX, iconBg: s.nonaktif > 0 ? 'bg-rose-100' : 'bg-surface', iconCls: s.nonaktif > 0 ? 'text-rose-600' : 'text-border' },
        { label: 'Kontrak Berakhir', value: s.kontrak_berakhir, sub: '30 hari', Icon: CalendarClock, iconBg: s.kontrak_berakhir > 0 ? 'bg-amber-100' : 'bg-surface', iconCls: s.kontrak_berakhir > 0 ? 'text-amber-600' : 'text-border', alert: s.kontrak_berakhir > 0 },
    ];

    const filterSelect = 'select-field text-xs h-9 w-full';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Manajemen Pegawai</h2>}
        >
            <Head title="Manajemen Pegawai" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {/* Flash */}
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between gap-3">
                        <div>
                            <h3 className="text-xl font-bold text-primary">Daftar Pegawai</h3>
                            <p className="mt-1 text-sm text-text-secondary">Kelola data seluruh pegawai di lingkungan yayasan.</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <a href={route('pegawai.template')} className="btn-secondary btn-sm flex items-center gap-1.5">
                                <Download className="h-3.5 w-3.5" /> Template
                            </a>
                            <button type="button" onClick={() => setShowImportModal(true)} className="btn-secondary btn-sm flex items-center gap-1.5">
                                <Upload className="h-3.5 w-3.5" /> Import
                            </button>
                            <Link href={route('pegawai.create')} className="btn-primary btn-sm flex items-center gap-1.5">
                                <Plus className="h-3.5 w-3.5" /> Tambah Pegawai
                            </Link>
                        </div>
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {statCards.map((card) => <StatCard key={card.label} {...card} />)}
                    </div>

                    {/* Filter bar */}
                    <div className="card p-5">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                            <div className="relative lg:col-span-2">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama / NIK…"
                                    className="input-field h-9 pl-9 text-xs w-full"
                                />
                            </div>
                            <select
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
                            </select>
                            {!isAdminUnit && (
                                <select
                                    className={filterSelect}
                                    value={unitSekolahId}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setUnitSekolahId(val);
                                        applyFilters({ unit_sekolah_id: val, search });
                                    }}
                                >
                                    <option value="">Semua Unit</option>
                                    {unitSekolahs && unitSekolahs.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </select>
                            )}
                            <select
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
                            </select>
                            <select
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
                            </select>
                        </div>

                        <div className="flex items-center gap-2 border-t border-border/60 pt-4">
                            {hasFilter && (
                                <button type="button" onClick={resetFilters} className="btn-secondary btn-sm flex items-center gap-1.5">
                                    <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                </button>
                            )}
                            <p className="ml-auto text-[11px] text-text-muted">Filter diterapkan otomatis saat dipilih.</p>
                        </div>
                    </div>

                    {/* Table */}
                    <div className={`card p-0 overflow-hidden transition-opacity ${processing ? 'opacity-60 pointer-events-none' : ''}`}>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface/80 sticky top-0 z-10 backdrop-blur-sm">
                                    <tr>
                                        <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Pegawai</th>
                                        <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Unit & Jabatan</th>
                                        <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Status Pegawai</th>
                                        <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Status Aktif</th>
                                        <th className="px-4 py-3.5 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/50">
                                    {pegawais.data.length > 0 ? (
                                        pegawais.data.map((pegawai) => {
                                            const kep = kepagawaianBadge(pegawai.status_kepegawaian);

                                            return (
                                                <tr key={pegawai.id} className="group hover:bg-surface/70 transition-colors">
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <div className="flex items-center gap-3">
                                                            <UserAvatar pegawai={pegawai} />
                                                            <div className="min-w-0">
                                                                <div className="text-sm font-bold text-primary truncate max-w-[200px]">{pegawai.nama_lengkap}</div>
                                                                <div className="mt-0.5 flex items-center gap-1 text-[11px] text-text-secondary">
                                                                    {pegawai.nip && <span className="inline-flex items-center gap-0.5"><Briefcase className="h-2.5 w-2.5" />{pegawai.nip}</span>}
                                                                    {pegawai.nip && pegawai.nik_masked && <span>•</span>}
                                                                    {pegawai.nik_masked && <span className="inline-flex items-center gap-0.5"><IdCard className="h-2.5 w-2.5" />{pegawai.nik_masked}</span>}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3.5">
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
                                                                            <div className="mt-0.5 text-[10px] text-text-secondary">{jabatan || '-'}</div>
                                                                            {mapels.length > 0 && (
                                                                                <div className="mt-0.5 flex items-center gap-1 text-[10px] font-medium text-blue-600">
                                                                                    <GraduationCap className="h-2.5 w-2.5" />
                                                                                    {mapels.map((m) => m.nama).join(', ')}
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-text-secondary">—</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${kep.badge}`}>
                                                            {kep.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${STATUS_AKTIF_BADGE(pegawai.status_aktif)}`}>
                                                            <span className={`h-1.5 w-1.5 rounded-full ${pegawai.status_aktif === 'aktif' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                                            {pegawai.status_aktif}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap text-right">
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            <Link href={route('pegawai.show', pegawai.id)} className="btn-secondary btn-sm flex items-center gap-1.5">
                                                                <Eye className="h-3.5 w-3.5" /> Detail
                                                            </Link>
                                                            <Link href={route('pegawai.edit', pegawai.id)} className="btn-secondary btn-sm flex items-center gap-1.5">
                                                                <Pencil className="h-3.5 w-3.5" /> Edit
                                                            </Link>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan="5" className="px-6 py-16">
                                                <div className="flex flex-col items-center text-center">
                                                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                                        <Users className="h-8 w-8 text-border" />
                                                    </div>
                                                    <p className="mt-4 text-base font-bold text-primary">Data pegawai tidak ditemukan</p>
                                                    <p className="mt-1 text-sm text-text-secondary">
                                                        {hasFilter ? 'Coba ubah kata kunci atau bersihkan filter.' : 'Tambahkan pegawai pertama Anda.'}
                                                    </p>
                                                    {hasFilter ? (
                                                        <button onClick={resetFilters} className="btn-secondary btn-sm mt-4 flex items-center gap-1.5">
                                                            <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                                        </button>
                                                    ) : (
                                                        <Link href={route('pegawai.create')} className="btn-primary btn-sm mt-4 flex items-center gap-1.5">
                                                            <Plus className="h-3.5 w-3.5" /> Tambah Pegawai
                                                        </Link>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

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
                            <button type="button" onClick={() => setShowImportModal(false)} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="space-y-4">
                            {!isAdminUnit && (
                                <div>
                                    <label className="form-label text-xs">Pilih Unit Sekolah <span className="text-danger">*</span></label>
                                    <select
                                        value={importData.unit_sekolah_id}
                                        onChange={(e) => setImportData('unit_sekolah_id', e.target.value)}
                                        className="select-field"
                                    >
                                        <option value="">-- Pilih Unit --</option>
                                        {unitSekolahs && unitSekolahs.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                    </select>
                                    {importErrors.unit_sekolah_id && <p className="form-error">{importErrors.unit_sekolah_id}</p>}
                                </div>
                            )}

                            <div>
                                <label className="form-label text-xs">File Excel (.xlsx) <span className="text-danger">*</span></label>
                                <input
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={(e) => setImportData('file', e.target.files[0])}
                                    className="mt-1 block w-full text-sm text-text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-100"
                                />
                                {importErrors.file && <p className="form-error">{importErrors.file}</p>}
                                {importErrors[0] && <p className="form-error">Error pada baris data: Silakan periksa file Anda. {importErrors[0]}</p>}
                            </div>

                            <div className="flex items-start gap-2 rounded-lg border border-info/30 bg-info-light p-3 text-xs text-info">
                                <FileSpreadsheet className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>Pastikan file mengikuti format <strong>Template Excel</strong>. Kolom <strong>Nama Jabatan</strong>, <strong>Status Kepegawaian</strong>, <strong>Pendidikan Terakhir</strong>, dan <strong>Unit Sekolah</strong> sudah berupa dropdown. <strong>Unit Sekolah opsional</strong>: kosongkan jika semua pegawai di file ini masuk ke unit yang dipilih di atas (untuk import multi-unit, isi kolomnya per baris). Sistem menolak seluruh data jika ada satu baris saja yang salah format.</span>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t border-border pt-4">
                            <button type="button" onClick={() => setShowImportModal(false)} className="btn-secondary">
                                Batal
                            </button>
                            <button type="submit" disabled={importProcessing} className="btn-primary flex items-center gap-1.5">
                                {importProcessing ? <><Loader2 className="h-4 w-4 animate-spin" /> Mengimport…</> : <><Upload className="h-4 w-4" /> Upload & Proses</>}
                            </button>
                        </div>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
