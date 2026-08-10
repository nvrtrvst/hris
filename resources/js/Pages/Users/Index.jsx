import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, router } from '@inertiajs/react';
import { Edit2, Shield, Search, Plus } from 'lucide-react';

export default function Index({ auth, users, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('users.index'), { search: searchTerm }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-text-primary leading-tight">Manajemen Hak Akses User</h2>}
        >
            <Head title="Manajemen User" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                    <div className="card-table">
                        <div className="page-card-header">
                            <div>
                                <h3 className="text-lg font-bold text-text-primary">Daftar Akun & Akses</h3>
                                <p className="page-subtitle">Kelola modul apa saja yang bisa dilihat oleh setiap pengguna.</p>
                            </div>

                            <div className="flex flex-col sm:flex-row items-center gap-3">
                                <form onSubmit={handleSearch} className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Search className="h-4 w-4 text-text-muted" />
                                    </div>
                                    <input
                                        type="text"
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="input-field pl-10"
                                        placeholder="Cari nama / email..."
                                    />
                                </form>
                                <Link
                                    href={route('users.create')}
                                    className="btn-primary"
                                >
                                    <Plus className="w-4 h-4" />
                                    Tambah User
                                </Link>
                            </div>
                        </div>
                        <div className="table-wrap">
                            <table className="table-base">
                                <thead className="bg-surface text-text-secondary font-semibold border-b border-border">
                                    <tr>
                                        <th className="px-6 py-4">Nama / Email</th>
                                        <th className="px-6 py-4">Role Utama</th>
                                        <th className="px-6 py-4">Unit Sekolah</th>
                                        <th className="px-6 py-4 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {users.data.map((u) => (
                                        <tr key={u.id} className="hover:bg-surface transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-text-primary">{u.name}</div>
                                                <div className="text-xs text-text-muted">{u.email}</div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`badge ${
                                                    u.roles.some(r => r.name === 'superadmin') ? 'badge-warning' :
                                                    u.roles.some(r => r.name === 'admin_unit') ? 'badge-info' :
                                                    'badge-neutral'
                                                }`}>
                                                    {u.roles.length > 0 ? u.roles.map(r => r.name).join(', ') : (u.role || 'pegawai')}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-text-secondary">
                                                {u.unit_sekolah ? u.unit_sekolah.nama : '-'}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <Link
                                                    href={route('users.edit', u.id)}
                                                    className="btn-secondary btn-sm"
                                                >
                                                    <Shield className="w-3.5 h-3.5" />
                                                    Atur Akses
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                    {users.data.length === 0 && (
                                        <tr>
                                            <td colSpan="4" className="px-6 py-8 text-center text-text-muted">
                                                Tidak ada data user.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="border-t border-border px-4 py-3">
                            <Pagination links={users.links} data={filters} />
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
