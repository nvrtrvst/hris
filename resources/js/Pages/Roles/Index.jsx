import React, { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import StatCard from '@/Components/StatCard';
import Pagination from '@/Components/Pagination';
import { Inbox, Layers, Plus, Search, ShieldCheck, Shield as ShieldIcon, Trash2, UserCog, AlertTriangle } from 'lucide-react';

const SYSTEM_ROLES = ['superadmin', 'admin_unit', 'pegawai'];

export default function Index({ auth, roles, filters, stats, flash }) {
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const latestRef = useRef({ search: filters?.search || '' });

    useEffect(() => {
        if (searchTerm === latestRef.current.search) return;
        const timer = setTimeout(() => {
            latestRef.current.search = searchTerm;
            router.get(route('roles.index'), { search: searchTerm }, { preserveState: true, replace: true });
        }, 350);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const handleDelete = (id, name) => {
        if (confirm(`Apakah Anda yakin ingin menghapus role "${name.toUpperCase()}"? User yang memiliki role ini mungkin akan kehilangan akses.`)) {
            router.delete(route('roles.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Manajemen Role</h2>}
        >
            <Head title="Manajemen Role" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-xl font-extrabold text-text-primary">Daftar Role Sistem</h3>
                            <p className="text-sm text-text-muted">Buat peran khusus dan atur izin default untuk peran tersebut.</p>
                        </div>
                        <Link href={route('roles.create')} className="btn-primary inline-flex shrink-0 items-center gap-2">
                            <Plus className="h-4 w-4" /> Tambah Role
                        </Link>
                    </div>

                    {/* Flash */}
                    {flash?.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash?.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={Layers} label="Total Role" value={stats?.total_role ?? 0} />
                        <StatCard Icon={ShieldIcon} label="Role Bawaan" value={stats?.total_system ?? 0} sub="superadmin · admin_unit · pegawai" />
                        <StatCard Icon={UserCog} label="Role Kustom" value={stats?.total_custom ?? 0} />
                    </div>

                    {/* Search */}
                    <div className="relative max-w-md">
                        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Search className="h-4 w-4 text-text-muted" />
                        </span>
                        <input
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="input-field pl-10"
                            placeholder="Cari nama role..."
                        />
                    </div>

                    {/* Table */}
                    <div className="card overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface">
                                    <tr>
                                        <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nama Role</th>
                                        <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Jumlah Izin</th>
                                        <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border bg-white">
                                    {roles.data.map((role) => {
                                        const isSystem = SYSTEM_ROLES.includes(role.name);
                                        return (
                                            <tr key={role.id} className="transition-colors hover:bg-surface">
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${isSystem ? 'bg-amber-50 text-amber-600' : 'bg-primary/10 text-primary'}`}>
                                                            <ShieldCheck className="h-5 w-5" />
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-extrabold uppercase tracking-wide text-text-primary">{role.name}</p>
                                                            {isSystem && (
                                                                <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700">
                                                                    <AlertTriangle className="h-3 w-3" /> Bawaan Sistem
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <span className="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-bold text-sky-700 tabular-nums">
                                                        {role.permissions.length} akses
                                                    </span>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    <div className="inline-flex items-center gap-2">
                                                        <Link href={route('roles.edit', role.id)} className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                                            <ShieldCheck className="h-3.5 w-3.5" /> Atur Akses
                                                        </Link>
                                                        {!isSystem && (
                                                            <button onClick={() => handleDelete(role.id, role.name)} className="btn-danger btn-sm inline-flex items-center gap-1.5" title="Hapus role">
                                                                <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {roles.data.length === 0 && (
                                        <tr>
                                            <td colSpan="3" className="px-6 py-12 text-center text-sm text-text-muted">
                                                <Inbox className="mx-auto mb-2 h-6 w-6 text-border" />
                                                Tidak ada data role.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="border-t border-border bg-surface/50 px-6 py-4">
                            <Pagination links={roles.links} data={filters} />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
