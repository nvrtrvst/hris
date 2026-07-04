import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

    export default function Index({ auth, jadwals, pegawais, units, filters }) {
        const [unitFilter, setUnitFilter] = useState(filters.unit_sekolah_id || '');
        const [searchName, setSearchName] = useState('');
        
        // Modal State
        const [showGenerateModal, setShowGenerateModal] = useState(false);
        const [genData, setGenData] = useState({
            tahun_ajaran: '2026/2027',
            semester: '1',
            waktu_mulai: '',
            waktu_selesai: '',
        });

        // Swap Modal State
        const [showSwapModal, setShowSwapModal] = useState(false);
        const [swapData, setSwapData] = useState({
            jadwal_asal_id: '',
            jadwal_tujuan_id: ''
        });
        const [targetPegawaiId, setTargetPegawaiId] = useState('');
        
        // Error state
        const { errors } = usePage().props;
    
        const handleFilterChange = (e) => {
            const value = e.target.value;
            setUnitFilter(value);
            router.get(route('jadwal.index'), { unit_sekolah_id: value }, { preserveState: true });
        };
        
        const handleGenerate = (e) => {
            e.preventDefault();
            if(confirm('Mesin akan mengacak shift guru dan mengisi jadwal kosong. Lanjutkan?')) {
                router.post(route('jadwal.generate'), {
                    ...genData,
                    unit_sekolah_id: unitFilter
                }, {
                    onSuccess: () => setShowGenerateModal(false)
                });
            }
        };

        const handleSwap = (e) => {
            e.preventDefault();
            if (!swapData.jadwal_asal_id || !swapData.jadwal_tujuan_id) {
                alert('Pilih jadwal target terlebih dahulu!');
                return;
            }
            if(confirm('Tukar jadwal ini?')) {
                router.post(route('jadwal.swap'), swapData, {
                    onSuccess: () => {
                        setShowSwapModal(false);
                        setSwapData({jadwal_asal_id: '', jadwal_tujuan_id: ''});
                        setTargetPegawaiId('');
                    }
                });
            }
        };
    
        const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        
        // Filter pegawais by search name locally
        const filteredPegawais = pegawais.filter(p => p.nama_lengkap.toLowerCase().includes(searchName.toLowerCase()));
    
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
                                <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                                    <div>
                                        <h3 className="text-xl font-bold text-gray-900">Jadwal Mingguan (Matriks)</h3>
                                        <p className="text-sm text-gray-500 mt-1">Tampilan matrik untuk memantau ratusan pegawai sekaligus tanpa lelah scroll ke bawah.</p>
                                    </div>
                                    <div className="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 w-full md:w-auto">
                                        <div className="relative">
                                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </div>
                                            <input 
                                                type="text" 
                                                placeholder="Cari Pegawai..." 
                                                value={searchName}
                                                onChange={(e) => setSearchName(e.target.value)}
                                                className="pl-10 border-gray-300 focus:border-primary focus:ring-primary rounded-lg shadow-sm font-medium text-gray-700 w-full sm:w-48"
                                            />
                                        </div>
                                        <select 
                                            value={unitFilter}
                                            onChange={handleFilterChange}
                                            className="border-gray-300 focus:border-primary focus:ring-primary rounded-lg shadow-sm font-medium text-gray-700 w-full sm:w-auto"
                                        >
                                            <option value="">Semua Unit Sekolah</option>
                                            {units.map(unit => (
                                                <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                            ))}
                                        </select>
                                        
                                        <button
                                            onClick={() => window.print()}
                                            className="inline-flex justify-center items-center px-4 py-2 bg-gray-600 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-md print:hidden"
                                        >
                                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                            Cetak PDF
                                        </button>
                                        <button
                                            onClick={() => setShowGenerateModal(true)}
                                            className="inline-flex justify-center items-center px-4 py-2 bg-accent border border-transparent rounded-lg font-bold text-xs text-primary uppercase tracking-widest hover:bg-yellow-500 active:bg-yellow-600 focus:outline-none focus:border-yellow-600 focus:ring ring-yellow-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-md print:hidden"
                                        >
                                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                            Generate Otomatis
                                        </button>
                                        <Link
                                            href={route('jadwal.create')}
                                            className="inline-flex justify-center items-center px-4 py-2 bg-primary border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-primary-dark active:bg-primary-dark focus:outline-none focus:border-primary-dark focus:ring ring-primary disabled:opacity-25 transition ease-in-out duration-150 shadow-md print:hidden"
                                        >
                                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                            Tambah
                                        </Link>
                                    </div>
                                </div>
    
                                {/* Matrix Table Board */}
                                <div className="overflow-hidden border border-gray-200 rounded-xl shadow-sm print:shadow-none print:border-none print:rounded-none">
                                    <table className="w-full table-fixed divide-y divide-gray-200 bg-white text-sm">
                                        <thead className="bg-primary text-white print:bg-gray-100 print:text-black">
                                            <tr>
                                                <th scope="col" className="w-[16%] px-2 py-3.5 text-left font-extrabold uppercase tracking-widest border-r border-primary/20 print:border-gray-300">
                                                    Pegawai
                                                </th>
                                                {days.map(day => (
                                                    <th key={day} scope="col" className="w-[12%] px-2 py-3.5 text-center font-extrabold uppercase tracking-widest text-accent print:text-black border-r border-primary/20 print:border-gray-300">
                                                        {day}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {filteredPegawais.length > 0 ? filteredPegawais.map((pegawai) => (
                                                <tr key={pegawai.id} className="hover:bg-gray-50 transition-colors group">
                                                    <td className="px-4 py-3 border-r border-gray-200 sticky left-0 z-10 bg-white group-hover:bg-gray-50 align-top">
                                                        <div className="font-bold text-gray-900 leading-tight">{pegawai.nama_lengkap}</div>
                                                        <div className="mt-1 flex items-center text-[11px] font-medium text-gray-500">
                                                            <svg className="w-3.5 h-3.5 mr-1 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                                            {pegawai.units?.[0]?.singkatan || '-'}
                                                        </div>
                                                    </td>
                                                    {days.map(day => {
                                                        const pJadwals = jadwals.filter(j => j.pegawai_id === pegawai.id && j.hari === day);
                                                        return (
                                                            <td key={day} className="px-2 py-2 border-r border-gray-100 align-top bg-gray-50/30">
                                                                <div className="flex flex-col gap-1.5">
                                                                    {pJadwals.length > 0 ? pJadwals.map(jadwal => (
                                                                        <div key={jadwal.id} className="bg-white p-2 rounded shadow-sm border border-gray-200 hover:border-primary/50 relative group/card flex flex-col items-center text-center">
                                                                            <span className="text-[11px] font-bold text-primary">
                                                                                {jadwal.jam_mulai.substring(0, 5)} - {jadwal.jam_selesai.substring(0, 5)}
                                                                            </span>
                                                                            <span className={`mt-1 text-[9px] font-bold px-1.5 py-0.5 rounded uppercase ${jadwal.jenis_jadwal === 'mengajar' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-amber-50 text-amber-700 border border-amber-100'}`}>
                                                                                {jadwal.jenis_jadwal}
                                                                            </span>
                                                                            
                                                                            {/* Action Overlay */}
                                                                            <div className="absolute inset-0 bg-primary/90 opacity-0 group-hover/card:opacity-100 flex items-center justify-center gap-2 rounded transition-opacity backdrop-blur-sm">
                                                                                <button 
                                                                                     onClick={() => {
                                                                                         setSwapData({...swapData, jadwal_asal_id: jadwal.id});
                                                                                         setShowSwapModal(true);
                                                                                     }}
                                                                                     className="text-blue-300 hover:text-blue-100 transform hover:scale-110 transition-all cursor-pointer"
                                                                                     title="Tukar Jadwal"
                                                                                >
                                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                                                                </button>
                                                                                <div className="w-px h-4 bg-white/30"></div>
                                                                                <button
                                                                                     onClick={() => {
                                                                                         if(confirm('Hapus jadwal ini?')) {
                                                                                             router.delete(route('jadwal.destroy', jadwal.id))
                                                                                         }
                                                                                     }}
                                                                                     className="text-red-400 hover:text-red-300 transform hover:scale-110 transition-all cursor-pointer"
                                                                                     title="Hapus Jadwal"
                                                                                >
                                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    )) : (
                                                                        <div className="text-gray-300 text-center py-2 text-xs">-</div>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            )) : (
                                                <tr>
                                                    <td colSpan="8" className="px-4 py-8 text-center text-gray-500">
                                                        Tidak ada data pegawai ditemukan.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div className="mt-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-md">
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

            {/* Generate Modal */}
            {showGenerateModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                        <div className="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-50 backdrop-blur-sm" onClick={() => setShowGenerateModal(false)}></div>
                        <div className="relative inline-block w-full max-w-md p-6 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                            <h3 className="text-xl font-bold leading-6 text-gray-900">Generate Jadwal Otomatis</h3>
                            <div className="mt-2 text-sm text-gray-500">
                                Mesin akan mengacak dan menebar jadwal ke guru (Senin-Jumat) pada unit ini. Jadwal akan diisi 2 shift secara acak per orang tanpa bentrok.
                            </div>
                            <form onSubmit={handleGenerate} className="mt-4 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Tahun Ajaran</label>
                                    <input 
                                        type="text" 
                                        value={genData.tahun_ajaran} 
                                        onChange={e => setGenData({...genData, tahun_ajaran: e.target.value})}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Semester</label>
                                    <select 
                                        value={genData.semester} 
                                        onChange={e => setGenData({...genData, semester: e.target.value})}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm"
                                    >
                                        <option value="1">1 (Ganjil)</option>
                                        <option value="2">2 (Genap)</option>
                                    </select>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Batas Jam Mulai (Opsional)</label>
                                        <input 
                                            type="time" 
                                            value={genData.waktu_mulai} 
                                            onChange={e => setGenData({...genData, waktu_mulai: e.target.value})}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm"
                                            title="Kosongkan jika tidak dibatasi"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Batas Jam Selesai (Opsional)</label>
                                        <input 
                                            type="time" 
                                            value={genData.waktu_selesai} 
                                            onChange={e => setGenData({...genData, waktu_selesai: e.target.value})}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm"
                                            title="Kosongkan jika tidak dibatasi"
                                        />
                                    </div>
                                </div>
                                <div className="mt-6 flex justify-end gap-3">
                                    <button 
                                        type="button" 
                                        onClick={() => setShowGenerateModal(false)}
                                        className="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                    >
                                        Batal
                                    </button>
                                    <button 
                                        type="submit" 
                                        className="inline-flex justify-center px-4 py-2 text-sm font-medium text-white border border-transparent rounded-md bg-accent hover:bg-yellow-500 text-primary font-bold"
                                    >
                                        Mulai Pahat!
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* Swap Modal */}
            {showSwapModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                        <div className="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-50 backdrop-blur-sm" onClick={() => setShowSwapModal(false)}></div>
                        <div className="relative inline-block w-full max-w-md p-6 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                            <h3 className="text-xl font-bold leading-6 text-gray-900 flex items-center gap-2">
                                <svg className="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                Tukar Jadwal
                            </h3>
                            <div className="mt-2 text-sm text-gray-500">
                                Pilih pegawai dan jadwal target untuk ditukar kepemilikannya.
                            </div>
                            
                            {errors?.conflict && (
                                <div className="mt-3 bg-red-50 text-red-700 p-3 rounded-md text-sm border border-red-200">
                                    {errors.conflict}
                                </div>
                            )}

                            <form onSubmit={handleSwap} className="mt-4 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Jadwal Asal</label>
                                    <div className="mt-1 p-3 bg-gray-50 border border-gray-200 rounded-md text-sm font-medium text-gray-800">
                                        {jadwals.find(j => j.id === swapData.jadwal_asal_id) ? (
                                            (() => {
                                                const j = jadwals.find(j => j.id === swapData.jadwal_asal_id);
                                                return `${j.pegawai.nama_lengkap} (${j.hari}, ${j.jam_mulai.substring(0,5)}-${j.jam_selesai.substring(0,5)})`;
                                            })()
                                        ) : 'Memuat...'}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Tukar Dengan Pegawai</label>
                                    <select 
                                        value={targetPegawaiId} 
                                        onChange={e => {
                                            setTargetPegawaiId(e.target.value);
                                            setSwapData({...swapData, jadwal_tujuan_id: ''}); // reset target jadwal
                                        }}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm"
                                    >
                                        <option value="">-- Pilih Pegawai --</option>
                                        {pegawais.filter(p => jadwals.find(j => j.id === swapData.jadwal_asal_id)?.pegawai_id !== p.id).map(p => (
                                            <option key={p.id} value={p.id}>{p.nama_lengkap}</option>
                                        ))}
                                    </select>
                                </div>

                                {targetPegawaiId && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Pilih Jadwal Target</label>
                                        <select 
                                            value={swapData.jadwal_tujuan_id} 
                                            onChange={e => setSwapData({...swapData, jadwal_tujuan_id: e.target.value})}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm"
                                        >
                                            <option value="">-- Pilih Jadwal --</option>
                                            {jadwals.filter(j => j.pegawai_id == targetPegawaiId).map(j => (
                                                <option key={j.id} value={j.id}>
                                                    {j.hari}, {j.jam_mulai.substring(0,5)} - {j.jam_selesai.substring(0,5)} ({j.jenis_jadwal})
                                                </option>
                                            ))}
                                        </select>
                                        {jadwals.filter(j => j.pegawai_id == targetPegawaiId).length === 0 && (
                                            <p className="mt-1 text-xs text-red-500">Pegawai ini tidak memiliki jadwal untuk ditukar.</p>
                                        )}
                                    </div>
                                )}

                                <div className="mt-6 flex justify-end gap-3">
                                    <button 
                                        type="button" 
                                        onClick={() => setShowSwapModal(false)}
                                        className="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                    >
                                        Batal
                                    </button>
                                    <button 
                                        type="submit" 
                                        disabled={!swapData.jadwal_tujuan_id}
                                        className={`inline-flex justify-center px-4 py-2 text-sm font-medium text-white border border-transparent rounded-md bg-blue-600 hover:bg-blue-700 font-bold ${!swapData.jadwal_tujuan_id ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    >
                                        Eksekusi Tukar!
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
