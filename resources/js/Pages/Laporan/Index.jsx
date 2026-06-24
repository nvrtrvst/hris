import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import InputLabel from '@/Components/InputLabel';
import axios from 'axios';

export default function LaporanIndex({ auth, units }) {
    const d = new Date();
    const currentMonth = d.getMonth() + 1;
    const currentYear = d.getFullYear();
    const firstDay = `${currentYear}-${String(currentMonth).padStart(2, '0')}-01`;
    const today = d.toISOString().split('T')[0];

    const [filter, setFilter] = useState({
        start_date: firstDay,
        end_date: today,
        report_type: 'presensi',
        unit_sekolah_id: ''
    });

    const [previewData, setPreviewData] = useState(null);
    const [activePreview, setActivePreview] = useState(null);
    const [loading, setLoading] = useState(false);

    const handlePreview = async () => {
        setLoading(true);
        try {
            const res = await axios.get(route('laporan.preview'), {
                params: {
                    type: filter.report_type,
                    start_date: filter.start_date,
                    end_date: filter.end_date,
                    unit_sekolah_id: filter.unit_sekolah_id
                }
            });
            setPreviewData(res.data);
            setActivePreview({ ...filter });
        } catch (error) {
            console.error("Preview failed", error);
            alert("Gagal memuat pratinjau data. Pastikan rentang tanggal valid.");
        }
        setLoading(false);
    };

    const handleDownload = () => {
        let url = '';
        if (filter.report_type === 'presensi') url = route('laporan.presensi');
        if (filter.report_type === 'penggajian') url = route('laporan.penggajian');
        if (filter.report_type === 'lemburan') url = route('laporan.lemburan');

        const params = new URLSearchParams();
        params.append('start_date', filter.start_date);
        params.append('end_date', filter.end_date);
        if (filter.unit_sekolah_id) {
            params.append('unit_sekolah_id', filter.unit_sekolah_id);
        }

        window.location.href = `${url}?${params.toString()}`;
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-bold text-xl text-primary leading-tight">Modul Laporan</h2>}
        >
            <Head title="Laporan" />

            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                {/* Filter Section - Standard Report Layout */}
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-border">
                    <div className="p-6 bg-surface">
                        <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-6">
                            <div className="flex-1 w-full grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <InputLabel htmlFor="report_type" value="Jenis Laporan" />
                                    <select 
                                        id="report_type" 
                                        className="mt-1 block w-full border-gray-300 focus:border-accent focus:ring-accent rounded-md shadow-sm"
                                        value={filter.report_type}
                                        onChange={e => setFilter({...filter, report_type: e.target.value})}
                                    >
                                        <option value="presensi">Laporan Presensi</option>
                                        <option value="penggajian">Laporan Rekap Gaji</option>
                                        <option value="lemburan">Laporan Detail Lembur & Potongan</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel htmlFor="start_date" value="Tanggal Mulai" />
                                    <input 
                                        type="date"
                                        id="start_date" 
                                        className="mt-1 block w-full border-gray-300 focus:border-accent focus:ring-accent rounded-md shadow-sm"
                                        value={filter.start_date}
                                        onChange={e => setFilter({...filter, start_date: e.target.value})}
                                    />
                                </div>
                                <div>
                                    <InputLabel htmlFor="end_date" value="Tanggal Akhir" />
                                    <input 
                                        type="date"
                                        id="end_date" 
                                        className="mt-1 block w-full border-gray-300 focus:border-accent focus:ring-accent rounded-md shadow-sm"
                                        value={filter.end_date}
                                        onChange={e => setFilter({...filter, end_date: e.target.value})}
                                    />
                                </div>
                                <div>
                                    <InputLabel htmlFor="unit" value="Unit Sekolah (Opsional)" />
                                    <select 
                                        id="unit" 
                                        className="mt-1 block w-full border-gray-300 focus:border-accent focus:ring-accent rounded-md shadow-sm"
                                        value={filter.unit_sekolah_id}
                                        onChange={e => setFilter({...filter, unit_sekolah_id: e.target.value})}
                                    >
                                        <option value="">-- Semua Unit Sekolah --</option>
                                        {units.map(u => (
                                            <option key={u.id} value={u.id}>{u.nama}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div className="flex space-x-3 pt-4 border-t border-gray-200">
                            <button onClick={handlePreview} className="flex-1 md:flex-none py-2 px-6 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-secondary focus:outline-none">
                                Tampilkan Data
                            </button>
                            <button onClick={handleDownload} className="flex-1 md:flex-none py-2 px-6 border border-primary text-primary rounded-md shadow-sm text-sm font-medium hover:bg-primary/5 focus:outline-none">
                                Download Excel
                            </button>
                        </div>
                    </div>
                </div>

                {/* Preview Table Section */}
                {loading && (
                    <div className="flex justify-center items-center py-12">
                        <svg className="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span className="ml-3 text-lg font-medium text-primary">Memuat Data Pratinjau...</span>
                    </div>
                )}

                {!loading && previewData && activePreview && (
                    <div className="bg-white rounded-xl shadow-sm border border-border p-6 overflow-hidden animate-fade-in-up">
                        <div className="flex justify-between items-center mb-6 border-b pb-4">
                            <div>
                                <h3 className="text-lg font-bold text-primary uppercase">
                                    Pratinjau: Laporan {activePreview.report_type}
                                </h3>
                                <p className="text-sm text-text-secondary mt-1">
                                    Periode: {activePreview.start_date} s/d {activePreview.end_date}
                                </p>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full whitespace-nowrap">
                                <thead>
                                    <tr className="bg-gray-100 text-left">
                                        {previewData.headings.map((head, idx) => (
                                            <th key={idx} className="p-3 font-semibold text-sm text-gray-700">{head}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {previewData.data.map((row, rowIdx) => (
                                        <tr key={rowIdx} className="border-b border-gray-100 hover:bg-gray-50">
                                            {row.map((cell, cellIdx) => {
                                                const headerStr = previewData.headings[cellIdx] ? previewData.headings[cellIdx].toLowerCase() : '';
                                                const isCurrency = headerStr.includes('(rp)') || headerStr.includes('nominal');
                                                let displayValue = cell;
                                                
                                                if (isCurrency && cell !== null && cell !== '-' && !isNaN(cell)) {
                                                    displayValue = new Intl.NumberFormat('id-ID').format(cell);
                                                }
                                                
                                                return (
                                                    <td key={cellIdx} className="p-3 text-sm text-gray-600">{displayValue}</td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                    {previewData.data.length === 0 && (
                                        <tr>
                                            <td colSpan={previewData.headings.length} className="p-8 text-center text-gray-500">
                                                Tidak ada data untuk filter dan rentang tanggal yang dipilih.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
