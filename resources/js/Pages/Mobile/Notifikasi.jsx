import React, { useState, useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Empty } from '@/Components/MobileUI';
import Pagination from '@/Components/Pagination';
import { Bell, CheckCheck, Search, Archive, ArchiveRestore, ChevronRight } from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { id as idLocale } from 'date-fns/locale';

/** Map notification data.type → route name + params for mobile navigation. */
function getNotificationRoute(n) {
    const type = n.data?.type;
    if ((type === 'status_izin' || type === 'izin_baru') && n.data?.pengajuan_id) {
        return { name: 'presensi.izin.show', params: n.data.pengajuan_id };
    }
    if (type === 'status_izin' || type === 'izin_baru') {
        return { name: 'presensi.izin.index', params: null };
    }
    if (type === 'koreksi_baru' || type === 'status_koreksi') {
        return { name: 'presensi.koreksi.index', params: null };
    }
    if (type === 'pulang_cepat_baru' || type === 'status_pulang_cepat') {
        return { name: 'presensi.pulang-cepat.index', params: null };
    }
    if (type === 'pulang_awal_baru' || type === 'status_lembur') {
        return { name: 'presensi.riwayat', params: null };
    }
    if (type === 'announcement') {
        return { name: 'presensi.pengumuman', params: null };
    }
    return null;
}

