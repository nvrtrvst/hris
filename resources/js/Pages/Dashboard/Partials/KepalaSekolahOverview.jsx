import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminShell from '@/Layouts/AdminShell';
import { Card, PageHeader } from '@/Components/AdminUI';
import usePolling from '@/Utils/usePolling';
import {
    AlertTriangle,
    ArrowRight,
    BookOpen,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Clock3,
    Megaphone,
    Users,
} from 'lucide-react';

const formatJp = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(value || 0));

const jamPendek = (waktu) => (waktu ? waktu.slice(0, 5) : '-');

const waktuLabel = {
    lewat: 'Lewat',
    berjalan: 'Berjalan',
    belum_mulai: 'Belum mulai',
};

const waktuClass = {
    lewat: 'border-rose-200 bg-rose-50 text-rose-700',
    berjalan: 'border-amber-200 bg-amber-50 text-amber-700',
    belum_mulai: 'border-slate-200 bg-slate-50 text-slate-600',
};

function ContextRow({ label, value, detail, href, icon: Icon }) {
    const content = (
        <span className="flex min-w-0 items-center gap-3">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Icon className="h-4 w-4" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-[10px] font-bold uppercase tracking-[0.14em] text-text-secondary">{label}</span>
                <span className="mt-0.5 block text-sm font-bold text-text-primary">{value}</span>
                {detail && <span className="mt-0.5 block text-xs text-text-secondary">{detail}</span>}
            </span>
            {href && <ArrowRight className="h-4 w-4 shrink-0 text-text-muted" aria-hidden="true" />}
        </span>
    );

    return href ? (
        <Link href={href} className="block rounded-lg px-1 py-2.5 transition-colors hover:bg-surface">
            {content}
        </Link>
    ) : (
        <div className="px-1 py-2.5">{content}</div>
    );
}

