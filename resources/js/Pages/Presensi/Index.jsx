import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, usePage, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { id } from 'date-fns/locale/id';

export default function Index({ auth, presensis, pegawai, filters, units }) {
    const isAdmin = auth.permissions?.includes('view_presensi');
    const [startDate, setStartDate] = React.useState(filters?.start_date || '');
    const [endDate, setEndDate] = React.useState(filters?.end_date || '');
    const [unitId, setUnitId] = React.useState(filters?.unit_id || '');
    const [lemburFilter, setLemburFilter] = React.useState(filters?.lembur_filter || '');
    const [lokasiFilter, setLokasiFilter] = React.useState(filters?.lokasi_filter || '');
    const [suspiciousFilter, setSuspiciousFilter] = React.useState(filters?.suspicious_filter || '');

    const applyFilter = () => {
        router.get(route('presensi.index'), {
            start_date: startDate, end_date: endDate, unit_id: unitId,
            lembur_filter: lemburFilter, lokasi_filter: lokasiFilter, suspicious_filter: suspiciousFilter,
        }, { preserveState: true });
    };

    const statusStyle = (s) => {
        const map = {
            hadir: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            telat: 'bg-amber-50 text-amber-700 border-amber-200',
            alpa: 'bg-rose-50 text-rose-700 border-rose-200',
            sakit: 'bg-purple-50 text-purple-700 border-purple-200',
            izin: 'bg-blue-50 text-blue-700 border-blue-200',
            cuti: 'bg-cyan-50 text-cyan-700 border-cyan-200',
        };
        return map[s] || 'bg-gray-50 text-gray-700 border-gray-200';
    };

    const lemburBadge = (p) => {
        if (!p.is_lembur) return null;
        const map = {
            pending: 'bg-amber-50 text-amber-700 border-amber-200',
            disetujui: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            ditolak: 'bg-rose-50 text-rose-700 border-rose-200',
        };
        return (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border ${map[p.lembur_status] || map.pending}`}>
                Lembur {p.lembur_status || 'Pending'}
            </span>
        );
    };

    return (
        <AuthenticatedLayout user={auth.user}
            header={<h2 className="font-semibold text-2xl text-primary leading-tight">
                {isAdmin ? 'Riwayat Presensi' : 'Presensi Saya'}
            </h2>}
        >
            <Head title="Presensi" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-border">
                        <div className="p-6">

                            {/* Header + Filters */}
                            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                                <div>
                                    <h3 className="text-lg font-bold text-primary">
                                        {isAdmin ? 'Riwayat Absensi' : `Riwayat Absensi${pegawai ? ` - ${pegawai.nama_lengkap}` : ''}`}
                                    </h3>
                                    <p className="text-sm text-text-secondary mt-1">Daftar rekaman waktu masuk dan keluar.</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {isAdmin && (
                                        <>
                                            {auth.permissions?.includes('view_all_units') && (
                                                <select className="border-border rounded-lg text-xs h-9 px-3" value={unitId} onChange={e => setUnitId(e.target.value)}>
                                                    <option value="">Semua Unit</option>
                                                    {units?.map(u => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                                </select>
                                            )}
                                            <select className="border-border rounded-lg text-xs h-9 px-3" value={lemburFilter} onChange={e => setLemburFilter(e.target.value)}>
                                                <option value="">Semua Status</option>
                                                <option value="lembur_semua">Semua Lembur</option>
                                                <option value="lembur_pending">Lembur Pending</option>
                                                <option value="lembur_disetujui">Lembur Disetujui</option>
                                                <option value="lembur_ditolak">Lembur Ditolak</option>
                                            </select>
                                            <select className="border-border rounded-lg text-xs h-9 px-3" value={lokasiFilter} onChange={e => setLokasiFilter(e.target.value)}>
                                                <option value="">Semua Lokasi</option>
                                                <option value="perlu_review">Perlu Review GPS</option>
                                            </select>
                                            <select className="border-border rounded-lg text-xs h-9 px-3" value={suspiciousFilter} onChange={e => setSuspiciousFilter(e.target.value)}>
                                                <option value="">Semua GPS</option>
                                                <option value="1">Posisi Mencurigakan</option>
                                            </select>
                                        </>
                                    )}
                                    <input type="date" className="border-border rounded-lg text-xs h-9 px-3" value={startDate} onChange={e => setStartDate(e.target.value)} />
                                    <span className="text-text-secondary text-xs">-</span>
                                    <input type="date" className="border-border rounded-lg text-xs h-9 px-3" value={endDate} onChange={e => setEndDate(e.target.value)} />
                                    <button onClick={applyFilter} className="bg-primary hover:bg-primary-light text-white px-4 h-9 rounded-lg text-xs font-bold transition-all shadow-sm inline-flex items-center">
                                        <svg className="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                        Filter
                                    </button>
                                    {pegawai && (
                                        <Link href={route('presensi.create')}
                                            className="bg-primary hover:bg-primary-light text-white font-semibold py-2 px-5 rounded-lg shadow-md hover:shadow-lg transition-all text-sm"
                                        >
                                            Absen Sekarang
                                        </Link>
                                    )}
                                </div>
                            </div>

                            {/* ─────── ADMIN: Table ─────── */}
                            {isAdmin ? (
                                <div className="overflow-x-auto rounded-xl border border-border">
                                    <table className="min-w-full divide-y divide-border">
                                        <thead className="bg-surface">
                                            <tr>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Pegawai & Unit</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Tanggal</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Jam Masuk</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Jam Keluar</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Lembur</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Status</th>
                                                <th className="px-6 py-4 text-right text-xs font-bold text-text-secondary uppercase tracking-wider">Geofence</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border/50">
                                            {presensis.data.map((p) => (
                                                <tr key={p.id} className="hover:bg-surface/80 transition-colors">
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-bold text-primary">{p.pegawai?.nama_lengkap || '-'}</div>
                                                        <div className="text-xs text-text-secondary mt-0.5">{p.unit_sekolah?.nama || '-'}</div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-primary">
                                                        {format(new Date(p.tanggal), 'EEE, d MMM yyyy', { locale: id })}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {p.jam_masuk ? (
                                                            <span className="text-sm font-semibold text-primary">{p.jam_masuk.substring(0, 5)}</span>
                                                        ) : <span className="text-sm text-text-secondary">-</span>}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {p.jam_keluar ? (
                                                            <span className="text-sm font-semibold text-primary">{p.jam_keluar.substring(0, 5)}</span>
                                                        ) : <span className="text-sm text-text-secondary">-</span>}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {p.is_lembur ? (
                                                            <div>
                                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${
                                                                    p.lembur_status === 'disetujui' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                    p.lembur_status === 'ditolak' ? 'bg-rose-50 text-rose-700 border-rose-200' :
                                                                    'bg-amber-50 text-amber-700 border-amber-200'
                                                                }`}>{p.lembur_status || 'pending'}</span>
{p.lembur_status === 'pending' && (
                                                                        <div className="flex gap-1 mt-1">
                                                                            <button onClick={() => router.post(route('presensi.approveLembur', p.id), {}, { preserveState: true })}
                                                                                className="text-[10px] bg-success hover:bg-green-700 text-white font-semibold py-0.5 px-2 rounded"
                                                                            >Setuju</button>
                                                                            <button onClick={() => router.post(route('presensi.rejectLembur', p.id), {}, { preserveState: true })}
                                                                                className="text-[10px] bg-danger hover:bg-red-700 text-white font-semibold py-0.5 px-2 rounded"
                                                                            >Tolak</button>
                                                                        </div>
                                                                    )}
                                                                    </div>
                                                        ) : <span className="text-sm text-text-secondary">-</span>}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {auth.user.email === 'admin@yayasan.com' ? (
                                                            <select className={`text-xs font-semibold rounded-md shadow-sm uppercase ${
                                                                 p.status === 'hadir' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                                 p.status === 'telat' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                                                                 'bg-rose-50 text-rose-700 border border-rose-200'
                                                             }`} value={p.status}
                                                                 onChange={(e) => { if (confirm('Ubah status?')) router.put(route('presensi.update', p.id), { status: e.target.value }); }}
                                                             >
                                                                 <option value="hadir">HADIR</option>
                                                                 <option value="telat">TELAT</option>
                                                                 <option value="alpa">ALPA</option>
                                                             </select>
                                                        ) : (
                                                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ${statusStyle(p.status)}`}>{p.status}</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                        <div className="text-primary">{p.jarak_masuk_meter ? `${p.jarak_masuk_meter}m` : '-'} <span className="text-text-secondary text-xs">Masuk</span></div>
                                                        <div className="text-primary">{p.jarak_keluar_meter ? `${p.jarak_keluar_meter}m` : '-'} <span className="text-text-secondary text-xs">Keluar</span></div>
                                                        {p.lokasi_perlu_review && <div className="mt-1 text-[10px] font-bold text-danger bg-rose-50 inline-block px-2 py-0.5 rounded">Perlu Review</div>}
                                                        {p.posisi_mencurigakan && <div className="mt-1 text-[10px] font-bold text-warning bg-amber-50 inline-block px-2 py-0.5 rounded">Posisi Mencurigakan</div>}
                                                    </td>
                                                </tr>
                                            ))}
                                            {presensis.data.length === 0 && (
                                                <tr><td colSpan="7" className="px-6 py-12 text-center text-text-secondary">Belum ada data presensi.</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {presensis.data.map((p) => (
                                        <div key={p.id} className="bg-white rounded-xl border border-border shadow-sm hover:shadow-md transition-all p-5">
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-start gap-4">
                                                    <div className="w-12 h-12 rounded-full bg-primary/5 border border-primary/10 flex items-center justify-center flex-shrink-0">
                                                        <svg className="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <div className="text-sm font-bold text-primary">
                                                            {format(new Date(p.tanggal), 'EEEE, d MMMM yyyy', { locale: id })}
                                                        </div>
                                                        <div className="flex items-center gap-3 mt-2">
                                                            <div className="flex items-center gap-1.5">
                                                                <div className="w-2 h-2 rounded-full bg-success"></div>
                                                                <span className="text-xs text-text-secondary">Masuk</span>
                                                                <span className="text-sm font-semibold text-primary">{p.jam_masuk?.substring(0, 5) || '-'}</span>
                                                            </div>
                                                            <div className="w-px h-4 bg-border"></div>
                                                            <div className="flex items-center gap-1.5">
                                                                <div className="w-2 h-2 rounded-full bg-danger/60"></div>
                                                                <span className="text-xs text-text-secondary">Keluar</span>
                                                                <span className="text-sm font-semibold text-primary">{p.jam_keluar?.substring(0, 5) || '-'}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ${statusStyle(p.status)}`}>
                                                        {p.status}
                                                    </span>
                                                    {lemburBadge(p)}
                                                </div>
                                            </div>
                                            {(p.foto_masuk_url || p.foto_keluar_url) && (
                                                <div className="mt-3 pt-3 border-t border-border flex items-center gap-4">
                                                    {p.foto_masuk_url && (
                                                        <a href={p.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-xs text-text-secondary hover:text-primary transition-colors">
                                                            <span className="w-2 h-2 rounded-full bg-success"></span>
                                                            Foto Masuk
                                                        </a>
                                                    )}
                                                    {p.foto_keluar_url && (
                                                        <a href={p.foto_keluar_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-xs text-text-secondary hover:text-primary transition-colors">
                                                            <span className="w-2 h-2 rounded-full bg-danger/60"></span>
                                                            Foto Keluar
                                                        </a>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                    {presensis.data.length === 0 && (
                                        <div className="text-center py-16 text-text-secondary">
                                            <svg className="w-16 h-16 mx-auto mb-4 text-border" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <p className="text-base font-medium">Belum ada riwayat presensi.</p>
                                            <p className="text-sm mt-1 text-text-secondary">Data akan muncul setelah Anda melakukan absen pertama.</p>
                                        </div>
                                    )}
                                </div>
                            )}

                            {presensis.links && (
                                <div className="mt-8">
                                    <Pagination links={presensis.links} />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