export default function Notifikasi({ auth, notifications, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [readingId, setReadingId] = useState(null);
    const [markingAll, setMarkingAll] = useState(false);
    const [error, setError] = useState('');
    const debounceRef = useRef(null);
    const view = filters?.view === 'archived' ? 'archived' : 'all';
    const unread = notifications.data?.filter((n) => !n.read_at) || [];
    const unreadTotal = auth?.unread_notifications ?? unread.length;

    const applyFilters = (next) => {
        router.get(route('presensi.notifikasi.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            if (search !== (filters?.search || '')) applyFilters({ search });
        }, 350);
        return () => clearTimeout(debounceRef.current);
    }, [search]);

    const markAllRead = () => {
        setError('');
        setMarkingAll(true);
        router.post(route('presensi.notifikasi.read-all'), {}, {
            preserveState: true,
            preserveScroll: true,
            onError: (errs) => setError(Object.values(errs || {}).flat()[0] || 'Gagal menandai semua notifikasi.'),
            onFinish: () => setMarkingAll(false),
        });
    };

    // Tunggu mark-read selesai sebelum navigasi; request bisa batal saat pindah halaman.
    const openNotification = (n) => {
        const target = getNotificationRoute(n);
        const targetUrl = target
            ? (target.params ? route(target.name, target.params) : route(target.name))
            : null;

        if (n.read_at) {
            if (targetUrl) router.visit(targetUrl);
            return;
        }

        setError('');
        setReadingId(n.id);
        router.post(route('presensi.notifikasi.read', n.id), {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setReadingId(null);
                if (targetUrl) router.visit(targetUrl);
            },
            onError: (errs) => {
                setReadingId(null);
                setError(Object.values(errs || {}).flat()[0] || 'Gagal menandai notifikasi terbaca.');
            },
        });
    };

    const archive = (id) => router.post(route('presensi.notifikasi.archive', id), {}, { preserveState: true });
    const restore = (id) => router.post(route('presensi.notifikasi.restore', id), {}, { preserveState: true });

    const tabs = [
        { key: 'all', label: 'Masuk' },
        { key: 'archived', label: 'Arsip' },
    ];

    return (
        <MobileLayout user={auth.user}>
            <Head title="Notifikasi" />
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Notifikasi</h1>
                    <p className="mt-0.5 text-sm text-slate-500">{view === 'all' ? `${unreadTotal} belum dibaca` : 'Notifikasi diarsipkan'}</p>
                </div>
                {view === 'all' && unreadTotal > 0 && (
                    <button type="button" onClick={markAllRead} disabled={markingAll} className="flex min-h-11 shrink-0 items-center gap-1 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white disabled:opacity-50">
                        <CheckCheck className="h-3.5 w-3.5" /> {markingAll ? 'Memproses...' : 'Baca semua'}
                    </button>
                )}
            </div>

            {error && (
                <p role="alert" className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                    {error}
                </p>
            )}

            <div className="mb-4 space-y-3">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari notifikasi..."
                        className="min-h-11 w-full rounded-xl border-slate-200 bg-white pl-9 pr-3 text-sm shadow-sm focus:border-primary focus:ring-primary"
                    />
                </div>
                <div className="inline-flex w-full rounded-xl bg-slate-200/70 p-1">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => { if (t.key === 'archived') setSearch(''); applyFilters({ view: t.key, search: t.key === 'all' ? search : '' }); }}
                            className={`flex-1 rounded-lg px-3 py-2 text-center text-xs font-bold transition-colors ${view === t.key ? 'bg-white text-primary shadow-sm' : 'text-slate-500'}`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
            </div>

            {notifications.data?.length === 0 ? (
                <Empty icon={Bell} title={search ? 'Tidak ada hasil' : view === 'archived' ? 'Tidak ada arsip' : 'Tidak ada notifikasi'} subtitle={search ? 'Coba kata kunci lain.' : 'Notifikasi akan muncul di sini.'} />
            ) : (
                <div className="space-y-2">
                    {notifications.data.map((n) => {
                        const routeInfo = getNotificationRoute(n);
                        const canOpen = Boolean(routeInfo) || !n.read_at;
                        const isReading = readingId === n.id;
                        return (
                            <Card key={n.id} press={false} className={`py-3.5 px-4 ${!n.read_at ? 'border-primary/30 bg-primary/[0.02]' : ''}`}>
                                <div className="flex items-start justify-between gap-2">
                                <div
                                    className={`flex min-w-0 flex-1 gap-3 ${canOpen ? 'cursor-pointer' : ''} ${isReading ? 'pointer-events-none opacity-60' : ''} ${canOpen ? 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30' : ''}`}
                                    onClick={canOpen ? () => openNotification(n) : undefined}
                                    role={canOpen ? 'button' : undefined}
                                    tabIndex={canOpen ? 0 : undefined}
                                    aria-busy={isReading || undefined}
                                    onKeyDown={canOpen ? (e) => {
                                        if (e.key === 'Enter' || e.key === ' ') {
                                            e.preventDefault();
                                            openNotification(n);
                                        }
                                    } : undefined}
                                >
                                    {n.data?.image && (
                                        <img src={n.data.image} alt="" className="h-12 w-12 shrink-0 rounded-lg border border-slate-200 object-cover" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className={`text-sm whitespace-pre-line break-words ${!n.read_at ? 'font-bold text-slate-900' : 'text-slate-600'}`}>
                                            {n.data?.message || n.data?.pegawai_nama || 'Notifikasi'}
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-600">{n.created_at ? format(parseISO(n.created_at), 'd MMM yyyy, HH:mm', { locale: idLocale }) : '-'}</p>
                                    </div>
                                </div>
                                    <div className="flex shrink-0 items-center gap-1.5">
                                        {routeInfo && (
                                            <ChevronRight className="h-4 w-4 flex-shrink-0 text-slate-500" />
                                        )}
                                        {view === 'all' ? (
                                            <button type="button" onClick={() => archive(n.id)} title="Arsipkan" aria-label="Arsipkan notifikasi" className="min-h-11 min-w-11 rounded-lg bg-slate-100 p-2 text-slate-500 active:bg-slate-200"><Archive className="h-4 w-4" /></button>
                                        ) : (
                                            <button type="button" onClick={() => restore(n.id)} title="Kembalikan" aria-label="Kembalikan notifikasi" className="min-h-11 min-w-11 rounded-lg bg-emerald-50 p-2 text-emerald-600"><ArchiveRestore className="h-4 w-4" /></button>
                                        )}
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>
            )}

            <Pagination variant="mobile" links={notifications.links} data={filters} pagination={notifications} />
        </MobileLayout>
    );
}