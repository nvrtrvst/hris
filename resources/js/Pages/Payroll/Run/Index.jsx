import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function RunPayrollIndex({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        month: new Date().getMonth() + 1 < 10 ? '0' + (new Date().getMonth() + 1) : (new Date().getMonth() + 1).toString(),
        year: new Date().getFullYear().toString(),
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('penggajian.run.init'));
    };

    const months = [
        { val: '01', label: 'Januari' }, { val: '02', label: 'Februari' }, { val: '03', label: 'Maret' },
        { val: '04', label: 'April' }, { val: '05', label: 'Mei' }, { val: '06', label: 'Juni' },
        { val: '07', label: 'Juli' }, { val: '08', label: 'Agustus' }, { val: '09', label: 'September' },
        { val: '10', label: 'Oktober' }, { val: '11', label: 'November' }, { val: '12', label: 'Desember' }
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Run Payroll - Wizard" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100 p-8">
                        <div className="text-center mb-8">
                            <h2 className="text-3xl font-extrabold text-gray-900 tracking-tight">Run Payroll</h2>
                            <p className="mt-4 text-lg text-gray-500">Mulai proses penggajian bulan ini. Sistem akan menarik data kehadiran dan jam mengajar secara otomatis.</p>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            <div className="flex gap-4 justify-center">
                                <div className="w-1/2">
                                    <label className="block text-sm font-bold text-gray-700 mb-2">Pilih Bulan</label>
                                    <select
                                        value={data.month}
                                        onChange={e => setData('month', e.target.value)}
                                        className="mt-1 block w-full pl-3 pr-10 py-3 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md shadow-sm"
                                    >
                                        {months.map(m => (
                                            <option key={m.val} value={m.val}>{m.label}</option>
                                        ))}
                                    </select>
                                    {errors.month && <p className="mt-2 text-sm text-red-600">{errors.month}</p>}
                                </div>
                                <div className="w-1/2">
                                    <label className="block text-sm font-bold text-gray-700 mb-2">Tahun</label>
                                    <input
                                        type="number"
                                        value={data.year}
                                        onChange={e => setData('year', e.target.value)}
                                        className="mt-1 block w-full pl-3 pr-10 py-3 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md shadow-sm"
                                    />
                                    {errors.year && <p className="mt-2 text-sm text-red-600">{errors.year}</p>}
                                </div>
                            </div>

                            <div className="mt-8 flex justify-center">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full flex justify-center py-4 px-4 border border-transparent rounded-xl shadow-sm text-lg font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                                >
                                    {processing ? 'Menarik Data...' : 'Mulai Penggajian (Step 1)'}
                                </button>
                            </div>
                        </form>
                    </div>

                    <div className="mt-6 text-center">
                        <Link href={route('penggajian.index')} className="text-indigo-600 hover:text-indigo-900 font-medium">
                            &larr; Kembali ke Riwayat Penggajian
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
