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
        <div style={{ padding: '20px', color: 'red', background: '#ffebee' }}>
          <h2>Something went wrong in Dashboard.</h2>
          <details style={{ whiteSpace: 'pre-wrap' }}>
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

function DashboardContent({ auth, roleType, stats, trends, kontrakBerakhir }) {
    const [chartData, setChartData] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

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
                <div className="flex justify-between items-end">
                    <div>
                        <h2 className="font-semibold text-2xl text-primary leading-tight">Dashboard {roleType}</h2>
                        <p className="text-text-secondary text-sm mt-1">{todayString}</p>
                    </div>
                </div>
            }
        >
            <Head title={`Dashboard ${roleType}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div className="bg-white rounded-2xl p-8 shadow-sm border border-border flex flex-col justify-center relative overflow-hidden">
                            <p className="text-text-secondary font-medium mb-1">Total Biaya Payroll Bulan Ini</p>
                            <h3 className="text-4xl font-bold text-primary mb-2">
                                {stats?.pengeluaran_gaji > 0 ? formatRupiah(stats.pengeluaran_gaji) : 'Rp 0'}
                            </h3>
                            {stats?.is_estimasi_payroll ? (
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-warning w-max">
                                    Estimasi Sementara
                                </span>
                            ) : (
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-success w-max">
                                    Sudah Diproses
                                </span>
                            )}
                        </div>

                        <div className="bg-white rounded-2xl p-8 shadow-sm border border-border flex flex-col justify-center relative overflow-hidden">
                            <p className="text-text-secondary font-medium mb-1">Tingkat Kehadiran Hari Ini</p>
                            <div className="flex items-baseline space-x-3 mb-2">
                                <h3 className="text-4xl font-bold text-success">{stats?.hadir_percentage}%</h3>
                                <p className="text-text-secondary text-lg">({stats?.hadir_hari_ini_count} dari {stats?.pegawai_dijadwalkan})</p>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div className="bg-white rounded-xl p-5 shadow-sm border border-border">
                            <p className="text-sm font-medium text-text-secondary">Pegawai Aktif</p>
                            <p className="text-2xl font-bold text-primary mt-1">{stats?.total_pegawai}</p>
                        </div>
                        <div className="bg-white rounded-xl p-5 shadow-sm border border-border">
                            <p className="text-sm font-medium text-text-secondary">Unit Sekolah</p>
                            <p className="text-2xl font-bold text-primary mt-1">{stats?.total_unit}</p>
                        </div>
                        <div className="bg-white rounded-xl p-5 shadow-sm border border-border">
                            <p className="text-sm font-medium text-text-secondary">Kontrak Berakhir</p>
                            <p className={`text-2xl font-bold mt-1 ${stats?.kontrak_berakhir_count > 0 ? 'text-warning' : 'text-primary'}`}>
                                {stats?.kontrak_berakhir_count}
                            </p>
                        </div>
                        <div className="bg-white rounded-xl p-5 shadow-sm border border-border">
                            <p className="text-sm font-medium text-text-secondary">Pengajuan Pending</p>
                            <p className="text-2xl font-bold text-primary mt-1">0</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="bg-white rounded-2xl p-6 shadow-sm border border-border">
                            <h3 className="text-lg font-bold text-primary mb-4">Tren Kehadiran Mingguan</h3>
                            <div className="h-64 w-full">
                                {isLoading ? (
                                    <p>Loading...</p>
                                ) : chartData.length === 0 ? (
                                    <p>No data</p>
                                ) : (
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={chartData} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
                                            <XAxis dataKey="day" />
                                            <YAxis />
                                            <Tooltip />
                                            <Line type="monotone" dataKey="hadir" stroke="#0F3D3E" strokeWidth={3} dot={{r: 4}} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                )}
                            </div>
                        </div>

                        <div className="bg-white rounded-2xl p-6 shadow-sm border border-border">
                            <h3 className="text-lg font-bold text-primary mb-4">Kontrak Berakhir (30 Hari)</h3>
                            {kontrakBerakhir && kontrakBerakhir.length > 0 ? (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <tbody>
                                        {kontrakBerakhir.slice(0, 5).map((pegawai) => (
                                            <tr key={pegawai.id}>
                                                <td className="px-3 py-4">{pegawai.nama_lengkap}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p>Tidak ada kontrak berakhir.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
