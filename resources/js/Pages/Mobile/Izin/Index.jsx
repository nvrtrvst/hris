import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { format } from 'date-fns';
import { id } from 'date-fns/locale/id';
import { FileText, Plus, ArrowLeft, Clock, CheckCircle, XCircle } from 'lucide-react';

export default function Index({ auth, pengajuan }) {
    const { flash } = usePage().props;

    const getStatusColor = (status) => {
        if (status === 'disetujui') return 'bg-green-100 text-green-800 border-green-200';
        if (status === 'ditolak') return 'bg-red-100 text-red-800 border-red-200';
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    };

    const getStatusIcon = (status) => {
        if (status === 'disetujui') return <CheckCircle className="w-4 h-4 mr-1" />;
        if (status === 'ditolak') return <XCircle className="w-4 h-4 mr-1" />;
        return <Clock className="w-4 h-4 mr-1" />;
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Pengajuan Izin & Cuti" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-extrabold text-gray-900">Izin & Cuti</h1>
                    <p className="text-sm text-gray-500">Kelola riwayat kehadiran Anda</p>
                </div>
                <Link
                    href={route('mobile.izin.create')}
                    className="flex items-center justify-center bg-emerald-500 text-white font-bold py-2.5 px-4 rounded-xl shadow-md hover:bg-emerald-600 transition-colors"
                >
                    <Plus className="w-4 h-4 mr-1.5" />
                    Buat Baru
                </Link>
            </div>

            <div className="relative z-20">
                {flash.message && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 text-sm font-medium">
                        {flash.message}
                    </div>
                )}
                {flash.error && (
                    <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm font-medium">
                        {flash.error}
                    </div>
                )}

                <h2 className="text-gray-800 font-bold mb-3 flex items-center text-sm uppercase tracking-wider">
                    <FileText className="w-4 h-4 mr-1.5 text-indigo-500" />
                    Riwayat Pengajuan
                </h2>

                <div className="space-y-4">
                    {pengajuan.length === 0 ? (
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
                            <div className="bg-gray-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                                <FileText className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-gray-900 font-semibold mb-1">Belum ada pengajuan</h3>
                            <p className="text-gray-500 text-sm">Anda belum pernah mengajukan izin, sakit, atau cuti.</p>
                        </div>
                    ) : (
                        pengajuan.map((item) => (
                            <div key={item.id} className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                                <div className="flex justify-between items-start mb-3">
                                    <div>
                                        <span className="inline-block bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-md font-bold uppercase tracking-wide">
                                            {item.jenis_izin}
                                        </span>
                                    </div>
                                    <span className={`inline-flex items-center text-xs px-2.5 py-1 rounded-full border font-medium ${getStatusColor(item.status)}`}>
                                        {getStatusIcon(item.status)}
                                        {item.status.charAt(0).toUpperCase() + item.status.slice(1)}
                                    </span>
                                </div>
                                
                                <div className="mb-2">
                                    <p className="text-sm font-semibold text-gray-800">
                                        {format(new Date(item.tanggal_mulai), 'd MMM yyyy', { locale: id })}
                                        {item.tanggal_mulai !== item.tanggal_selesai && ` - ${format(new Date(item.tanggal_selesai), 'd MMM yyyy', { locale: id })}`}
                                    </p>
                                    <p className="text-sm text-gray-600 mt-1">{item.alasan}</p>
                                </div>

                                {item.status === 'ditolak' && item.alasan_penolakan && (
                                    <div className="mt-3 bg-red-50 border border-red-100 rounded-lg p-3">
                                        <p className="text-xs font-semibold text-red-800 mb-1">Alasan Penolakan:</p>
                                        <p className="text-sm text-red-600">{item.alasan_penolakan}</p>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>
            </div>
        </MobileLayout>
    );
}
