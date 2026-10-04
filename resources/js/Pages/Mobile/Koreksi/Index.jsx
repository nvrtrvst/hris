import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge, Empty } from '@/Components/MobileUI';
import { format, parseISO } from 'date-fns';
import { id } from 'date-fns/locale';
import { FileText, Clock, CheckCircle, XCircle, Printer, AlertTriangle } from 'lucide-react';

export default function Index({ auth, pengajuan, koreksi_count, kuota }) {
    const { flash } = usePage().props;

    const getStatus = (status) => {
        if (status === 'disetujui') return { tone: 'emerald', icon: CheckCircle, label: 'Disetujui' };
        if (status === 'ditolak') return { tone: 'rose', icon: XCircle, label: 'Ditolak' };
        return { tone: 'amber', icon: Clock, label: 'Menunggu' };
    };

    const alasanLabel = {
        lupa_presensi: 'Lupa presensi',
        hp_rusak: 'HP rusak',
        dinas_luar: 'Dinas luar',
        rapat: 'Rapat',
        lainnya: 'Lainnya',
    };

    const fmtJam = (j) => (j ? j.substring(0, 5) : '—');

    return (
        <MobileLayout user={auth.user}>
            <Head title="Pengajuan Koreksi Presensi" />

            <div className="mb-5 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Koreksi Presensi</h1>
                <p className="mt-0.5 text-sm text-slate-500">Pengajuan koreksi jam keluar Anda</p>
                <p className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-bold ${koreksi_count >= kuota ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'}`}>
                    Kuota disetujui bulan ini: {koreksi_count}/{kuota}
                </p>
            </div>

            {flash.message && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.message}</div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{flash.error}</div>
            )}

            {pengajuan.length === 0 ? (
                <Empty
                    icon={FileText}
                    title="Belum ada pengajuan koreksi"
                    subtitle="Ajukan koreksi dari halaman Riwayat saat absen masuk tercatat tapi absen keluar belum."
                />
            ) : (
                <div className="space-y-3">
                    {pengajuan.map((item) => {
                        const st = getStatus(item.status);
                        const lewatKuota = Boolean(item.penjelasan_khusus);
                        return (
                            <Card key={item.id} className="py-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700">
                                                {item.nomor}
                                            </span>
                                            {lewatKuota && (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700">
                                                    <AlertTriangle className="h-3 w-3" /> Lewat kuota
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-2 text-sm font-bold text-slate-800">
                                            {format(parseISO(item.tanggal), 'd MMMM yyyy', { locale: id })}
                                        </p>
                                        <p className="mt-0.5 text-sm text-slate-500">
                                            Jam keluar: {fmtJam(item.presensi?.jam_keluar)}
                                            {item.presensi?.jam_keluar ? ' → ' : ''}
                                            <span className="font-bold text-slate-700">{fmtJam(item.nilai_baru)}</span>
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-400">
                                            {alasanLabel[item.alasan] || item.alasan}
                                            {item.alasan_detail ? ` — ${item.alasan_detail}` : ''}
                                        </p>
                                    </div>
                                    <Badge tone={st.tone} icon={st.icon}>{st.label}</Badge>
                                </div>

                                {item.status === 'ditolak' && item.alasan_penolakan && (
                                    <div className="mt-3 rounded-2xl border border-rose-100 bg-rose-50 p-3">
                                        <p className="text-xs font-bold text-rose-800">Alasan Penolakan:</p>
                                        <p className="mt-0.5 text-sm text-rose-600">{item.alasan_penolakan}</p>
                                    </div>
                                )}

                                <button
                                    type="button"
                                    onClick={() => window.open(route('presensi.koreksi.cetak', item.id), '_blank')}
                                    className="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-slate-100 py-2.5 text-xs font-bold text-slate-600 transition active:scale-95"
                                >
                                    <Printer className="h-3.5 w-3.5" /> Cetak Formulir
                                </button>
                            </Card>
                        );
                    })}
                </div>
            )}
        </MobileLayout>
    );
}
