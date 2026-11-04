import React, { useState, useEffect } from 'react';
import { subscribeRouter } from '@/Utils/routerEvents';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, PageHeader, Select } from '@/Components/AdminUI';
import FilterSheet from '@/Components/FilterSheet';
import StatCard from '@/Components/StatCard';
import Pagination from '@/Components/Pagination';
import { Head, Link, router } from '@inertiajs/react';
import { avatarTone, initials } from '@/Utils/avatar';
import {
    Banknote,
    CheckCircle2,
    Clock3,
    Eye,
    FileText,
    Lock,
    RotateCcw,
    Settings2,
    Sparkles,
    Trash2,
    Wallet,
} from 'lucide-react';

const STATUS_META = {
    draft: { label: 'Draft', badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
    finalized: { label: 'Finalized', badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    paid: { label: 'Dibayar', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
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

const formatRupiah = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(angka || 0);

export default function Index({ auth, penggajians, stats = {}, periodeOptions = [], filters = {} }) {
    const isAdmin = auth.permissions?.includes('view_payroll') || auth.is_payroll_operator;
    const isAdminUnit = isAdmin && !auth.permissions?.includes('view_all_units');
    const [processing, setProcessing] = useState(false);

    useEffect(() => subscribeRouter({
        start: () => setProcessing(true),
        finish: () => setProcessing(false),
    }), []);

    const [filterPeriode, setFilterPeriode] = useState(filters.periode_bulan || '');
    const [filterStatus, setFilterStatus] = useState(filters.status || '');

    const hasFilter = Boolean(filterPeriode || filterStatus);

    const resetFilters = () => {
        setFilterPeriode('');
        setFilterStatus('');
        router.get(route('penggajian.index'), {}, { preserveState: true });
    };

    const s = stats || { total: 0, total_bersih: 0, draft: 0, finalized: 0, paid: 0 };

    const statCards = [
        { label: 'Total Slip', value: s.total, sub: 'terfilter', Icon: FileText, iconBg: 'bg-primary/10', iconCls: 'text-primary' },
        { label: 'Total Gaji Bersih', value: formatRupiah(s.total_bersih), Icon: Wallet, iconBg: 'bg-emerald-100', iconCls: 'text-emerald-600' },
        { label: 'Draft', value: s.draft, Icon: Clock3, iconBg: 'bg-amber-100', iconCls: 'text-amber-600' },
        { label: 'Finalized', value: s.finalized, Icon: Lock, iconBg: 'bg-blue-100', iconCls: 'text-blue-600' },
        { label: 'Dibayar', value: s.paid, Icon: CheckCircle2, iconBg: 'bg-cyan-100', iconCls: 'text-cyan-600' },
    ];

    const filterSelect = 'h-9 text-xs';
    const [filterSheetOpen, setFilterSheetOpen] = React.useState(false);
    const activeFilterCount = [filterPeriode, filterStatus].filter(Boolean).length;

    const unitName = (p) => p.pegawai?.units?.[0]?.singkatan || p.pegawai?.units?.[0]?.nama || '-';

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Sistem Penggajian (Payroll)</h2>}
        >
            <Head title="Penggajian" />
            <div className="mx-auto w-full max-w-[1600px] space-y-5">
                <PageHeader
                    eyebrow="Penggajian"
                    title="Riwayat Penggajian"
                    description="Pantau slip gaji per periode, dari draft hingga dibayar."
                    actions={isAdmin ? (
                        <>
                            <Link href={route('komponen-gaji.index')} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                <Settings2 className="h-4 w-4" /> Konfigurasi
                            </Link>
                            <Link href={route('penggajian.run')} className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-primary px-4 text-xs font-bold text-white transition-colors hover:bg-primary-600">
                                <Sparkles className="h-4 w-4" /> Run Payroll
                            </Link>
                        </>
                    ) : null}
                />

                {/* Stats */}
                {isAdmin && (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {statCards.map((card) => <StatCard key={card.label} {...card} />)}
                    </div>
                )}

                {/* Filter bar */}
                    {isAdmin && (
                        <FilterSheet
                            open={filterSheetOpen}
                            onOpenChange={setFilterSheetOpen}
                            activeCount={activeFilterCount}
                            onReset={() => { resetFilters(); setFilterSheetOpen(false); }}
                        >
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <Select
                                    className={filterSelect}
                                    value={filterPeriode}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setFilterPeriode(val);
                                        router.get(route('penggajian.index'), { periode_bulan: val, status: filterStatus }, { preserveState: true, preserveScroll: true });
                                    }}
                                >
                                    <option value="">Semua Periode</option>
                                    {periodeOptions.map((p) => <option key={p} value={p}>{p}</option>)}
                                </Select>
                                <Select
                                    className={filterSelect}
                                    value={filterStatus}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setFilterStatus(val);
                                        router.get(route('penggajian.index'), { periode_bulan: filterPeriode, status: val }, { preserveState: true, preserveScroll: true });
                                    }}
                                >
                                    <option value="">Semua Status</option>
                                    {Object.entries(STATUS_META).map(([key, m]) => <option key={key} value={key}>{m.label}</option>)}
                                </Select>
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
                    )}

                    {/* Mass Delete Draft Payroll */}
                    {isAdmin && (
                        <Card className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 className="text-sm font-bold text-primary">Hapus Draft</h4>
                                <p className="mt-1 text-xs text-text-secondary">Hapus semua data draft periode ini</p>
                            </div>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => {
                                    if (confirm(`Yakin hapus semua draft slip gaji periode ${filterPeriode}?`)) {
                                        router.delete(route('penggajian.destroyPeriod'), { preserveState: true, preserveScroll: true, body: { periode_bulan: filterPeriode } });
                                    }
                                }}
                            >
                                Hapus Semua Draft
                                <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                        </Card>
                    )}
                    {/* Table */}
                    <Card className={`overflow-hidden p-0 transition-opacity ${processing ? 'pointer-events-none opacity-60' : ''}`}>
                        <div className="overflow-x-auto">
                            <table className="w-full table-fixed divide-y divide-border">
                                <thead className="bg-surface/80 sticky top-0 z-10 backdrop-blur-sm">
                                    <tr>
                                        <th className="hidden px-4 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-text-secondary md:table-cell md:w-28">Periode</th>
                                        <th className="px-4 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-text-secondary">Pegawai</th>
                                        <th className="hidden px-4 py-3.5 text-right text-[11px] font-bold uppercase tracking-wider text-text-secondary lg:table-cell">Pendapatan</th>
                                        <th className="hidden px-4 py-3.5 text-right text-[11px] font-bold uppercase tracking-wider text-text-secondary lg:table-cell">Potongan</th>
                                        <th className="w-32 px-4 py-3.5 text-right text-[11px] font-bold uppercase tracking-wider text-text-secondary sm:w-40">Gaji Bersih</th>
                                        <th className="hidden px-4 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-text-secondary md:table-cell md:w-32">Status</th>
                                        <th className="w-16 px-2 py-3.5 text-right text-[11px] font-bold uppercase tracking-wider text-text-secondary md:w-32 md:px-3 lg:w-60 lg:px-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/50">
                                    {penggajians.data.length > 0 ? penggajians.data.map((p) => {
                                        const nama = p.pegawai?.nama_lengkap || '-';

                                        return (
                                            <tr key={p.id} className="group transition-colors hover:bg-surface/70">
                                                <td className="hidden whitespace-nowrap px-4 py-3.5 md:table-cell">
                                                    <span className="inline-flex items-center rounded-full bg-primary/5 px-3 py-1 font-mono text-xs font-bold text-primary">{p.periode_bulan}</span>
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <div className="flex items-center gap-3">
                                                        <span className={`hidden h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold sm:flex ${avatarTone(nama)}`}>
                                                            {initials(nama)}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <div className="text-sm font-bold leading-tight text-primary">{nama}</div>
                                                            <div className="text-[11px] text-text-secondary">{unitName(p)}</div>
                                                            <div className="mt-1 flex flex-wrap items-center gap-1.5 md:hidden">
                                                                <span className="inline-flex items-center rounded-full bg-primary/5 px-2 py-0.5 font-mono text-[10px] font-bold text-primary">{p.periode_bulan}</span>
                                                                <StatusBadge status={p.status} />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="hidden whitespace-nowrap px-4 py-3.5 text-right text-sm font-medium text-emerald-600 tabular-nums lg:table-cell">
                                                    {formatRupiah(p.total_pendapatan)}
                                                </td>
                                                <td className="hidden whitespace-nowrap px-4 py-3.5 text-right text-sm font-medium text-rose-600 tabular-nums lg:table-cell">
                                                    {formatRupiah(p.total_potongan)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3.5 text-right text-base font-bold text-primary tabular-nums">
                                                    {formatRupiah(p.gaji_bersih)}
                                                </td>
                                                <td className="hidden whitespace-nowrap px-4 py-3.5 md:table-cell">
                                                    <StatusBadge status={p.status} />
                                                </td>
                                                <td className="px-2 py-3.5 text-right md:px-3 lg:px-4">
                                                    <div className="flex flex-col items-end gap-1 md:flex-row md:items-center md:justify-end md:gap-1.5">
                                                        {isAdmin && p.status === 'draft' && (
                                                            <>
                                                                {isAdminUnit && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => {
                                                                            if (confirm(`Finalisasi slip gaji ${nama} (${p.periode_bulan})?`)) {
                                                                                router.post(route('penggajian.finalize', p.id), {}, { preserveState: true });
                                                                            }
                                                                        }}
                                                                        className="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 p-1.5 text-[11px] font-bold text-emerald-700 transition-colors hover:bg-emerald-100 lg:px-2.5 lg:py-1.5"
                                                                        title="Finalisasi slip gaji ini"
                                                                    >
                                                                        <Lock className="h-3 w-3" />
                                                                        <span className="hidden lg:inline">Finalisasi</span>
                                                                        <span className="sr-only lg:hidden">Finalisasi</span>
                                                                    </button>
                                                                )}
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        if (confirm(`Hapus draft slip gaji ${nama} (${p.periode_bulan})?`)) {
                                                                            router.delete(route('penggajian.destroy', p.id), { preserveState: true });
                                                                        }
                                                                    }}
                                                                    className="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 p-1.5 text-rose-600 transition-colors hover:bg-rose-100"
                                                                    title="Hapus draft slip gaji"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                    <span className="sr-only">Hapus draft</span>
                                                                </button>
                                                            </>
                                                        )}
                                                        <Link
                                                            href={route('penggajian.show', p.id)}
                                                            className="btn-primary btn-sm flex items-center gap-1.5 p-1.5 lg:px-3 lg:py-1.5"
                                                        >
                                                            <Eye className="h-3.5 w-3.5" />
                                                            <span className="hidden lg:inline">Slip</span>
                                                            <span className="sr-only lg:hidden">Slip</span>
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    }) : (
                                        <tr>
                                            <td colSpan="7" className="px-6 py-16">
                                                <div className="flex flex-col items-center text-center">
                                                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                                        <Banknote className="h-8 w-8 text-border" />
                                                    </div>
                                                    <p className="mt-4 text-base font-bold text-primary">Belum ada data penggajian</p>
                                                    <p className="mt-1 text-sm text-text-secondary">
                                                        {hasFilter ? 'Coba ubah atau bersihkan filter.' : 'Jalankan Run Payroll Wizard untuk membuat draft gaji pertama.'}
                                                    </p>
                                                    {hasFilter ? (
                        <Button variant="secondary" size="sm" className="mt-4" onClick={resetFilters}>
                            <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                        </Button>
                                                    ) : isAdmin ? (
                                                        <Link href={route('penggajian.run')} className="btn-primary btn-sm mt-4 flex items-center gap-1.5">
                                                            <Sparkles className="h-3.5 w-3.5" /> Buka Run Payroll Wizard
                                                        </Link>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {/* Pagination */}
                    {penggajians.total > 0 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-text-secondary">
                                Menampilkan <b className="text-primary">{penggajians.from || 0}</b> - <b className="text-primary">{penggajians.to || 0}</b> dari <b className="text-primary">{penggajians.total}</b> data
                            </p>
                            <Pagination links={penggajians.links} pagination={{ current_page: penggajians.current_page, last_page: penggajians.last_page }} />
                        </div>
                    )}
            </div>
        </AdminShell>
    );
}

