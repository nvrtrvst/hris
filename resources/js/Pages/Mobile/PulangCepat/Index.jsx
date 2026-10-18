import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge, Empty } from '@/Components/MobileUI';
import { format, parseISO } from 'date-fns';
import { id } from 'date-fns/locale';
import { Clock3, CheckCircle, XCircle, Printer, Plus } from 'lucide-react';
import { realisasiInfo } from '@/Utils/pulangAwal';

const realisasiTone = {
    emerald: 'border-emerald-100 bg-emerald-50 text-emerald-700',
    amber: 'border-amber-100 bg-amber-50 text-amber-700',
    slate: 'border-slate-200 bg-slate-50 text-slate-600',
};

export default function Index({ auth, pengajuan }) {
    const { flash } = usePage().props;

    const getStatus = (status) => {
        if (status === 'disetujui') return { tone: 'emerald', icon: CheckCircle, label: 'Disetujui' };
        if (status === 'ditolak') return { tone: 'rose', icon: XCircle, label: 'Ditolak' };
        return { tone: 'amber', icon: Clock3, label: 'Menunggu' };
    };

    const alasanLabel = {
        sakit: 'Sakit',
        urusan_keluarga: 'Urusan keluarga',
        keperluan_pribadi: 'Keperluan pribadi',
        dinas_luar: 'Dinas luar',
        lainnya: 'Lainnya',
    };

    const fmtJam = (j) => (j ? j.substring(0, 5) : '—');

    return (
        <MobileLayout user={auth.user}>
            <Head title="Izin Pulang Cepat" />

            <div className="mb-5 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Izin Pulang Cepat</h1>
                <p className="mt-0.5 text-sm text-slate-500">Pengajuan izin pulang lebih awal</p>
            </div>

            {flash.message && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.message}</div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{flash.error}</div>
            )}

            <Link
                href={route('presensi.pulang-cepat.create')}
                className="mb-4 flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition-transform active:scale-[0.99]"
            >
                <Plus className="h-4 w-4" /> Ajukan Izin Pulang Cepat
            </Link>

            {pengajuan.length === 0 ? (
                <Empty
                    icon={Clock3}
                    title="Belum ada pengajuan"
                    subtitle="Ajukan izin bila perlu pulang lebih awal dari jam kerja/jam mengajar."
                />
            ) : (
                <div className="space-y-3">
                    {pengajuan.map((item) => {
                        const st = getStatus(item.status);
                        const realisasi = realisasiInfo(item.realisasi, item.status);
                        return (
                            <Card key={item.id} className="py-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700">
                                            {item.nomor}
                                        </span>
                                        <p className="mt-2 text-sm font-bold text-slate-800">
                                            {format(parseISO(item.tanggal), 'd MMMM yyyy', { locale: id })}
                                        </p>
                                        <p className="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                            <Clock3 className="h-4 w-4 text-emerald-400" />
                                            Pulang pukul <span className="font-bold text-slate-700">{fmtJam(item.jam_diminta)}</span>
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-400">
                                            {alasanLabel[item.alasan] || item.alasan}
                                            {item.alasan_detail ? ` — ${item.alasan_detail}` : ''}
                                        </p>
                                    </div>
                                    <Badge tone={st.tone} icon={st.icon}>{st.label}</Badge>
                                </div>

                                {realisasi && (
                                    <div className={`mt-3 flex items-start gap-2 rounded-2xl border px-3 py-2 text-xs font-semibold ${realisasiTone[realisasi.tone]}`}>
                                        {realisasi.tone === 'emerald'
                                            ? <CheckCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            : <Clock3 className="mt-0.5 h-3.5 w-3.5 shrink-0" />}
                                        <span>
                                            {realisasi.ada
                                                ? <>
                                                    Tercatat pulang {realisasi.jam}
                                                    {realisasi.awal && ` — ${realisasi.menit} menit lebih awal (${realisasi.diizinkan ? 'diizinkan' : 'tanpa izin'})`}
                                                </>
                                                : 'Belum tercatat pulang — tidak ada presensi keluar pada tanggal ini.'}
                                        </span>
                                    </div>
                                )}

                                {item.status === 'ditolak' && item.alasan_penolakan && (
                                    <div className="mt-3 rounded-2xl border border-rose-100 bg-rose-50 p-3">
                                        <p className="text-xs font-bold text-rose-800">Alasan Penolakan:</p>
                                        <p className="mt-0.5 text-sm text-rose-600">{item.alasan_penolakan}</p>
                                    </div>
                                )}

                                <button
                                    type="button"
                                    onClick={() => window.open(route('presensi.pulang-cepat.cetak', item.id), '_blank')}
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
