import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id } from 'date-fns/locale';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge, Empty } from '@/Components/MobileUI';
import { ArrowLeft, CheckCircle2, ChevronDown, ClipboardCheck } from 'lucide-react';

export default function PiketRiwayat({ riwayat = [], label = {} }) {
    const { auth } = usePage().props;
    const [openId, setOpenId] = useState(null);

    return (
        <MobileLayout user={auth.user}>
            <Head title="Riwayat Piket" />

            <div className="mb-5 px-1">
                <Link href={route('presensi.piket.index')} className="mb-2 inline-flex items-center text-sm font-semibold text-primary transition-colors active:scale-95">
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Piket hari ini
                </Link>
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Riwayat Piket</h1>
                <p className="mt-0.5 text-sm text-slate-500">Laporan yang sudah Anda kirim, 30 hari terakhir.</p>
            </div>

            {riwayat.length === 0 ? (
                <Empty icon={ClipboardCheck} title="Belum ada laporan" subtitle="Laporan terkirim akan tampil di sini." />
            ) : (
                <div className="space-y-3">
                    {riwayat.map((lap) => {
                        const open = openId === lap.id;
                        const items = lap.items || {};
                        return (
                            <Card key={lap.id} press={false}>
                                <button
                                    type="button"
                                    onClick={() => setOpenId(open ? null : lap.id)}
                                    aria-expanded={open}
                                    className="flex w-full items-center justify-between gap-3 text-left focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/15 rounded-xl"
                                >
                                    <span>
                                        <span className="block text-sm font-bold text-slate-900">
                                            {format(parseISO(lap.tanggal), 'EEEE, d MMM yyyy', { locale: id })}
                                        </span>
                                        <span className="mt-0.5 block text-xs text-slate-500">{lap.done}/{lap.total} item{lap.submitted_at ? ` · terkirim ${lap.submitted_at}` : ''}</span>
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1.5">
                                        {lap.status === 'submitted'
                                            ? <Badge tone="emerald" icon={CheckCircle2}>Terkirim</Badge>
                                            : <Badge tone="amber">Draft</Badge>}
                                        <ChevronDown className={`h-4 w-4 text-slate-500 transition-transform ${open ? 'rotate-180' : ''}`} />
                                    </span>
                                </button>
                                {open && (
                                    <div className="mt-3 space-y-2 border-t border-slate-100 pt-3 text-sm">
                                        {items.gerbang?.done_at && (
                                            <p><span className="font-bold">{label.gerbang}:</span> {items.gerbang.done_at.substring(0, 5)} ({items.gerbang.terlambat_count ?? 0} terlambat)</p>
                                        )}
                                        {items.cek_absen && (items.cek_absen.entries ?? []).length > 0 && (
                                            <div>
                                                <p className="font-bold">{label.cek_absen}:</p>
                                                <ul className="mt-1 list-disc space-y-0.5 pl-5 text-xs text-slate-600">
                                                    {items.cek_absen.entries.map((e, i) => <li key={i}>{e.nama_guru}: {e.keterangan}</li>)}
                                                </ul>
                                            </div>
                                        )}
                                        {items.kelas_kosong && ((items.kelas_kosong.entries ?? []).length > 0 ? (
                                            <div>
                                                <p className="font-bold">{label.kelas_kosong}:</p>
                                                <ul className="mt-1 list-disc space-y-0.5 pl-5 text-xs text-slate-600">
                                                    {items.kelas_kosong.entries.map((e, i) => (
                                                        <li key={i}>{e.kelas}{e.mapel ? `, ${e.mapel}` : ''} ({e.guru_nama}){e.ada_amanat ? `: ${e.detail_amanat}` : ''}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ) : items.kelas_kosong.nihil ? <p><span className="font-bold">{label.kelas_kosong}:</span> Nihil.</p> : null)}
                                        {items.buku?.done_at && <p><span className="font-bold">{label.buku}:</span> {items.buku.done_at.substring(0, 5)}</p>}
                                        {items.insiden && ((items.insiden.entries ?? []).length > 0 ? (
                                            <div>
                                                <p className="font-bold">{label.insiden}:</p>
                                                <ul className="mt-1 list-disc space-y-0.5 pl-5 text-xs text-slate-600">
                                                    {items.insiden.entries.map((e, i) => <li key={i}>{e.jenis}: {e.tindakan}</li>)}
                                                </ul>
                                            </div>
                                        ) : items.insiden.nihil ? <p><span className="font-bold">{label.insiden}:</span> Nihil.</p> : null)}
                                    </div>
                                )}
                            </Card>
                        );
                    })}
                </div>
            )}
        </MobileLayout>
    );
}
