import React, { useEffect, useRef, useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import Pagination from '@/Components/Pagination';
import StatCard from '@/Components/StatCard';
import { Badge, Card, EmptyState, Input, PageHeader, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/AdminUI';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Search, ShieldCheck, UserCog, UserPlus, Users } from 'lucide-react';
import { avatarTone, initials } from '@/Utils/avatar';

const ROLE_META = {
    superadmin: { label: 'Superadmin', variant: 'warning' },
    admin_unit: { label: 'Admin Unit', variant: 'info' },
    pegawai: { label: 'Pegawai', variant: 'neutral' },
};

export default function Index({ auth, users, filters, stats }) {
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

    const roleMeta = (u) => {
        const names = u.roles?.map((r) => r.name) || [];
        const roleName = names.includes('superadmin') ? 'superadmin' : names.includes('admin_unit') ? 'admin_unit' : names[0] || 'pegawai';

        return ROLE_META[roleName] || ROLE_META.pegawai;
    };

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Manajemen User</h2>}
        >
            <Head title="Manajemen User" />

            <div className="mx-auto max-w-7xl space-y-5">
                <PageHeader
                    eyebrow="Pengaturan"
                    title="Daftar Akun & Akses"
                    description="Kelola akun dan modul yang bisa diakses setiap pengguna."
                    actions={
                        <Link href={route('users.create')} className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-bold text-white transition-colors hover:bg-primary-600">
                            <UserPlus className="h-4 w-4" /> Tambah User
                        </Link>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard Icon={Users} label="Total User" value={stats?.total_user ?? 0} sub="Berdasarkan filter aktif" />
                    <StatCard Icon={ShieldCheck} label="Superadmin" value={stats?.total_superadmin ?? 0} />
                    <StatCard Icon={UserCog} label="Admin Unit" value={stats?.total_admin_unit ?? 0} />
                </div>

                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div className="relative w-full sm:max-w-sm">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                            <Input
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="h-10 pl-9"
                                placeholder="Cari nama / email..."
                                aria-label="Cari user"
                            />
                        </div>
                        <p className="text-xs text-text-secondary">
                            {users.total} akun
                        </p>
                    </div>

                    {users.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title={searchTerm ? 'User tidak ditemukan' : 'Belum ada akun user'}
                            description={searchTerm ? 'Coba kata kunci lain atau bersihkan pencarian.' : 'Tambahkan akun pertama untuk mulai mengatur akses.'}
                            action={!searchTerm ? (
                                <Link href={route('users.create')} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-bold text-white hover:bg-primary-600">
                                    <UserPlus className="h-3.5 w-3.5" /> Tambah User
                                </Link>
                            ) : null}
                        />
                    ) : (
                        <>
                        <Table className="hidden md:block">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Nama / Email</TableHead>
                                    <TableHead>Role Utama</TableHead>
                                    <TableHead className="hidden md:table-cell">Unit Sekolah</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((u) => {
                                    const meta = roleMeta(u);

                                    return (
                                        <TableRow key={u.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(u.name)}`}>
                                                        {initials(u.name)}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-bold text-primary">{u.name}</p>
                                                        <p className="truncate text-xs text-text-muted">{u.email}</p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={meta.variant}>
                                                    <ShieldCheck className="h-3 w-3" />
                                                    {meta.label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                {u.unit_sekolah ? (
                                                    <span className="inline-flex items-center gap-1.5 text-sm text-text-secondary">
                                                        <Building2 className="h-3.5 w-3.5 text-text-muted" /> {u.unit_sekolah.nama}
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-text-muted">Pusat</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link href={route('users.edit', u.id)} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                                    <ShieldCheck className="h-3.5 w-3.5" /> Atur Akses
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>

                        <div className="divide-y divide-border md:hidden">
                            {users.data.map((u) => {
                                const meta = roleMeta(u);

                                return (
                                    <div key={u.id} className="p-4">
                                        <div className="flex items-start gap-3">
                                            <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(u.name)}`}>
                                                {initials(u.name)}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-bold text-primary">{u.name}</p>
                                                <p className="truncate text-xs text-text-muted">{u.email}</p>
                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                    <Badge variant={meta.variant}>
                                                        <ShieldCheck className="h-3 w-3" />
                                                        {meta.label}
                                                    </Badge>
                                                    <span className="inline-flex items-center gap-1 text-[11px] text-text-secondary">
                                                        <Building2 className="h-3 w-3" /> {u.unit_sekolah?.nama || 'Pusat'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <Link href={route('users.edit', u.id)} className="mt-3 flex h-10 w-full items-center justify-center gap-1.5 rounded-lg border border-border bg-white text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                            <ShieldCheck className="h-3.5 w-3.5" /> Atur Akses
                                        </Link>
                                    </div>
                                );
                            })}
                        </div>
                        </>
                    )}

                    {users.links?.length > 0 && (
                        <div className="border-t border-border px-4 py-3 sm:px-5">
                            <Pagination links={users.links} data={filters} />
                        </div>
                    )}
                </Card>
            </div>
        </AdminShell>
    );
}
