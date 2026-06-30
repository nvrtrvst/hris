import React, { useState, useEffect } from 'react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Head, Link } from '@inertiajs/react';

export default function MobileDashboard({ auth, pegawai, presensi, presensiSeminggu }) {
    const [currentTime, setCurrentTime] = useState(new Date());

    useEffect(() => {
        const timer = setInterval(() => setCurrentTime(new Date()), 1000);
        return () => clearInterval(timer);
    }, []);

    const formatDate = (date) => {
        return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    };

    const formatTime = (date) => {
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Beranda Mobile" />

            <div className="space-y-6">
                {/* Greeting Card */}
                <div className="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-5 text-white shadow-lg relative overflow-hidden">
                    <div className="relative z-10">
                        <p className="text-indigo-100 text-sm mb-1">{formatDate(currentTime)}</p>
                        <h2 className="text-2xl font-bold mb-4">Halo, {auth.user.name.split(' ')[0]}!</h2>
                        
                        <div className="bg-white/20 backdrop-blur-md rounded-xl p-4 text-center border border-white/10">
                            <p className="text-xs text-indigo-100 mb-1 uppercase tracking-wider">Waktu Saat Ini</p>
                            <p className="text-4xl font-mono font-bold tracking-tight">{formatTime(currentTime)}</p>
                        </div>
                    </div>
                    {/* Decorative circles */}
                    <div className="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-10 rounded-full blur-xl"></div>
                    <div className="absolute bottom-0 left-0 -mb-4 -ml-4 w-20 h-20 bg-purple-400 opacity-20 rounded-full blur-lg"></div>
                </div>

                {/* Status Presensi Hari Ini */}
                <div>
                    <h3 className="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider flex items-center">
                        <svg className="w-4 h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Status Kehadiran
                    </h3>
                    
                    <div className="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                        {presensi ? (
                            <div className="space-y-4">
                                <div className="flex justify-between items-center pb-3 border-b border-gray-50">
                                    <div className="flex items-center space-x-3">
                                        <div className="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-500">Jam Masuk</p>
                                            <p className="font-bold text-gray-900">{presensi.jam_masuk || '--:--'}</p>
                                        </div>
                                    </div>
                                    {presensi.status === 'telat' && <span className="bg-red-100 text-red-700 text-xs px-2 py-1 rounded-md font-bold">Telat</span>}
                                    {presensi.status === 'hadir' && <span className="bg-emerald-100 text-emerald-700 text-xs px-2 py-1 rounded-md font-bold">Tepat Waktu</span>}
                                </div>
                                <div className="flex justify-between items-center">
                                    <div className="flex items-center space-x-3">
                                        <div className="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-500">Jam Pulang</p>
                                            <p className="font-bold text-gray-900">{presensi.jam_keluar || '--:--'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-6">
                                <div className="w-12 h-12 rounded-full bg-gray-100 mx-auto flex items-center justify-center mb-3">
                                    <svg className="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <p className="text-gray-500 text-sm mb-4">Anda belum melakukan absen masuk hari ini.</p>
                                <Link href={route('mobile.absen')} className="inline-block bg-indigo-600 text-white font-bold py-2 px-6 rounded-full shadow-md text-sm">
                                    Absen Sekarang
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                {/* Quick Menu */}
                {/* <div className="grid grid-cols-2 gap-4">
                    <Link href={route('mobile.absen')} className="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center transition-all active:scale-95">
                        <div className="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mb-2">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <span className="text-sm font-semibold text-gray-800">Absen</span>
                    </Link>
                    
                    <Link href={route('mobile.izin.index')} className="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center transition-all active:scale-95">
                        <div className="w-12 h-12 bg-purple-50 text-purple-600 rounded-full flex items-center justify-center mb-2">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <span className="text-sm font-semibold text-gray-800">Izin & Cuti</span>
                    </Link>
                </div> */}

                {/* Riwayat Kehadiran (Seminggu Terakhir) */}
                <div>
                    <div className="flex justify-between items-center mb-3">
                        <h3 className="text-sm font-bold text-gray-800 uppercase tracking-wider flex items-center">
                            <svg className="w-4 h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Riwayat Kehadiran
                        </h3>
                        <Link href={route('mobile.riwayat')} className="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                            Lihat Semua
                        </Link>
                    </div>
                    
                    <div className="space-y-3">
                        {presensiSeminggu && presensiSeminggu.length > 0 ? (
                            presensiSeminggu.slice(0, 5).map(item => (
                                <Link key={item.id} href={route('mobile.riwayat')} className="bg-white rounded-2xl p-4 shadow-sm border border-l-4 border-l-indigo-500 flex items-center hover:bg-gray-50 transition-colors">
                                    <div className="flex-1">
                                        <p className="font-bold text-gray-900">{new Date(item.tanggal).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' })}</p>
                                        <div className="flex items-center text-xs text-gray-500 mt-0.5">
                                            <span className="inline-block w-2 h-2 rounded-full mr-1.5 bg-emerald-500"></span>
                                            {item.status.charAt(0).toUpperCase() + item.status.slice(1)}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md text-sm">
                                            {item.jam_masuk ? item.jam_masuk.substring(0, 5) : '--:--'}
                                        </p>
                                    </div>
                                </Link>
                            ))
                        ) : (
                            <div className="bg-gray-50 border border-dashed border-gray-300 rounded-2xl p-6 text-center">
                                <p className="text-gray-500 text-sm">Belum ada riwayat kehadiran dalam seminggu terakhir.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </MobileLayout>
    );
}
