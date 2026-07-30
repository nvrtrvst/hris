import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Keuangan({ auth, pegawai, komponens }) {
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
                    <h2 className="font-bold text-xl text-text-primary leading-tight">
                        <Link href={route('pegawai.index')} className="text-text-muted hover:text-text-secondary mr-2">Pegawai /</Link>
                        Profil Keuangan: {pegawai.nama_lengkap}
                    </h2>
                    <Link href={route('pegawai.show', pegawai.id)} className="text-sm font-semibold text-primary bg-primary-50 px-4 py-2 rounded-full">
                        Lihat Profil Lengkap &rarr;
                    </Link>
                </div>
            }
        >
            <Head title={`Keuangan - ${pegawai.nama_lengkap}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

                    <form onSubmit={handleSave} className="page-card p-0">
                        <div className="p-6 border-b border-border bg-surface/80">
                            <div className="flex items-center gap-4">
                                <div className="stat-icon bg-primary-100 text-primary">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <h3 className="text-xl font-bold text-text-primary">{pegawai.nama_lengkap}</h3>
                                    <p className="text-sm text-text-muted font-mono mt-1">NIK: {pegawai.nik}</p>
                                </div>
                            </div>
                        </div>

                        <div className="p-6">
                            <div className="bg-info-light text-info p-4 rounded-card border border-info mb-6">
                                Halaman ini digunakan untuk mengeset nominal gaji khusus yang berbeda dari standar yayasan.
                                <strong className="block mt-1">Biarkan kosong jika ingin menggunakan nilai default sistem.</strong>
                            </div>

                            <div className="form-section">
                                {komponens.map(k => (
                                    <div key={k.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-card border border-border hover:border-primary hover:bg-primary-50/30 transition-colors">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className={`w-2 h-2 rounded-full ${k.tipe === 'pendapatan' ? 'bg-success' : 'bg-danger'}`}></span>
                                                <h4 className="font-bold text-text-primary text-sm">{k.nama}</h4>
                                                <span className="text-[10px] uppercase font-bold text-text-muted bg-surface px-2 py-0.5 rounded-full">{k.jenis.replace('_', ' ')}</span>
                                            </div>
                                            <p className="text-xs text-text-muted mt-1">
                                                Default Sistem: <strong className="text-text-primary">{formatRupiah(k.nilai_default || 0)}</strong>
                                            </p>
                                        </div>
                                        <div className="w-full sm:w-64">
                                            <div className="relative">
                                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span className="text-text-muted sm:text-sm">Rp</span>
                                                </div>
                                                <input
                                                    type="number"
                                                    value={data.komponens[k.id]}
                                                    onChange={(e) => handleNominalChange(k.id, e.target.value)}
                                                    placeholder="Gunakan Default"
                                                    className="input-field pl-10"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="px-6 py-4 bg-surface border-t border-border flex justify-end">
                            <PrimaryButton disabled={processing} className="btn-primary">
                                {processing ? 'Menyimpan...' : 'Simpan Profil Keuangan'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
