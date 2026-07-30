import React, { useState, useEffect, Component } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null, errorInfo: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true };
  }

  componentDidCatch(error, errorInfo) {
    this.setState({ error, errorInfo });
    console.error("Dashboard Error:", error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="card p-6">
          <h2 className="text-danger font-semibold">Terjadi kesalahan di Dashboard.</h2>
          <details className="mt-2 text-sm text-text-muted whitespace-pre-wrap">
            {this.state.error && this.state.error.toString()}
            <br />
            {this.state.errorInfo && this.state.errorInfo.componentStack}
          </details>
        </div>
      );
    }
    return this.props.children;
  }
}

export default function Dashboard(props) {
    return (
        <ErrorBoundary>
            <DashboardContent {...props} />
        </ErrorBoundary>
    );
}

function DashboardContent({ auth, roleType, stats, trends, kontrakBerakhir, jadwalHariIni, presensiHariIni }) {
    const [chartData, setChartData] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [detailPresensi, setDetailPresensi] = useState(null);

    const presensiMap = React.useMemo(() => {
        const map = {};
        (presensiHariIni || []).forEach(p => {
            const key = p.pegawai_id;
            if (!map[key] || p.jam_masuk > (map[key].jam_masuk || '')) map[key] = p;
        });
        return map;
    }, [presensiHariIni]);

    const presensiByJadwal = React.useMemo(() => {
        const map = {};
        (presensiHariIni || []).forEach(p => {
            if (p.jadwal_id) map[p.jadwal_id] = p;
        });
        return map;
    }, [presensiHariIni]);

    const statusBadge = (status) => {
        const colors = { hadir: 'bg-emerald-50 text-emerald-700 border-emerald-200', telat: 'bg-amber-50 text-amber-700 border-amber-200', sakit: 'bg-purple-50 text-purple-700 border-purple-200', izin: 'bg-blue-50 text-blue-700 border-blue-200', cuti: 'bg-teal-50 text-teal-700 border-teal-200', alpa: 'bg-rose-50 text-rose-700 border-rose-200' };
        return <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full border ${colors[status] || 'bg-gray-50 text-gray-500 border-gray-200'}`}>{status || '−'}</span>;
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (trends && Array.isArray(trends)) {
                setChartData([...trends].reverse());
            } else {
                setChartData([]);
            }
            setIsLoading(false);
        }, 800);
        return () => clearTimeout(timer);
    }, [trends]);

    const formatRupiah = (angka) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    };

    const todayString = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={
                <div className="page-header mb-0">
                    <div>
                        <h1 className="page-title">Dashboard {roleType}</h1>
                        <p className="page-subtitle">{todayString}</p>
                    </div>
                </div>
            }
        >
            <Head title={`Dashboard ${roleType}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* Ringkasan Utama */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="card p-8 flex flex-col justify-center relative overflow-hidden">
                            <p className="text-text-muted text-sm font-medium mb-1">Total Biaya Payroll Bulan Ini</p>
                            <h3 className="text-3xl font-bold text-primary mb-2">
                                {stats?.pengeluaran_gaji > 0 ? formatRupiah(stats.pengeluaran_gaji) : 'Rp 0'}
                            </h3>
                            {stats?.is_estimasi_payroll ? (
                                <span className="badge-warning">Estimasi Sementara</span>
                            ) : (
                                <span className="badge-success">Sudah Diproses</span>
                            )}
                        </div>

                        <div className="card p-8 flex flex-col justify-center relative overflow-hidden">
                            <p className="text-text-muted text-sm font-medium mb-1">Tingkat Kehadiran Hari Ini</p>
                            <div className="flex items-baseline gap-3 mb-2">
                                <h3 className="text-3xl font-bold text-success">{stats?.hadir_percentage}%</h3>
                                <p className="text-text-muted text-base">({stats?.hadir_hari_ini_count} dari {stats?.pegawai_dijadwalkan})</p>
                            </div>
                        </div>
                    </div>

                    {/* Stat Cards */}
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div className="card p-5">
                            <p className="text-xs font-semibold text-text-muted uppercase tracking-wider">Pegawai Aktif</p>
                            <p className="text-2xl font-bold text-primary mt-1">{stats?.total_pegawai}</p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold text-text-muted uppercase tracking-wider">Unit Sekolah</p>
                            <p className="text-2xl font-bold text-primary mt-1">{stats?.total_unit}</p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold text-text-muted uppercase tracking-wider">Kontrak Berakhir</p>
                            <p className={`text-2xl font-bold mt-1 ${stats?.kontrak_berakhir_count > 0 ? 'text-warning' : 'text-primary'}`}>
                                {stats?.kontrak_berakhir_count}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold text-text-muted uppercase tracking-wider">Pengajuan Pending</p>
                            <p className="text-2xl font-bold text-primary mt-1">0</p>
                        </div>
                    </div>

                    {/* Jadwal Hari Ini */}
                    <div className="card p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="section-title flex items-center gap-2">
                                <svg className="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Jadwal Hari Ini — {new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}
                            </h3>
                            <span className="text-xs text-text-muted">{stats?.pegawai_dijadwalkan || 0} guru dijadwalkan</span>
                        </div>
                        {jadwalHariIni && jadwalHariIni.length > 0 ? (
                            <div className="table-wrap">
                                <table className="table-base">
                                    <thead>
                                        <tr>
                                            <th className="w-28">Jam</th>
                                            <th>Mapel</th>
                                            <th>Kelas</th>
                                            <th>Guru</th>
                                            <th>Unit</th>
                                            <th className="w-20 text-center text-[10px]">Ngajar</th>
                                            <th className="w-20 text-center text-[10px]">Harian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {jadwalHariIni.map((j, i) => {
                                            const ngajar = presensiByJadwal[j.id];
                                            const harian = presensiMap[j.pegawai_id];
                                            return (
                                            <tr key={j.id || i}
                                                onClick={() => setDetailPresensi(detailPresensi === j.pegawai_id ? null : j.pegawai_id)}
                                                className="hover:bg-surface transition-colors cursor-pointer"
                                            >
                                                <td className="font-mono text-sm font-semibold text-primary">{j.jam_mulai?.substring(0,5)}-{j.jam_selesai?.substring(0,5)}</td>
                                                <td>
                                                    <span className="text-sm">{j.mata_pelajaran?.nama || j.jenis_jadwal || '-'}</span>
                                                </td>
                                                <td className="text-sm">{j.kelas_label || '-'}</td>
                                                <td className="text-sm font-medium">{j.pegawai?.nama_lengkap || '-'}</td>
                                                <td className="text-xs text-text-muted">{j.unit_sekolah?.singkatan || j.unit_sekolah?.nama || '-'}</td>
                                                <td className="text-center">{ngajar ? statusBadge(ngajar.status) : <span className="text-[10px] text-text-muted">—</span>}</td>
                                                <td className="text-center">{harian ? statusBadge(harian.status) : <span className="text-[10px] text-text-muted">—</span>}</td>
                                            </tr>
                                        )})}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="empty-state py-8">
                                <svg className="w-10 h-10 mx-auto mb-2 text-border" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p className="empty-state-desc">Belum ada jadwal untuk hari ini.</p>
                            </div>
                        )}

                        {detailPresensi && (() => {
                            const pList = presensiHariIni?.filter(pr => pr.pegawai_id === detailPresensi) || [];
                            const nama = pList[0]?.pegawai?.nama_lengkap || 'Guru';
                            if (pList.length === 0) return null;
                            const harian = presensiMap[detailPresensi];
                            const pNgajar = pList.filter(pr => pr.jadwal_id);
                            return (
                                <div className="mt-3 p-4 bg-surface border border-border rounded-card text-sm">
                                    <div className="flex items-center justify-between mb-3">
                                        <h4 className="font-bold text-primary">{nama}</h4>
                                        <button onClick={() => setDetailPresensi(null)} className="text-text-muted hover:text-primary text-xs cursor-pointer">Tutup</button>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="p-3 bg-white border border-border rounded-card">
                                            <p className="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-2">Harian</p>
                                            <div className="grid grid-cols-2 gap-2 text-xs">
                                                <div><span className="text-text-muted">Masuk</span><p className="font-semibold">{harian?.jam_masuk?.substring(0,5) || '—'}</p></div>
                                                <div><span className="text-text-muted">Keluar</span><p className="font-semibold">{harian?.jam_keluar?.substring(0,5) || '—'}</p></div>
                                                <div><span className="text-text-muted">Status</span><p className="mt-0.5">{harian ? statusBadge(harian.status) : '—'}</p></div>
                                                <div><span className="text-text-muted">Jarak</span><p className="font-semibold">{harian?.jarak_meter ? harian.jarak_meter+'m' : '—'}</p></div>
                                            </div>
                                        </div>
                                        <div className="p-3 bg-white border border-border rounded-card">
                                            <p className="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-2">Per Jadwal Mengajar</p>
                                            {pNgajar.length > 0 ? pNgajar.slice(0, 3).map((p, i) => (
                                                <div key={i} className="text-xs mb-1.5 last:mb-0">
                                                    <span className="font-mono text-text-muted">{p.jam_masuk?.substring(0,5)}-{p.jam_keluar?.substring(0,5) || '?'} </span>
                                                    {statusBadge(p.status)}
                                                </div>
                                            )) : <p className="text-xs text-text-muted">Belum ada absen per jadwal</p>}
                                        </div>
                                    </div>
                                    {harian?.lokasi_perlu_review && (
                                        <div className="mt-2 p-2 bg-amber-50 border border-amber-200 rounded text-amber-700 text-[10px] font-medium">Lokasi perlu direview</div>
                                    )}
                                </div>
                            );
                        })()}
                    </div>

                    {/* Charts & Tabel */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="card p-6">
                            <h3 className="section-title">Tren Kehadiran Mingguan</h3>
                            <div className="h-64 w-full">
                                {isLoading ? (
                                    <div className="empty-state">
                                        <p className="text-text-muted">Memuat data…</p>
                                    </div>
                                ) : chartData.length === 0 ? (
                                    <div className="empty-state">
                                        <p className="empty-state-desc">Belum ada data kehadiran.</p>
                                    </div>
                                ) : (
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={chartData} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
                                            <XAxis dataKey="day" />
                                            <YAxis />
                                            <Tooltip />
                                            <Line type="monotone" dataKey="hadir" stroke="#1B4A4A" strokeWidth={3} dot={{r: 4}} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                )}
                            </div>
                        </div>

                        <div className="card p-6">
                            <h3 className="section-title">Kontrak Berakhir (30 Hari)</h3>
                            {kontrakBerakhir && kontrakBerakhir.length > 0 ? (
                                <div className="table-wrap">
                                    <table className="table-base">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Unit</th>
                                                <th>Berakhir</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {kontrakBerakhir.slice(0, 5).map((pegawai) => (
                                                <tr key={pegawai.id}>
                                                    <td className="font-medium">{pegawai.nama_lengkap}</td>
                                                    <td className="text-text-muted">{pegawai.unit_nama || '-'}</td>
                                                    <td className="text-text-muted">{pegawai.kontrak_berakhir || '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="empty-state py-8">
                                    <p className="empty-state-desc">Tidak ada kontrak berakhir.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
