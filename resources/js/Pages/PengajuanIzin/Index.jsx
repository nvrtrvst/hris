import React, { useState, useEffect, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { Search, Filter, CheckCircle, XCircle, Clock, Info, User, FileText, Calendar, AlertCircle, ChevronRight, CalendarCheck2, Inbox, Users } from 'lucide-react';

const TABS = [
    { key: 'l1', label: 'Approval L1' },
    { key: 'l2', label: 'Approval L2' },
    { key: 'semua', label: 'Semua' },
];

export default function Index({ auth, pengajuans, filters, stats }) {
    const { flash } = usePage().props;
    const [selectedItem, setSelectedItem] = useState(null);
    const [modalType, setModalType] = useState(null);

    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || 'semua');
    const [dateFilter, setDateFilter] = useState(filters?.tanggal || '');
    const [jenisFilter, setJenisFilter] = useState(filters?.jenis_filter || '');
    const [activeTab, setActiveTab] = useState(filters?.tab || 'l1');

    const { data, setData, post, processing, errors, reset } = useForm({
        alasan_penolakan: '',
        catatan_approval: '',
        dihitung_hadir_kcd: false,
    });

    const openModal = (item, type) => {
        setSelectedItem(item);
        setModalType(type);
        reset();
        setData('catatan_approval', '');
    };

    const closeModal = () => {
        setSelectedItem(null);
        setTimeout(() => setModalType(null), 300);
        reset();
        setData('catatan_approval', '');
    };

    const submitAction = (e) => {
        e.preventDefault();
        if (modalType === 'approve') {
            post(route('pengajuan-izin.approve', selectedItem.id), {
                onSuccess: () => closeModal()
            });
        } else if (modalType === 'reject') {
            post(route('pengajuan-izin.reject', selectedItem.id), {
                onSuccess: () => closeModal()
            });
        }
    };

    const handleFilter = (tab) => {
        router.get(route('pengajuan-izin.index'), {
            search,
            status: statusFilter,
            tanggal: dateFilter,
            jenis_filter: jenisFilter,
            tab: tab || activeTab,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const switchTab = (tab) => {
        setActiveTab(tab);
        handleFilter(tab);
    };

    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timer = setTimeout(() => handleFilter(), 300);
        return () => clearTimeout(timer);
    }, [search, statusFilter, dateFilter, jenisFilter]);

    const getStatusBadge = (status) => {
        switch (status) {
            case 'disetujui':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800 border border-green-200">
                        <CheckCircle className="w-3.5 h-3.5 mr-1" />
                        Disetujui
                    </span>
                );
            case 'ditolak':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                        <XCircle className="w-3.5 h-3.5 mr-1" />
                        Ditolak
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800 border border-yellow-200">
                        <Clock className="w-3.5 h-3.5 mr-1" />
                        Pending
                    </span>
                );
        }
    };

    const getStageBadge = (stage) => {
        switch (stage) {
            case 'pending_l1':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200 ml-1">
                        L1
                    </span>
                );
            case 'pending_l2':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-orange-100 text-orange-800 border border-orange-200 ml-1">
                        L2
                    </span>
                );
            case 'approved':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200 ml-1">
                        Final
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-red-100 text-red-800 border border-red-200 ml-1">
                        Ditolak
                    </span>
                );
            default:
                return null;
        }
    };


    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Kelola Pengajuan Izin / Cuti</h2>}
        >
            <Head title="Pengajuan Izin" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div>
                        <h3 className="text-xl font-extrabold text-text-primary">Pengajuan Izin / Cuti</h3>
                        <p className="text-sm text-text-muted">Tinjau dan proses pengajuan izin, sakit, dan cuti dari pegawai.</p>
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="card flex items-center gap-4 p-5">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                <Inbox className="h-5 w-5 text-primary" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-2xl font-extrabold leading-tight text-text-primary tabular-nums">{stats?.total ?? 0}</p>
                                <p className="truncate text-xs font-semibold uppercase tracking-wide text-text-muted">Total Pengajuan</p>
                                <p className="truncate text-[11px] text-text-muted">Sesuai tab & filter aktif</p>
                            </div>
                        </div>
                        <div className="card flex items-center gap-4 p-5">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50">
                                <Clock className="h-5 w-5 text-amber-600" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-2xl font-extrabold leading-tight text-text-primary tabular-nums">{stats?.pending ?? 0}</p>
                                <p className="truncate text-xs font-semibold uppercase tracking-wide text-text-muted">Menunggu Persetujuan</p>
                                <p className="truncate text-[11px] text-text-muted">Butuh tindakan Anda</p>
                            </div>
                        </div>
                        <div className="card flex items-center gap-4 p-5">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50">
                                <CalendarCheck2 className="h-5 w-5 text-emerald-600" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-2xl font-extrabold leading-tight text-text-primary tabular-nums">{stats?.selesai ?? 0}</p>
                                <p className="truncate text-xs font-semibold uppercase tracking-wide text-text-muted">Selesai Diproses</p>
                                <p className="truncate text-[11px] text-text-muted">Disetujui / ditolak</p>
                            </div>
                        </div>
                    </div>

                    {flash.message && (
                        <div className="bg-success-light border border-success/30 text-success px-4 py-3 rounded-card mb-6 flex items-center shadow-card">
                            <CheckCircle className="w-5 h-5 mr-3 text-success" />
                            <span className="font-medium">{flash.message}</span>
                        </div>
                    )}
                    {flash.error && (
                        <div className="bg-danger-light border border-danger/30 text-danger px-4 py-3 rounded-card mb-6 flex items-center shadow-card">
                            <AlertCircle className="w-5 h-5 mr-3 text-danger" />
                            <span className="font-medium">{flash.error}</span>
                        </div>
                    )}

                    {/* Tabs */}
                    <div className="card p-1 mb-6 inline-flex overflow-x-auto max-w-full">
                        {TABS.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => switchTab(t.key)}
                                className={`px-4 py-2 rounded-button text-sm font-bold transition-all ${
                                    activeTab === t.key
                                        ? 'bg-primary text-white shadow-card'
                                        : 'text-text-secondary hover:text-text-primary hover:bg-surface'
                                }`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>

                    {/* Filter Section */}
                    <div className="card p-4 sm:p-5 mb-6 flex flex-col gap-3">
                        <div className="relative w-full max-w-md">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Search className="h-5 w-5 text-text-muted" />
                            </div>
                            <input
                                type="text"
                                className="input-field pl-10"
                                placeholder="Cari nama pegawai atau NIK..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Filter className="h-4 w-4 text-text-muted" />
                                </div>
                                <select
                                    className="select-field pl-9"
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                >
                                    <option value="semua">Semua Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="disetujui">Disetujui</option>
                                    <option value="ditolak">Ditolak</option>
                                </select>
                            </div>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Calendar className="h-4 w-4 text-text-muted" />
                                </div>
                                <input
                                    type="date"
                                    className="input-field pl-9"
                                    value={dateFilter}
                                    onChange={(e) => setDateFilter(e.target.value)}
                                />
                            </div>
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Users className="h-4 w-4 text-text-muted" />
                                </div>
                                <select
                                    className="select-field pl-9"
                                    value={jenisFilter}
                                    onChange={(e) => setJenisFilter(e.target.value)}
                                >
                                    <option value="">Semua Jenis</option>
                                    <option value="pendidik">Pendidik (Guru)</option>
                                    <option value="kependidikan">Tenaga Kependidikan</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Mobile: Card list */}
                    <div className="md:hidden space-y-3 mb-6">
                        {pengajuans.data.length === 0 ? (
                            <div className="card px-6 py-12 text-center">
                                <FileText className="w-10 h-10 text-border mx-auto mb-3" />
                                <p className="text-sm font-bold text-primary">Tidak ada data</p>
                            </div>
                        ) : pengajuans.data.map((item) => (
                            <div key={item.id} className="card p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-3 min-w-0">
                                        <div className="flex-shrink-0 h-9 w-9 rounded-full bg-primary/10 flex items-center justify-center border border-primary/20">
                                            {item.pegawai?.foto_url ? (
                                                <img src={item.pegawai.foto_url} className="h-full w-full object-cover rounded-full" alt="" />
                                            ) : (
                                                <span className="text-primary font-bold text-xs">{item.pegawai?.nama_lengkap?.charAt(0) || 'P'}</span>
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-bold text-primary truncate">{item.pegawai?.nama_lengkap}</p>
                                            <p className="text-[11px] text-text-muted">{format(new Date(item.created_at), 'd MMM yyyy, HH:mm', { locale: idLocale })}</p>
                                        </div>
                                    </div>
                                    <span className="badge badge-info uppercase shrink-0 text-[10px]">{item.jenis_izin}</span>
                                </div>
                                <div className="mt-2.5 flex items-center justify-between">
                                    <div className="text-xs text-text-secondary">
                                        {format(new Date(item.tanggal_mulai), 'd MMM', { locale: idLocale })}
                                        {item.tanggal_mulai !== item.tanggal_selesai && (
                                            <span> s.d {format(new Date(item.tanggal_selesai), 'd MMM', { locale: idLocale })}</span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        {getStatusBadge(item.status)}
                                        {getStageBadge(item.approval_stage)}
                                    </div>
                                </div>
                                <div className="mt-3 flex justify-end gap-2 border-t border-border/50 pt-2.5">
                                    <button onClick={() => openModal(item, 'detail')} className="btn-secondary btn-sm py-1 px-2.5">
                                        <Info className="w-3.5 h-3.5" /> Detail
                                    </button>
                                    {(item.status === 'pending' && item.can_act) && (
                                        <>
                                            <button onClick={() => openModal(item, 'approve')} className="btn-sm py-1 px-2.5 bg-success-light text-success rounded-button font-medium hover:bg-success/20">
                                                <CheckCircle className="w-3.5 h-3.5" />
                                            </button>
                                            <button onClick={() => openModal(item, 'reject')} className="btn-sm py-1 px-2.5 bg-danger-light text-danger rounded-button font-medium hover:bg-danger/20">
                                                <XCircle className="w-3.5 h-3.5" />
                                            </button>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Desktop: Table */}
                    <div className="hidden md:block card-table">
                        <div className="overflow-x-auto">
                            <table className="table-base">
                                <thead className="bg-surface/50">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Tanggal Pengajuan</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Pegawai</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Jenis</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Waktu Izin/Cuti</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Status</th>
                                        <th className="px-6 py-4 text-right text-xs font-bold text-text-muted uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-border">
                                    {pengajuans.data.length === 0 ? (
                                        <tr>
                                            <td colSpan="6" className="empty-state py-12">
                                                <FileText className="w-12 h-12 text-border mx-auto mb-3" />
                                                <p className="empty-state-desc">Tidak ada data pengajuan yang ditemukan.</p>
                                            </td>
                                        </tr>
                                    ) : pengajuans.data.map((item) => (
                                        <tr key={item.id} className="hover:bg-surface/50 transition-colors group">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-text-muted">
                                                {format(new Date(item.created_at), 'd MMM yyyy', { locale: idLocale })}
                                                <div className="text-xs text-text-muted/60">{format(new Date(item.created_at), 'HH:mm', { locale: idLocale })}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center">
                                                    <div className="flex-shrink-0 h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center overflow-hidden border border-primary/20">
                                                        {item.pegawai?.foto_url ? (
                                                            <img src={item.pegawai.foto_url} className="h-full w-full object-cover" alt="" />
                                                        ) : (
                                                            <span className="text-primary font-bold text-sm">
                                                                {item.pegawai?.nama_lengkap?.charAt(0) || 'P'}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="ml-4">
                                                        <div className="text-sm font-bold text-text-primary group-hover:text-primary transition-colors">{item.pegawai?.nama_lengkap}</div>
                                                        <div className="text-xs text-text-muted font-mono mt-0.5">{item.pegawai?.nip || '—'}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="badge badge-info uppercase">
                                                    {item.jenis_izin}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-text-primary font-medium">
                                                    {format(new Date(item.tanggal_mulai), 'd MMM yyyy', { locale: idLocale })}
                                                </div>
                                                {item.tanggal_mulai !== item.tanggal_selesai && (
                                                    <div className="text-xs text-text-muted flex items-center mt-0.5">
                                                        <span className="text-border mx-1">s.d</span>
                                                        {format(new Date(item.tanggal_selesai), 'd MMM yyyy', { locale: idLocale })}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    {getStatusBadge(item.status)}
                                                    {getStageBadge(item.approval_stage)}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div className="flex justify-end space-x-2 opacity-80 group-hover:opacity-100 transition-opacity">
                                                    <button onClick={() => openModal(item, 'detail')} className="btn-secondary btn-sm" title="Detail">
                                                        <Info className="w-4 h-4 mr-1" /> Detail
                                                    </button>
                                                    {(item.status === 'pending' && item.can_act) && (
                                                        <>
                                                            <button onClick={() => openModal(item, 'approve')} className="btn-sm inline-flex items-center justify-center gap-2 font-medium bg-success-light text-success rounded-button hover:bg-success/20 focus:ring-2 focus:ring-success/40 disabled:opacity-50" title="Setujui">
                                                                <CheckCircle className="w-4 h-4" />
                                                            </button>
                                                            <button onClick={() => openModal(item, 'reject')} className="btn-sm inline-flex items-center justify-center gap-2 font-medium bg-danger-light text-danger rounded-button hover:bg-danger/20 focus:ring-2 focus:ring-danger/40 disabled:opacity-50" title="Tolak">
                                                                <XCircle className="w-4 h-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {pengajuans.links && pengajuans.data.length > 0 && (
                            <div className="pagination px-6 py-4 border-t border-border bg-surface/50">
                                <div className="text-sm text-text-muted">
                                    Menampilkan {pengajuans.from} hingga {pengajuans.to} dari {pengajuans.total} entri
                                </div>
                                <Pagination
                                    links={pengajuans.links}
                                    data={{ search, status: statusFilter, tanggal: dateFilter, jenis_filter: jenisFilter, tab: activeTab }}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal Detail / Approve / Reject */}
            <Modal show={selectedItem !== null} onClose={closeModal} maxWidth={modalType === 'detail' ? '2xl' : 'lg'}>
                {selectedItem && (
                    <div className="overflow-hidden bg-white rounded-card">
                        {/* Modal Header */}
                        <div className={`page-card-header px-6 py-4 ${
                            modalType === 'approve' ? 'bg-success-light border-success-light' :
                            modalType === 'reject' ? 'bg-danger-light border-danger-light' :
                            'bg-surface border-border'
                        }`}>
                            <h2 className={`text-lg font-bold flex items-center ${
                                modalType === 'approve' ? 'text-success' :
                                modalType === 'reject' ? 'text-danger' :
                                'text-text-primary'
                            }`}>
                                {modalType === 'approve' && <CheckCircle className="w-5 h-5 mr-2" />}
                                {modalType === 'reject' && <XCircle className="w-5 h-5 mr-2" />}
                                {modalType === 'detail' && <Info className="w-5 h-5 mr-2 text-primary" />}

                                {modalType === 'approve' ? 'Setujui Pengajuan' :
                                 modalType === 'reject' ? 'Tolak Pengajuan' :
                                 'Detail Pengajuan Izin'}
                            </h2>
                            <button onClick={closeModal} className="text-text-muted hover:text-text-secondary transition-colors">
                                <XCircle className="w-6 h-6" />
                            </button>
                        </div>

                        <div className="p-6">
                            <div className="bg-surface/50 rounded-card border border-border p-5 mb-6">
                                <div className="flex flex-col md:flex-row gap-6">
                                    <div className="flex-1 space-y-4">
                                        <div>
                                            <span className="section-title block mb-1">Informasi Pegawai</span>
                                            <div className="flex items-center">
                                                <User className="w-4 h-4 text-text-muted mr-2" />
                                                <span className="text-text-primary font-bold">{selectedItem.pegawai?.nama_lengkap}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <span className="section-title block mb-1">Jenis Pengajuan</span>
                                            <span className="badge badge-info uppercase font-bold">
                                                {selectedItem.jenis_izin}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="section-title block mb-1">Tanggal</span>
                                            <div className="flex items-center text-text-primary">
                                                <Calendar className="w-4 h-4 text-text-muted mr-2" />
                                                {format(new Date(selectedItem.tanggal_mulai), 'd MMMM yyyy', { locale: idLocale })}
                                                {selectedItem.tanggal_mulai !== selectedItem.tanggal_selesai && ` - ${format(new Date(selectedItem.tanggal_selesai), 'd MMMM yyyy', { locale: idLocale })}`}
                                            </div>
                                        </div>
                                        <div>
                                            <span className="section-title block mb-1">Tahap Persetujuan</span>
                                            <div className="flex items-center gap-1 text-sm">
                                                <span className={`px-2 py-0.5 rounded text-xs font-bold ${selectedItem.approval_stage === 'pending_l1' ? 'badge-warning' : selectedItem.approval_stage === 'approved' || selectedItem.approval_stage === 'rejected' ? 'badge-neutral' : 'badge-success'}`}>
                                                    L1
                                                </span>
                                                {(selectedItem.approver_l2_id || selectedItem.approval_stage === 'pending_l2') && (
                                                    <>
                                                        <ChevronRight className="w-4 h-4 text-border" />
                                                        <span className={`px-2 py-0.5 rounded text-xs font-bold ${selectedItem.approval_stage === 'pending_l2' ? 'badge-warning' : selectedItem.approval_stage === 'approved' ? 'badge-success' : 'badge-neutral'}`}>
                                                            L2
                                                        </span>
                                                    </>
                                                )}
                                                {selectedItem.approval_stage === 'approved' && (
                                                    <>
                                                        <ChevronRight className="w-4 h-4 text-border" />
                                                        <span className="badge-success">Final</span>
                                                    </>
                                                )}
                                            </div>
                                            {/* Disetujui oleh */}
                                            {selectedItem.approved_at_l1 && (
                                                <p className="mt-2 text-xs text-text-muted">
                                                    Disetujui oleh {selectedItem.approver_l1?.name || 'Atasan'}
                                                    {selectedItem.approved_at_l2 && (
                                                        <>, {selectedItem.approver_l2?.name || 'Atasan'}</>
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex-1 space-y-4">
                                        <div>
                                            <span className="section-title block mb-1">Alasan / Keterangan</span>
                                            <p className="text-text-secondary bg-white p-3 rounded-card border border-border text-sm leading-relaxed">
                                                {selectedItem.alasan}
                                            </p>
                                        </div>
                                        {selectedItem.status === 'ditolak' && selectedItem.alasan_penolakan && (
                                            <div>
                                                <span className="section-title text-danger block mb-1">Alasan Penolakan</span>
                                                <p className="text-danger bg-danger-light p-3 rounded-card border border-danger/20 text-sm leading-relaxed">
                                                    {selectedItem.alasan_penolakan}
                                                </p>
                                            </div>
                                        )}
                                        {selectedItem.catatan_approval && (
                                            <div>
                                                <span className="section-title block mb-1">Catatan Persetujuan</span>
                                                <p className="text-text-secondary bg-white p-3 rounded-card border border-border text-sm leading-relaxed">
                                                    {selectedItem.catatan_approval}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {selectedItem.bukti_foto_url && (
                                    <div className="mt-5 pt-5 border-t border-border">
                                        <span className="section-title block mb-3">Bukti Lampiran</span>
                                        <div className="bg-gray-50/80 rounded-card overflow-hidden border border-border-light">
                                            <img src={selectedItem.bukti_foto_url} alt="Bukti Lampiran" className="w-full h-auto max-h-80 object-contain" />
                                        </div>
                                    </div>
                                )}
                            </div>

                            <form onSubmit={submitAction}>
                                {modalType === 'approve' && (
                                    <>
                                        <div className={`rounded-card p-4 mb-4 ${
                                            selectedItem.approval_stage === 'pending_l1' && selectedItem.approver_l2_id
                                                ? 'bg-warning-light border border-warning/30'
                                                : 'bg-success-light border border-success/30'
                                        }`}>
                                            <p className={`text-sm ${
                                                selectedItem.approval_stage === 'pending_l1' && selectedItem.approver_l2_id
                                                    ? 'text-warning' : 'text-success'
                                            }`}>
                                                {selectedItem.approval_stage === 'pending_l1' && selectedItem.approver_l2_id
                                                    ? 'Anda akan menyetujui sebagai atasan L1. Pengajuan akan diteruskan ke atasan L2 untuk persetujuan akhir.'
                                                    : 'Anda yakin ingin menyetujui pengajuan ini? Sistem akan meng-generate data absensi secara otomatis.'}
                                            </p>
                                        </div>
                                        <div className="mb-6">
                                            <label className="form-label text-sm font-semibold">Catatan Persetujuan <span className="text-text-muted">(opsional)</span></label>
                                            <textarea
                                                value={data.catatan_approval}
                                                onChange={e => setData('catatan_approval', e.target.value)}
                                                className="input-field w-full"
                                                rows="2"
                                                placeholder="Tambahkan catatan untuk pegawai..."
                                            ></textarea>
                                        </div>
                                        <label className="flex items-start gap-2.5 rounded-card border border-border bg-surface p-3">
                                            <input
                                                type="checkbox"
                                                checked={data.dihitung_hadir_kcd}
                                                onChange={e => setData('dihitung_hadir_kcd', e.target.checked)}
                                                className="mt-0.5 h-4 w-4 accent-[#0F3D3E]"
                                            />
                                            <span className="text-sm text-text-secondary">
                                                Dihitung hadir untuk Laporan KCD <span className="text-text-muted">(izin/sakit ditampilkan sebagai HADIR berdasarkan jam kantor)</span>
                                            </span>
                                        </label>
                                    </>
                                )}

                                {modalType === 'reject' && (
                                    <div className="mb-6">
                                        <label className="form-label text-sm font-semibold">
                                            Tuliskan Alasan Penolakan <span className="text-red-500">*</span>
                                        </label>
                                        <textarea
                                            value={data.alasan_penolakan}
                                            onChange={e => setData('alasan_penolakan', e.target.value)}
                                            className="input-field w-full"
                                            rows="3"
                                            placeholder="Masukkan alasan mengapa izin ini ditolak..."
                                            required
                                        ></textarea>
                                    </div>
                                )}

                                <div className="flex justify-end gap-3 pt-4 border-t border-border">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="btn-secondary"
                                    >
                                        {modalType === 'detail' ? 'Tutup' : 'Batal'}
                                    </button>

                                    {modalType === 'approve' && (
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="btn-primary bg-success hover:bg-green-700"
                                        >
                                            <CheckCircle className="w-4 h-4 mr-2" />
                                            Ya, Setujui
                                        </button>
                                    )}

                                    {modalType === 'reject' && (
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="btn-danger"
                                        >
                                            <XCircle className="w-4 h-4 mr-2" />
                                            Ya, Tolak Pengajuan
                                        </button>
                                    )}
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
