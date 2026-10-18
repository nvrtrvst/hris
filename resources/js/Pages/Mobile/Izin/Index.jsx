import React from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge, Empty } from '@/Components/MobileUI';
import { format, parseISO } from 'date-fns';
import { id } from 'date-fns/locale';
import { FileText, Clock, CheckCircle, XCircle, ChevronRight, ChevronDown } from 'lucide-react';

const jenisLabel = { sakit: 'Sakit', izin: 'Izin', cuti: 'Cuti' };

const fmtTanggal = (t) => format(parseISO(t), 'd MMM yyyy', { locale: id });
const durasiHari = (mulai, selesai) => Math.round((parseISO(selesai) - parseISO(mulai)) / 86400000) + 1;

// pending_l2 = sudah lolos atasan langsung, tinggal satu tingkat lagi.
function statusOf(item) {
    if (item.status === 'disetujui') return { tone: 'emerald', icon: CheckCircle, label: 'Disetujui' };
    if (item.status === 'ditolak') return { tone: 'rose', icon: XCircle, label: 'Ditolak' };
    if (item.approval_stage === 'pending_l2') return { tone: 'sky', icon: Clock, label: 'Menunggu' };
    return { tone: 'amber', icon: Clock, label: 'Menunggu' };
}

export default function Index({ auth, pengajuan, filters = {}, stats = {}, pegawai }) {
    const { flash } = usePage().props;
    const items = pengajuan?.data ?? [];
    const adaFilter = filters.status !== 'semua' || Boolean(filters.jenis) || Boolean(filters.bulan);

    const chips = [
        { value: 'semua', label: 'Semua', count: stats.total },
        { value: 'pending', label: 'Menunggu', count: stats.pending },
        { value: 'disetujui', label: 'Disetujui', count: stats.disetujui },
        { value: 'ditolak', label: 'Ditolak', count: stats.ditolak },
    ];

    // Filter disimpan di URL — tombol back & bookmark ikut benar.
    const applyFilters = (next) => {
        const params = { ...filters, ...next };
        Object.keys(params).forEach((key) => {
            if (!params[key] || params[key] === 'semua') delete params[key];
        });
        router.get(route('presensi.izin.index'), params, { preserveState: true, replace: true });
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Pengajuan Izin & Cuti" />

            <div className="mb-5 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Izin & Cuti</h1>
                <p className="mt-0.5 text-sm text-slate-500">Kelola riwayat pengajuan Anda</p>
                {pegawai?.sisa_cuti != null && (
                    <p className="mt-2 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">
                        Sisa cuti tahunan: {pegawai.sisa_cuti} hari
                    </p>
                )}
            </div>

            <Link
                href={route('presensi.pulang-cepat.index')}
                className="mb-4 flex min-h-16 items-center gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition active:scale-[0.99] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/20"
            >
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-primary">
                    <Clock className="h-5 w-5" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block text-sm font-bold text-slate-800">Izin Pulang Cepat</span>
                    <span className="block text-xs text-slate-500">Perlu pulang lebih awal? Ajukan di sini</span>
                </span>
                <ChevronRight className="h-4 w-4 shrink-0 text-slate-300" />
            </Link>

            <div className="mb-3 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Filter status pengajuan">
                {chips.map((c) => {
                    const active = filters.status === c.value;
                    return (
                        <button
                            key={c.value}
                            type="button"
                            onClick={() => applyFilters({ status: c.value })}
                            aria-pressed={active}
                            className={`shrink-0 rounded-full px-3 py-1.5 text-xs font-bold transition active:scale-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/20 ${
                                active ? 'bg-primary text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'
                            }`}
                        >
                            {c.label}
                            <span className={`ml-1 ${active ? 'text-white/70' : 'text-slate-400'}`}>{c.count ?? 0}</span>
                        </button>
                    );
                })}
            </div>

            <div className="mb-4 grid grid-cols-2 gap-2">
                <select
                    value={filters.jenis || ''}
                    onChange={(e) => applyFilters({ jenis: e.target.value })}
                    aria-label="Filter jenis pengajuan"
                    className="min-h-11 rounded-xl border-slate-200 bg-white text-sm font-bold text-slate-700 shadow-sm focus:border-primary focus:ring-primary"
                >
                    <option value="">Semua jenis</option>
                    <option value="sakit">Sakit</option>
                    <option value="izin">Izin</option>
                    <option value="cuti">Cuti</option>
                </select>
                <input
                    type="month"
                    value={filters.bulan || ''}
                    onChange={(e) => applyFilters({ bulan: e.target.value })}
                    aria-label="Filter bulan pengajuan"
                    className="min-h-11 w-full rounded-xl border-slate-200 bg-white text-sm font-bold text-slate-700 shadow-sm focus:border-primary focus:ring-primary"
                />
            </div>

            {flash.message && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.message}</div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{flash.error}</div>
            )}

            {items.length === 0 ? (
                <Empty
                    icon={FileText}
                    title={adaFilter ? 'Tidak ada pengajuan' : 'Belum ada pengajuan'}
                    subtitle={adaFilter ? 'Tidak ada pengajuan yang cocok dengan filter ini.' : 'Anda belum pernah mengajukan izin, sakit, atau cuti.'}
                />
            ) : (
                <ul className="space-y-3">
                    {items.map((item) => {
                        const st = statusOf(item);
                        const menungguTingkat2 = item.status === 'pending' && item.approval_stage === 'pending_l2';
                        return (
                            <li key={item.id}>
                                <Link
                                    href={route('presensi.izin.show', item.id)}
                                    className="block rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/20"
                                >
                                    <Card className="py-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <Badge tone="indigo">{jenisLabel[item.jenis_izin] || item.jenis_izin}</Badge>
                                                <p className="mt-2 text-sm font-bold text-slate-800">
                                                    {fmtTanggal(item.tanggal_mulai)}
                                                    {item.tanggal_mulai !== item.tanggal_selesai && ` – ${fmtTanggal(item.tanggal_selesai)}`}
                                                    <span className="ml-1 font-semibold text-slate-400">· {durasiHari(item.tanggal_mulai, item.tanggal_selesai)} hari</span>
                                                </p>
                                                <p className="mt-0.5 line-clamp-2 text-sm text-slate-500">{item.alasan}</p>
                                                {menungguTingkat2 && item.approver_l1?.name && (
                                                    <p className="mt-0.5 text-xs text-slate-400">
                                                        Disetujui {item.approver_l1.name}
                                                        {item.approved_at_l1 ? ` · ${format(new Date(item.approved_at_l1), 'd MMM yyyy', { locale: id })}` : ''}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <Badge tone={st.tone} icon={st.icon}>{st.label}</Badge>
                                                <ChevronRight className="h-4 w-4 flex-shrink-0 text-slate-300" />
                                            </div>
                                        </div>
                                        {item.status === 'ditolak' && item.alasan_penolakan && (
                                            <div className="mt-3 rounded-2xl border border-rose-100 bg-rose-50 p-3">
                                                <p className="text-xs font-bold text-rose-800">Alasan Penolakan:</p>
                                                <p className="mt-0.5 text-sm text-rose-600">{item.alasan_penolakan}</p>
                                            </div>
                                        )}
                                    </Card>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}

            {pengajuan?.next_page_url && (
                <Link
                    href={pengajuan.next_page_url}
                    preserveScroll
                    className="mt-4 flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-white py-3 text-sm font-bold text-slate-600 ring-1 ring-slate-200 transition active:scale-[0.99] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/20"
                >
                    <ChevronDown className="h-4 w-4" /> Muat lebih banyak
                </Link>
            )}
        </MobileLayout>
    );
}
