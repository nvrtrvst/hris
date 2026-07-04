import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Keuangan({ auth, pegawai, komponens }) {
    // Siapkan initial data
    const initialKomponens = {};
    komponens.forEach(k => {
        const pivot = pegawai.komponen_gaji.find(kg => kg.id === k.id);
        initialKomponens[k.id] = pivot ? pivot.pivot.nominal : '';
    });

    const { data, setData, post, processing } = useForm({
        komponens: initialKomponens
    });

    const handleNominalChange = (komponenId, value) => {
        setData('komponens', { ...data.komponens, [komponenId]: value });
    };

    const handleSave = (e) => {
        e.preventDefault();
        post(route('pegawai.keuangan.update', pegawai.id));
    };

    const formatRupiah = (angka) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-bold text-xl text-gray-800 leading-tight">
                        <Link href={route('pegawai.index')} className="text-gray-400 hover:text-gray-600 mr-2">Pegawai /</Link>
                        Profil Keuangan: {pegawai.nama_lengkap}
                    </h2>
                    <Link href={route('pegawai.show', pegawai.id)} className="text-sm font-semibold text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-4 py-2 rounded-full">
                        Lihat Profil Lengkap &rarr;
                    </Link>
                </div>
            }
        >
            <Head title={`Keuangan - ${pegawai.nama_lengkap}`} />

            <div className="py-8 bg-gray-50 min-h-screen">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    
                    <form onSubmit={handleSave} className="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div className="p-6 border-b border-gray-100 bg-gray-50/50">
                            <div className="flex items-center gap-4">
                                <div className="h-16 w-16 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <h3 className="text-xl font-bold text-gray-900">{pegawai.nama_lengkap}</h3>
                                    <p className="text-sm text-gray-500 font-mono mt-1">NIK: {pegawai.nik}</p>
                                </div>
                            </div>
                        </div>

                        <div className="p-6">
                            <p className="text-sm text-gray-600 mb-6 bg-blue-50 text-blue-800 p-4 rounded-xl border border-blue-100">
                                Halaman ini digunakan untuk mengeset nominal gaji khusus yang berbeda dari standar yayasan. 
                                <strong className="block mt-1">Biarkan kosong jika ingin menggunakan nilai default sistem.</strong>
                            </p>

                            <div className="space-y-6">
                                {komponens.map(k => (
                                    <div key={k.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition-colors">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className={`w-2 h-2 rounded-full ${k.tipe === 'pendapatan' ? 'bg-green-500' : 'bg-red-500'}`}></span>
                                                <h4 className="font-bold text-gray-900 text-sm">{k.nama}</h4>
                                                <span className="text-[10px] uppercase font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">{k.jenis.replace('_', ' ')}</span>
                                            </div>
                                            <p className="text-xs text-gray-500 mt-1">
                                                Default Sistem: <strong className="text-gray-700">{formatRupiah(k.nilai_default || 0)}</strong>
                                            </p>
                                        </div>
                                        <div className="w-full sm:w-64">
                                            <div className="relative">
                                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span className="text-gray-500 sm:text-sm">Rp</span>
                                                </div>
                                                <input
                                                    type="number"
                                                    value={data.komponens[k.id]}
                                                    onChange={(e) => handleNominalChange(k.id, e.target.value)}
                                                    placeholder="Gunakan Default"
                                                    className="pl-10 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm sm:text-sm transition-shadow"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                            <PrimaryButton disabled={processing} className="px-8 py-3 bg-indigo-600 hover:bg-indigo-700">
                                {processing ? 'Menyimpan...' : 'Simpan Profil Keuangan'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
