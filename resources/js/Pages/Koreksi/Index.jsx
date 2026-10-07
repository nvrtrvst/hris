import React, { useState, useEffect, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { Search, Filter, CheckCircle, XCircle, Clock, Info, FileText, AlertCircle, Inbox, CalendarCheck2, Printer } from 'lucide-react';

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

const ALASAN_LABEL = {
    lupa_presensi: 'Lupa presensi',
    hp_rusak: 'HP rusak',
    dinas_luar: 'Dinas luar',
    rapat: 'Rapat',
    lainnya: 'Lainnya',
};

const fmtJam = (j) => (j ? String(j).substring(0, 5) : '—');

export default function Index({ auth, pengajuans, filters, stats }) {
    const { flash } = usePage().props;
    const [selectedItem, setSelectedItem] = useState(null);
    const [modalType, setModalType] = useState(null);

    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || 'semua');

    const { data, setData, post, processing, errors, reset } = useForm({
        alasan_penolakan: '',
        catatan_approval: '',
    });

    const openModal = (item, type) => {
        setSelectedItem(item);
        setModalType(type);
        reset();
    };

    const closeModal = () => {
        setSelectedItem(null);
        setTimeout(() => setModalType(null), 300);
        reset();
    };

    const submitAction = (e) => {
        e.preventDefault();
        if (modalType === 'approve') {
            post(route('koreksi-presensi.approve', selectedItem.id), { onSuccess: () => closeModal() });
        } else if (modalType === 'reject') {
            post(route('koreksi-presensi.reject', selectedItem.id), { onSuccess: () => closeModal() });
        }
    };

    const handleFilter = () => {
        router.get(route('koreksi-presensi.index'), { search, status: statusFilter }, { preserveState: true, replace: true });
    };

    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timer = setTimeout(handleFilter, 300);
        return () => clearTimeout(timer);
    }, [search, statusFilter]);

    const cetak = (item) => window.open(route('koreksi-presensi.cetak', item.id), '_blank');

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Koreksi Presensi</h2>}
        >
            <Head title="Koreksi Presensi" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <div>
                        <h3 className="text-xl font-extrabold text-text-primary">Pengajuan Koreksi Presensi</h3>
                        <p className="text-sm text-text-muted">Tinjau dan proses pengajuan koreksi jam keluar dari pegawai.</p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="card flex items-center gap-4 p-5">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                <Inbox className="h-5 w-5 text-primary" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-2xl font-extrabold leading-tight text-text-primary tabular-nums">{stats?.total ?? 0}</p>
                                <p className="truncate text-xs font-semibold uppercase tracking-wide text-text-muted">Total Pengajuan</p>
                            </div>
                        </div>
                        <div className="card flex items-center gap-4 p-5">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50">
                                <Clock className="h-5 w-5 text-amber-600" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-2xl font-extrabold leading-tight text-text-primary tabular-nums">{stats?.pending ?? 0}</p>
                                <p className="truncate text-xs font-semibold uppercase tracking-wide text-text-muted">Menunggu Persetujuan</p>
                            </div>
                        </div>
                        <div className="card flex items-center gap-4 p-5">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50">
                                <CalendarCheck2 className="h-5 w-5 text-emerald-600" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-2xl font-extrabold leading-tight text-text-primary tabular-nums">{stats?.selesai ?? 0}</p>
                                <p className="truncate text-xs font-semibold uppercase tracking-wide text-text-muted">Selesai Diproses</p>
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

                    <div className="card p-4 sm:p-5 mb-6 flex flex-col gap-3">
                        <div className="relative w-full max-w-md">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Search className="h-5 w-5 text-text-muted" />
                            </div>
                            <input
                                type="text"
                                className="input-field pl-10"
                                placeholder="Cari nama pegawai..."
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
                        </div>
                    </div>

                    <div className="card overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface/50">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">No. / Tanggal</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Pegawai</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Presensi</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Jam Keluar</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Alasan</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Status</th>
                                        <th className="px-6 py-4 text-right text-xs font-bold text-text-muted uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-border">
                                    {pengajuans.data.length === 0 ? (
                                        <tr>
                                            <td colSpan="7" className="empty-state py-12">
                                                <FileText className="w-12 h-12 text-border mx-auto mb-3" />
                                                <p className="empty-state-desc">Tidak ada pengajuan koreksi yang ditemukan.</p>
                                            </td>
                                        </tr>
                                    ) : pengajuans.data.map((item) => (
                                        <tr key={item.id} className="hover:bg-surface/50 transition-colors group">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                <div className="font-mono text-xs font-bold text-text-primary">{item.nomor}</div>
                                                <div className="text-xs text-text-muted mt-0.5">
                                                    {format(new Date(item.created_at), 'd MMM yyyy HH:mm', { locale: idLocale })}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="text-sm font-bold text-text-primary group-hover:text-primary transition-colors">
                                                    {item.pegawai?.nama_lengkap}
                                                </div>
                                                <div className="text-xs text-text-muted font-mono mt-0.5">{item.pegawai?.nip || '—'}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-text-primary font-medium">
                                                    {item.tanggal ? format(new Date(item.tanggal), 'd MMM yyyy', { locale: idLocale }) : '—'}
                                                </div>
                                                <div className="text-xs text-text-muted">Masuk {fmtJam(item.presensi?.jam_masuk)}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                <span className="text-text-muted line-through">{item.presensi?.jam_keluar ? fmtJam(item.presensi.jam_keluar) : 'Belum tercatat'}</span>
                                                <span className="mx-1 text-border">→</span>
                                                <span className="font-bold text-text-primary">{fmtJam(item.nilai_baru)}</span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="badge badge-info uppercase">{ALASAN_LABEL[item.alasan] || item.alasan}</span>
                                                {item.penjelasan_khusus && (
                                                    <span className="ml-1 inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-800 border border-amber-200" title={item.penjelasan_khusus}>
                                                        <AlertCircle className="w-3 h-3" /> Lewat kuota
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {getStatusBadge(item.status)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div className="flex justify-end space-x-2 opacity-80 group-hover:opacity-100 transition-opacity">
                                                    <button onClick={() => openModal(item, 'detail')} className="btn-secondary btn-sm" title="Detail">
                                                        <Info className="w-4 h-4 mr-1" /> Detail
                                                    </button>
                                                    <button onClick={() => cetak(item)} className="btn-secondary btn-sm" title="Cetak">
                                                        <Printer className="w-4 h-4 mr-1" /> Cetak
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
                                    data={{ search, status: statusFilter }}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Modal show={selectedItem !== null} onClose={closeModal} maxWidth={modalType === 'detail' ? '2xl' : 'lg'}>
                {selectedItem && (
                    <div className="p-6">
                        <div className={`rounded-card p-4 mb-5 border ${
                            modalType === 'approve' ? 'bg-success-light border-success/30' :
                            modalType === 'reject' ? 'bg-danger-light border-danger/30' :
                            'bg-primary/5 border-primary/20'
                        }`}>
                            <div className="flex items-center">
                                {modalType === 'approve' && <CheckCircle className="w-5 h-5 mr-2 text-success" />}
                                {modalType === 'reject' && <XCircle className="w-5 h-5 mr-2 text-danger" />}
                                {modalType === 'detail' && <Info className="w-5 h-5 mr-2 text-primary" />}
                                <span className="font-bold text-text-primary">
                                    {modalType === 'approve' ? 'Setujui Koreksi' :
                                     modalType === 'reject' ? 'Tolak Koreksi' :
                                     'Detail Pengajuan Koreksi'}
                                </span>
                                <span className="ml-auto font-mono text-xs font-bold text-text-muted">{selectedItem.nomor}</span>
                            </div>
                        </div>

                        <div className="space-y-3 text-sm">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-card border border-border bg-surface p-3">
                                    <p className="text-xs font-semibold uppercase text-text-muted">Pegawai</p>
                                    <p className="mt-1 font-bold text-text-primary">{selectedItem.pegawai?.nama_lengkap}</p>
                                    <p className="text-xs text-text-muted font-mono">{selectedItem.pegawai?.nip || '—'}</p>
                                </div>
                                <div className="rounded-card border border-border bg-surface p-3">
                                    <p className="text-xs font-semibold uppercase text-text-muted">Tanggal Presensi</p>
                                    <p className="mt-1 font-bold text-text-primary">
                                        {selectedItem.tanggal ? format(new Date(selectedItem.tanggal), 'EEEE, d MMMM yyyy', { locale: idLocale }) : '—'}
                                    </p>
                                    <p className="text-xs text-text-muted">{selectedItem.presensi?.unitSekolah?.nama || '—'}</p>
                                </div>
                            </div>

                            <div className="rounded-card border border-border bg-surface p-3">
                                <p className="text-xs font-semibold uppercase text-text-muted">Koreksi Jam Keluar</p>
                                <p className="mt-1">
                                    <span className="text-text-muted line-through">{selectedItem.presensi?.jam_keluar ? fmtJam(selectedItem.presensi.jam_keluar) : 'Belum tercatat'}</span>
                                    <span className="mx-2 text-border">→</span>
                                    <span className="font-bold text-text-primary">{fmtJam(selectedItem.nilai_baru)}</span>
                                    <span className="ml-2 text-xs text-text-muted">· Masuk {fmtJam(selectedItem.presensi?.jam_masuk)}</span>
                                </p>
                            </div>

                            <div className="rounded-card border border-border bg-surface p-3">
                                <p className="text-xs font-semibold uppercase text-text-muted">Alasan</p>
                                <p className="mt-1 font-semibold text-text-primary">{ALASAN_LABEL[selectedItem.alasan] || selectedItem.alasan}</p>
                                {selectedItem.alasan_detail && <p className="mt-1 text-text-secondary">{selectedItem.alasan_detail}</p>}
                            </div>

                            {selectedItem.penjelasan_khusus && (
                                <div className="rounded-card border border-amber-200 bg-amber-50 p-3">
                                    <p className="flex items-center gap-1.5 text-xs font-semibold uppercase text-amber-800"><AlertCircle className="h-3.5 w-3.5 shrink-0" /> Lewat kuota — Penjelasan Khusus</p>
                                    <p className="mt-1 text-amber-900">{selectedItem.penjelasan_khusus}</p>
                                </div>
                            )}

                            <div className="rounded-card border border-border bg-surface p-3">
                                <p className="text-xs font-semibold uppercase text-text-muted">Status Approval</p>
                                <div className="mt-1 flex items-center gap-2">
                                    {getStatusBadge(selectedItem.status)}
                                    {selectedItem.status === 'disetujui' && (
                                        <span className="text-xs text-text-muted">
                                            oleh {selectedItem.approver?.name || '—'}
                                            {selectedItem.approved_at && ` · ${format(new Date(selectedItem.approved_at), 'd MMM yyyy HH:mm', { locale: idLocale })}`}
                                        </span>
                                    )}
                                    {selectedItem.status === 'ditolak' && (
                                        <span className="text-xs text-text-muted">
                                            oleh {selectedItem.rejectedByUser?.name || '—'}
                                            {selectedItem.rejected_at && ` · ${format(new Date(selectedItem.rejected_at), 'd MMM yyyy HH:mm', { locale: idLocale })}`}
                                        </span>
                                    )}
                                </div>
                                {selectedItem.catatan_approval && (
                                    <p className="mt-2 text-text-secondary">Catatan: {selectedItem.catatan_approval}</p>
                                )}
                                {selectedItem.alasan_penolakan && (
                                    <p className="mt-2 text-danger">Alasan penolakan: {selectedItem.alasan_penolakan}</p>
                                )}
                            </div>
                        </div>

                        <form onSubmit={submitAction}>
                            {modalType === 'approve' && (
                                <div className="mt-4 mb-2">
                                    <label className="form-label text-sm font-semibold">Catatan Persetujuan <span className="text-text-muted">(opsional)</span></label>
                                    <textarea
                                        value={data.catatan_approval}
                                        onChange={(e) => setData('catatan_approval', e.target.value)}
                                        className="input-field w-full"
                                        rows="2"
                                        placeholder="Tambahkan catatan untuk pegawai..."
                                    ></textarea>
                                </div>
                            )}

                            {modalType === 'reject' && (
                                <div className="mt-4 mb-2">
                                    <label className="form-label text-sm font-semibold">
                                        Tuliskan Alasan Penolakan <span className="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        value={data.alasan_penolakan}
                                        onChange={(e) => setData('alasan_penolakan', e.target.value)}
                                        className="input-field w-full"
                                        rows="3"
                                        placeholder="Masukkan alasan mengapa koreksi ini ditolak..."
                                        required
                                    ></textarea>
                                    {errors.alasan_penolakan && <p className="mt-1 text-xs text-danger">{errors.alasan_penolakan}</p>}
                                </div>
                            )}

                            <div className="flex justify-end gap-3 pt-4 border-t border-border">
                                <button type="button" onClick={closeModal} className="btn-secondary">
                                    {modalType === 'detail' ? 'Tutup' : 'Batal'}
                                </button>

                                {modalType === 'detail' && (
                                    <button type="button" onClick={() => cetak(selectedItem)} className="btn-primary">
                                        <Printer className="w-4 h-4 mr-2" />
                                        Cetak Formulir
                                    </button>
                                )}

                                {modalType === 'approve' && (
                                    <button type="submit" disabled={processing} className="btn-primary bg-success hover:bg-green-700">
                                        <CheckCircle className="w-4 h-4 mr-2" />
                                        Ya, Setujui
                                    </button>
                                )}

                                {modalType === 'reject' && (
                                    <button type="submit" disabled={processing} className="btn-danger">
                                        <XCircle className="w-4 h-4 mr-2" />
                                        Ya, Tolak Pengajuan
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
