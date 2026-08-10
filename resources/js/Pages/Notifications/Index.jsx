import React, { useState, useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { CheckCheck, Bell, Search, Archive, ArchiveRestore } from 'lucide-react';

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

    const markRead = (id) => router.post(route('notifications.read', id), {}, { preserveState: true });
    const markAllRead = () => router.post(route('notifications.read-all'), {}, { preserveState: true });
    const archive = (id) => router.post(route('notifications.archive', id), {}, { preserveState: true });
    const restore = (id) => router.post(route('notifications.restore', id), {}, { preserveState: true });

    const tabs = [
        { key: 'all', label: `Masuk (${notifications.total ?? notifications.data?.length ?? 0})` },
        { key: 'archived', label: 'Diarsipkan' },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="font-semibold text-xl text-primary">Notifikasi</h2>}>
            <Head title="Notifikasi" />
            <div className="py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    {/* Search + Tabs */}
                    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="relative flex-1 sm:max-w-xs">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari notifikasi..."
                                className="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:border-primary focus:ring-primary"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="inline-flex rounded-lg border border-slate-200 bg-white p-0.5">
                                {tabs.map((t) => (
                                    <button
                                        key={t.key}
                                        onClick={() => { if (t.key === 'archived') setSearch(''); applyFilters({ view: t.key, search: t.key === 'all' ? search : '' }); }}
                                        className={`rounded-md px-3 py-1.5 text-xs font-bold transition-colors ${view === t.key ? 'bg-primary text-white' : 'text-slate-500 hover:text-primary'}`}
                                    >
                                        {t.label}
                                    </button>
                                ))}
                            </div>
                            {view === 'all' && unread.length > 0 && (
                                <button onClick={markAllRead} className="flex items-center gap-1 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white"><CheckCheck className="h-3.5 w-3.5" /> Baca semua</button>
                            )}
                        </div>
                    </div>

                    {notifications.data?.length === 0 ? (
                        <div className="rounded-xl bg-white p-8 text-center"><Bell className="mx-auto h-8 w-8 text-slate-300" /><p className="mt-3 font-bold text-slate-500">{search ? 'Tidak ada hasil pencarian' : view === 'archived' ? 'Tidak ada notifikasi diarsipkan' : 'Tidak ada notifikasi'}</p></div>
                    ) : (
                        <div className="space-y-2">
                            {notifications.data.map((n) => (
                                <div key={n.id} className={`rounded-xl border p-4 ${!n.read_at ? 'border-primary/30 bg-primary/[0.02]' : 'bg-white'}`}>
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <p className={`text-sm ${!n.read_at ? 'font-bold text-slate-900' : 'text-slate-600'}`}>{n.data?.message || n.data?.pegawai_nama || 'Notifikasi'}</p>
                                            <p className="mt-0.5 text-xs text-slate-400">{n.created_at}</p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            {view === 'all' && !n.read_at && (
                                                <button onClick={() => markRead(n.id)} className="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-bold text-primary">Tandai baca</button>
                                            )}
                                            {view === 'all' ? (
                                                <button onClick={() => archive(n.id)} title="Arsipkan" className="rounded-lg bg-slate-100 p-2 text-slate-500 hover:text-primary"><Archive className="h-4 w-4" /></button>
                                            ) : (
                                                <button onClick={() => restore(n.id)} title="Kembalikan" className="rounded-lg bg-emerald-50 p-2 text-emerald-600 hover:text-emerald-700"><ArchiveRestore className="h-4 w-4" /></button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <Pagination links={notifications.links} data={filters} pagination={notifications} className="mt-4 justify-center" />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}