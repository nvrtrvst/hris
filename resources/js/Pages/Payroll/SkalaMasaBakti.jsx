import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';

export default function SkalaMasaBakti({ auth, skalas }) {
    const { data, setData, post, reset, processing, errors, clearErrors } = useForm({
        masa_kerja_tahun: '',
        nominal_gaji: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('skala-masa-bakti.store'), {
            onSuccess: () => {
                reset();
                clearErrors();
            }
        });
    };

    const formatRupiah = (angka) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-primary leading-tight">Pengaturan Skala Gaji Masa Bakti</h2>}
        >
            <Head title="Skala Gaji Masa Bakti" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-[1400px] mx-auto sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-8">

                    <div className="w-full lg:w-1/3">
                        <div className="page-card sticky top-6">
                            <div className="page-card-header">
                                <h3 className="section-title mb-0 uppercase">Tambah Skala</h3>
                            </div>

                            <form onSubmit={submit} className="form-section">
                                <div>
                                    <label className="form-label">Masa Kerja (Tahun)</label>
                                    <input type="number" value={data.masa_kerja_tahun} onChange={e => setData('masa_kerja_tahun', e.target.value)} placeholder="Contoh: 1" className="input-field" />
                                    {errors.masa_kerja_tahun && <p className="form-error">{errors.masa_kerja_tahun}</p>}
                                </div>

                                <div>
                                    <label className="form-label">Nominal Gaji Pokok</label>
                                    <input type="number" step="0.01" value={data.nominal_gaji} onChange={e => setData('nominal_gaji', e.target.value)} placeholder="Contoh: 2000000" className="input-field" />
                                    {errors.nominal_gaji && <p className="form-error">{errors.nominal_gaji}</p>}
                                </div>

                                <div className="pt-4 flex justify-end space-x-3">
                                    <button type="submit" disabled={processing} className="btn-primary">
                                        {processing ? 'Menyimpan...' : 'Simpan Skala'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div className="w-full lg:w-2/3">
                        <div className="card-table">
                            <div className="px-6 py-5 border-b border-border bg-white/50 flex justify-between items-center">
                                <h3 className="text-lg font-bold text-text-primary">Daftar Skala Masa Bakti</h3>
                            </div>
                            <div className="table-wrap">
                                <table className="table-base">
                                    <thead className="bg-surface/50 border-b border-border">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Masa Kerja (Tahun)</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Nominal Gaji Pokok</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-text-secondary uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border/50">
                                        {skalas.length > 0 ? skalas.map((s) => (
                                            <tr key={s.id} className="hover:bg-surface/50 group">
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-bold text-text-primary">{s.masa_kerja_tahun} Tahun</div>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-text-primary font-medium">
                                                    {formatRupiah(s.nominal_gaji)}
                                                </td>
                                                <td className="px-6 py-4 text-right text-sm font-medium">
                                                    <button
                                                        onClick={() => {
                                                            if(confirm('Hapus skala ini?')) {
                                                                router.delete(route('skala-masa-bakti.destroy', s.id));
                                                            }
                                                        }}
                                                        className="text-danger hover:text-danger/80 font-medium"
                                                    >
                                                        Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="3" className="px-6 py-4 text-center text-sm text-text-muted">Belum ada skala yang ditambahkan.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
