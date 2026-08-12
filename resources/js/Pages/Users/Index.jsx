import React, { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import StatCard from '@/Components/StatCard';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Inbox, Search, ShieldCheck, UserCog, Users, UserPlus } from 'lucide-react';
import { avatarTone, initials } from '@/Utils/avatar';

const ROLE_META = {
    superadmin: { label: 'Superadmin', badge: 'border-amber-200 bg-amber-50 text-amber-700' },
    admin_unit: { label: 'Admin Unit', badge: 'border-blue-200 bg-blue-50 text-blue-700' },
    pegawai: { label: 'Pegawai', badge: 'border-gray-200 bg-gray-100 text-gray-600' },
};

export default function Index({ auth, users, filters, stats, flash }) {
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const latestRef = useRef({ search: filters?.search || '' });

    useEffect(() => {
        if (searchTerm === latestRef.current.search) return;
        const timer = setTimeout(() => {
            latestRef.current.search = searchTerm;
            router.get(route('users.index'), { search: searchTerm }, { preserveState: true, replace: true });
        }, 350);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const roleBadge = (u) => {
        const names = u.roles?.map((r) => r.name) || [];
        const roleName = names.includes('superadmin') ? 'superadmin' : names.includes('admin_unit') ? 'admin_unit' : names[0] || 'pegawai';
        const meta = ROLE_META[roleName] || ROLE_META.pegawai;
        return (
            <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-bold ${meta.badge}`}>
                <ShieldCheck className="h-3 w-3" />
                {names.includes('superadmin') ? 'Superadmin' : names.includes('admin_unit') ? 'Admin Unit' : (u.role || 'Pegawai')}
            </span>
        );
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Manajemen Hak Akses User</h2>}
        >
            <Head title="Manajemen User" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-xl font-extrabold text-text-primary">Daftar Akun & Akses</h3>
                            <p className="text-sm text-text-muted">Kelola modul apa saja yang bisa dilihat oleh setiap pengguna.</p>
                        </div>
                        <Link href={route('users.create')} className="btn-primary inline-flex shrink-0 items-center gap-2">
                            <UserPlus className="h-4 w-4" /> Tambah User
                        </Link>
                    </div>

                    {/* Flash */}
                    {flash?.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash?.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={Users} label="Total User" value={stats?.total_user ?? 0} sub="Berdasarkan filter aktif" />
                        <StatCard Icon={ShieldCheck} label="Superadmin" value={stats?.total_superadmin ?? 0} />
                        <StatCard Icon={UserCog} label="Admin Unit" value={stats?.total_admin_unit ?? 0} />
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
                            placeholder="Cari nama / email..."
                        />
                    </div>

                    {/* Table */}
                    <div className="card overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface">
                                    <tr>
                                        <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nama / Email</th>
                                        <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Role Utama</th>
                                        <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Unit Sekolah</th>
                                        <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border bg-white">
                                    {users.data.map((u) => (
                                        <tr key={u.id} className="transition-colors hover:bg-surface">
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(u.name)}`}>
                                                        {initials(u.name)}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-bold text-text-primary">{u.name}</p>
                                                        <p className="truncate text-xs text-text-muted">{u.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">{roleBadge(u)}</td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                {u.unit_sekolah ? (
                                                    <span className="inline-flex items-center gap-1.5 text-sm font-medium text-text-secondary">
                                                        <Building2 className="h-3.5 w-3.5 text-text-muted" /> {u.unit_sekolah.nama}
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-text-muted">Pusat</span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right">
                                                <Link href={route('users.edit', u.id)} className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                                    <ShieldCheck className="h-3.5 w-3.5" /> Atur Akses
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                    {users.data.length === 0 && (
                                        <tr>
                                            <td colSpan="4" className="px-6 py-12 text-center text-sm text-text-muted">
                                                <Inbox className="mx-auto mb-2 h-6 w-6 text-border" />
                                                Tidak ada data user.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="border-t border-border bg-surface/50 px-6 py-4">
                            <Pagination links={users.links} data={filters} />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
