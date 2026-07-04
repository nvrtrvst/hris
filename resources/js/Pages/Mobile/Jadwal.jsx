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

            <div className="space-y-6 pb-8">
                {sortedHari.length === 0 ? (
                    <div className="bg-white rounded-2xl p-6 text-center border border-gray-100 shadow-sm mt-4">
                        <CalendarDays className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <h3 className="text-gray-800 font-bold mb-1">Belum Ada Jadwal</h3>
                        <p className="text-gray-500 text-sm">Tidak ada jadwal yang terdaftar.</p>
                    </div>
                ) : (
                    sortedHari.map(hari => (
                        <div key={hari} className="mb-2">
                            {/* Header Hari */}
                            <div className="flex items-center space-x-2 mb-2 ml-1">
                                <h2 className="text-sm font-bold text-gray-700 uppercase tracking-wider">
                                    {hari}
                                </h2>
                                {hari === hariIni && (
                                    <span className="bg-indigo-100 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">
                                        Hari Ini
                                    </span>
                                )}
                            </div>
                            
                            {/* Daftar Jadwal per Hari dalam satu Card (List) */}
                            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-50">
                                {jadwalPerHari[hari].map((jadwal, index) => (
                                    <div key={jadwal.id} className={`p-4 flex items-start ${hari === hariIni ? 'bg-indigo-50/30' : ''}`}>
                                        
                                        {/* Waktu (Kiri) */}
                                        <div className="flex-shrink-0 w-16 text-center mr-3 pt-0.5 border-r border-gray-100 pr-3">
                                            <p className="text-sm font-extrabold text-indigo-600 leading-none mb-1">{jadwal.jam_mulai.substring(0, 5)}</p>
                                            <p className="text-xs font-semibold text-gray-400 leading-none">{jadwal.jam_selesai.substring(0, 5)}</p>
                                        </div>

                                        {/* Info Utama (Kanan) */}
                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-sm font-bold text-gray-900 mb-1 truncate">
                                                {jadwal.mata_pelajaran ? jadwal.mata_pelajaran.nama : (jadwal.jenis_jadwal.charAt(0).toUpperCase() + jadwal.jenis_jadwal.slice(1))}
                                            </h3>
                                            
                                            {/* Detail Tambahan (Lokasi & Kelas) */}
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-[11px] text-gray-500 font-medium">
                                                <span className="flex items-center bg-gray-50 px-1.5 py-0.5 rounded text-gray-600">
                                                    <MapPin className="w-3 h-3 mr-1 text-gray-400" />
                                                    <span className="truncate max-w-[120px]">{jadwal.unit_sekolah?.singkatan || jadwal.unit_sekolah?.nama || '-'}</span>
                                                </span>
                                                
                                                {jadwal.kelas && (
                                                    <span className="flex items-center bg-gray-50 px-1.5 py-0.5 rounded text-gray-600">
                                                        <Layers className="w-3 h-3 mr-1 text-gray-400" />
                                                        <span className="truncate">Kls {jadwal.kelas.tingkat} {jadwal.kelas.nama}</span>
                                                    </span>
                                                )}
                                            </div>
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
