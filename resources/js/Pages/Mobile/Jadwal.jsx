import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Clock, MapPin, CalendarDays, BookOpen, Layers } from 'lucide-react';

export default function Jadwal({ auth, pegawai, jadwalPerHari }) {
    const hariUrutan = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    
    // Urutkan jadwal sesuai hari kerja
    const sortedHari = Object.keys(jadwalPerHari).sort(
        (a, b) => hariUrutan.indexOf(a) - hariUrutan.indexOf(b)
    );

    const getHariIni = () => {
        const date = new Date();
        const hariMap = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return hariMap[date.getDay()];
    };

    const hariIni = getHariIni();

    return (
        <MobileLayout user={auth.user}>
            <Head title="Jadwal Kerja" />

            <div className="mb-6">
                <h1 className="text-xl font-extrabold text-gray-900">Jadwal Anda</h1>
                <p className="text-sm text-gray-500">Jadwal kerja dan mengajar Anda</p>
            </div>

            <div className="space-y-8 pb-8">
                {sortedHari.length === 0 ? (
                    <div className="bg-white rounded-2xl p-6 text-center border border-gray-100 shadow-sm mt-4">
                        <CalendarDays className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <h3 className="text-gray-800 font-bold mb-1">Belum Ada Jadwal</h3>
                        <p className="text-gray-500 text-sm">Tidak ada jadwal yang terdaftar.</p>
                    </div>
                ) : (
                    sortedHari.map(hari => (
                        <div key={hari} className="mb-6">
                            {/* Header Hari */}
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-extrabold text-gray-900">
                                    {hari}
                                </h2>
                                {hari === hariIni && (
                                    <span className="bg-indigo-600 text-white text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">
                                        Hari Ini
                                    </span>
                                )}
                            </div>
                            
                            {/* Daftar Jadwal per Hari */}
                            <div className="space-y-4">
                                {jadwalPerHari[hari].map((jadwal, index) => (
                                    <div key={jadwal.id} className={`bg-white p-5 rounded-2xl border ${hari === hariIni ? 'border-indigo-200 shadow-md shadow-indigo-100/50' : 'border-gray-100 shadow-sm'}`}>
                                        
                                        {/* Waktu */}
                                        <div className="flex items-center mb-3">
                                            <div className="bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg font-bold font-mono text-sm flex items-center">
                                                <Clock className="w-4 h-4 mr-2" />
                                                {jadwal.jam_mulai.substring(0, 5)} - {jadwal.jam_selesai.substring(0, 5)}
                                            </div>
                                        </div>

                                        {/* Info Utama */}
                                        <h3 className="text-base font-bold text-gray-900 mb-1">
                                            {jadwal.mata_pelajaran ? jadwal.mata_pelajaran.nama : jadwal.jenis_jadwal}
                                        </h3>
                                        
                                        {/* Detail Tambahan */}
                                        <div className="flex flex-col space-y-2 mt-3 pt-3 border-t border-gray-50">
                                            <div className="flex items-center text-sm text-gray-600">
                                                <MapPin className="w-4 h-4 mr-2 text-gray-400" />
                                                {jadwal.unit_sekolah?.nama || 'Lokasi Tidak Diketahui'}
                                            </div>
                                            
                                            {jadwal.kelas && (
                                                <div className="flex items-center text-sm text-gray-600">
                                                    <Layers className="w-4 h-4 mr-2 text-gray-400" />
                                                    Kelas {jadwal.kelas.tingkat} {jadwal.kelas.nama}
                                                </div>
                                            )}
                                        </div>

                                    </div>
                                ))}
                            </div>
                        </div>
                    ))
                )}
            </div>
        </MobileLayout>
    );
}
