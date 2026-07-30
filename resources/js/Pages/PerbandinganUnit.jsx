import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

const statusStyle = {
    hadir: 'badge-success',
    telat: 'badge-warning',
    sakit: 'badge-info',
    izin: 'bg-blue-50 text-blue-700',
    cuti: 'bg-cyan-50 text-cyan-700',
    alpa: 'badge-danger',
};

function PersenBar({ value }) {
    const color = value >= 90 ? 'bg-success' : value >= 75 ? 'bg-warning' : 'bg-danger';
    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 h-2 bg-border rounded-full overflow-hidden">
                <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${Math.min(value, 100)}%` }} />
            </div>
            <span className="text-sm font-bold w-12 text-right text-text-primary">{value}%</span>
        </div>
    );
}

export default function PerbandinganUnit({ units, filter }) {
    const [period, setPeriod] = React.useState(filter.period);
    const [startDate, setStartDate] = React.useState(filter.start_date);
    const [endDate, setEndDate] = React.useState(filter.end_date);

    const applyFilter = () => {
        const params = { period };
        if (period === 'custom') {
            params.start_date = startDate;
            params.end_date = endDate;
        }
        router.get(route('dashboard.perbandingan-unit'), params, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            user={null}
            header={
                <div className="page-header mb-0">
                    <h1 className="page-title">Perbandingan Kehadiran Antar Unit</h1>
                </div>
            }
        >
            <Head title="Perbandingan Unit" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* Filter */}
                    <div className="filter-bar card p-4">
                        <div>
                            <label className="form-label text-xs">Periode</label>
                            <select className="select-field text-sm h-9" value={period} onChange={e => {
                                setPeriod(e.target.value);
                                if (e.target.value !== 'custom') {
                                    router.get(route('dashboard.perbandingan-unit'), { period: e.target.value }, { preserveState: true });
                                }
                            }}>
                                <option value="this_month">Bulan Ini</option>
                                <option value="last_month">Bulan Lalu</option>
                                <option value="custom">Kustom</option>
                            </select>
                        </div>
                        {period === 'custom' && (
                            <>
                                <div>
                                    <label className="form-label text-xs">Dari</label>
                                    <input type="date" className="input-field text-sm h-9" value={startDate} onChange={e => setStartDate(e.target.value)} />
                                </div>
                                <div>
                                    <label className="form-label text-xs">Sampai</label>
                                    <input type="date" className="input-field text-sm h-9" value={endDate} onChange={e => setEndDate(e.target.value)} />
                                </div>
                                <button onClick={applyFilter} className="btn-primary btn-sm self-end">Terapkan</button>
                            </>
                        )}
                    </div>

                    {/* Tabel */}
                    <div className="card-table">
                        <div className="table-wrap">
                            <table className="table-base">
                                <thead>
                                    <tr>
                                        <th className="text-left">Unit</th>
                                        <th className="text-center">Hadir</th>
                                        <th className="text-center">Telat</th>
                                        <th className="text-center">Sakit</th>
                                        <th className="text-center">Izin</th>
                                        <th className="text-center">Cuti</th>
                                        <th className="text-center">Alpa</th>
                                        <th className="text-center">Total</th>
                                        <th className="text-left min-w-[180px]">% Kehadiran</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/50">
                                    {units.map((u) => (
                                        <tr key={u.unit_id} className="hover:bg-surface/80 transition-colors">
                                            <td className="font-semibold text-primary">{u.unit_nama}</td>
                                            <td className="text-center"><span className={statusStyle.hadir}>{u.total_hadir}</span></td>
                                            <td className="text-center"><span className={statusStyle.telat}>{u.total_telat}</span></td>
                                            <td className="text-center"><span className={statusStyle.sakit}>{u.total_sakit}</span></td>
                                            <td className="text-center"><span className={statusStyle.izin}>{u.total_izin}</span></td>
                                            <td className="text-center"><span className={statusStyle.cuti}>{u.total_cuti}</span></td>
                                            <td className="text-center"><span className={statusStyle.alpa}>{u.total_alpa}</span></td>
                                            <td className="text-center text-text-muted">{u.total}</td>
                                            <td>
                                                <PersenBar value={u.kehadiran_persen} />
                                            </td>
                                        </tr>
                                    ))}
                                    {units.length === 0 && (
                                        <tr>
                                            <td colSpan={9}>
                                                <div className="empty-state">
                                                    <p className="empty-state-desc">Belum ada data presensi untuk periode ini.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
