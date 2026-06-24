import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function DashboardSelfService({ auth, stats }) {
    const todayString = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    // Asumsi status pegawai
    const isPegawai = true; // since it's self service

    const hadirBulanIni = stats?.hadir_bulan_ini || 0;
    const jadwalBulanIni = stats?.jadwal_bulan_ini || 22;
    const persentaseHadir = Math.round((hadirBulanIni / jadwalBulanIni) * 100);

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
                    
                    {/* Welcome Mini Banner */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm border border-border">
                        <h3 className="text-xl font-bold text-primary">Halo, {auth.user.name}</h3>
                        <p className="text-text-secondary mt-1">Selamat datang di portal self-service HRIS. Berikut adalah ringkasan data kepegawaian Anda.</p>
                    </div>

                    {/* Stats Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        {/* Kehadiran Bulan Ini */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm border border-border">
                            <h4 className="text-sm font-medium text-text-secondary uppercase tracking-wider mb-2">Kehadiran Bulan Ini</h4>
                            <div className="flex items-end justify-between">
                                <div>
                                    <span className="text-3xl font-bold text-primary">{hadirBulanIni}</span>
                                    <span className="text-text-secondary ml-2">/ {jadwalBulanIni} Hari</span>
                                </div>
                                <div className={`text-sm font-bold ${persentaseHadir >= 90 ? 'text-success' : 'text-warning'}`}>
                                    {persentaseHadir}%
                                </div>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-2 mt-4">
                                <div className={`h-2 rounded-full ${persentaseHadir >= 90 ? 'bg-success' : 'bg-warning'}`} style={{ width: `${persentaseHadir}%` }}></div>
                            </div>
                        </div>

                        {/* Status Kontrak */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm border border-border">
                            <h4 className="text-sm font-medium text-text-secondary uppercase tracking-wider mb-2">Status Kepegawaian</h4>
                            <div className="mt-2">
                                <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-indigo-100 text-indigo-800">
                                    Pegawai Aktif
                                </span>
                            </div>
                            <p className="text-xs text-text-secondary mt-3">Kontrak hingga: -</p>
                        </div>

                        {/* Slip Gaji Terakhir */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm border border-border flex flex-col justify-between">
                            <div>
                                <h4 className="text-sm font-medium text-text-secondary uppercase tracking-wider mb-2">Slip Gaji Terakhir</h4>
                                <p className="text-2xl font-bold text-primary mt-1">Belum Tersedia</p>
                                <p className="text-xs text-text-secondary mt-1">Data payroll periode ini belum final.</p>
                            </div>
                            <button disabled className="mt-4 w-full bg-gray-100 text-gray-400 py-2 rounded-lg text-sm font-bold cursor-not-allowed border border-gray-200">
                                Unduh PDF
                            </button>
                        </div>
                    </div>

                    {/* Pengajuan Saya */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm border border-border">
                        <h3 className="text-lg font-bold text-primary mb-4">Pengajuan Pending (Menunggu Approval)</h3>
                        <div className="flex flex-col items-center justify-center py-8 border-2 border-dashed border-gray-200 rounded-xl">
                            <svg className="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            <p className="text-text-secondary">Anda tidak memiliki pengajuan koreksi presensi atau cuti yang pending.</p>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
