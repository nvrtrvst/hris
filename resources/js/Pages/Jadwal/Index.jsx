import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, router, usePage } from '@inertiajs/react';

    export default function Index({ auth, jadwals, pegawais, units, kelasLabels, filters }) {
        const isAdmin = auth.permissions?.includes('view_jadwal');
        const [unitFilter, setUnitFilter] = useState(filters.unit_sekolah_id || '');
        const [kelasFilter, setKelasFilter] = useState(filters.kelas_label || '');
        const [searchName, setSearchName] = useState('');
        const [showRekapModal, setShowRekapModal] = useState(false);
        const [viewMode, setViewMode] = useState('matrix');
        const [expandedGuru, setExpandedGuru] = useState(null);
        
        // Modal State
        const [showGenerateModal, setShowGenerateModal] = useState(false);
        const [generating, setGenerating] = useState(false);
        const [genData, setGenData] = useState({
            tahun_ajaran: '2026/2027',
            semester: '1',
            unit_sekolah_id: '',
            waktu_mulai: '07:00',
            waktu_selesai: '15:00',
        });

        // Hitung jumlah pegawai per unit buat info di modal generate
        const pegawaiCountByUnit = pegawais.reduce((acc, p) => {
            const u = p.units?.[0]?.id || '0';
            acc[u] = (acc[u] || 0) + 1;
            return acc;
        }, {});
        const selectedUnitPegCount = genData.unit_sekolah_id
            ? (pegawaiCountByUnit[genData.unit_sekolah_id] || 0)
            : pegawais.length;

        // Swap Modal State
        const [showSwapModal, setShowSwapModal] = useState(false);
        const [swapData, setSwapData] = useState({
            jadwal_asal_id: '',
            jadwal_tujuan_id: ''
        });
        const [targetPegawaiId, setTargetPegawaiId] = useState('');
        
        // Error state
        const { errors } = usePage().props;
    
        const handleUnitFilterChange = (e) => {
            const value = e.target.value;
            setUnitFilter(value);
            setKelasFilter('');
            router.get(route('jadwal.index'), { unit_sekolah_id: value, kelas_label: '' }, { preserveState: true });
        };
        
        const handleKelasFilterChange = (e) => {
            const value = e.target.value;
            setKelasFilter(value);
            router.get(route('jadwal.index'), { unit_sekolah_id: unitFilter, kelas_label: value }, { preserveState: true });
        };
        
        const handleGenerate = (e) => {
            e.preventDefault();
            setGenerating(true);
            router.post(route('jadwal.generate'), genData, {
                onSuccess: () => {
                    setShowGenerateModal(false);
                    setGenerating(false);
                },
                onError: () => setGenerating(false),
                onFinish: () => setGenerating(false),
            });
        };

        const handleSwap = (e) => {
            e.preventDefault();
            if (!swapData.jadwal_asal_id || !swapData.jadwal_tujuan_id) {
                alert('Pilih jadwal target terlebih dahulu!');
                return;
            }
            router.post(route('jadwal.swap'), swapData, {
                onSuccess: () => {
                    setShowSwapModal(false);
                    setSwapData({jadwal_asal_id: '', jadwal_tujuan_id: ''});
                    setTargetPegawaiId('');
                }
            });
        };
    
        const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        
        // Filter pegawais by search name locally
        const filteredPegawais = pegawais.filter(p => p.nama_lengkap.toLowerCase().includes(searchName.toLowerCase()));
    
        return (
            <AuthenticatedLayout
                user={auth.user}
                header={<h2 className="page-title">{isAdmin ? 'Jadwal Pegawai' : 'Jadwal Pribadi'}</h2>}
            >
                <Head title="Jadwal Pegawai" />
    
                <div className="py-8 bg-surface min-h-screen">
                    <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                        <div className="card">
                            <div className="p-6">
                                {isAdmin ? <>
                                <div className="page-header mb-6">
                                    <div>
                                        <h3 className="text-lg font-bold text-primary">Jadwal Mingguan (Matriks)</h3>
                                        <p className="page-subtitle">Tampilan matrik untuk memantau ratusan pegawai sekaligus tanpa lelah scroll ke bawah.</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button onClick={() => setViewMode('matrix')} className={`btn-sm ${viewMode === 'matrix' ? 'btn-primary' : 'btn-secondary'}`}>
                                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            Matriks
                                        </button>
                                        <button onClick={() => setViewMode('guru')} className={`btn-sm ${viewMode === 'guru' ? 'btn-primary' : 'btn-secondary'}`}>
                                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            Per Guru
                                        </button>
                                    </div>
                                    <div className="filter-bar mb-0 flex-col sm:flex-row">
                                        <div className="relative">
                                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg className="input-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </div>
                                            <input 
                                                type="text" 
                                                placeholder="Cari Pegawai..." 
                                                value={searchName}
                                                onChange={(e) => setSearchName(e.target.value)}
                                                className="input-field pl-10 w-full sm:w-48"
                                            />
                                        </div>
                                        <select 
                                            value={unitFilter}
                                            onChange={handleUnitFilterChange}
                                            className="select-field w-full sm:w-auto"
                                        >
                                            <option value="">Semua Unit Sekolah</option>
                                            {units.map(unit => (
                                                <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                            ))}
                                        </select>
                                        
                                        <select
                                            value={kelasFilter}
                                            onChange={handleKelasFilterChange}
                                            className="select-field w-full sm:w-auto"
                                        >
                                            <option value="">Semua Kelas</option>
                                            {kelasLabels.map(kl => (
                                                <option key={kl} value={kl}>{kl}</option>
                                            ))}
                                        </select>
                                        
                                        <button
                                            onClick={() => window.print()}
                                            className="btn-secondary btn-sm print:hidden"
                                        >
                                            <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                            Cetak PDF
                                        </button>
                                        <button
                                            onClick={() => setShowGenerateModal(true)}
                                            className="btn-primary btn-sm bg-accent text-primary-800 hover:bg-yellow-500 print:hidden"
                                        >
                                            <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                            Generate Otomatis
                                        </button>
                                        <button
                                            onClick={() => setShowRekapModal(true)}
                                            className="btn-secondary btn-sm"
                                        >
                                            <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                            Rekap Kelas
                                        </button>
                                        <Link
                                            href={route('jadwal.create')}
                                            className="btn-primary btn-sm"
                                        >
                                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                            Tambah
                                        </Link>
                                    </div>
                                </div>
                                
                                {/* Per Guru View */}
                                {viewMode === 'guru' && (
                                    <div className="space-y-3">
                                        {filteredPegawais.length === 0 ? (
                                            <div className="empty-state py-12">
                                                <svg className="w-12 h-12 mx-auto mb-3 text-border" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                <p className="empty-state-desc">Tidak ada data pegawai.</p>
                                            </div>
                                        ) : (
                                            filteredPegawais.map(pegawai => {
                                                const pJadwals = jadwals.filter(j => j.pegawai_id === pegawai.id && j.jenis_jadwal === 'mengajar');
                                                const totalMenit = pJadwals.reduce((sum, j) => {
                                                    const [h1, m1] = (j.jam_mulai || '0:0').split(':').map(Number);
                                                    const [h2, m2] = (j.jam_selesai || '0:0').split(':').map(Number);
                                                    return sum + Math.max(0, (h2 * 60 + m2) - (h1 * 60 + m1));
                                                }, 0);
                                                const totalJam = totalMenit / 60;
                                                const kelasCount = new Set(pJadwals.map(j => j.kelas_label).filter(Boolean)).size;
                                                const selectedUnit = units.find(u => u.id == (pegawai.units?.[0]?.id || 0));
                                                const maxJam = selectedUnit?.max_jam_minggu || 30;
                                                const pct = Math.min(100, Math.round((totalJam / maxJam) * 100));
                                                const isExpanded = expandedGuru === pegawai.id;

                                                return (
                                                    <div key={pegawai.id} className="card p-4">
                                                        <button
                                                            onClick={() => setExpandedGuru(isExpanded ? null : pegawai.id)}
                                                            className="w-full text-left cursor-pointer"
                                                        >
                                                            <div className="flex items-center justify-between gap-4">
                                                                <div className="min-w-0 flex-1">
                                                                    <div className="flex items-center gap-2">
                                                                        <span className="font-bold text-primary text-sm truncate">{pegawai.nama_lengkap}</span>
                                                                        <span className="text-xs text-text-muted shrink-0">{pegawai.units?.[0]?.singkatan || '-'}</span>
                                                                    </div>
                                                                    <div className="mt-1 flex items-center gap-2 text-xs text-text-secondary">
                                                                        <span>{kelasCount} kelas</span>
                                                                        <span>•</span>
                                                                        <span>{pJadwals.length} jadwal</span>
                                                                    </div>
                                                                </div>
                                                                <div className="shrink-0 text-right min-w-[120px]">
                                                                    <div className="text-sm font-bold text-primary">{totalJam.toFixed(1)} / {maxJam} jam</div>
                                                                    <div className="mt-1 h-1.5 w-full bg-border rounded-full overflow-hidden">
                                                                        <div className="h-full bg-primary rounded-full transition-all" style={{width: pct + '%'}} />
                                                                    </div>
                                                                </div>
                                                                <svg className={`w-4 h-4 text-text-muted transition-transform shrink-0 ${isExpanded ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"/></svg>
                                                            </div>
                                                        </button>

                                                        {isExpanded && (
                                                            <div className="mt-3 pt-3 border-t border-border space-y-1.5">
                                                                {pJadwals.length === 0 ? (
                                                                    <p className="text-xs text-text-muted py-2">Belum ada jadwal mengajar.</p>
                                                                ) : (
                                                                    (() => {
                                                                        const grouped = {};
                                                                        pJadwals.forEach(j => {
                                                                            const key = `${j.hari}-${j.jam_mulai}-${j.jam_selesai}`;
                                                                            if (!grouped[key]) {
                                                                                grouped[key] = { ...j, count: 1 };
                                                                            }
                                                                        });
                                                                        return Object.values(grouped).sort((a, b) => {
                                                                            const hariOrder = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
                                                                            return hariOrder.indexOf(a.hari) - hariOrder.indexOf(b.hari) || a.jam_mulai.localeCompare(b.jam_mulai);
                                                                        }).map((j, i) => (
                                                                            <div key={i} className="flex items-center gap-3 px-2 py-1.5 rounded hover:bg-surface text-xs">
                                                                                <span className="font-mono font-semibold text-primary w-24 shrink-0">{j.hari?.substring(0,3)}, {j.jam_mulai?.substring(0,5)}-{j.jam_selesai?.substring(0,5)}</span>
                                                                                <span className="font-medium text-text-primary truncate">{j.mata_pelajaran?.nama || '-'}</span>
                                                                                <span className="text-text-muted truncate">{j.kelas_label || '-'}</span>
                                                                            </div>
                                                                        ));
                                                                    })()
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })
                                        )}
                                    </div>
                                )}

                                {/* Print Header */}
                                <div className="hidden print:block">
                                    <div className="print-header">
                                        <h1>JADWAL PEGAWAI</h1>
                                        <div className="sub">
                                            Unit: {units.find(u => u.id == unitFilter)?.nama || 'Semua Unit'} &mdash; {filters.tahun_ajaran ? 'T.A. ' + filters.tahun_ajaran : ''}{filters.semester ? ' Semester ' + filters.semester : ''}
                                        </div>
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
                                                            <svg className="w-3.5 h-3.5 mr-1 text-accent print:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
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
                                                                                        onClick={() => router.get(route('jadwal.edit', jadwal.id))}
                                                                                        className="text-emerald-300 hover:text-emerald-100 transform hover:scale-110 transition-all cursor-pointer"
                                                                                        title="Edit Jadwal"
                                                                                    >
                                                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                                                    </button>
                                                                                <div className="w-px h-4 bg-white/30"></div>
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
                                
                                {/* Print Footer */}
                                <div className="hidden print:block print-footer">
                                    Dicetak oleh: <span className="font-bold">{auth.user.name}</span>
                                    {' '}&mdash;{' '}
                                    {new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                                    <div className="signature-line"></div>
                                </div>

                                <div className="mt-6 bg-info-light border-l-4 border-info p-4 rounded-r-card">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <svg className="h-5 w-5 text-info" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <p className="text-sm text-info">
                                            Sistem otomatis menolak penambahan jadwal jika terdeteksi adanya bentrok (overlap waktu pada hari yang sama) untuk pegawai yang sama, bahkan jika berbeda unit sekolah.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            </> : <>
                                <div className="space-y-3">
                                {jadwals.length === 0 ? (
                                    <div className="empty-state py-12">
                                        <svg className="w-12 h-12 mx-auto mb-3 text-border" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <p className="empty-state-desc">Belum ada jadwal untuk Anda.</p>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {jadwals.map(j => (
                                            <div key={j.id} className="card-hover p-4 flex items-center gap-4">
                                                <div className="w-12 h-12 rounded-full bg-primary/5 border border-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                                                    {j.hari.substring(0, 3)}
                                                </div>
                                                <div className="flex-1">
                                                    <div className="font-bold text-primary">{j.jenis_jadwal}</div>
                                                    <div className="text-sm text-text-secondary">{j.jam_mulai?.substring(0, 5)} - {j.jam_selesai?.substring(0, 5)}</div>
                                                    {j.mata_pelajaran?.nama && <div className="text-xs text-text-secondary">{j.mata_pelajaran.nama}</div>}
                                                </div>
                                                <div className="text-xs text-text-secondary">{j.unit_sekolah?.singkatan}</div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                </div>
            </>}
                        </div>
                    </div>
                </div>
            </div>

            {isAdmin && <Modal show={showGenerateModal} onClose={() => setShowGenerateModal(false)} maxWidth="md">
                <div className="px-6 py-5">
                    <div className="flex items-center gap-3 mb-4">
                        <div className="w-10 h-10 rounded-full bg-warning-light flex items-center justify-center">
                            <svg className="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <div>
                            <h3 className="page-title">Generate Jadwal Otomatis</h3>
                            <p className="page-subtitle">Algoritma mengisi jadwal mengajar per pegawai secara acak tanpa bentrok</p>
                        </div>
                    </div>

                    <div className="bg-warning-light border border-warning/30 rounded-card p-3 text-xs text-warning mb-4 flex items-start gap-2">
                        <svg className="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        <span>Jadwal yang <strong>sudah ada tidak akan dihapus</strong>. Generate hanya menambah jadwal baru jika tidak ada bentrok. Anda bisa generate berulang kali.</span>
                    </div>

                    <form onSubmit={handleGenerate} className="space-y-4">
                        {/* Unit + Semester baris pertama */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="form-label mb-1">Unit Sekolah</label>
                                <select
                                    value={genData.unit_sekolah_id}
                                    onChange={e => setGenData({...genData, unit_sekolah_id: e.target.value})}
                                    className="select-field"
                                >
                                    <option value="">Semua Unit</option>
                                    {units.map(unit => (
                                        <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                    ))}
                                </select>
                                {selectedUnitPegCount > 0 && (
                                    <p className="mt-1 text-xs text-gray-500">≈ {selectedUnitPegCount} pegawai aktif</p>
                                )}
                            </div>
                            <div>
                                <label className="form-label mb-1">Semester</label>
                                <select 
                                    value={genData.semester} 
                                    onChange={e => setGenData({...genData, semester: e.target.value})}
                                    className="select-field"
                                >
                                    <option value="1">1 (Ganjil)</option>
                                    <option value="2">2 (Genap)</option>
                                </select>
                            </div>
                        </div>

                        {/* Tahun Ajaran */}
                        <div>
                                <label className="form-label mb-1">Tahun Ajaran</label>
                                <select
                                value={genData.tahun_ajaran}
                                onChange={e => setGenData({...genData, tahun_ajaran: e.target.value})}
                                    className="select-field"
                                >
                                    <option value="2024/2025">2024/2025</option>
                                <option value="2025/2026">2025/2026</option>
                                <option value="2026/2027">2026/2027</option>
                                <option value="2027/2028">2027/2028</option>
                                <option value="2028/2029">2028/2029</option>
                            </select>
                        </div>

                        {/* Batas Waktu */}
                        <div>
                                <label className="form-label mb-1">Rentang Jam</label>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="relative">
                                    <span className="absolute -top-2 left-3 text-[10px] text-text-muted bg-white px-1">Mulai</span>
                                    <input 
                                        type="time" 
                                        value={genData.waktu_mulai} 
                                        onChange={e => setGenData({...genData, waktu_mulai: e.target.value})}
                                        className="input-field pt-2"
                                    />
                                </div>
                                <div className="relative">
                                    <span className="absolute -top-2 left-3 text-[10px] text-text-muted bg-white px-1">Selesai</span>
                                    <input 
                                        type="time" 
                                        value={genData.waktu_selesai} 
                                        onChange={e => setGenData({...genData, waktu_selesai: e.target.value})}
                                        className="input-field pt-2"
                                    />
                                </div>
                            </div>
                            <p className="form-hint">Blok waktu default: 07:00-09:00, 09:30-11:30, 13:00-15:00. Filter ini mempersempit blok yang digunakan.</p>
                        </div>

                        {/* Tombol */}
                        <div className="flex justify-end gap-3 pt-2 border-t border-border">
                            <button 
                                type="button" 
                                onClick={() => setShowGenerateModal(false)}
                                disabled={generating}
                                className="btn-secondary"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                disabled={generating}
                                className="btn-primary bg-accent text-primary-800 hover:bg-yellow-500"
                            >
                                {generating ? (
                                    <>
                                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Memproses...
                                    </>
                                ) : 'Generate Jadwal'}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>}

            {isAdmin && <Modal show={showSwapModal} onClose={() => setShowSwapModal(false)} maxWidth="lg">
                <div className="px-6 py-5">
                    <div className="flex items-center gap-3 mb-5">
                        <div className="w-10 h-10 rounded-full bg-info-light flex items-center justify-center shrink-0">
                            <svg className="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                        </div>
                        <div>
                            <h3 className="text-lg font-bold text-primary">Tukar Jadwal</h3>
                            <p className="text-sm text-text-secondary">Pilih pegawai dan jadwal target untuk ditukar kepemilikannya.</p>
                        </div>
                    </div>

                    {errors?.conflict && (
                        <div className="mb-4 bg-danger-light text-danger p-3 rounded-card text-sm border border-danger/20">
                            {errors.conflict}
                        </div>
                    )}

                    {/* Asal */}
                    <div className="mb-4">
                        <label className="form-label font-semibold mb-2 flex items-center gap-2">
                            <span className="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center font-bold">1</span>
                            Jadwal Asal
                        </label>
                        {(() => {
                            const j = jadwals.find(j => j.id === swapData.jadwal_asal_id);
                            if (!j) return <div className="p-3 bg-surface border border-border rounded-card text-sm text-text-muted">Memuat...</div>;
                            return (
                                <div className="p-3 bg-surface border border-primary/20 rounded-card">
                                    <div className="font-bold text-primary text-sm">{j.pegawai?.nama_lengkap}</div>
                                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-text-secondary">
                                        <span>{j.hari}, {j.jam_mulai?.substring(0,5)}-{j.jam_selesai?.substring(0,5)}</span>
                                        <span className="font-medium text-primary/70 uppercase">{j.jenis_jadwal}</span>
                                        {j.mata_pelajaran?.nama && <span>{j.mata_pelajaran.nama}</span>}
                                        {j.kelas_label && <span>Kls: {j.kelas_label}</span>}
                                        <span>{j.unit_sekolah?.singkatan || '-'}</span>
                                    </div>
                                </div>
                            );
                        })()}
                    </div>

                    {/* Target Pegawai */}
                    <div className="mb-4">
                        <label className="form-label font-semibold mb-2 flex items-center gap-2">
                            <span className="w-5 h-5 rounded-full bg-accent text-primary-800 text-xs flex items-center justify-center font-bold">2</span>
                            Tukar Dengan Pegawai
                        </label>
                        <select
                            value={targetPegawaiId}
                            onChange={e => {
                                setTargetPegawaiId(e.target.value);
                                setSwapData({...swapData, jadwal_tujuan_id: ''});
                            }}
                            className="select-field"
                        >
                            <option value="">-- Pilih Pegawai --</option>
                            {(() => {
                                const asal = jadwals.find(j => j.id === swapData.jadwal_asal_id);
                                const unitId = asal?.unit_sekolah_id;
                                return pegawais.filter(p => {
                                    if (asal && asal.pegawai_id === p.id) return false;
                                    if (unitId) return p.units?.some(u => u.id == unitId);
                                    return true;
                                }).map(p => (
                                    <option key={p.id} value={p.id}>{p.nama_lengkap}</option>
                                ));
                            })()}
                        </select>
                    </div>

                    {/* Target Jadwal */}
                    {targetPegawaiId && (
                        <div className="mb-4">
                            <label className="form-label font-semibold mb-2 flex items-center gap-2">
                                <span className="w-5 h-5 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center font-bold">3</span>
                                Pilih Jadwal Target
                            </label>
                            <div className="space-y-1.5 max-h-48 overflow-y-auto">
                                {(() => {
                                    const asal = jadwals.find(j => j.id === swapData.jadwal_asal_id);
                                    const unitId = asal?.unit_sekolah_id;
                                    const targets = jadwals.filter(j => j.pegawai_id == targetPegawaiId && (!unitId || j.unit_sekolah_id == unitId));
                                    if (targets.length === 0) {
                                        return <p className="p-3 bg-surface border border-border rounded-card text-sm text-text-muted">Pegawai ini tidak memiliki jadwal.</p>;
                                    }
                                    return targets.map(j => (
                                        <button
                                            key={j.id}
                                            type="button"
                                            onClick={() => setSwapData({...swapData, jadwal_tujuan_id: j.id})}
                                            className={`w-full text-left p-3 rounded-card border text-sm transition-colors cursor-pointer ${
                                                swapData.jadwal_tujuan_id === j.id
                                                    ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                                    : 'border-border bg-surface hover:border-primary/40'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <span className="font-bold text-text-primary">{j.hari}, {j.jam_mulai?.substring(0,5)}-{j.jam_selesai?.substring(0,5)}</span>
                                                    <span className="ml-2 text-[10px] font-bold px-1.5 py-0.5 rounded uppercase bg-blue-50 text-blue-700 border border-blue-100">{j.jenis_jadwal}</span>
                                                </div>
                                                <div className="text-xs text-text-secondary text-right">
                                                    {j.mata_pelajaran?.nama && <div>{j.mata_pelajaran.nama}</div>}
                                                    {j.kelas_label && <div>{j.kelas_label}</div>}
                                                </div>
                                            </div>
                                            {swapData.jadwal_tujuan_id === j.id && (
                                                <div className="mt-1 text-xs text-primary font-medium">✓ Dipilih</div>
                                            )}
                                        </button>
                                    ));
                                })()}
                            </div>
                        </div>
                    )}

                    {/* Preview hasil tukar */}
                    {swapData.jadwal_asal_id && swapData.jadwal_tujuan_id && (() => {
                        const asal = jadwals.find(j => j.id === swapData.jadwal_asal_id);
                        const tujuan = jadwals.find(j => j.id === swapData.jadwal_tujuan_id);
                        if (!asal || !tujuan) return null;
                        return (
                            <div className="mb-4 p-3 bg-info-light border border-info/30 rounded-card">
                                <p className="text-xs font-bold text-info uppercase tracking-wider mb-2">Preview Hasil Tukar</p>
                                <div className="grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <p className="text-text-muted mb-0.5">← {asal.pegawai?.nama_lengkap} mendapat:</p>
                                        <p className="font-semibold text-text-primary">{tujuan.hari}, {tujuan.jam_mulai?.substring(0,5)}-{tujuan.jam_selesai?.substring(0,5)}</p>
                                    </div>
                                    <div>
                                        <p className="text-text-muted mb-0.5">→ {tujuan.pegawai?.nama_lengkap} mendapat:</p>
                                        <p className="font-semibold text-text-primary">{asal.hari}, {asal.jam_mulai?.substring(0,5)}-{asal.jam_selesai?.substring(0,5)}</p>
                                    </div>
                                </div>
                            </div>
                        );
                    })()}

                    <div className="flex justify-end gap-3 pt-4 border-t border-border">
                        <button type="button" onClick={() => setShowSwapModal(false)} className="btn-secondary">
                            Batal
                        </button>
                        <button
                            onClick={handleSwap}
                            disabled={!swapData.jadwal_tujuan_id}
                            className="btn-primary"
                        >
                            <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            Eksekusi Tukar
                        </button>
                    </div>
                </div>
            </Modal>}

            {isAdmin && <Modal show={showRekapModal} onClose={() => setShowRekapModal(false)} maxWidth="2xl">
                <div className="px-6 py-5 max-h-[90vh] overflow-y-auto">
                    <div className="flex items-center gap-3 mb-5">
                        <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                            <svg className="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <div>
                            <h3 className="text-lg font-bold text-primary">Rekap Kelas</h3>
                            <p className="text-sm text-text-secondary">Mapel, guru, dan total jam per kelas.</p>
                        </div>
                    </div>

                    {(() => {
                        const grouped = {};
                        jadwals.filter(j => j.kelas_label && j.jenis_jadwal === 'mengajar').forEach(j => {
                            if (!grouped[j.kelas_label]) grouped[j.kelas_label] = {};
                            const mapelId = j.mata_pelajaran?.id || 0;
                            const mapelName = j.mata_pelajaran?.nama || 'Tanpa Mapel';
                            if (!grouped[j.kelas_label][mapelId]) {
                                grouped[j.kelas_label][mapelId] = { nama: mapelName, guru: {} };
                            }
                            const pegNama = j.pegawai?.nama_lengkap || 'Tanpa Nama';
                            const [h1, m1] = (j.jam_mulai || '0:0').split(':').map(Number);
                            const [h2, m2] = (j.jam_selesai || '0:0').split(':').map(Number);
                            const durasi = (h2 * 60 + m2) - (h1 * 60 + m1);
                            if (!grouped[j.kelas_label][mapelId].guru[pegNama]) {
                                grouped[j.kelas_label][mapelId].guru[pegNama] = 0;
                            }
                            grouped[j.kelas_label][mapelId].guru[pegNama] += Math.max(0, durasi);
                        });

                        const entries = Object.entries(grouped).sort((a, b) => a[0].localeCompare(b[0]));
                        if (entries.length === 0) {
                            return <div className="p-6 text-center text-text-muted">Belum ada jadwal mengajar dengan kelas.</div>;
                        }

                        return (
                            <div className="space-y-6">
                                {entries.map(([kelas, mapels]) => {
                                    const mapelEntries = Object.entries(mapels);
                                    const totalJam = mapelEntries.reduce((sum, [, m]) => {
                                        return sum + Object.values(m.guru).reduce((a, b) => a + b, 0);
                                    }, 0);
                                    return (
                                        <div key={kelas} className="bg-surface border border-border rounded-card overflow-hidden">
                                            <div className="px-4 py-3 bg-primary/5 border-b border-border flex items-center justify-between">
                                                <h4 className="font-bold text-primary text-sm">{kelas}</h4>
                                                <span className="text-xs text-text-muted">{totalJam} mnt / {mapelEntries.length} mapel</span>
                                            </div>
                                            <div className="divide-y divide-border">
                                                {mapelEntries.sort((a, b) => a[1].nama.localeCompare(b[1].nama)).map(([mapelId, m]) => (
                                                    <div key={mapelId} className="px-4 py-2.5">
                                                        <div className="flex items-center justify-between">
                                                            <span className="font-medium text-sm text-text-primary">{m.nama}</span>
                                                            <span className="text-xs text-text-muted">{Object.values(m.guru).reduce((a, b) => a + b, 0)} mnt</span>
                                                        </div>
                                                        <div className="mt-1 flex flex-wrap gap-1.5">
                                                            {Object.entries(m.guru).sort((a, b) => b[1] - a[1]).map(([guru, menit]) => (
                                                                <span key={guru} className="inline-flex items-center gap-1 text-xs bg-white border border-border px-2 py-0.5 rounded-full">
                                                                    <span className="text-text-primary">{guru}</span>
                                                                    <span className="text-text-muted">{menit}m</span>
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })()}

                    <div className="mt-6 pt-4 border-t border-border flex justify-end">
                        <button type="button" onClick={() => setShowRekapModal(false)} className="btn-secondary">
                            Tutup
                        </button>
                    </div>
                </div>
            </Modal>}

        </AuthenticatedLayout>
    );
}
