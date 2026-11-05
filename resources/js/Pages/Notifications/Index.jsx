import React, { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminShell from '@/Layouts/AdminShell';
import { PageHeader } from '@/Components/AdminUI';
import Pagination from '@/Components/Pagination';
import StatCard from '@/Components/StatCard';
import { Archive, ArchiveRestore, Bell, CheckCheck, ChevronRight, Inbox, MailOpen, Search } from 'lucide-react';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';

const NOTIF_ROUTE_BY_TYPE = {
    status_izin: 'pengajuan-izin.index',
    izin_baru: 'pengajuan-izin.index',
    izin_reply: 'pengajuan-izin.index',
    tembusan_izin: 'pengajuan-izin.index',
    koreksi_baru: 'koreksi-presensi.index',
    status_koreksi: 'koreksi-presensi.index',
    pulang_cepat_baru: 'pulang-cepat.index',
    status_pulang_cepat: 'pulang-cepat.index',
    pulang_awal_baru: 'pulang-cepat.index',
    announcement: 'pengumuman.index',
};

export default function NotificationsIndex({ auth, notifications, filters }) {
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

    const markRead = (id, afterSuccess) => router.post(route('notifications.read', id), {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: afterSuccess,
        onError: afterSuccess,
    });
    const markAllRead = () => router.post(route('notifications.read-all'), {}, { preserveState: true });
    const archive = (id) => router.post(route('notifications.archive', id), {}, { preserveState: true });
    const restore = (id) => router.post(route('notifications.restore', id), {}, { preserveState: true });

    const tabs = [
        { key: 'all', label: `Masuk (${notifications.total ?? notifications.data?.length ?? 0})` },
        { key: 'archived', label: 'Diarsipkan' },
    ];

    const getNotificationRoute = (n) => {
        const name = NOTIF_ROUTE_BY_TYPE[n.data?.type];

        return name ? route(name) : null;
    };

    const total = notifications?.total ?? notifications.data?.length ?? 0;

    return (
        <AdminShell header={<h2 className="page-title">Notifikasi</h2>}>
            <Head title="Notifikasi" />
            <div className="mx-auto w-full max-w-4xl space-y-5">
                    <PageHeader
                        eyebrow="Inbox"
                        title="Inbox Notifikasi"
                        description="Pantau pemberitahuan sistem dan aktivitas pengajuan."
                    />

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={Inbox} label="Total Notifikasi" value={total} />
                        <StatCard Icon={MailOpen} label="Belum Dibaca" value={unread.length} />
                    </div>

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

                    {notifications.data?.length === 0 ? (
                        <div className="card flex flex-col items-center px-6 py-12 text-center">
                            <Bell className="h-8 w-8 text-border" />
                            <p className="mt-3 text-sm font-bold text-text-primary">
                                {search ? 'Tidak ada hasil pencarian' : view === 'archived' ? 'Belum ada notifikasi diarsipkan' : 'Belum ada notifikasi masuk'}
                            </p>
                            {!search && view === 'all' && (
                                <p className="mt-1 max-w-xs text-xs text-text-secondary">
                                    Notifikasi pengajuan dan pengingat akan muncul di sini.
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {notifications.data.map((n) => {
                            const targetUrl = getNotificationRoute(n);
                            const actionable = Boolean(targetUrl || !n.read_at);
                            const handleClick = () => {
                                if (!actionable) return;

                                const openTarget = () => {
                                    if (targetUrl) router.visit(targetUrl);
                                };

                                if (n.read_at) {
                                    openTarget();
                                } else {
                                    markRead(n.id, openTarget);
                                }
                            };
                            return (
                                <div key={n.id} className={`card p-4 transition-all hover:shadow-card ${!n.read_at ? 'border-primary/30 bg-primary/[0.02]' : ''}`}>
                                    <div className="flex items-start justify-between gap-2">
                                        <div
                                            className={`flex min-w-0 flex-1 gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 ${actionable ? 'cursor-pointer' : ''}`}
                                            onClick={handleClick}
                                            role={actionable ? 'button' : undefined}
                                            tabIndex={actionable ? 0 : undefined}
                                            onKeyDown={actionable ? (e) => {
                                                if (e.key === 'Enter' || e.key === ' ') {
                                                    e.preventDefault();
                                                    handleClick();
                                                }
                                            } : undefined}
                                        >
                                            {n.data?.image && (
                                                <img src={n.data.image} alt="" className="h-12 w-12 shrink-0 rounded-lg border border-border object-cover" />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className={`text-sm ${!n.read_at ? 'font-bold text-text-primary' : 'text-text-secondary'}`}>
                                                    {n.data?.message || n.data?.pegawai_nama || 'Notifikasi'}
                                                </p>
                                                <p className="mt-0.5 text-xs text-text-muted">
                                                    {n.created_at ? format(new Date(n.created_at), 'd MMM yyyy, HH:mm', { locale: idLocale }) : '-'}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1.5">
                                            {targetUrl && (
                                                <ChevronRight className="h-4 w-4 flex-shrink-0 text-border" />
                                            )}
                                            {view === 'all' ? (
                                                <button onClick={(e) => { e.stopPropagation(); archive(n.id); }} title="Arsipkan"
                                                    className="rounded-lg bg-surface p-2 text-text-secondary transition-colors hover:text-primary">
                                                    <Archive className="h-4 w-4" />
                                                </button>
                                            ) : (
                                                <button onClick={(e) => { e.stopPropagation(); restore(n.id); }} title="Kembalikan"
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
        </AdminShell>
    );
}
