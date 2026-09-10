import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Database } from 'lucide-react';

export default function ReferenceTypes({ auth, types, allowedSources }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Payroll Reference Types</h2>}
        >
            <Head title="Reference Types" />

            <div className="space-y-4">
                <div className="bg-white dark:bg-slate-800 rounded-lg p-4 shadow-sm">
                    <p className="text-sm text-slate-600 dark:text-slate-300 mb-3">
                        Whitelisted source fields (aman dipakai FE):
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {allowedSources.map((field) => (
                            <span
                                key={field}
                                className="px-2 py-1 text-xs bg-slate-100 dark:bg-slate-700 rounded"
                            >
                                {field}
                            </span>
                        ))}
                    </div>
                </div>

                <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm">
                    <div className="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
                        <Database className="w-5 h-5" />
                        <h3 className="font-semibold">Daftar Reference Types</h3>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th className="text-left p-3">Kode</th>
                                <th className="text-left p-3">Nama</th>
                                <th className="text-left p-3">Source Field</th>
                                <th className="text-right p-3">Jumlah Nilai</th>
                                <th className="text-center p-3">Aktif</th>
                            </tr>
                        </thead>
                        <tbody>
                            {types.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="p-6 text-center text-slate-500">
                                        Belum ada reference type.
                                    </td>
                                </tr>
                            )}
                            {types.map((t) => (
                                <tr key={t.id} className="border-t border-slate-200 dark:border-slate-700">
                                    <td className="p-3 font-mono">{t.kode}</td>
                                    <td className="p-3">{t.nama}</td>
                                    <td className="p-3 font-mono text-xs">{t.source_field}</td>
                                    <td className="p-3 text-right">{t.values?.length ?? 0}</td>
                                    <td className="p-3 text-center">
                                        {t.is_active ? (
                                            <span className="text-emerald-600">Aktif</span>
                                        ) : (
                                            <span className="text-slate-400">Non-aktif</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}