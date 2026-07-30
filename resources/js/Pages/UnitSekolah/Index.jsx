import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ auth, units }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Kelola Unit Sekolah</h2>}
        >
            <Head title="Unit Sekolah" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="card">
                        <div className="p-8">
                            <div className="page-header mb-6">
                                <div>
                                    <h3 className="text-xl font-bold text-text-primary">Daftar Unit & Lokasi GPS</h3>
                                    <p className="page-subtitle">Atur titik koordinat (Latitude/Longitude) dan Radius toleransi absen untuk setiap unit.</p>
                                </div>
                                <a
                                    href={route('unit-sekolah.create')}
                                    className="btn-primary"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                                    </svg>
                                    Tambah Unit
                                </a>
                            </div>

                            <div className="overflow-x-auto rounded-card border border-border">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Nama Unit</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Latitude</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Longitude</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Radius (Meter)</th>
                                            <th className="px-6 py-4 text-right text-xs font-bold text-text-muted uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border">
                                        {units.map((unit) => (
                                            <tr key={unit.id} className="hover:bg-surface transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-bold text-text-primary">{unit.nama}</div>
                                                    <div className="text-sm text-text-muted">{unit.singkatan}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-text-secondary font-mono">
                                                    {unit.latitude}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-text-secondary font-mono">
                                                    {unit.longitude}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="badge-success font-bold">
                                                        {unit.radius_meter} M
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <Link href={route('unit-sekolah.edit', unit.id)} className="btn-secondary btn-sm">
                                                        Edit Lokasi
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
