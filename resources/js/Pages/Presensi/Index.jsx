import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, usePage, router } from '@inertiajs/react';

export default function Index({ auth, presensis, pegawai, filters, units, userRole }) {
    const { url } = usePage();
    const [startDate, setStartDate] = React.useState(filters?.start_date || '');
    const [endDate, setEndDate] = React.useState(filters?.end_date || '');
    const [unitId, setUnitId] = React.useState(filters?.unit_id || '');
    const [lemburFilter, setLemburFilter] = React.useState(filters?.lembur_filter || '');
    const [lokasiFilter, setLokasiFilter] = React.useState(filters?.lokasi_filter || '');

    const applyFilter = () => {
        router.get(route('presensi.index'), { start_date: startDate, end_date: endDate, unit_id: unitId, lembur_filter: lemburFilter, lokasi_filter: lokasiFilter }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Riwayat Absensi</h2>}
        >
            <Head title="Riwayat Absensi" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <div className="flex justify-between items-center mb-8">
                                <div>
                                    <h3 className="text-xl font-bold text-gray-900">Riwayat Absensi {pegawai ? `- ${pegawai.nama_lengkap}` : '(Semua Pegawai)'}</h3>
                                    <p className="text-sm text-gray-500 mt-1">Daftar rekaman waktu masuk dan keluar beserta lokasi (Geofencing).</p>
                                </div>
                                <div className="flex space-x-4 items-center">
                                    <div className="flex space-x-2 items-center bg-gray-50 p-2 rounded-lg border border-gray-200">
                                        {userRole === 'superadmin' && (
                                            <>
                                                <select className="border-gray-300 rounded-md shadow-sm text-xs h-8 pr-8" value={unitId} onChange={e => setUnitId(e.target.value)}>
                                                    <option value="">Semua Unit</option>
                                                    {units?.map(u => (
                                                        <option key={u.id} value={u.id}>{u.nama}</option>
                                                    ))}
                                                </select>
                                                <div className="w-px h-5 bg-gray-300 mx-1"></div>
                                            </>
                                        )}
                                        <select className="border-gray-300 rounded-md shadow-sm text-xs h-8 pr-8" value={lemburFilter} onChange={e => setLemburFilter(e.target.value)}>
                                            <option value="">Semua Status</option>
                                            <option value="lembur_semua">Semua Lembur</option>
                                            <option value="lembur_pending">Lembur Pending</option>
                                            <option value="lembur_disetujui">Lembur Disetujui</option>
                                            <option value="lembur_ditolak">Lembur Ditolak</option>
                                        </select>
                                        <select className="border-gray-300 rounded-md shadow-sm text-xs h-8 pr-8" value={lokasiFilter} onChange={e => setLokasiFilter(e.target.value)}>
                                            <option value="">Semua Lokasi</option>
                                            <option value="perlu_review">Perlu Review GPS</option>
                                        </select>
                                        <input type="date" className="border-gray-300 rounded-md shadow-sm text-xs h-8" value={startDate} onChange={e => setStartDate(e.target.value)} />
                                        <span className="text-gray-500">-</span>
                                        <input type="date" className="border-gray-300 rounded-md shadow-sm text-xs h-8" value={endDate} onChange={e => setEndDate(e.target.value)} />
                                        <button onClick={applyFilter} className="bg-indigo-600 hover:bg-indigo-700 text-white px-3 h-8 rounded-md text-xs font-bold transition-colors shadow-sm flex items-center">
                                            <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                            Filter
                                        </button>
                                    </div>
                                    {pegawai && (
                                        <Link
                                            href={route('presensi.create')}
                                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-6 rounded-lg shadow-md hover:shadow-lg transition-all duration-200"
                                        >
                                            Absen Sekarang
                                        </Link>
                                    )}
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pegawai & Unit</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jam Masuk</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jam Keluar</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Lembur</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Geofence (Jarak)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {presensis.data.length > 0 ? (
                                            presensis.data.map((presensi) => (
                                                <tr key={presensi.id} className="hover:bg-gray-50 transition-colors">
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-bold text-gray-900">{presensi.pegawai?.nama_lengkap || 'Unknown'}</div>
                                                        <div className="text-xs text-gray-500 mt-1">{presensi.unit_sekolah?.nama || '-'}</div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-bold text-indigo-900">{new Date(presensi.tanggal).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' })}</div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {presensi.jam_masuk ? (
                                                            <div className="flex items-center space-x-2">
                                                                <span className="text-sm font-medium text-gray-900">{presensi.jam_masuk.substring(0,5)}</span>
                                                                {presensi.foto_masuk_url && (
                                                                    <a href={presensi.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="text-indigo-500 hover:text-indigo-700">
                                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                                    </a>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-sm text-gray-400">-</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {presensi.jam_keluar ? (
                                                            <div className="flex items-center space-x-2">
                                                                <span className="text-sm font-medium text-gray-900">{presensi.jam_keluar.substring(0,5)}</span>
                                                                {presensi.foto_keluar_url && (
                                                                    <a href={presensi.foto_keluar_url} target="_blank" rel="noopener noreferrer" className="text-indigo-500 hover:text-indigo-700">
                                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                                    </a>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-sm text-gray-400">-</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {presensi.is_lembur ? (
                                                            <div>
                                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase ${
                                                                    presensi.lembur_status === 'disetujui' ? 'bg-green-100 text-green-800' :
                                                                    presensi.lembur_status === 'ditolak' ? 'bg-red-100 text-red-800' :
                                                                    'bg-yellow-100 text-yellow-800'
                                                                }`}>
                                                                    {presensi.lembur_status || 'pending'}
                                                                </span>
                                                                {presensi.lembur_status === 'pending' && (
                                                                    <div className="flex space-x-1 mt-1">
                                                                        <button
                                                                            onClick={() => router.post(route('presensi.approveLembur', presensi.id), {}, { preserveState: true })}
                                                                            className="text-xs bg-green-500 hover:bg-green-600 text-white font-medium py-0.5 px-2 rounded"
                                                                        >
                                                                            Setuju
                                                                        </button>
                                                                        <button
                                                                            onClick={() => router.post(route('presensi.rejectLembur', presensi.id), {}, { preserveState: true })}
                                                                            className="text-xs bg-red-500 hover:bg-red-600 text-white font-medium py-0.5 px-2 rounded"
                                                                        >
                                                                            Tolak
                                                                        </button>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <div>
                                                                <span className="text-sm text-gray-400">{presensi.tipe_presensi === 'kantor' ? 'Kehadiran Kantor' : 'Reguler'}</span>
                                                                {presensi.tipe_presensi === 'kantor' && (
                                                                    <span className="mt-1 block text-[10px] font-semibold text-slate-500">Tanpa jadwal mengajar</span>
                                                                )}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {auth.user.email === 'admin@yayasan.com' ? (
                                                            <select 
                                                                className={`text-xs font-semibold rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase ${
                                                                    presensi.status === 'hadir' ? 'bg-green-50 text-green-700' :
                                                                    presensi.status === 'telat' ? 'bg-amber-50 text-amber-700' :
                                                                    'bg-red-50 text-red-700'
                                                                }`}
                                                                value={presensi.status}
                                                                onChange={(e) => {
                                                                    if(confirm('Ubah status absensi ini?')) {
                                                                        import('@inertiajs/react').then(({ router }) => {
                                                                            router.put(route('presensi.update', presensi.id), { status: e.target.value });
                                                                        });
                                                                    }
                                                                }}
                                                            >
                                                                <option value="hadir">HADIR</option>
                                                                <option value="telat">TELAT</option>
                                                                <option value="alpa">ALPA</option>
                                                            </select>
                                                        ) : (
                                                            <span className={`px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full uppercase ${
                                                                presensi.status === 'hadir' ? 'bg-green-100 text-green-800' :
                                                                presensi.status === 'telat' ? 'bg-amber-100 text-amber-800' :
                                                                'bg-red-100 text-red-800'
                                                            }`}>
                                                                {presensi.status}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right">
                                                        <div className="text-sm text-gray-900">{presensi.jarak_masuk_meter ? `${presensi.jarak_masuk_meter}m` : '-'} (Masuk)</div>
                                                        <div className="text-sm text-gray-500">{presensi.jarak_keluar_meter ? `${presensi.jarak_keluar_meter}m` : '-'} (Keluar)</div>
                                                        {presensi.lokasi_perlu_review && (
                                                            <span className="mt-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">
                                                                Perlu review GPS
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="7" className="px-6 py-12 text-center">
                                                    <div className="flex flex-col items-center">
                                                        <svg className="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        <p className="text-gray-500 text-lg">Belum ada riwayat absensi.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

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
