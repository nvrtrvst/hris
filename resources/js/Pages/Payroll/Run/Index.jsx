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

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">

                    <div className="page-card p-8">
                        <div className="text-center mb-8">
                            <h2 className="page-title text-3xl">Run Payroll</h2>
                            <p className="mt-4 text-lg text-text-muted">Mulai proses penggajian bulan ini. Sistem akan menarik data kehadiran dan jam mengajar secara otomatis.</p>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            <div className="flex gap-4 justify-center">
                                <div className="w-1/2">
                                    <label className="form-label">Pilih Bulan</label>
                                    <select
                                        value={data.month}
                                        onChange={e => setData('month', e.target.value)}
                                        className="select-field"
                                    >
                                        {months.map(m => (
                                            <option key={m.val} value={m.val}>{m.label}</option>
                                        ))}
                                    </select>
                                    {errors.month && <p className="form-error">{errors.month}</p>}
                                </div>
                                <div className="w-1/2">
                                    <label className="form-label">Tahun</label>
                                    <input
                                        type="number"
                                        value={data.year}
                                        onChange={e => setData('year', e.target.value)}
                                        className="input-field"
                                    />
                                    {errors.year && <p className="form-error">{errors.year}</p>}
                                </div>
                            </div>

                            <div className="mt-8 flex justify-center">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn-primary w-full py-4 text-lg"
                                >
                                    {processing ? 'Menarik Data...' : 'Mulai Penggajian (Step 1)'}
                                </button>
                            </div>
                        </form>
                    </div>

                    <div className="mt-6 text-center">
                        <Link href={route('penggajian.index')} className="link text-sm">
                            &larr; Kembali ke Riwayat Penggajian
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
