import React, { useState, useEffect, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { Search, Filter, CheckCircle, XCircle, Clock, Info, User, FileText, Calendar, AlertCircle, ChevronRight } from 'lucide-react';

const TABS = [
    { key: 'l1', label: 'Approval L1' },
    { key: 'l2', label: 'Approval L2' },
    { key: 'semua', label: 'Semua' },
];

export default function Index({ auth, pengajuans, filters }) {
    const { flash } = usePage().props;
    const [selectedItem, setSelectedItem] = useState(null);
    const [modalType, setModalType] = useState(null);

    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || 'semua');
    const [dateFilter, setDateFilter] = useState(filters?.tanggal || '');
    const [activeTab, setActiveTab] = useState(filters?.tab || 'l1');

    const { data, setData, post, processing, errors, reset } = useForm({
        alasan_penolakan: ''
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
    }, [search, statusFilter, dateFilter]);

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

    const isSuperadmin = auth.permissions?.includes('manage_users');
    const canActOn = (item) => {
        if (!['pending_l1', 'pending_l2'].includes(item.approval_stage)) return false;
        if (isSuperadmin) return true;
        if (item.approval_stage === 'pending_l1') return item.approver_l1_id === auth.user.id;
        if (item.approval_stage === 'pending_l2') return item.approver_l2_id === auth.user.id;
        return false;
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Kelola Pengajuan Izin / Cuti</h2>}
        >
            <Head title="Pengajuan Izin" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {flash.message && (
                        <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm">
                            <CheckCircle className="w-5 h-5 mr-3 text-emerald-500" />
                            <span className="font-medium">{flash.message}</span>
                        </div>
                    )}
                    {flash.error && (
                        <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm">
                            <AlertCircle className="w-5 h-5 mr-3 text-red-500" />
                            <span className="font-medium">{flash.error}</span>
                        </div>
                    )}

                    {/* Tabs */}
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-1 mb-6 inline-flex">
                        {TABS.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => switchTab(t.key)}
                                className={`px-4 py-2 rounded-xl text-sm font-bold transition-all ${
                                    activeTab === t.key
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                                }`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>

                    {/* Filter Section */}
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div className="relative flex-1 max-w-md">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Search className="h-5 w-5 text-gray-400" />
                            </div>
                            <input
                                type="text"
                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-shadow"
                                placeholder="Cari nama pegawai atau NIK..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <div className="flex flex-col md:flex-row gap-3">
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Filter className="h-4 w-4 text-gray-400" />
                                </div>
                                <select
                                    className="block w-full pl-9 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-xl"
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
                                    <Calendar className="h-4 w-4 text-gray-400" />
                                </div>
                                <input
                                    type="date"
                                    className="block w-full pl-9 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-shadow"
                                    value={dateFilter}
                                    onChange={(e) => setDateFilter(e.target.value)}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Table Section */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50/50">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tanggal Pengajuan</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Pegawai</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jenis</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Waktu Izin/Cuti</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-100">
                                    {pengajuans.data.length === 0 ? (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-12 text-center">
                                                <FileText className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                                                <p className="text-gray-500 text-base">Tidak ada data pengajuan yang ditemukan.</p>
                                            </td>
                                        </tr>
                                    ) : pengajuans.data.map((item) => (
                                        <tr key={item.id} className="hover:bg-gray-50/50 transition-colors group">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {format(new Date(item.created_at), 'd MMM yyyy', { locale: idLocale })}
                                                <div className="text-xs text-gray-400">{format(new Date(item.created_at), 'HH:mm', { locale: idLocale })}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center">
                                                    <div className="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden border border-indigo-200">
                                                        {item.pegawai?.foto_url ? (
                                                            <img src={item.pegawai.foto_url} className="h-full w-full object-cover" alt="" />
                                                        ) : (
                                                            <span className="text-indigo-600 font-bold text-sm">
                                                                {item.pegawai?.nama_lengkap?.charAt(0) || 'P'}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="ml-4">
                                                        <div className="text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition-colors">{item.pegawai?.nama_lengkap}</div>
                                                        <div className="text-xs text-gray-500 font-mono mt-0.5">{item.pegawai?.nik}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="text-xs font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-100 px-2.5 py-1 rounded-md">
                                                    {item.jenis_izin}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-gray-900 font-medium">
                                                    {format(new Date(item.tanggal_mulai), 'd MMM yyyy', { locale: idLocale })}
                                                </div>
                                                {item.tanggal_mulai !== item.tanggal_selesai && (
                                                    <div className="text-xs text-gray-500 flex items-center mt-0.5">
                                                        <span className="text-gray-300 mx-1">s.d</span>
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
                                                    <button onClick={() => openModal(item, 'detail')} className="inline-flex items-center text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-2.5 py-1.5 rounded-lg transition-colors border border-transparent hover:border-blue-200" title="Detail">
                                                        <Info className="w-4 h-4 mr-1" /> Detail
                                                    </button>
                                                    {(item.status === 'pending' && canActOn(item)) && (
                                                        <>
                                                            <button onClick={() => openModal(item, 'approve')} className="inline-flex items-center text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-lg transition-colors border border-transparent hover:border-emerald-200" title="Setujui">
                                                                <CheckCircle className="w-4 h-4" />
                                                            </button>
                                                            <button onClick={() => openModal(item, 'reject')} className="inline-flex items-center text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg transition-colors border border-transparent hover:border-red-200" title="Tolak">
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
                            <div className="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex items-center justify-between">
                                <div className="text-sm text-gray-500">
                                    Menampilkan {pengajuans.from} hingga {pengajuans.to} dari {pengajuans.total} entri
                                </div>
                                <Pagination
                                    links={pengajuans.links}
                                    data={{ search, status: statusFilter, tanggal: dateFilter, tab: activeTab }}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal Detail / Approve / Reject */}
            <Modal show={selectedItem !== null} onClose={closeModal} maxWidth={modalType === 'detail' ? '2xl' : 'lg'}>
                {selectedItem && (
                    <div className="overflow-hidden bg-white rounded-xl">
                        {/* Modal Header */}
                        <div className={`px-6 py-4 border-b flex items-center justify-between ${
                            modalType === 'approve' ? 'bg-emerald-50 border-emerald-100' :
                            modalType === 'reject' ? 'bg-red-50 border-red-100' :
                            'bg-gray-50 border-gray-100'
                        }`}>
                            <h2 className={`text-lg font-bold flex items-center ${
                                modalType === 'approve' ? 'text-emerald-800' :
                                modalType === 'reject' ? 'text-red-800' :
                                'text-gray-900'
                            }`}>
                                {modalType === 'approve' && <CheckCircle className="w-5 h-5 mr-2" />}
                                {modalType === 'reject' && <XCircle className="w-5 h-5 mr-2" />}
                                {modalType === 'detail' && <Info className="w-5 h-5 mr-2 text-indigo-500" />}

                                {modalType === 'approve' ? 'Setujui Pengajuan' :
                                 modalType === 'reject' ? 'Tolak Pengajuan' :
                                 'Detail Pengajuan Izin'}
                            </h2>
                            <button onClick={closeModal} className="text-gray-400 hover:text-gray-600 transition-colors">
                                <XCircle className="w-6 h-6" />
                            </button>
                        </div>

                        <div className="p-6">
                            <div className="bg-gray-50/50 rounded-xl border border-gray-100 p-5 mb-6">
                                <div className="flex flex-col md:flex-row gap-6">
                                    <div className="flex-1 space-y-4">
                                        <div>
                                            <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Informasi Pegawai</span>
                                            <div className="flex items-center">
                                                <User className="w-4 h-4 text-gray-400 mr-2" />
                                                <span className="text-gray-900 font-bold">{selectedItem.pegawai?.nama_lengkap}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Jenis Pengajuan</span>
                                            <span className="inline-block px-2.5 py-1 bg-indigo-100 text-indigo-800 rounded-md text-xs font-bold uppercase tracking-wider">
                                                {selectedItem.jenis_izin}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Tanggal</span>
                                            <div className="flex items-center text-gray-900">
                                                <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                                                {format(new Date(selectedItem.tanggal_mulai), 'd MMMM yyyy', { locale: idLocale })}
                                                {selectedItem.tanggal_mulai !== selectedItem.tanggal_selesai && ` - ${format(new Date(selectedItem.tanggal_selesai), 'd MMMM yyyy', { locale: idLocale })}`}
                                            </div>
                                        </div>
                                        <div>
                                            <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Tahap Persetujuan</span>
                                            <div className="flex items-center gap-1 text-sm">
                                                <span className={`px-2 py-0.5 rounded text-xs font-bold ${selectedItem.approval_stage === 'pending_l1' ? 'bg-amber-200 text-amber-900' : selectedItem.approval_stage === 'approved' || selectedItem.approval_stage === 'rejected' ? 'bg-gray-100 text-gray-400' : 'bg-emerald-100 text-emerald-800'}`}>
                                                    L1
                                                </span>
                                                {(selectedItem.approver_l2_id || selectedItem.approval_stage === 'pending_l2') && (
                                                    <>
                                                        <ChevronRight className="w-4 h-4 text-gray-300" />
                                                        <span className={`px-2 py-0.5 rounded text-xs font-bold ${selectedItem.approval_stage === 'pending_l2' ? 'bg-orange-200 text-orange-900' : selectedItem.approval_stage === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-400'}`}>
                                                            L2
                                                        </span>
                                                    </>
                                                )}
                                                {selectedItem.approval_stage === 'approved' && (
                                                    <>
                                                        <ChevronRight className="w-4 h-4 text-gray-300" />
                                                        <span className="px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">Final</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex-1 space-y-4">
                                        <div>
                                            <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Alasan / Keterangan</span>
                                            <p className="text-gray-800 bg-white p-3 rounded-lg border border-gray-200 text-sm leading-relaxed">
                                                {selectedItem.alasan}
                                            </p>
                                        </div>
                                        {selectedItem.status === 'ditolak' && selectedItem.alasan_penolakan && (
                                            <div>
                                                <span className="text-xs font-semibold text-red-500 uppercase tracking-wider block mb-1">Alasan Penolakan</span>
                                                <p className="text-red-800 bg-red-50 p-3 rounded-lg border border-red-100 text-sm leading-relaxed">
                                                    {selectedItem.alasan_penolakan}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {selectedItem.bukti_foto_url && (
                                    <div className="mt-5 pt-5 border-t border-gray-200">
                                        <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-3">Bukti Lampiran</span>
                                        <div className="bg-gray-200 rounded-lg overflow-hidden border border-gray-300">
                                            <img src={selectedItem.bukti_foto_url} alt="Bukti Lampiran" className="w-full h-auto max-h-80 object-contain" />
                                        </div>
                                    </div>
                                )}
                            </div>

                            <form onSubmit={submitAction}>
                                {modalType === 'approve' && (
                                    <div className={`rounded-lg p-4 mb-6 ${
                                        selectedItem.approval_stage === 'pending_l1' && selectedItem.approver_l2_id
                                            ? 'bg-amber-50 border border-amber-200'
                                            : 'bg-emerald-50 border border-emerald-200'
                                    }`}>
                                        <p className={`text-sm ${
                                            selectedItem.approval_stage === 'pending_l1' && selectedItem.approver_l2_id
                                                ? 'text-amber-800' : 'text-emerald-800'
                                        }`}>
                                            {selectedItem.approval_stage === 'pending_l1' && selectedItem.approver_l2_id
                                                ? 'Anda akan menyetujui sebagai atasan L1. Pengajuan akan diteruskan ke atasan L2 untuk persetujuan akhir.'
                                                : 'Anda yakin ingin menyetujui pengajuan ini? Sistem akan meng-generate data absensi secara otomatis.'
                                            }
                                        </p>
                                    </div>
                                )}

                                {modalType === 'reject' && (
                                    <div className="mb-6">
                                        <label className="block text-sm font-semibold text-gray-700 mb-2">
                                            Tuliskan Alasan Penolakan <span className="text-red-500">*</span>
                                        </label>
                                        <textarea
                                            value={data.alasan_penolakan}
                                            onChange={e => setData('alasan_penolakan', e.target.value)}
                                            className="w-full border-gray-300 rounded-xl shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-3"
                                            rows="3"
                                            placeholder="Masukkan alasan mengapa izin ini ditolak..."
                                            required
                                        ></textarea>
                                    </div>
                                )}

                                <div className="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="px-5 py-2.5 rounded-xl text-sm font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors"
                                    >
                                        {modalType === 'detail' ? 'Tutup' : 'Batal'}
                                    </button>

                                    {modalType === 'approve' && (
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm disabled:opacity-50 flex items-center"
                                        >
                                            <CheckCircle className="w-4 h-4 mr-2" />
                                            Ya, Setujui
                                        </button>
                                    )}

                                    {modalType === 'reject' && (
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-red-600 hover:bg-red-700 transition-colors shadow-sm disabled:opacity-50 flex items-center"
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
