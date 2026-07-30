import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { CalendarCheck, UserCheck, Receipt, FileText, ChevronRight, Clock, ShieldCheck } from 'lucide-react';

export default function DashboardSelfService({ auth, stats }) {
    const todayString = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    const hadirBulanIni = stats?.hadir_bulan_ini || 0;
    const jadwalBulanIni = stats?.jadwal_bulan_ini || 22;
    const persentaseHadir = Math.round((hadirBulanIni / jadwalBulanIni) * 100);

    const statCards = [
        { label: 'Kehadiran Bulan Ini', value: `${hadirBulanIni} / ${jadwalBulanIni}`, sub: `${persentaseHadir}%`, icon: CalendarCheck, progress: persentaseHadir },
        { label: 'Status Kepegawaian', value: 'Aktif', sub: stats?.kontrak_hingga || '-', icon: ShieldCheck, kontrak: true },
        { label: 'Slip Gaji Terakhir', value: stats?.gaji_terakhir ? 'Tersedia' : 'Belum Ada', sub: stats?.gaji_terakhir || 'Data belum final', icon: Receipt },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="page-header mb-0">
                    <div>
                        <h1 className="page-title">Beranda Pegawai</h1>
                        <p className="page-subtitle">{todayString}</p>
                    </div>
                </div>
            }
        >
            <Head title="Beranda Pegawai" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* Welcome Banner */}
                    <div className="bg-gradient-to-r from-primary to-primary-600 rounded-xl p-6 text-white shadow-card">
                        <h3 className="text-xl font-bold">Halo, {auth.user.name}</h3>
                        <p className="text-white/70 mt-1">Selamat datang di portal self-service HRIS Yayasan.</p>
                    </div>

                    {/* Stats Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {statCards.map((card, i) => (
                            <div key={i} className="card p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h4 className="text-xs font-semibold text-text-muted uppercase tracking-wider">{card.label}</h4>
                                    <div className="stat-icon bg-primary/5">
                                        <card.icon className="w-5 h-5 text-primary" />
                                    </div>
                                </div>
                                {card.kontrak ? (
                                    <div>
                                        <span className="badge-success gap-1.5">
                                            <UserCheck className="w-4 h-4" /> {card.value}
                                        </span>
                                        <p className="text-xs text-text-muted mt-3">Kontrak hingga: {card.sub}</p>
                                    </div>
                                ) : (
                                    <div>
                                        <span className="text-2xl font-bold text-text-primary">{card.value}</span>
                                        <p className="text-xs text-text-muted mt-1">{card.sub}</p>
                                    </div>
                                )}
                                {card.progress !== undefined && (
                                    <div className="w-full bg-border rounded-full h-2 mt-4">
                                        <div
                                            className={`h-2 rounded-full transition-all duration-500 ${persentaseHadir >= 90 ? 'bg-success' : 'bg-warning'}`}
                                            style={{ width: `${Math.min(card.progress, 100)}%` }}
                                        />
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Quick Actions */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <Link href={route('presensi.index')} className="card-hover p-5 flex items-center justify-between group">
                            <div className="flex items-center gap-4">
                                <div className="stat-icon bg-primary/5">
                                    <Clock className="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <h4 className="font-semibold text-text-primary group-hover:text-primary transition-colors">Presensi Saya</h4>
                                    <p className="text-sm text-text-muted">Riwayat absensi harian</p>
                                </div>
                            </div>
                            <ChevronRight className="w-5 h-5 text-text-muted group-hover:text-primary transition-colors" />
                        </Link>
                        <Link href={route('jadwal.index')} className="card-hover p-5 flex items-center justify-between group">
                            <div className="flex items-center gap-4">
                                <div className="stat-icon bg-accent/10">
                                    <CalendarCheck className="w-5 h-5 text-accent" />
                                </div>
                                <div>
                                    <h4 className="font-semibold text-text-primary group-hover:text-primary transition-colors">Jadwal Pribadi</h4>
                                    <p className="text-sm text-text-muted">Lihat jadwal mengajar</p>
                                </div>
                            </div>
                            <ChevronRight className="w-5 h-5 text-text-muted group-hover:text-primary transition-colors" />
                        </Link>
                        <Link href={route('pengajuan-izin.index')} className="card-hover p-5 flex items-center justify-between group">
                            <div className="flex items-center gap-4">
                                <div className="stat-icon bg-success/5">
                                    <FileText className="w-5 h-5 text-success" />
                                </div>
                                <div>
                                    <h4 className="font-semibold text-text-primary group-hover:text-primary transition-colors">Pengajuan Izin</h4>
                                    <p className="text-sm text-text-muted">Cuti, izin, sakit</p>
                                </div>
                            </div>
                            <ChevronRight className="w-5 h-5 text-text-muted group-hover:text-primary transition-colors" />
                        </Link>
                        <Link href={route('penggajian.index')} className="card-hover p-5 flex items-center justify-between group">
                            <div className="flex items-center gap-4">
                                <div className="stat-icon bg-warning/10">
                                    <Receipt className="w-5 h-5 text-warning" />
                                </div>
                                <div>
                                    <h4 className="font-semibold text-text-primary group-hover:text-primary transition-colors">Slip Gaji</h4>
                                    <p className="text-sm text-text-muted">Lihat riwayat gaji bulanan</p>
                                </div>
                            </div>
                            <ChevronRight className="w-5 h-5 text-text-muted group-hover:text-primary transition-colors" />
                        </Link>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
