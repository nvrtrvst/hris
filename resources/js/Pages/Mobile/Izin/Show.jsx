import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge } from '@/Components/MobileUI';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import { ArrowLeft, Clock, CheckCircle, XCircle, Calendar, FileText, AlertTriangle } from 'lucide-react';

const jenisLabel = { sakit: 'Sakit', izin: 'Izin', cuti: 'Cuti' };

function getStatus(status) {
    if (status === 'disetujui') return { tone: 'emerald', icon: CheckCircle, label: 'Disetujui' };
    if (status === 'ditolak') return { tone: 'rose', icon: XCircle, label: 'Ditolak' };
    return { tone: 'amber', icon: Clock, label: 'Menunggu' };
}

function daysBetween(start, end) {
    const s = new Date(start);
    const e = new Date(end);
    return Math.round((e - s) / 86400000) + 1;
}

export default function Show({ auth, pengajuan }) {
    const st = getStatus(pengajuan.status);
    const StatusIcon = st.icon;
    const totalDays = daysBetween(pengajuan.tanggal_mulai, pengajuan.tanggal_selesai);

    return (
        <MobileLayout user={auth.user}>
            <Head title={`Pengajuan ${jenisLabel[pengajuan.jenis_izin] || pengajuan.jenis_izin}`} />

            <Link
                href={route('presensi.izin.index')}
                className="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary transition-colors active:scale-95"
            >
                <ArrowLeft className="h-4 w-4" /> Kembali
            </Link>

            {/* Header card */}
            <Card className="mb-4">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <Badge tone="indigo">{jenisLabel[pengajuan.jenis_izin] || pengajuan.jenis_izin}</Badge>
                        <h1 className="mt-2 text-lg font-extrabold text-slate-900">Detail Pengajuan</h1>
                    </div>
                    <Badge tone={st.tone} icon={StatusIcon}>{st.label}</Badge>
                </div>
            </Card>

            {/* Info card */}
            <Card className="mb-4 space-y-4">
                {/* Periode */}
                <div className="flex items-start gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100">
                        <Calendar className="h-4 w-4 text-slate-500" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Periode</p>
                        <p className="mt-0.5 text-sm font-bold text-slate-800">
                            {format(new Date(pengajuan.tanggal_mulai), 'd MMM yyyy', { locale: id })}
                            {pengajuan.tanggal_mulai !== pengajuan.tanggal_selesai && (
                                <> &ndash; {format(new Date(pengajuan.tanggal_selesai), 'd MMM yyyy', { locale: id })}</>
                            )}
                        </p>
                        <p className="mt-0.5 text-xs text-slate-400">{totalDays} hari</p>
                    </div>
                </div>

                {/* Alasan */}
                <div className="flex items-start gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100">
                        <FileText className="h-4 w-4 text-slate-500" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Alasan</p>
                        <p className="mt-0.5 text-sm text-slate-700 whitespace-pre-wrap">{pengajuan.alasan}</p>
                    </div>
                </div>

                {/* Bukti foto */}
                {pengajuan.bukti_foto_url && (
                    <div className="flex items-start gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100">
                            <FileText className="h-4 w-4 text-slate-500" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Bukti Foto</p>
                            <img
                                src={pengajuan.bukti_foto_url}
                                alt="Bukti foto pengajuan"
                                className="mt-2 max-h-60 w-full rounded-2xl object-contain ring-1 ring-slate-200"
                            />
                        </div>
                    </div>
                )}
            </Card>

            {/* Status detail card */}
            {(pengajuan.status === 'disetujui' || pengajuan.status === 'ditolak') && (
                <Card className={`mb-4 ${
                    pengajuan.status === 'disetujui'
                        ? 'border-emerald-200 bg-emerald-50/50'
                        : 'border-rose-200 bg-rose-50/50'
                }`}>
                    <div className="flex items-start gap-3">
                        <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${
                            pengajuan.status === 'disetujui' ? 'bg-emerald-100' : 'bg-rose-100'
                        }`}>
                            <StatusIcon className={`h-4 w-4 ${
                                pengajuan.status === 'disetujui' ? 'text-emerald-600' : 'text-rose-600'
                            }`} />
                        </div>
                        <div className="min-w-0">
                            <p className={`text-sm font-bold ${
                                pengajuan.status === 'disetujui' ? 'text-emerald-800' : 'text-rose-800'
                            }`}>
                                Pengajuan {st.label}
                            </p>
                            {/* Disetujui oleh L1 */}
                            {pengajuan.approved_at_l1 && (
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Disetujui L1: {pengajuan.approver_l1?.name || 'Atasan'}
                                    <span className="ml-1 text-slate-400">· {format(new Date(pengajuan.approved_at_l1), 'd MMM yyyy, HH:mm', { locale: id })}</span>
                                </p>
                            )}
                            {/* Disetujui oleh L2 */}
                            {pengajuan.approved_at_l2 && (
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Disetujui L2: {pengajuan.approver_l2?.name || 'Atasan'}
                                    <span className="ml-1 text-slate-400">· {format(new Date(pengajuan.approved_at_l2), 'd MMM yyyy, HH:mm', { locale: id })}</span>
                                </p>
                            )}
                            {/* Catatan atasan */}
                            {pengajuan.catatan_approval && (
                                <div className="mt-2 rounded-xl bg-white/80 p-3 ring-1 ring-emerald-100">
                                    <p className="text-xs font-bold text-emerald-700">Catatan Atasan:</p>
                                    <p className="mt-0.5 text-sm text-emerald-600">{pengajuan.catatan_approval}</p>
                                </div>
                            )}
                            {/* Ditolak */}
                            {pengajuan.status === 'ditolak' && pengajuan.alasan_penolakan && (
                                <div className="mt-2 rounded-xl bg-white/80 p-3 ring-1 ring-rose-100">
                                    <p className="flex items-center gap-1 text-xs font-bold text-rose-700">
                                        <AlertTriangle className="h-3 w-3" /> Alasan Penolakan
                                    </p>
                                    <p className="mt-1 text-sm text-rose-600">{pengajuan.alasan_penolakan}</p>
                                    {pengajuan.rejected_by_user && (
                                        <p className="mt-1 text-xs text-rose-400">Ditolak oleh {pengajuan.rejected_by_user.name}</p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </Card>
            )}

            {/* Pending notice */}
            {pengajuan.status === 'pending' && (
                <Card className="mb-4 border-amber-200 bg-amber-50/50">
                    <div className="flex items-start gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100">
                            <Clock className="h-4 w-4 text-amber-600" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-amber-800">Menunggu Persetujuan</p>
                            <p className="mt-0.5 text-xs text-amber-600">Pengajuan Anda sedang ditinjau oleh atasan.</p>
                        </div>
                    </div>
                </Card>
            )}
        </MobileLayout>
    );
}
