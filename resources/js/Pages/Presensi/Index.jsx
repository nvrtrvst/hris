import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
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
    const [confirmStatus, setConfirmStatus] = React.useState(null);
    const [persentaseBayar, setPersentaseBayar] = React.useState(100);
    const [auditModal, setAuditModal] = React.useState({ show: false, loading: false, data: [], presensi: null });
    const [auditPegawai, setAuditPegawai] = React.useState('');
    const [reviewModal, setReviewModal] = React.useState({ show: false, loading: false, data: null });

    const applyFilter = () => {
        router.get(route('presensi.index'), {
            start_date: startDate, end_date: endDate, unit_id: unitId,
            lembur_filter: lemburFilter, lokasi_filter: lokasiFilter, suspicious_filter: suspiciousFilter,
        }, { preserveState: true });
    };

    React.useEffect(() => {
        if (confirmStatus) setPersentaseBayar(confirmStatus.persentase_bayar_jam ?? 100);
    }, [confirmStatus]);

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

    const openAudit = (p) => {
        setAuditPegawai(p.pegawai?.nama_lengkap || '');
        setAuditModal({ show: true, loading: true, data: [], presensi: null });
        fetch(route('presensi.audit', p.id))
            .then(r => r.json())
            .then(res => setAuditModal({ show: true, loading: false, data: res.audits || [], presensi: res.presensi || null }))
            .catch(() => setAuditModal({ show: true, loading: false, data: [], presensi: null }));
    };

    const openReview = (p) => {
        setReviewModal({ show: true, loading: true, data: null });
        fetch(route('presensi.review', p.id))
            .then(r => r.json())
            .then(res => setReviewModal({ show: true, loading: false, data: res.presensi || null }))
            .catch(() => setReviewModal({ show: true, loading: false, data: null }));
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

                    <div className="card p-6">

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
                                                <select className="select-field text-xs h-9 w-full md:w-auto md:min-w-[140px]" value={unitId} onChange={e => setUnitId(e.target.value)}>
                                                    <option value="">Semua Unit</option>
                                                    {units?.map(u => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                                </select>
                                            )}
                                            <select className="select-field text-xs h-9 w-full md:w-auto md:min-w-[140px]" value={lemburFilter} onChange={e => setLemburFilter(e.target.value)}>
                                                <option value="">Semua Status</option>
                                                <option value="lembur_semua">Semua Lembur</option>
                                                <option value="lembur_pending">Lembur Pending</option>
                                                <option value="lembur_disetujui">Lembur Disetujui</option>
                                                <option value="lembur_ditolak">Lembur Ditolak</option>
                                            </select>
                                            <select className="select-field text-xs h-9 w-full md:w-auto md:min-w-[140px]" value={lokasiFilter} onChange={e => setLokasiFilter(e.target.value)}>
                                                <option value="">Semua Lokasi</option>
                                                <option value="review_semua">Perlu Review (Semua)</option>
                                                <option value="perlu_review">Perlu Review GPS</option>
                                                <option value="pulang_awal">Pulang Awal</option>
                                            </select>
                                            <select className="select-field text-xs h-9 w-full md:w-auto md:min-w-[140px]" value={suspiciousFilter} onChange={e => setSuspiciousFilter(e.target.value)}>
                                                <option value="">Semua GPS</option>
                                                <option value="1">Posisi Mencurigakan</option>
                                            </select>
                                        </>
                                    )}
                                    <input type="date" className="input-field text-xs h-9 w-full md:w-auto" value={startDate} onChange={e => setStartDate(e.target.value)} />
                                    <span className="text-text-secondary text-xs hidden md:inline">-</span>
                                    <input type="date" className="input-field text-xs h-9 w-full md:w-auto" value={endDate} onChange={e => setEndDate(e.target.value)} />
                                    <button onClick={applyFilter} className="btn-primary btn-sm w-full md:w-auto">
                                        <svg className="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                        Filter
                                    </button>
                                    {pegawai && (
                                        <Link href={route('presensi.create')}
                                            className="btn-primary"
                                        >
                                            Absen Sekarang
                                        </Link>
                                    )}
                                </div>
                            </div>

                            {/* ─────── ADMIN: Table ─────── */}
                            {isAdmin ? (
                                <div className="card-table">
                                    <div className="overflow-x-auto rounded-card border border-border">
                                    <table className="min-w-full divide-y divide-border">
                                        <thead className="bg-surface">
                                            <tr>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Pegawai & Unit</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Tanggal</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Jadwal</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Jam Masuk</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Jam Keluar</th>
                                                <th className="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Foto</th>
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
                                                        {p.jadwal ? (
                                                            <div className="text-xs leading-tight">
                                                                <div className="font-semibold text-primary">{p.jadwal.mata_pelajaran?.nama || '-'}</div>
                                                                 <div className="text-text-secondary">{p.jadwal.kelas_label || '-'}</div>
                                                                <div className="text-text-secondary">{p.jadwal.jam_mulai?.substring(0, 5)}-{p.jadwal.jam_selesai?.substring(0, 5)}</div>
                                                            </div>
                                                        ) : p.is_lembur ? (
                                                            <span className="text-xs font-semibold text-amber-600 uppercase">Lembur</span>
                                                        ) : <span className="text-xs text-text-secondary">-</span>}
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
                                                        <div className="flex gap-1.5">
                                                            {p.foto_masuk_url ? (
                                                                <a href={p.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="block w-10 h-10 rounded-lg overflow-hidden border border-border hover:ring-2 hover:ring-primary transition-shadow" title="Foto Masuk">
                                                                    <img src={p.foto_masuk_url} alt="Masuk" className="w-full h-full object-cover" loading="lazy" />
                                                                </a>
                                                            ) : p.foto_masuk_status ? (
                                                                <span className="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-slate-100 text-[9px] font-semibold uppercase text-slate-400">{p.foto_masuk_status}</span>
                                                            ) : null}
                                                            {p.foto_keluar_url ? (
                                                                <a href={p.foto_keluar_url} target="_blank" rel="noopener noreferrer" className="block w-10 h-10 rounded-lg overflow-hidden border border-border hover:ring-2 hover:ring-primary transition-shadow" title="Foto Keluar">
                                                                    <img src={p.foto_keluar_url} alt="Keluar" className="w-full h-full object-cover" loading="lazy" />
                                                                </a>
                                                            ) : p.foto_keluar_status ? (
                                                                <span className="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-slate-100 text-[9px] font-semibold uppercase text-slate-400">{p.foto_keluar_status}</span>
                                                            ) : null}
                                                        </div>
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
                                                                                className="btn-sm bg-success text-white font-semibold rounded-button hover:bg-green-700"
                                                                            >Setuju</button>
                                                                            <button onClick={() => router.post(route('presensi.rejectLembur', p.id), {}, { preserveState: true })}
                                                                                className="btn-sm bg-danger text-white font-semibold rounded-button hover:bg-red-700"
                                                                            >Tolak</button>
                                                                        </div>
                                                                    )}
                                                                    </div>
                                                        ) : <span className="text-sm text-text-secondary">-</span>}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {auth.permissions?.includes('manage_master_data') ? (
                                                            <select className={`text-xs font-semibold rounded-md shadow-sm uppercase ${
                                                                 p.status === 'hadir' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                                 p.status === 'telat' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                                                                 'bg-rose-50 text-rose-700 border border-rose-200'
                                                             }`} value={p.status}
                                                                  onChange={(e) => setConfirmStatus({ id: p.id, statusLama: p.status, statusBaru: e.target.value })}
                                                             >
                                                                  <option value="hadir">HADIR</option>
                                                                  <option value="telat">TELAT</option>
                                                                  <option value="sakit">SAKIT</option>
                                                                  <option value="izin">IZIN</option>
                                                                  <option value="cuti">CUTI</option>
                                                                  <option value="alpa">ALPA</option>
                                                             </select>
                                                        ) : (
                                                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ${statusStyle(p.status)}`}>{p.status}</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <div>
                                                                <div className="text-primary">{p.jarak_masuk_meter ? `${p.jarak_masuk_meter}m` : '-'} <span className="text-text-secondary text-xs">Masuk</span></div>
                                                                <div className="text-primary">{p.jarak_keluar_meter ? `${p.jarak_keluar_meter}m` : '-'} <span className="text-text-secondary text-xs">Keluar</span></div>
                                                            </div>
                                                            {isAdmin && (
                                                                <>
                                                                    {(p.lokasi_perlu_review || p.posisi_mencurigakan || p.motion_suspect) && (
                                                                        <button onClick={() => openReview(p)} className="p-1.5 rounded-button text-warning hover:text-amber-600 hover:bg-amber-50 transition-colors" title="Detail Review anti-spoof">
                                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                                        </button>
                                                                    )}
                                                                    <button onClick={() => openAudit(p)} className="p-1.5 rounded-button text-text-secondary hover:text-primary hover:bg-surface transition-colors" title="Riwayat perubahan">
                                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                    </button>
                                                                </>
                                                            )}
                                                        </div>
                                                        {p.lokasi_perlu_review && <div className="mt-1 text-[10px] font-bold text-danger bg-rose-50 inline-block px-2 py-0.5 rounded">Perlu Review</div>}
                                                        {p.posisi_mencurigakan && <div className="mt-1 text-[10px] font-bold text-warning bg-amber-50 inline-block px-2 py-0.5 rounded">Posisi Mencurigakan</div>}
                                                    </td>
                                                </tr>
                                            ))}
                                            {presensis.data.length === 0 && (
                                                <tr><td colSpan="9" className="px-6 py-12 text-center text-text-secondary">Belum ada data presensi.</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                    </div>
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

                            <Modal show={auditModal.show} onClose={() => setAuditModal({ show: false, loading: false, data: [], presensi: null })} maxWidth="lg">
                                <div className="p-6">
                                    <div className="flex items-center justify-between mb-6">
                                        <div className="flex items-center gap-3">
                                            <div className="w-9 h-9 rounded-full bg-surface flex items-center justify-center">
                                                <svg className="w-4.5 h-4.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-bold text-primary">Riwayat Perubahan</h3>
                                                {auditPegawai && <p className="text-sm text-text-secondary mt-0.5">{auditPegawai}</p>}
                                            </div>
                                        </div>
                                        <button onClick={() => setAuditModal({ show: false, loading: false, data: [], presensi: null })} className="p-1.5 rounded-button text-text-secondary hover:text-primary hover:bg-surface transition-colors">
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    {auditModal.presensi && (auditModal.presensi.foto_masuk_url || auditModal.presensi.foto_keluar_url) && (
                                        <div className="bg-surface rounded-lg p-4 mb-5">
                                            <p className="text-xs font-bold uppercase tracking-wide text-text-secondary mb-2">Bukti Foto</p>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                {[{ label: 'Foto Masuk', url: auditModal.presensi.foto_masuk_url, status: auditModal.presensi.foto_masuk_status, error: auditModal.presensi.foto_masuk_error, tone: 'bg-emerald-100 text-emerald-700' }, { label: 'Foto Keluar', url: auditModal.presensi.foto_keluar_url, status: auditModal.presensi.foto_keluar_status, error: auditModal.presensi.foto_keluar_error, tone: 'bg-rose-100 text-rose-700' }].map((f, i) => (
                                                    f.url && (
                                                        <div key={i}>
                                                            <a href={f.url} target="_blank" rel="noopener noreferrer" className="block rounded-lg overflow-hidden border border-border hover:ring-2 hover:ring-primary transition-shadow" title={`${f.label} — buka di tab baru`}>
                                                                <img src={f.url} alt={f.label} className="w-full aspect-[3/4] object-cover" loading="lazy" />
                                                            </a>
                                                            <div className="flex items-center justify-between mt-1.5 px-0.5">
                                                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${f.tone}`}>{f.label}</span>
                                                                {f.status && f.status !== 'success' && (
                                                                    <span className="text-[10px] font-semibold text-warning">{f.status}{f.error ? ` — ${f.error}` : ''}</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    )
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {auditModal.loading ? (
                                        <div className="space-y-4 py-4">
                                            {[1,2,3].map(i => (
                                                <div key={i} className="flex gap-4 animate-pulse">
                                                    <div className="w-2 h-2 mt-2 rounded-full bg-border flex-shrink-0"/>
                                                    <div className="flex-1 space-y-2">
                                                        <div className="h-3 bg-surface rounded w-24"/>
                                                        <div className="h-4 bg-surface rounded w-40"/>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : auditModal.data.length === 0 ? (
                                        <div className="text-center py-12">
                                            <svg className="w-12 h-12 mx-auto text-border mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <p className="text-sm text-text-secondary">Belum ada perubahan.</p>
                                        </div>
                                    ) : (
                                        <div className="relative">
                                            <div className="absolute left-[7px] top-2 bottom-2 w-0.5 bg-border/60"/>
                                            <div className="space-y-5">
                                                {auditModal.data.map((a, i) => {
                                                    const isStatus = a.field === 'status';
                                                    const aksiLabel = a.aksi === 'approve_lembur' ? 'Setujui Lembur'
                                                        : a.aksi === 'reject_lembur' ? 'Tolak Lembur'
                                                        : a.aksi === 'ubah_status' ? 'Ubah Status'
                                                        : a.aksi;
                                                    const aksiIcon = a.aksi === 'approve_lembur' ? 'check'
                                                        : a.aksi === 'reject_lembur' ? 'x'
                                                        : 'edit';
                                                    return (
                                                        <div key={a.id} className="flex gap-4">
                                                            <div className={`w-4 h-4 rounded-full border-2 mt-0.5 flex items-center justify-center flex-shrink-0 z-10 ${
                                                                a.aksi === 'approve_lembur' ? 'bg-emerald-50 border-emerald-400' :
                                                                a.aksi === 'reject_lembur' ? 'bg-rose-50 border-rose-400' :
                                                                'bg-amber-50 border-amber-300'
                                                            }`}>
                                                                <div className={`w-1.5 h-1.5 rounded-full ${
                                                                    a.aksi === 'approve_lembur' ? 'bg-emerald-500' :
                                                                    a.aksi === 'reject_lembur' ? 'bg-rose-500' :
                                                                    'bg-amber-500'
                                                                }`}/>
                                                            </div>
                                                            <div className="flex-1 min-w-0 pb-1">
                                                                <div className="flex items-center gap-2 mb-1">
                                                                    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold uppercase ${
                                                                        a.aksi === 'approve_lembur' ? 'bg-emerald-50 text-emerald-700' :
                                                                        a.aksi === 'reject_lembur' ? 'bg-rose-50 text-rose-700' :
                                                                        'bg-amber-50 text-amber-700'
                                                                    }`}>{aksiLabel}</span>
                                                                    <span className="text-[11px] text-text-secondary">{format(new Date(a.created_at), 'd MMM HH:mm', { locale: id })}</span>
                                                                </div>
                                                                {isStatus && (
                                                                    <div className="flex items-center gap-2 mt-1.5">
                                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-bold border ${
                                                                            a.nilai_lama === 'hadir' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                            a.nilai_lama === 'telat' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                                                            'bg-rose-50 text-rose-700 border-rose-200'
                                                                        }`}>{a.nilai_lama?.toUpperCase() || '-'}</span>
                                                                        <svg className="w-4 h-4 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                                                        </svg>
                                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-bold border ${
                                                                            a.nilai_baru === 'hadir' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                            a.nilai_baru === 'telat' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                                                            'bg-rose-50 text-rose-700 border-rose-200'
                                                                        }`}>{a.nilai_baru?.toUpperCase() || '-'}</span>
                                                                    </div>
                                                                )}
                                                                <div className="flex items-center gap-1.5 mt-1">
                                                                    <svg className="w-3 h-3 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                                    </svg>
                                                                    <span className="text-[11px] text-text-secondary">{a.user?.name || '-'}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </Modal>

                            <Modal show={reviewModal.show} onClose={() => setReviewModal({ show: false, loading: false, data: null })} maxWidth="lg">
                                <div className="p-6">
                                    <div className="flex items-center justify-between mb-6">
                                        <div className="flex items-center gap-3">
                                            <div className="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center">
                                                <svg className="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-bold text-primary">Detail Review Anti-Spoof</h3>
                                                {reviewModal.data && <p className="text-sm text-text-secondary mt-0.5">{reviewModal.data.pegawai_nama} • {reviewModal.data.tanggal}</p>}
                                            </div>
                                        </div>
                                        <button onClick={() => setReviewModal({ show: false, loading: false, data: null })} className="p-1.5 rounded-button text-text-secondary hover:text-primary hover:bg-surface transition-colors">
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    {reviewModal.loading ? (
                                        <div className="space-y-4 py-4">
                                            {[1,2,3].map(i => (
                                                <div key={i} className="flex gap-4 animate-pulse">
                                                    <div className="flex-1 space-y-2">
                                                        <div className="h-3 bg-surface rounded w-24"/>
                                                        <div className="h-4 bg-surface rounded w-40"/>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : !reviewModal.data ? (
                                        <div className="text-center py-12">
                                            <p className="text-sm text-text-secondary">Data detail tidak tersedia.</p>
                                        </div>
                                    ) : (() => {
                                        const d = reviewModal.data;
                                        const flags = [
                                            d.lokasi_perlu_review && { label: 'Lokasi Perlu Review', cls: 'bg-rose-50 text-rose-700 border-rose-200' },
                                            d.posisi_mencurigakan && { label: 'Posisi Mencurigakan', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
                                            d.motion_suspect && { label: 'Motion Suspect (emulator)', cls: 'bg-purple-50 text-purple-700 border-purple-200' },
                                        ].filter(Boolean);
                                        return (
                                            <div className="space-y-5">
                                                {/* Flags */}
                                                <div className="flex flex-wrap gap-2">
                                                    {flags.length ? flags.map((f, i) => (
                                                        <span key={i} className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border ${f.cls}`}>{f.label}</span>
                                                    )) : <span className="text-xs text-text-secondary">Tidak ada flag aktif.</span>}
                                                </div>

                                                {/* Bukti Foto (overlay burn-in) */}
                                                <div className="bg-surface rounded-lg p-4">
                                                    <p className="text-xs font-bold uppercase tracking-wide text-text-secondary mb-2">Bukti Foto (overlay nama • waktu • lokasi)</p>
                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                        {[{ label: 'Foto Masuk', url: d.foto_masuk_url, status: d.foto_masuk_status, error: d.foto_masuk_error, tone: 'bg-emerald-100 text-emerald-700' }, { label: 'Foto Keluar', url: d.foto_keluar_url, status: d.foto_keluar_status, error: d.foto_keluar_error, tone: 'bg-rose-100 text-rose-700' }].map((f, i) => (
                                                            <div key={i}>
                                                                {f.url ? (
                                                                    <a href={f.url} target="_blank" rel="noopener noreferrer" className="block rounded-lg overflow-hidden border border-border hover:ring-2 hover:ring-primary transition-shadow" title={`${f.label} — buka di tab baru`}>
                                                                        <img src={f.url} alt={f.label} className="w-full aspect-[3/4] object-cover" loading="lazy" />
                                                                    </a>
                                                                ) : (
                                                                    <div className="flex items-center justify-center w-full aspect-[3/4] rounded-lg border border-dashed border-border bg-white">
                                                                        <span className="text-xs text-text-secondary">{f.label} tidak tersedia</span>
                                                                    </div>
                                                                )}
                                                                <div className="flex items-center justify-between mt-1.5 px-0.5">
                                                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${f.tone}`}>{f.label}</span>
                                                                    {f.status && f.status !== 'success' && (
                                                                        <span className="text-[10px] font-semibold text-warning">{f.status}{f.error ? ` — ${f.error}` : ''}</span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                    <p className="mt-2 text-[11px] leading-relaxed text-text-secondary">Foto di-burn-in nama, unit, waktu (HH:mm:ss) dan koordinat saat pengambilan. EXIF GPS (jika tersedia) juga dibandingkan di bawah.</p>
                                                </div>

                                                {/* GPS Report */}
                                                <div className="bg-surface rounded-lg p-4">
                                                    <p className="text-xs font-bold uppercase tracking-wide text-text-secondary mb-2">Data GPS</p>
                                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                                        <div><span className="text-text-secondary">Akurasi:</span> <b className="text-primary">{d.akurasi_masuk ?? '-'}m</b></div>
                                                        <div><span className="text-text-secondary">Kecepatan:</span> <b className="text-primary">{d.kecepatan_masuk ?? '-'} m/s</b></div>
                                                        <div className="col-span-2"><span className="text-text-secondary">Koordinat:</span> <b className="text-primary font-mono">{d.latitude_masuk ?? '-'}, {d.longitude_masuk ?? '-'}</b></div>
                                                        {d.captured_at && <div className="col-span-2"><span className="text-text-secondary">Waktu capture:</span> <b className="text-primary">{new Date(d.captured_at).toLocaleString('id-ID')}</b></div>}
                                                    </div>
                                                </div>

                                                {/* Trajectory */}
                                                <div className="bg-surface rounded-lg p-4">
                                                    <p className="text-xs font-bold uppercase tracking-wide text-text-secondary mb-2">Trajectory (Awal → A → B)</p>
                                                    {d.trajectory?.length ? (
                                                        <div className="space-y-2">
                                                            {d.trajectory.map((t, i) => (
                                                                <div key={i} className="flex items-center justify-between text-xs">
                                                                    <span className="font-bold uppercase text-primary">{t.label || '?'}</span>
                                                                    <span className="font-mono text-text-secondary">{t.lat}, {t.lng}</span>
                                                                    <span className="text-text-secondary">akurasi {t.accuracy ?? '-'}m</span>
                                                                    <span className="text-text-secondary">{t.captured_at ? new Date(t.captured_at).toLocaleTimeString('id-ID') : '-'}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : <p className="text-xs text-text-secondary">Tidak ada data trajectory (client lama / tidak didukung).</p>}
                                                </div>

                                                {/* Motion */}
                                                <div className="bg-surface rounded-lg p-4">
                                                    <p className="text-xs font-bold uppercase tracking-wide text-text-secondary mb-2">Motion (Accelerometer)</p>
                                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                                        <div><span className="text-text-secondary">Sample count:</span> <b className="text-primary">{d.motion_sample_count}</b></div>
                                                        <div><span className="text-text-secondary">Varians:</span> <b className="text-primary">{d.motion_variance ?? '-'}</b></div>
                                                    </div>
                                                    {d.motion_suspect && <p className="mt-2 text-xs text-purple-600 bg-purple-50 rounded-lg px-3 py-2">Varians sangat rendah / nol — indikasi emulator atau device virtual.</p>}
                                                </div>

                                                {/* IP Geo */}
                                                {d.ip_geo && (
                                                    <div className="bg-surface rounded-lg p-4">
                                                        <p className="text-xs font-bold uppercase tracking-wide text-text-secondary mb-2">IP Geolocation</p>
                                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                                            <div><span className="text-text-secondary">IP:</span> <b className="text-primary font-mono">{d.ip_geo.ip}</b></div>
                                                            <div><span className="text-text-secondary">Lokasi IP:</span> <b className="text-primary">{d.ip_geo.city ? `${d.ip_geo.city}, ${d.ip_geo.country}` : '-'}</b></div>
                                                            <div className="col-span-2"><span className="text-text-secondary">Jarak GPS vs IP:</span> <b className={d.ip_geo.distance_km > 500 ? 'text-danger' : 'text-primary'}>{d.ip_geo.distance_km ?? '-'} km</b></div>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* EXIF */}
                                                {d.exif_meta && (
                                                    <div className="bg-surface rounded-lg p-4">
                                                        <p className="text-xs font-bold uppercase tracking-wide text-text-secondary mb-2">EXIF Foto</p>
                                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                                            <div><span className="text-text-secondary">EXIF GPS:</span> <b className="text-primary font-mono">{d.exif_meta.gps_lat ? `${d.exif_meta.gps_lat.toFixed(5)}, ${d.exif_meta.gps_lng?.toFixed(5)}` : '-'}</b></div>
                                                            <div><span className="text-text-secondary">DateTimeOriginal:</span> <b className="text-primary">{d.exif_meta.datetime_original || '-'}</b></div>
                                                            {d.exif_meta.mismatch && (
                                                                <div className="col-span-2"><span className="text-danger font-bold">⚠ Mismatch {d.exif_meta.mismatch_distance_m}m dengan koordinat reported</span></div>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })()}
                                </div>
                            </Modal>

                            <Modal show={confirmStatus !== null} onClose={() => setConfirmStatus(null)} maxWidth="sm">
                                <div className="p-6">
                                    <div className="flex items-center gap-3 mb-4">
                                        <div className="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                                            <svg className="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-bold text-primary">Konfirmasi Ubah Status</h3>
                                            <p className="text-sm text-text-secondary mt-0.5">Pastikan perubahan status presensi sudah sesuai.</p>
                                        </div>
                                    </div>
                                            <div className="bg-surface rounded-lg p-4 mb-6">
                                                <div className="flex items-center justify-between">
                                                    <div className="text-center">
                                                        <div className="text-xs text-text-secondary uppercase tracking-wide mb-1">Status Saat Ini</div>
                                                        <span className={`inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold border-2 ${
                                                            confirmStatus?.statusLama === 'hadir' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                            confirmStatus?.statusLama === 'telat' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                                            'bg-rose-50 text-rose-700 border-rose-200'
                                                        }`}>{confirmStatus?.statusLama?.toUpperCase()}</span>
                                                    </div>
                                                    <svg className="w-6 h-6 text-text-secondary mx-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                                    </svg>
                                                    <div className="text-center">
                                                        <div className="text-xs text-text-secondary uppercase tracking-wide mb-1">Akan Diubah</div>
                                                        <span className={`inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold border-2 ${
                                                            confirmStatus?.statusBaru === 'hadir' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                            confirmStatus?.statusBaru === 'telat' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                                            'bg-rose-50 text-rose-700 border-rose-200'
                                                        }`}>{confirmStatus?.statusBaru?.toUpperCase()}</span>
                                                    </div>
                                                </div>
                                                {confirmStatus?.statusBaru === 'sakit' && (
                                                    <div className="mt-4 pt-4 border-t border-border">
                                                        <label className="form-label text-xs">Persentase Bayar Jam Sakit</label>
                                                        <div className="flex gap-2 mt-1">
                                                            {[0, 50, 100].map(v => (
                                                                <button key={v} type="button"
                                                                    onClick={() => setPersentaseBayar(v)}
                                                                    className={`px-4 py-2 rounded-button text-sm font-semibold border transition-colors ${
                                                                        persentaseBayar === v
                                                                        ? 'bg-primary text-white border-primary'
                                                                        : 'bg-white text-text-secondary border-border hover:bg-surface'
                                                                    }`}
                                                                >{v}%</button>
                                                            ))}
                                                        </div>
                                                        <p className="form-hint mt-1">Pilih persentase gaji yang tetap dibayarkan untuk hari sakit.</p>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex justify-end gap-3">
                                                <button onClick={() => setConfirmStatus(null)} className="btn-secondary btn-sm min-w-[80px]">Batal</button>
                                                <button onClick={() => {
                                                    router.put(route('presensi.update', confirmStatus.id), { status: confirmStatus.statusBaru, persentase_bayar_jam: persentaseBayar }, { preserveState: false });
                                                    setConfirmStatus(null);
                                                }} className="btn-primary btn-sm min-w-[100px]">Ya, Ubah</button>
                                            </div>
                                </div>
                            </Modal>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
