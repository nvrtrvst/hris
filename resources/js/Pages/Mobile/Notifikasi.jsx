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
    if (type === 'announcement') {
        return { name: 'presensi.pengumuman', params: null };
    }
    return null;
}

export default function Notifikasi({ auth, notifications, filters }) {
    const [search, setSearch] = useState(filters?.search || '');
    const debounceRef = useRef(null);
    const view = filters?.view === 'archived' ? 'archived' : 'all';
    const unread = notifications.data?.filter((n) => !n.read_at) || [];

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

    const markRead = (id) => router.post(route('presensi.notifikasi.read', id), {}, { preserveState: true });
    const markAllRead = () => router.post(route('presensi.notifikasi.read-all'), {}, { preserveState: true });
    const archive = (id) => router.post(route('presensi.notifikasi.archive', id), {}, { preserveState: true });
    const restore = (id) => router.post(route('presensi.notifikasi.restore', id), {}, { preserveState: true });

    const tabs = [
        { key: 'all', label: 'Masuk' },
        { key: 'archived', label: 'Arsip' },
    ];

    return (
        <MobileLayout user={auth.user}>
            <Head title="Notifikasi" />
            <div className="mb-5 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Notifikasi</h1>
                    <p className="mt-0.5 text-sm text-slate-500">{view === 'all' ? `${unread.length} belum dibaca` : 'Notifikasi diarsipkan'}</p>
                </div>
                {view === 'all' && unread.length > 0 && (
                    <button onClick={markAllRead} className="flex items-center gap-1 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white"><CheckCheck className="h-3.5 w-3.5" /> Baca semua</button>
                )}
            </div>

            {/* Search + Tabs */}
            <div className="mb-4 space-y-3">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
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
                        const handleClick = () => {
                            if (routeInfo) {
                                if (!n.read_at) markRead(n.id);
                                router.visit(routeInfo.params
                                    ? route(routeInfo.name, routeInfo.params)
                                    : route(routeInfo.name)
                                );
                            } else if (!n.read_at) {
                                markRead(n.id);
                            }
                        };
                        return (
                            <Card key={n.id} press={false} className={`py-3.5 px-4 ${!n.read_at ? 'border-primary/30 bg-primary/[0.02]' : ''}`}>
                                <div className="flex items-start justify-between gap-2">
                                <div
                                    className={`flex min-w-0 flex-1 gap-3 ${routeInfo ? 'cursor-pointer' : ''}`}
                                    onClick={handleClick}
                                    role={routeInfo ? 'button' : undefined}
                                    tabIndex={routeInfo ? 0 : undefined}
                                    onKeyDown={routeInfo ? (e) => { if (e.key === 'Enter' || e.key === ' ') handleClick(); } : undefined}
                                >
                                    {n.data?.image && (
                                        <img src={n.data.image} alt="" className="h-12 w-12 shrink-0 rounded-lg border border-slate-200 object-cover" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className={`text-sm whitespace-pre-line ${!n.read_at ? 'font-bold text-slate-900' : 'text-slate-600'}`}>
                                            {n.data?.message || n.data?.pegawai_nama || 'Notifikasi'}
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-400">{n.created_at ? format(parseISO(n.created_at), 'd MMM yyyy, HH:mm', { locale: idLocale }) : '-'}</p>
                                    </div>
                                </div>
                                    <div className="flex shrink-0 items-center gap-1.5">
                                        {routeInfo && (
                                            <ChevronRight className="h-4 w-4 flex-shrink-0 text-slate-300" />
                                        )}
                                        {view === 'all' && !n.read_at && (
                                            <button onClick={() => markRead(n.id)} className="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold text-primary">Tandai baca</button>
                                        )}
                                        {view === 'all' ? (
                                            <button onClick={() => archive(n.id)} title="Arsipkan" aria-label="Arsipkan notifikasi" className="rounded-lg bg-slate-100 p-2 text-slate-500 active:bg-slate-200"><Archive className="h-4 w-4" /></button>
                                        ) : (
                                            <button onClick={() => restore(n.id)} title="Kembalikan" aria-label="Kembalikan notifikasi" className="rounded-lg bg-emerald-50 p-2 text-emerald-600"><ArchiveRestore className="h-4 w-4" /></button>
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