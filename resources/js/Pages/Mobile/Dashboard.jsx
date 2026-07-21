import { Head, Link, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import MobileLayout from '@/Layouts/MobileLayout';
import { Badge, Card, Empty, SectionTitle } from '@/Components/MobileUI';
import {
    AlertTriangle,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    Clock3,
    LogIn,
    LogOut,
    MapPin,
} from 'lucide-react';

const statusTone = {
    hadir: 'emerald',
    telat: 'amber',
    izin: 'sky',
    sakit: 'sky',
    alpa: 'rose',
};

const statusLabel = {
    hadir: 'Hadir',
    telat: 'Terlambat',
    izin: 'Izin',
    sakit: 'Sakit',
    alpa: 'Alpa',
};

function time(value) {
    return value ? String(value).slice(0, 5) : '--:--';
}

export default function Dashboard({ auth, pegawai, presensi, presensiSeminggu = [] }) {
    const { flash = {} } = usePage().props;
    const today = format(new Date(), 'EEEE, d MMMM yyyy', { locale: id });
    const primaryUnit = pegawai?.units?.find((unit) => unit.pivot?.is_primary) ?? pegawai?.units?.[0];
    const displayName = pegawai?.nama_lengkap || auth?.user?.name || 'Pegawai';
    const firstName = displayName.split(' ')[0];
    const unitName = primaryUnit?.nama || primaryUnit?.nama_unit || 'Yayasan';
    const records = Array.isArray(presensiSeminggu) ? presensiSeminggu : Object.values(presensiSeminggu || {}).flat();
    const currentStatus = presensi?.status;

    return (
        <MobileLayout user={auth.user}>
            <Head title="Beranda" />

            <section className="mb-5">
                <p className="text-xs font-semibold capitalize tracking-wide text-slate-500">{today}</p>
                <div className="mt-1 flex items-end justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="truncate text-2xl font-bold tracking-tight text-slate-950">Selamat datang, {firstName}</h1>
                        <p className="mt-1 flex items-center gap-1.5 truncate text-sm text-slate-600">
                            <MapPin className="h-4 w-4 shrink-0 text-primary" />
                            {unitName}
                        </p>
                    </div>
                    <span className="mb-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500 ring-4 ring-emerald-100" aria-label="Sistem aktif" />
                </div>
            </section>

            {flash.message && <div role="status" className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
            {flash.error && <div role="alert" className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

            <section aria-labelledby="today-status" className="overflow-hidden rounded-2xl bg-primary text-white shadow-[0_10px_28px_-18px_rgba(15,61,62,0.75)]">
                <div className="border-b border-white/10 px-5 py-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-100">Status hari ini</p>
                            <h2 id="today-status" className="mt-1 text-xl font-bold text-white">{currentStatus ? statusLabel[currentStatus] || currentStatus : 'Belum presensi'}</h2>
                        </div>
                        <Badge tone={statusTone[currentStatus] || 'slate'} icon={currentStatus ? CheckCircle2 : Clock3} className={!currentStatus ? 'bg-white/10 text-white' : ''}>
                            {presensi?.jam_keluar ? 'Selesai' : presensi?.jam_masuk ? 'Sedang bekerja' : 'Belum masuk'}
                        </Badge>
                    </div>
                </div>

                <div className="grid grid-cols-2 divide-x divide-white/10">
                    <div className="px-5 py-4">
                        <p className="flex items-center gap-1.5 text-xs text-emerald-100"><LogIn className="h-4 w-4" /> Masuk</p>
                        <p className="mt-1 font-mono text-2xl font-bold tabular-nums text-white">{time(presensi?.jam_masuk)}</p>
                    </div>
                    <div className="px-5 py-4">
                        <p className="flex items-center gap-1.5 text-xs text-emerald-100"><LogOut className="h-4 w-4" /> Keluar</p>
                        <p className="mt-1 font-mono text-2xl font-bold tabular-nums text-white">{time(presensi?.jam_keluar)}</p>
                    </div>
                </div>

                {presensi?.lokasi_perlu_review && (
                    <div className="mx-4 mb-4 flex items-start gap-2 rounded-xl bg-amber-400/15 px-3 py-2.5 text-xs font-medium text-amber-50">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" /> Lokasi perlu ditinjau admin.
                    </div>
                )}

                <Link href={route('presensi.absen')} className="mx-4 mb-4 flex min-h-12 items-center justify-between rounded-xl bg-white px-4 py-3 text-sm font-bold text-primary transition-transform active:scale-[0.99] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/30">
                    {presensi?.jam_masuk && !presensi?.jam_keluar ? 'Lakukan presensi keluar' : 'Buka presensi'}
                    <ArrowRight className="h-5 w-5" />
                </Link>
            </section>

            <Link href={route('presensi.riwayat')} className="mt-5 flex min-h-14 items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-left shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-transform active:scale-[0.99] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/15">
                <span>
                    <span className="block text-sm font-bold text-slate-900">Riwayat & statistik</span>
                    <span className="mt-0.5 block text-xs text-slate-500">Lihat kalender kehadiran bulanan</span>
                </span>
                <ArrowRight className="h-5 w-5 text-primary" />
            </Link>

            <SectionTitle icon={Clock3} className="mt-6">Riwayat Terbaru</SectionTitle>
            <Card press={false} className="divide-y divide-slate-100 p-0">
                {records.length === 0 ? (
                    <Empty icon={Clock3} title="Belum ada riwayat" subtitle="Presensi terbaru akan tampil di sini." />
                ) : records.slice(0, 3).map((record) => (
                    <div key={record.id} className="flex items-center gap-3 px-4 py-3.5">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${record.status === 'telat' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-primary'}`}>
                            <CalendarDays className="h-5 w-5" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-bold text-slate-800">{format(new Date(record.tanggal), 'EEEE, d MMM', { locale: id })}</p>
                            <p className="mt-0.5 text-xs text-slate-500">{time(record.jam_masuk)} - {time(record.jam_keluar)}</p>
                        </div>
                        <Badge tone={statusTone[record.status] || 'slate'}>{statusLabel[record.status] || record.status}</Badge>
                    </div>
                ))}
            </Card>
        </MobileLayout>
    );
}
