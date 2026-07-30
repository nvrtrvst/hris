import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';

export default function Index({ auth, mapels }) {
    const { data, setData, post, reset, processing, errors, clearErrors } = useForm({
        nama: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('mata-pelajaran.store'), {
            onSuccess: () => {
                reset();
                clearErrors();
            }
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Referensi Mata Pelajaran</h2>}
        >
            <Head title="Mata Pelajaran" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-[1400px] mx-auto sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-8">

                    {/* Form Section */}
                    <div className="w-full lg:w-1/3">
                        <div className="card p-6 sticky top-6">
                            <h3 className="section-title text-lg font-bold text-text-primary mb-2 pb-2 border-b border-border">Tambah Mata Pelajaran</h3>
                            <p className="form-hint mb-4">
                                Master ini dipakai saat menugaskan mapel ke guru (Pegawai) dan saat membuat Jadwal.
                            </p>

                            <form onSubmit={submit} className="form-section space-y-4">
                                <div>
                                    <label className="form-label">Nama Mata Pelajaran</label>
                                    <input type="text" value={data.nama} onChange={e => setData('nama', e.target.value)} placeholder="Contoh: Matematika" className="input-field" />
                                    {errors.nama && <p className="form-error">{errors.nama}</p>}
                                </div>

                                <div className="pt-4 flex justify-end space-x-3">
                                    <button type="submit" disabled={processing} className="btn-primary">
                                        {processing ? 'Menyimpan...' : 'Simpan'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Table Section */}
                    <div className="w-full lg:w-2/3">
                        <div className="card">
                            <div className="px-6 py-5 border-b border-border bg-surface flex justify-between items-center">
                                <h3 className="section-title text-lg font-bold text-text-primary">Daftar Mata Pelajaran</h3>
                                <span className="text-sm text-text-muted">{mapels.length} item</span>
                            </div>
                            <div className="p-0 overflow-x-auto">
                                <table className="table-base min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Nama Mata Pelajaran</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-text-muted uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border">
                                        {mapels.length > 0 ? mapels.map((m) => (
                                            <tr key={m.id} className="hover:bg-surface group">
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-bold text-text-primary">{m.nama}</div>
                                                </td>
                                                <td className="px-6 py-4 text-right text-sm font-medium">
                                                    <button
                                                        onClick={() => {
                                                            if (confirm(`Hapus mata pelajaran "${m.nama}"?`)) {
                                                                router.delete(route('mata-pelajaran.destroy', m.id));
                                                            }
                                                        }}
                                                        className="btn-danger btn-sm"
                                                    >
                                                        Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="2" className="px-6 py-4 text-center text-sm text-text-muted">Belum ada mata pelajaran.</td>
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
