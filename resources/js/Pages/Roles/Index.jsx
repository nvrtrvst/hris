import React, { useEffect, useRef, useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Badge, Button, Card, EmptyState, Input, PageHeader, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/AdminUI';
import { Head, Link, router } from '@inertiajs/react';
import StatCard from '@/Components/StatCard';
import Pagination from '@/Components/Pagination';
import { Layers, Plus, Search, Shield as ShieldIcon, ShieldCheck, Trash2, UserCog } from 'lucide-react';

const SYSTEM_ROLES = ['superadmin', 'admin_unit', 'pegawai'];

export default function Index({ auth, roles, filters, stats }) {
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
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Manajemen Role</h2>}
        >
            <Head title="Manajemen Role" />

            <div className="mx-auto max-w-7xl space-y-5">
                <PageHeader
                    eyebrow="Pengaturan"
                    title="Daftar Role Sistem"
                    description="Kelola peran dan izin default untuk setiap pengguna."
                    actions={
                        <Link href={route('roles.create')} className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-bold text-white transition-colors hover:bg-primary-600">
                            <Plus className="h-4 w-4" /> Tambah Role
                        </Link>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard Icon={Layers} label="Total Role" value={stats?.total_role ?? 0} />
                    <StatCard Icon={ShieldIcon} label="Role Bawaan" value={stats?.total_system ?? 0} sub="superadmin, admin_unit, pegawai" />
                    <StatCard Icon={UserCog} label="Role Kustom" value={stats?.total_custom ?? 0} />
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
                                placeholder="Cari nama role..."
                                aria-label="Cari role"
                            />
                        </div>
                        <p className="text-xs text-text-secondary">{roles.total} role</p>
                    </div>

                    {roles.data.length === 0 ? (
                        <EmptyState
                            icon={ShieldIcon}
                            title={searchTerm ? 'Role tidak ditemukan' : 'Belum ada role kustom'}
                            description={searchTerm ? 'Coba kata kunci lain atau bersihkan pencarian.' : 'Role bawaan sudah tersedia. Tambahkan role khusus jika diperlukan.'}
                            action={!searchTerm ? (
                                <Link href={route('roles.create')} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-bold text-white hover:bg-primary-600">
                                    <Plus className="h-3.5 w-3.5" /> Tambah Role
                                </Link>
                            ) : null}
                        />
                    ) : (
                        <>
                        <Table className="hidden md:block">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Nama Role</TableHead>
                                    <TableHead>Jumlah Izin</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {roles.data.map((role) => {
                                    const isSystem = SYSTEM_ROLES.includes(role.name);

                                    return (
                                        <TableRow key={role.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${isSystem ? 'bg-amber-50 text-amber-600' : 'bg-primary/10 text-primary'}`}>
                                                        <ShieldCheck className="h-5 w-5" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-bold uppercase tracking-wide text-primary">{role.name}</p>
                                                        {isSystem && <Badge variant="warning" className="mt-1">Bawaan Sistem</Badge>}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="info">{role.permissions.length} akses</Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="inline-flex flex-wrap items-center justify-end gap-2">
                                                    <Link href={route('roles.edit', role.id)} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                                        <ShieldCheck className="h-3.5 w-3.5" /> Atur Akses
                                                    </Link>
                                                    {!isSystem && (
                                                        <Button
                                                            variant="secondary"
                                                            size="sm"
                                                            onClick={() => handleDelete(role.id, role.name)}
                                                            className="border-rose-200 text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>

                        <div className="divide-y divide-border md:hidden">
                            {roles.data.map((role) => {
                                const isSystem = SYSTEM_ROLES.includes(role.name);

                                return (
                                    <div key={role.id} className="p-4">
                                        <div className="flex items-start gap-3">
                                            <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${isSystem ? 'bg-amber-50 text-amber-600' : 'bg-primary/10 text-primary'}`}>
                                                <ShieldCheck className="h-5 w-5" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-bold uppercase tracking-wide text-primary">{role.name}</p>
                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                    {isSystem && <Badge variant="warning">Bawaan Sistem</Badge>}
                                                    <Badge variant="info">{role.permissions.length} akses</Badge>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="mt-3 flex gap-2">
                                            <Link href={route('roles.edit', role.id)} className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-white text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                                <ShieldCheck className="h-3.5 w-3.5" /> Atur Akses
                                            </Link>
                                            {!isSystem && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => handleDelete(role.id, role.name)}
                                                    className="h-10 border-rose-200 text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        </>
                    )}

                    {roles.links?.length > 0 && (
                        <div className="border-t border-border px-4 py-3 sm:px-5">
                            <Pagination links={roles.links} data={filters} />
                        </div>
                    )}
                </Card>
            </div>
        </AdminShell>
    );
}
