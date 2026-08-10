import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit2, Search, Plus, Trash2, ShieldAlert } from 'lucide-react';
import FlashToast from '@/Components/FlashToast';
import Pagination from '@/Components/Pagination';

export default function Index({ auth, roles, filters, flash }) {
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('roles.index'), { search: searchTerm }, { preserveState: true });
    };

    const handleDelete = (id, name) => {
        if (confirm(`Apakah Anda yakin ingin menghapus role "${name.toUpperCase()}"? User yang memiliki role ini mungkin akan kehilangan akses.`)) {
            router.delete(route('roles.destroy', id), {
                preserveScroll: true
            });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-text-primary leading-tight">Manajemen Role</h2>}
        >
            <Head title="Manajemen Role" />

            <FlashToast flash={flash} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                    <div className="card-table">
                        <div className="page-card-header">
                            <div>
                                <h3 className="text-lg font-bold text-text-primary">Daftar Role Sistem</h3>
                                <p className="page-subtitle">Buat peran khusus dan atur izin default untuk peran tersebut.</p>
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
                                        placeholder="Cari nama role..."
                                    />
                                </form>
                                <Link
                                    href={route('roles.create')}
                                    className="btn-primary"
                                >
                                    <Plus className="w-4 h-4" />
                                    Tambah Role
                                </Link>
                            </div>
                        </div>
                        <div className="table-wrap">
                            <table className="table-base">
                                <thead className="text-xs text-text-secondary uppercase bg-surface/50 border-b border-border">
                                    <tr>
                                        <th className="px-6 py-4 font-semibold">Nama Role</th>
                                        <th className="px-6 py-4 font-semibold">Jumlah Izin (Permissions)</th>
                                        <th className="px-6 py-4 font-semibold text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roles.data.length > 0 ? roles.data.map((role) => (
                                        <tr key={role.id} className="border-b border-border hover:bg-surface/50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className={`p-2 rounded-button ${['superadmin', 'admin_unit', 'pegawai'].includes(role.name) ? 'bg-amber-100 text-amber-600' : 'bg-primary-100 text-primary'}`}>
                                                        <ShieldAlert className="w-4 h-4" />
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-text-primary">{role.name.toUpperCase()}</p>
                                                        {['superadmin', 'admin_unit', 'pegawai'].includes(role.name) && (
                                                            <span className="badge-warning">Bawaan Sistem</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="badge-info">
                                                    {role.permissions.length} akses
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right space-x-2">
                                                <Link
                                                    href={route('roles.edit', role.id)}
                                                    className="btn-secondary btn-sm"
                                                >
                                                    <Edit2 className="w-3.5 h-3.5" />
                                                    Atur Akses Default
                                                </Link>
                                                {!['superadmin', 'admin_unit', 'pegawai'].includes(role.name) && (
                                                    <button
                                                        onClick={() => handleDelete(role.id, role.name)}
                                                        className="btn-danger btn-sm"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan="3" className="px-6 py-8 text-center text-text-muted">
                                                Tidak ada data role.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="border-t border-border px-4 py-3">
                            <Pagination links={roles.links} data={filters} />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
