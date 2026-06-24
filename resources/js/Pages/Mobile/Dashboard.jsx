import React, { useState, useEffect } from 'react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Head, Link } from '@inertiajs/react';

export default function MobileDashboard({ auth, pegawai, presensi, jadwals }) {
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

                {/* Jadwal Hari Ini */}
                <div>
                    <h3 className="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider flex items-center">
                        <svg className="w-4 h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Jadwal Anda
                    </h3>
                    
                    <div className="space-y-3">
                        {jadwals && jadwals.length > 0 ? (
                            jadwals.map(jadwal => (
                                <div key={jadwal.id} className="bg-white rounded-2xl p-4 shadow-sm border border-l-4 border-l-indigo-500 flex items-center">
                                    <div className="flex-1">
                                        <p className="font-bold text-gray-900">{jadwal.unit_sekolah.nama}</p>
                                        <p className="text-xs text-gray-500">{jadwal.keterangan || 'Tugas Reguler'}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md text-sm">{jadwal.jam_mulai.substring(0, 5)} - {jadwal.jam_selesai.substring(0, 5)}</p>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="bg-gray-50 rounded-xl p-4 text-center border border-gray-100 border-dashed">
                                <p className="text-gray-500 text-sm">Tidak ada jadwal terdaftar untuk hari ini.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </MobileLayout>
    );
}
