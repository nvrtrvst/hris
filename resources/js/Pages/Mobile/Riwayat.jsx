import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Calendar as CalendarIcon, Clock, MapPin, ChevronDown, CheckCircle, XCircle, AlertCircle, FileText } from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';

export default function Riwayat({ auth, presensi, filters }) {
    const [bulan, setBulan] = useState(filters?.bulan || format(new Date(), 'MM'));
    const [tahun, setTahun] = useState(filters?.tahun || format(new Date(), 'yyyy'));

    const handleFilterChange = (newBulan, newTahun) => {
        router.get(route('mobile.riwayat'), {
            bulan: newBulan,
            tahun: newTahun
        }, {
            preserveState: true,
            replace: true
        });
    };

    const getStatusStyle = (status) => {
        switch(status?.toLowerCase()) {
            case 'hadir':
                return { bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200', icon: <CheckCircle className="w-4 h-4 mr-1.5" /> };
            case 'telat':
                return { bg: 'bg-yellow-50', text: 'text-yellow-700', border: 'border-yellow-200', icon: <Clock className="w-4 h-4 mr-1.5" /> };
            case 'izin':
            case 'cuti':
                return { bg: 'bg-indigo-50', text: 'text-indigo-700', border: 'border-indigo-200', icon: <FileText className="w-4 h-4 mr-1.5" /> };
            case 'sakit':
                return { bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200', icon: <AlertCircle className="w-4 h-4 mr-1.5" /> };
            case 'alpha':
                return { bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200', icon: <XCircle className="w-4 h-4 mr-1.5" /> };
            default:
                return { bg: 'bg-gray-50', text: 'text-gray-700', border: 'border-gray-200', icon: <Clock className="w-4 h-4 mr-1.5" /> };
        }
    };

    const bulanOptions = [
        { value: '01', label: 'Januari' },
        { value: '02', label: 'Februari' },
        { value: '03', label: 'Maret' },
        { value: '04', label: 'April' },
        { value: '05', label: 'Mei' },
        { value: '06', label: 'Juni' },
        { value: '07', label: 'Juli' },
        { value: '08', label: 'Agustus' },
        { value: '09', label: 'September' },
        { value: '10', label: 'Oktober' },
        { value: '11', label: 'November' },
        { value: '12', label: 'Desember' }
    ];

    const tahunOptions = Array.from({length: 5}, (_, i) => {
        const year = new Date().getFullYear() - i;
        return { value: year.toString(), label: year.toString() };
    });

    return (
        <MobileLayout user={auth.user} header="Riwayat Kehadiran">
            <Head title="Riwayat Kehadiran" />

            {/* Filter Section */}
            <div className="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-6 flex gap-3 sticky top-4 z-10">
                <div className="relative flex-1">
                    <select
                        value={bulan}
                        onChange={(e) => {
                            setBulan(e.target.value);
                            handleFilterChange(e.target.value, tahun);
                        }}
                        className="w-full appearance-none bg-gray-50 border border-gray-200 text-gray-700 py-2.5 px-4 pr-8 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-bold shadow-sm"
                    >
                        {bulanOptions.map(opt => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                        <ChevronDown className="w-4 h-4" />
                    </div>
                </div>
                
                <div className="relative w-1/3">
                    <select
                        value={tahun}
                        onChange={(e) => {
                            setTahun(e.target.value);
                            handleFilterChange(bulan, e.target.value);
                        }}
                        className="w-full appearance-none bg-gray-50 border border-gray-200 text-gray-700 py-2.5 px-4 pr-8 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-bold shadow-sm"
                    >
                        {tahunOptions.map(opt => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                        <ChevronDown className="w-4 h-4" />
                    </div>
                </div>
            </div>

            {/* Content List */}
            <div className="space-y-4 pb-8">
                {presensi.length === 0 ? (
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center mt-10">
                        <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <CalendarIcon className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-gray-900 font-bold mb-1">Data Kosong</h3>
                        <p className="text-gray-500 text-sm">Tidak ada riwayat kehadiran pada bulan ini.</p>
                    </div>
                ) : (
                    presensi.map((item) => {
                        const style = getStatusStyle(item.status);
                        const isWeekend = new Date(item.tanggal).getDay() === 0 || new Date(item.tanggal).getDay() === 6;
                        
                        return (
                            <div key={item.id} className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 relative overflow-hidden transition-all hover:shadow-md">
                                <div className="flex justify-between items-start mb-3">
                                    <div>
                                        <div className={`text-xs font-bold uppercase tracking-wider mb-1 ${isWeekend ? 'text-red-500' : 'text-indigo-600'}`}>
                                            {format(parseISO(item.tanggal), 'EEEE', { locale: idLocale })}
                                        </div>
                                        <div className="text-gray-900 font-bold text-lg">
                                            {format(parseISO(item.tanggal), 'dd MMMM yyyy', { locale: idLocale })}
                                        </div>
                                    </div>
                                    <div className={`inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold border ${style.bg} ${style.text} ${style.border}`}>
                                        {style.icon}
                                        {item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : 'Tidak Ada'}
                                    </div>
                                </div>
                                
                                {['hadir', 'telat'].includes(item.status?.toLowerCase()) ? (
                                    <div className="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-4">
                                        <div className="flex items-center">
                                            <div className="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center mr-3 border border-indigo-100">
                                                <Clock className="w-4 h-4 text-indigo-600" />
                                            </div>
                                            <div>
                                                <div className="text-xs text-gray-500 mb-0.5">Masuk</div>
                                                <div className="font-bold text-gray-800 font-mono text-sm">{item.jam_masuk ? item.jam_masuk.substring(0, 5) : '--:--'}</div>
                                            </div>
                                        </div>
                                        <div className="flex items-center">
                                            <div className="w-8 h-8 rounded-full bg-orange-50 flex items-center justify-center mr-3 border border-orange-100">
                                                <Clock className="w-4 h-4 text-orange-600" />
                                            </div>
                                            <div>
                                                <div className="text-xs text-gray-500 mb-0.5">Keluar</div>
                                                <div className="font-bold text-gray-800 font-mono text-sm">{item.jam_keluar ? item.jam_keluar.substring(0, 5) : '--:--'}</div>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="mt-3 pt-3 border-t border-gray-100">
                                        <p className="text-sm text-gray-600">
                                            {item.keterangan || (item.status === 'alpha' ? 'Tanpa Keterangan' : 'Sesuai Pengajuan Sistem')}
                                        </p>
                                    </div>
                                )}
                            </div>
                        );
                    })
                )}
            </div>
        </MobileLayout>
    );
}
