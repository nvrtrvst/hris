import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge, FAB, Empty } from '@/Components/MobileUI';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import { FileText, Plus, Clock, CheckCircle, XCircle } from 'lucide-react';

export default function Index({ auth, pengajuan }) {
    const { flash } = usePage().props;

    const getStatus = (status) => {
        if (status === 'disetujui') return { tone: 'emerald', icon: CheckCircle, label: 'Disetujui' };
        if (status === 'ditolak') return { tone: 'rose', icon: XCircle, label: 'Ditolak' };
        return { tone: 'amber', icon: Clock, label: 'Menunggu' };
    };

    const jenisLabel = { sakit: 'Sakit', izin: 'Izin', cuti: 'Cuti' };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Pengajuan Izin & Cuti" />

            <div className="mb-5 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Izin & Cuti</h1>
                <p className="mt-0.5 text-sm text-slate-500">Kelola riwayat pengajuan Anda</p>
            </div>

            {flash.message && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.message}</div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{flash.error}</div>
            )}

            {pengajuan.length === 0 ? (
                <Empty icon={FileText} title="Belum ada pengajuan" subtitle="Anda belum pernah mengajukan izin, sakit, atau cuti." />
            ) : (
                <div className="space-y-3">
                    {pengajuan.map((item) => {
                        const st = getStatus(item.status);
                        return (
                            <Card key={item.id} className="py-4">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <Badge tone="indigo">{jenisLabel[item.jenis_izin] || item.jenis_izin}</Badge>
                                        <p className="mt-2 text-sm font-bold text-slate-800">
                                            {format(new Date(item.tanggal_mulai), 'd MMM yyyy', { locale: id })}
                                            {item.tanggal_mulai !== item.tanggal_selesai && ` – ${format(new Date(item.tanggal_selesai), 'd MMM yyyy', { locale: id })}`}
                                        </p>
                                        <p className="mt-0.5 text-sm text-slate-500">{item.alasan}</p>
                                    </div>
                                    <Badge tone={st.tone} icon={st.icon}>{st.label}</Badge>
                                </div>
                                {item.status === 'ditolak' && item.alasan_penolakan && (
                                    <div className="mt-3 rounded-2xl border border-rose-100 bg-rose-50 p-3">
                                        <p className="text-xs font-bold text-rose-800">Alasan Penolakan:</p>
                                        <p className="mt-0.5 text-sm text-rose-600">{item.alasan_penolakan}</p>
                                    </div>
                                )}
                            </Card>
                        );
                    })}
                </div>
            )}

            <FAB href={route('mobile.izin.create')} icon={Plus} label="Buat Pengajuan" />
        </MobileLayout>
    );
}