export default function KepalaSekolahOverview({ auth, roleType, kepsekDaily, pengumuman = [], stats = {} }) {
    usePolling({ only: ['kepsekDaily'] });

    const todayString = new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    const jp = kepsekDaily?.jp || {};
    const guru = kepsekDaily?.guru || {};
    const piket = kepsekDaily?.piket || {};
    const siswa = kepsekDaily?.siswa;
    const jpKosong = jp.kosong || 0;
    const jpPenugasan = jp.penugasan || 0;
    const jpKelasKosong = jp.kelas_kosong ?? (jpKosong + jpPenugasan);
    const adaKosong = jpKosong > 0;
    const permissions = auth.permissions || [];
    const can = (permission) => permissions.includes(permission);

    const jpRows = [...(jp.rows || [])]
        .sort((a, b) => {
            const rank = (row) => (row.penugasan ? 3 : row.status_waktu === 'lewat' ? 0 : row.status_waktu === 'berjalan' ? 1 : 2);

            return rank(a) - rank(b) || (a.jam_mulai || '').localeCompare(b.jam_mulai || '');
        })
        .slice(0, 5);

    const guruHref = can('view_presensi') ? route('presensi.index') : null;
    const jpHref = can('view_presensi') ? route('laporan.pengawasan') : null;
    const piketHref = can('view_piket') || can('view_presensi') ? route('laporan.piket') : null;
    const siswaHref = can('view_absen_siswa') ? route('laporan.absen-siswa') : null;

    const jpProgress = jp.total > 0 ? Math.round((jp.tercatat / jp.total) * 100) : 0;

    return (
        <AdminShell user={auth.user} header={<h2 className="page-title">Dashboard {roleType}</h2>}>
            <Head title={`Dashboard ${roleType}`} />

            <div className="mx-auto w-full max-w-[1400px] space-y-5">
                <PageHeader
                    eyebrow="Kepala Sekolah"
                    title={kepsekDaily?.unit_nama ? `Dashboard ${kepsekDaily.unit_nama}` : 'Dashboard Kepala Sekolah'}
                    description={todayString}
                />

                <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <div className="space-y-4 xl:col-span-2">
                        {jp.hari_libur ? (
                            <Card className="border-l-4 border-sky-500 p-5">
                                <h3 className="flex items-center gap-2 text-base font-extrabold text-text-primary">
                                    <CalendarDays className="h-4 w-4 text-sky-600" aria-hidden="true" />
                                    Hari libur: {jp.hari_libur}
                                </h3>
                                <p className="mt-1 text-sm text-text-secondary">Tidak ada KBM, jadi JP hari ini tidak dihitung kosong.</p>
                            </Card>
                        ) : jpKelasKosong > 0 ? (
                            <Card className={`border-l-4 p-5 ${adaKosong ? 'border-warning' : 'border-info'}`}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 className="flex items-center gap-2 text-base font-extrabold text-text-primary">
                                            {adaKosong
                                                ? <AlertTriangle className="h-4 w-4 text-warning" aria-hidden="true" />
                                                : <CalendarDays className="h-4 w-4 text-info" aria-hidden="true" />}
                                            {adaKosong ? 'JP Belum Diabsen' : 'JP Penugasan'}
                                        </h3>
                                        <p className="mt-1 text-sm text-text-secondary">
                                            {adaKosong
                                                ? `${formatJp(jp.tercatat)} dari ${formatJp(jp.total)} JP tercatat hari ini.`
                                                : `${formatJp(jpPenugasan)} JP kelas kosong tercatat sebagai penugasan.`}
                                        </p>
                                    </div>
                                    <p className={`text-3xl font-extrabold leading-none tabular-nums ${adaKosong ? 'text-warning' : 'text-info'}`}>
                                        {formatJp(adaKosong ? jpKosong : jpPenugasan)} <span className="text-sm font-semibold text-text-secondary">JP</span>
                                    </p>
                                </div>

                                <div
                                    className="mt-4 h-2 w-full overflow-hidden rounded-full bg-border/60"
                                    role="progressbar"
                                    aria-valuenow={jpProgress}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-label={`JP tercatat ${jpProgress} persen`}
                                >
                                    <div className={`h-full rounded-full ${adaKosong ? 'bg-warning' : 'bg-info'}`} style={{ width: `${jpProgress}%` }} />
                                </div>

                                {adaKosong && (
                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                        {jp.lewat > 0 && <span className="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700">Lewat {formatJp(jp.lewat)} JP</span>}
                                        {jp.berjalan > 0 && <span className="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">Berjalan {formatJp(jp.berjalan)} JP</span>}
                                        {jp.belum_mulai > 0 && <span className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-slate-600">Belum mulai {formatJp(jp.belum_mulai)} JP</span>}
                                    </div>
                                )}

                                {adaKosong && jpPenugasan > 0 && (
                                    <p className="mt-2 text-xs text-text-secondary">{formatJp(jpPenugasan)} JP tercatat sebagai penugasan.</p>
                                )}

                                <ul className="mt-4 divide-y divide-border/60 border-t border-border/60">
                                    {jpRows.map((row) => (
                                        <li key={row.id} className="flex flex-wrap items-center gap-x-2 gap-y-1 py-2.5 text-xs">
                                            <span className="font-mono font-semibold tabular-nums text-text-primary">{jamPendek(row.jam_mulai)}-{jamPendek(row.jam_selesai)}</span>
                                            <span className="font-semibold text-text-primary">{row.kelas_label || '-'}</span>
                                            <span className="text-text-secondary">{row.mata_pelajaran?.nama || row.pegawaiMapel?.mataPelajaran?.nama || row.jenis_jadwal || '-'}</span>
                                            <span className="max-w-[12rem] truncate text-text-secondary">{row.pegawai?.nama_lengkap || '-'}</span>
                                            <span className="ml-auto flex flex-wrap items-center gap-1.5">
                                                {row.penugasan ? (
                                                    <span className="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700" title={row.tujuan_penugasan || undefined}>Penugasan</span>
                                                ) : row.guru_hadir ? (
                                                    <span className="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700">Guru di unit</span>
                                                ) : (
                                                    <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-600">Guru belum hadir</span>
                                                )}
                                                {!row.penugasan && waktuLabel[row.status_waktu] && (
                                                    <span className={`rounded-full border px-2 py-0.5 text-[10px] font-bold ${waktuClass[row.status_waktu]}`}>
                                                        {waktuLabel[row.status_waktu]}
                                                    </span>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>

                                <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-xs text-text-secondary">
                                        {jp.guru_hadir > 0
                                            ? `${formatJp(jp.guru_hadir)} JP di antaranya guru sudah absen harian.`
                                            : (jpPenugasan > 0 ? `${formatJp(jpPenugasan)} JP tercatat sebagai penugasan.` : 'Perlu tindak lanjut guru terkait.')}
                                    </p>
                                    {jpHref && (
                                        <Link href={jpHref} className="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                                            Lihat semua JP <ArrowRight className="h-3.5 w-3.5" aria-hidden="true" />
                                        </Link>
                                    )}
                                </div>
                            </Card>
                        ) : (
                            <Card className={`border-l-4 p-5 ${jp.total > 0 ? 'border-success' : 'border-border'}`}>
                                <h3 className="flex items-center gap-2 text-base font-extrabold text-text-primary">
                                    {jp.total > 0 ? (
                                        <CheckCircle2 className="h-4 w-4 text-success" aria-hidden="true" />
                                    ) : (
                                        <CalendarDays className="h-4 w-4 text-text-muted" aria-hidden="true" />
                                    )}
                                    {jp.total > 0 ? `Semua ${formatJp(jp.total)} JP tercatat` : 'Belum ada JP terjadwal'}
                                </h3>
                                <p className="mt-1 text-sm text-text-secondary">
                                    {jp.total > 0 ? 'Presensi guru untuk setiap JP hari ini lengkap.' : 'Tidak ada jadwal mengajar pada hari ini.'}
                                </p>
                            </Card>
                        )}

                        <Card className="p-4">
                            <div className="flex items-center justify-between gap-3 border-b border-border/60 px-1 pb-2">
                                <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Ringkasan Hari Ini</h3>
                                {stats?.pengajuan_pending > 0 && can('view_izin') && (
                                    <Link href={route('pengajuan-izin.index')} className="text-xs font-bold text-primary hover:underline">
                                        {stats.pengajuan_pending} izin pending
                                    </Link>
                                )}
                            </div>
                            <div className="divide-y divide-border/60">
                                <ContextRow
                                    label="Guru"
                                    icon={Users}
                                    value={guru.dijadwalkan > 0 ? `${guru.hadir || 0} / ${guru.dijadwalkan} hadir` : 'Belum ada guru terjadwal'}
                                    detail={guru.dijadwalkan > 0
                                        ? `${guru.telat || 0} telat · ${guru.izin || 0} izin · ${guru.sakit || 0} sakit · ${guru.alpa || 0} alpa`
                                        : `${guru.hadir || 0} presensi harian · ${guru.total || 0} pegawai aktif`}
                                    href={guruHref}
                                />
                                {siswa && (
                                    <ContextRow
                                        label="Siswa"
                                        icon={BookOpen}
                                        value={siswa.sesi > 0 ? `${siswa.sesi} sesi kelas · ${siswa.siswa || 0} siswa` : 'Belum ada sesi kelas'}
                                        detail={siswa.sesi > 0
                                            ? `${siswa.terlambat_gerbang || 0} terlambat gerbang · ${siswa.persen || 0}% hadir`
                                            : 'Absensi kelas belum diisi hari ini'}
                                        href={siswaHref}
                                    />
                                )}
                                <ContextRow
                                    label="Piket"
                                    icon={ClipboardList}
                                    value={piket.jadwal > 0 ? `${piket.lengkap || 0} / ${piket.jadwal} laporan lengkap` : 'Tidak ada jadwal piket'}
                                    detail={piket.perlu_cek > 0 ? `${piket.perlu_cek} perlu dicek` : 'Tidak ada temuan'}
                                    href={piketHref}
                                />
                            </div>
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <Card className="overflow-hidden">
                            <div className="flex items-center justify-between border-b border-border/60 bg-surface/50 px-5 py-4">
                                <h3 className="flex items-center gap-2 text-sm font-extrabold text-primary">
                                    <Megaphone className="h-4 w-4" aria-hidden="true" /> Pengumuman
                                </h3>
                                <Link href={route('pengumuman.index')} className="text-xs font-semibold text-primary hover:underline">Lihat</Link>
                            </div>
                            {pengumuman.length > 0 ? (
                                <div className="divide-y divide-border/50">
                                    {pengumuman.slice(0, 2).map((a) => (
                                        <div key={a.id} className="flex items-start gap-2 px-5 py-3">
                                            <Clock3 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-text-muted" aria-hidden="true" />
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-bold text-text-primary">{a.title}</p>
                                                <p className="mt-0.5 text-[11px] text-text-secondary">
                                                    {a.published_at ? new Date(a.published_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Draft'}
                                                    {a.unit_nama ? ` · ${a.unit_nama}` : ' · Semua unit'}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="px-5 py-4 text-sm text-text-secondary">Belum ada pengumuman.</p>
                            )}
                        </Card>

                        <Card className="p-5">
                            <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Pintasan</h3>
                            <div className="mt-3 space-y-1">
                                {jpHref && <Link href={jpHref} className="flex items-center justify-between rounded-lg px-2 py-2 text-sm font-semibold text-text-secondary transition-colors hover:bg-surface hover:text-primary">Laporan pengawasan <ArrowRight className="h-4 w-4" aria-hidden="true" /></Link>}
                                {piketHref && <Link href={piketHref} className="flex items-center justify-between rounded-lg px-2 py-2 text-sm font-semibold text-text-secondary transition-colors hover:bg-surface hover:text-primary">Laporan piket <ArrowRight className="h-4 w-4" aria-hidden="true" /></Link>}
                                {siswaHref && <Link href={siswaHref} className="flex items-center justify-between rounded-lg px-2 py-2 text-sm font-semibold text-text-secondary transition-colors hover:bg-surface hover:text-primary">Absen siswa <ArrowRight className="h-4 w-4" aria-hidden="true" /></Link>}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
