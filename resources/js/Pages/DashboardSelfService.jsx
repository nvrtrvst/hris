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
        { label: 'Kehadiran Bulan Ini', value: `${hadirBulanIni} / ${jadwalBulanIni}`, sub: `${persentaseHadir}%`, icon: CalendarCheck, color: 'text-success', bg: 'bg-success/5', progress: persentaseHadir },
        { label: 'Status Kepegawaian', value: 'Aktif', sub: stats?.kontrak_hingga || '-', icon: ShieldCheck, color: 'text-primary', bg: 'bg-primary/5', kontrak: true },
        { label: 'Slip Gaji Terakhir', value: stats?.gaji_terakhir ? 'Tersedia' : 'Belum Ada', sub: stats?.gaji_terakhir || 'Data belum final', icon: Receipt, color: 'text-accent', bg: 'bg-accent/5' },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-end">
                    <div>
                        <h2 className="font-semibold text-2xl text-primary leading-tight">Beranda Pegawai</h2>
                        <p className="text-text-secondary text-sm mt-1">{todayString}</p>
                    </div>
                </div>
            }
        >
            <Head title="Beranda Pegawai" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    
                    {/* Welcome Banner */}
                    <div className="bg-gradient-to-r from-primary to-primary-light rounded-xl p-6 text-white shadow-sm">
                        <h3 className="text-xl font-bold">Halo, {auth.user.name}</h3>
                        <p className="text-white/70 mt-1">Selamat datang di portal self-service HRIS Yayasan.</p>
                    </div>

                    {/* Stats Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {statCards.map((card, i) => (
                            <div key={i} className="bg-white rounded-xl p-6 shadow-sm border border-border">
                                <div className="flex items-center justify-between mb-4">
                                    <h4 className="text-sm font-semibold text-text-secondary uppercase tracking-wider">{card.label}</h4>
                                    <div className={`w-10 h-10 rounded-lg ${card.bg} flex items-center justify-center`}>
                                        <card.icon className={`w-5 h-5 ${card.color}`} />
                                    </div>
                                </div>
                                {card.kontrak ? (
                                    <div>
                                        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-bold bg-success/10 text-success">
                                            <UserCheck className="w-4 h-4" /> {card.value}
                                        </span>
                                        <p className="text-xs text-text-secondary mt-3">Kontrak hingga: {card.sub}</p>
                                    </div>
                                ) : (
                                    <div>
                                        <span className="text-2xl font-bold text-primary">{card.value}</span>
                                        <p className="text-xs text-text-secondary mt-1">{card.sub}</p>
                                    </div>
                                )}
                                {card.progress !== undefined && (
                                    <div className="w-full bg-border rounded-full h-2 mt-4">
                                        <div className={`h-2 rounded-full transition-all duration-500 ${persentaseHadir >= 90 ? 'bg-success' : 'bg-warning'}`} style={{ width: `${Math.min(card.progress, 100)}%` }}></div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Quick Actions */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <Link href={route('presensi.index')} className="bg-white rounded-xl p-5 shadow-sm border border-border hover:shadow-md transition-all flex items-center justify-between group">
                            <div className="flex items-center gap-4">
                                <div className="w-12 h-12 rounded-xl bg-primary/5 flex items-center justify-center">
                                    <Clock className="w-6 h-6 text-primary" />
                                </div>
                                <div>
                                    <h4 className="font-bold text-primary group-hover:text-primary-light transition-colors">Presensi Saya</h4>
                                    <p className="text-sm text-text-secondary">Riwayat absensi harian</p>
                                </div>
                            </div>
                            <ChevronRight className="w-5 h-5 text-text-secondary group-hover:text-primary transition-colors" />
                        </Link>
                        <Link href={route('jadwal.index')} className="bg-white rounded-xl p-5 shadow-sm border border-border hover:shadow-md transition-all flex items-center justify-between group">
                            <div className="flex items-center gap-4">
                                <div className="w-12 h-12 rounded-xl bg-accent/10 flex items-center justify-center">
                                    <CalendarCheck className="w-6 h-6 text-accent" />
                                </div>
                                <div>
                                    <h4 className="font-bold text-primary group-hover:text-primary-light transition-colors">Jadwal Pribadi</h4>
                                    <p className="text-sm text-text-secondary">Lihat jadwal mengajar</p>
                                </div>
                            </div>
                            <ChevronRight className="w-5 h-5 text-text-secondary group-hover:text-primary transition-colors" />
                        </Link>
                        <Link href={route('pengajuan-izin.index')} className="bg-white rounded-xl p-5 shadow-sm border border-border hover:shadow-md transition-all flex items-center justify-between group">
                            <div className="flex items-center gap-4">
                                <div className="w-12 h-12 rounded-xl bg-success/5 flex items-center justify-center">
                                    <FileText className="w-6 h-6 text-success" />
                                </div>
                                <div>
                                    <h4 className="font-bold text-primary group-hover:text-primary-light transition-colors">Pengajuan Izin</h4>
                                    <p className="text-sm text-text-secondary">Cuti, izin, sakit</p>
                                </div>
                            </div>
                            <ChevronRight className="w-5 h-5 text-text-secondary group-hover:text-primary transition-colors" />
                        </Link>
                        <Link href={route('penggajian.index')} className="bg-white rounded-xl p-5 shadow-sm border border-border hover:shadow-md transition-all flex items-center justify-between group">
                            <div className="flex items-center gap-4">
                                <div className="w-12 h-12 rounded-xl bg-warning/10 flex items-center justify-center">
                                    <Receipt className="w-6 h-6 text-warning" />
                                </div>
                                <div>
                                    <h4 className="font-bold text-primary group-hover:text-primary-light transition-colors">Slip Gaji</h4>
                                    <p className="text-sm text-text-secondary">Lihat riwayat gaji bulanan</p>
                                </div>
                            </div>
                            <ChevronRight className="w-5 h-5 text-text-secondary group-hover:text-primary transition-colors" />
                        </Link>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
