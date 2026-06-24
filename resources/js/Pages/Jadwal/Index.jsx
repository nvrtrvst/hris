import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ auth, jadwals, units, filters }) {
    const [unitFilter, setUnitFilter] = useState(filters.unit_sekolah_id || '');

    const handleFilterChange = (e) => {
        const value = e.target.value;
        setUnitFilter(value);
        router.get(route('jadwal.index'), { unit_sekolah_id: value }, { preserveState: true });
    };

    const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Jadwal Pegawai</h2>}
        >
            <Head title="Jadwal Pegawai" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                                <div>
                                    <h3 className="text-xl font-bold text-gray-900">Jadwal Mingguan</h3>
                                    <p className="text-sm text-gray-500 mt-1">Pantau jadwal mengajar dan piket seluruh pegawai. Sistem otomatis mendeteksi bentrok lintas unit.</p>
                                </div>
                                <div className="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4 w-full md:w-auto">
                                    <select 
                                        value={unitFilter}
                                        onChange={handleFilterChange}
                                        className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    >
                                        <option value="">Semua Unit Sekolah</option>
                                        {units.map(unit => (
                                            <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                        ))}
                                    </select>
                                    <Link
                                        href={route('jadwal.create')}
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-6 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 text-center whitespace-nowrap"
                                    >
                                        + Tambah Jadwal
                                    </Link>
                                </div>
                            </div>

                            {/* Calendar Weekly Board */}
                            <div className="grid grid-cols-1 lg:grid-cols-7 gap-4">
                                {days.map(day => (
                                    <div key={day} className="flex flex-col bg-gray-50 rounded-xl border border-gray-200 overflow-hidden">
                                        <div className="bg-indigo-600 px-4 py-3 text-center">
                                            <h4 className="font-bold text-white uppercase tracking-wider text-sm">{day}</h4>
                                        </div>
                                        <div className="p-3 space-y-3 min-h-[150px]">
                                            {jadwals.filter(j => j.hari === day).length > 0 ? (
                                                jadwals.filter(j => j.hari === day).map(jadwal => (
                                                    <div key={jadwal.id} className="bg-white p-3 rounded-lg shadow-sm border border-gray-100 hover:shadow-md transition-shadow relative group">
                                                        <div className="flex justify-between items-start">
                                                            <span className="text-xs font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">
                                                                {jadwal.jam_mulai.substring(0, 5)} - {jadwal.jam_selesai.substring(0, 5)}
                                                            </span>
                                                            <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded uppercase ${jadwal.jenis_jadwal === 'mengajar' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'}`}>
                                                                {jadwal.jenis_jadwal}
                                                            </span>
                                                        </div>
                                                        <h5 className="font-semibold text-gray-900 mt-2 text-sm leading-tight">{jadwal.pegawai.nama_lengkap}</h5>
                                                        <div className="mt-1 flex items-center text-xs text-gray-500">
                                                            <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                                            {jadwal.unit_sekolah.singkatan}
                                                        </div>
                                                        
                                                        {/* Delete Button overlay on hover */}
                                                        <div className="absolute top-0 right-0 bottom-0 left-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 flex items-center justify-center rounded-lg transition-opacity">
                                                            <button 
                                                                onClick={() => {
                                                                    if(confirm('Hapus jadwal ini?')) {
                                                                        router.delete(route('jadwal.destroy', jadwal.id))
                                                                    }
                                                                }}
                                                                className="bg-red-600 text-white p-2 rounded-full hover:bg-red-700 shadow-lg"
                                                            >
                                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                ))
                                            ) : (
                                                <div className="flex h-full items-center justify-center text-gray-400 text-sm py-4">
                                                    Kosong
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            
                            <div className="mt-8 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-md">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <svg className="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <p className="text-sm text-blue-700">
                                            Sistem otomatis menolak penambahan jadwal jika terdeteksi adanya bentrok (overlap waktu pada hari yang sama) untuk pegawai yang sama, bahkan jika berbeda unit sekolah.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
