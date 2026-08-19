import React, { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import StatCard from '@/Components/StatCard';
import { Archive, ArchiveRestore, Bell, CheckCheck, ChevronRight, Inbox, MailOpen, Search } from 'lucide-react';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';

export default function NotificationsIndex({ auth, notifications, filters, flash }) {
    const [search, setSearch] = useState(filters?.search || '');
    const debounceRef = useRef(null);
    const view = filters?.view === 'archived' ? 'archived' : 'all';
    const unread = notifications.data?.filter((n) => !n.read_at) || [];

    const applyFilters = (next) => {
        router.get(route('notifications.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            if (search !== (filters?.search || '')) applyFilters({ search });
        }, 350);
        return () => clearTimeout(debounceRef.current);
    }, [search]);

    const markRead = (id) => router.post(route('notifications.read', id), {}, { preserveState: true });
    const markAllRead = () => router.post(route('notifications.read-all'), {}, { preserveState: true });
    const archive = (id) => router.post(route('notifications.archive', id), {}, { preserveState: true });
    const restore = (id) => router.post(route('notifications.restore', id), {}, { preserveState: true });

    const tabs = [
        { key: 'all', label: `Masuk (${notifications.total ?? notifications.data?.length ?? 0})` },
        { key: 'archived', label: 'Diarsipkan' },
    ];

    /** Map notification data.type → admin route. */
    const getNotificationRoute = (n) => {
        const type = n.data?.type;
        if (type === 'status_izin') return route('pengajuan-izin.index');
        if (type === 'izin_baru') return route('pengajuan-izin.index');
        return null;
    };

    const total = notifications?.total ?? notifications.data?.length ?? 0;

    return (
        <AuthenticatedLayout header={<h2 className="page-title">Notifikasi</h2>}>
            <Head title="Notifikasi" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div>
                        <h3 className="text-xl font-extrabold text-text-primary">Inbox Notifikasi</h3>
                        <p className="text-sm text-text-muted">Pantau pemberitahuan sistem dan aktivitas pengajuan.</p>
                    </div>

                    {/* Flash */}
                    {flash?.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash?.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={Inbox} label="Total Notifikasi" value={total} />
                        <StatCard Icon={MailOpen} label="Belum Dibaca" value={unread.length} />
                    </div>

                    {/* Search + Tabs */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="relative flex-1 sm:max-w-xs">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari notifikasi..."
                                className="input-field pl-9"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="inline-flex rounded-lg border border-border bg-white p-0.5">
                                {tabs.map((t) => (
                                    <button
                                        key={t.key}
                                        onClick={() => { if (t.key === 'archived') setSearch(''); applyFilters({ view: t.key, search: t.key === 'all' ? search : '' }); }}
                                        className={`rounded-md px-3 py-1.5 text-xs font-bold transition-colors ${view === t.key ? 'bg-primary text-white' : 'text-text-secondary hover:text-primary'}`}
                                    >
                                        {t.label}
                                    </button>
                                ))}
                            </div>
                            {view === 'all' && unread.length > 0 && (
                                <button onClick={markAllRead} className="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white">
                                    <CheckCheck className="h-3.5 w-3.5" /> Baca semua
                                </button>
                            )}
                        </div>
                    </div>

                    {/* List */}
                    {notifications.data?.length === 0 ? (
                        <div className="card flex flex-col items-center px-6 py-12 text-center">
                            <Bell className="h-8 w-8 text-border" />
                            <p className="mt-3 text-sm font-bold text-text-primary">
                                {search ? 'Tidak ada hasil pencarian' : view === 'archived' ? 'Tidak ada notifikasi diarsipkan' : 'Tidak ada notifikasi'}
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {notifications.data.map((n) => {
                            const targetUrl = getNotificationRoute(n);
                            const handleClick = () => {
                                if (targetUrl) {
                                    if (!n.read_at) markRead(n.id);
                                    router.visit(targetUrl);
                                } else if (!n.read_at) {
                                    markRead(n.id);
                                }
                            };
                            return (
                                <div key={n.id} className={`card p-4 transition-all hover:shadow-card ${!n.read_at ? 'border-primary/30 bg-primary/[0.02]' : ''}`}>
                                    <div className="flex items-start justify-between gap-2">
                                        <div
                                            className={`min-w-0 flex-1 ${targetUrl ? 'cursor-pointer' : ''}`}
                                            onClick={handleClick}
                                            role={targetUrl ? 'button' : undefined}
                                            tabIndex={targetUrl ? 0 : undefined}
                                            onKeyDown={targetUrl ? (e) => { if (e.key === 'Enter' || e.key === ' ') handleClick(); } : undefined}
                                        >
                                            <p className={`text-sm ${!n.read_at ? 'font-bold text-text-primary' : 'text-text-secondary'}`}>
                                                {n.data?.message || n.data?.pegawai_nama || 'Notifikasi'}
                                            </p>
                                            <p className="mt-0.5 text-xs text-text-muted">
                                                {n.created_at ? format(new Date(n.created_at), 'd MMM yyyy, HH:mm', { locale: idLocale }) : '-'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1.5">
                                            {targetUrl && (
                                                <ChevronRight className="h-4 w-4 flex-shrink-0 text-border" />
                                            )}
                                            {view === 'all' && !n.read_at && (
                                                <button onClick={() => markRead(n.id)}
                                                    className="rounded-lg bg-surface px-2 py-1 text-[10px] font-bold text-primary transition-colors hover:bg-primary/10">
                                                    Tandai baca
                                                </button>
                                            )}
                                            {view === 'all' ? (
                                                <button onClick={() => archive(n.id)} title="Arsipkan"
                                                    className="rounded-lg bg-surface p-2 text-text-secondary transition-colors hover:text-primary">
                                                    <Archive className="h-4 w-4" />
                                                </button>
                                            ) : (
                                                <button onClick={() => restore(n.id)} title="Kembalikan"
                                                    className="rounded-lg bg-emerald-50 p-2 text-emerald-600 transition-colors hover:bg-emerald-100">
                                                    <ArchiveRestore className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                        </div>
                    )}

                    <Pagination links={notifications.links} data={filters} pagination={notifications} className="justify-center" />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
