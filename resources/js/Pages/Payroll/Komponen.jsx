import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { formatRupiah } from '@/Utils/format';

export default function Komponen({ auth, komponens, units }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        nama: '',
        kode: '',
        tipe: 'pendapatan',
        jenis: 'fixed',
        applies_to_status_kepegawaian: '',
        syarat_bayar_jam_mengajar: '',
        nilai_default: '',
        unit_sekolah_id: '',
        is_taxable: true,
        is_active: true,
        urutan: 99,
        tampil_di_matrix: true,
    });

    const handleEdit = (k) => {
        setIsEditing(true);
        setEditId(k.id);
        setData({
            nama: k.nama,
            kode: k.kode || '',
            tipe: k.tipe,
            jenis: k.jenis,
            applies_to_status_kepegawaian: k.applies_to_status_kepegawaian || '',
            syarat_bayar_jam_mengajar: k.syarat_bayar_jam_mengajar || '',
            nilai_default: k.nilai_default || '',
            unit_sekolah_id: k.unit_sekolah_id || '',
            is_taxable: k.is_taxable == 1,
            is_active: k.is_active == 1,
            urutan: k.urutan || 99,
            tampil_di_matrix: k.tampil_di_matrix == 1,
        });
        clearErrors();
    };

    const handleCancel = () => {
        setIsEditing(false);
        setEditId(null);
        reset();
        clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('komponen-gaji.update', editId), {
                onSuccess: () => handleCancel()
            });
        } else {
            post(route('komponen-gaji.store'), {
                onSuccess: () => handleCancel()
            });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-primary leading-tight">Pengaturan Komponen Gaji</h2>}
        >
            <Head title="Komponen Gaji" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-[1400px] mx-auto sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-8">

                    <div className="w-full lg:w-1/3">
                        <div className="page-card sticky top-6">
                            <div className="page-card-header">
                                <h3 className="section-title mb-0 uppercase">{isEditing ? 'Edit Komponen' : 'Tambah Komponen'}</h3>
                            </div>

                            <form onSubmit={submit} className="form-section">
                                <div>
                                    <label className="form-label">Nama Komponen</label>
                                    <input type="text" value={data.nama} onChange={e => setData('nama', e.target.value)} placeholder="Contoh: Gaji Pokok / PPh21" className="input-field" />
                                    {errors.nama && <p className="form-error">{errors.nama}</p>}
                                </div>

                                <div>
                                    <label className="form-label">Kode (stabil untuk logika)</label>
                                    <input type="text" value={data.kode} onChange={e => setData('kode', e.target.value)} placeholder="Contoh: gaji_pokok, kehadiran_telat, kehadiran_alpa, tunjangan_kehadiran" className="input-field" />
                                    <p className="form-hint">Isi agar payroll tidak bergantung pada nama. Kosong = pakai pencocokan nama (legacy).</p>
                                    {errors.kode && <p className="form-error">{errors.kode}</p>}
                                </div>

                                <div>
                                    <label className="form-label">Tipe</label>
                                    <select value={data.tipe} onChange={e => setData('tipe', e.target.value)} className="select-field">
                                        <option value="pendapatan">Pendapatan (+)</option>
                                        <option value="potongan">Potongan (-)</option>
                                    </select>
                                    {errors.tipe && <p className="form-error">{errors.tipe}</p>}
                                </div>

                                <div>
                                    <label className="form-label">Jenis Perhitungan</label>
                                    <select value={data.jenis} onChange={e => setData('jenis', e.target.value)} className="select-field">
                                        <option value="fixed">Fixed (Nominal Pasti)</option>
                                        <option value="persentase">Persentase (dari Gaji Pokok)</option>
                                        <option value="dinamis_kehadiran">Dinamis Kehadiran (Uang Makan / Telat)</option>
                                        <option value="dinamis_jam_mengajar">Dinamis Jam Mengajar (Honor JTM)</option>
                                        <option value="dinamis_masa_bakti">Dinamis Masa Bakti (Otomatis dari Skala)</option>
                                    </select>
                                    {errors.jenis && <p className="form-error">{errors.jenis}</p>}
                                </div>

                                <div>
                                    <label className="form-label">Nilai Default (Rp / %)</label>
                                    <input type="number" step="0.01" value={data.nilai_default} onChange={e => setData('nilai_default', e.target.value)} placeholder="Contoh: 5000000 atau 5 untuk 5%" className="input-field" />
                                    <p className="form-hint">Biarkan kosong jika nilai diatur spesifik per pegawai.</p>
                                    {errors.nilai_default && <p className="form-error">{errors.nilai_default}</p>}
                                </div>

                                <div>
                                    <label className="form-label">Status Kepegawaian</label>
                                    <select value={data.applies_to_status_kepegawaian} onChange={e => setData('applies_to_status_kepegawaian', e.target.value)} className="select-field">
                                        <option value="">Semua Status</option>
                                        <option value="tetap">Tetap / GTYS</option>
                                        <option value="honorer">Honorer</option>
                                    </select>
                                    {errors.applies_to_status_kepegawaian && <p className="form-error">{errors.applies_to_status_kepegawaian}</p>}
                                </div>

                                <div>
                                    <label className="form-label">Unit (khusus Honor Jam Mengajar)</label>
                                    <select value={data.unit_sekolah_id} onChange={e => setData('unit_sekolah_id', e.target.value)} className="select-field">
                                        <option value="">Semua Unit</option>
                                        {units && units.map(u => (
                                            <option key={u.id} value={u.id}>{u.nama}</option>
                                        ))}
                                    </select>
                                    <p className="form-hint">Isi bila komponen hanya berlaku untuk unit tertentu (mis. Honor Mengajar unit TK).</p>
                                    {errors.unit_sekolah_id && <p className="form-error">{errors.unit_sekolah_id}</p>}
                                </div>

                                {data.jenis === 'dinamis_jam_mengajar' && (
                                    <div>
                                        <label className="form-label">Syarat Bayar Jam Mengajar</label>
                                        <select value={data.syarat_bayar_jam_mengajar} onChange={e => setData('syarat_bayar_jam_mengajar', e.target.value)} className="select-field">
                                            <option value="hanya_hadir">Hanya Jadwal dengan Presensi Hadir/Telat</option>
                                            <option value="semua_jadwal">Semua Jadwal (tanpa cek presensi)</option>
                                        </select>
                                        <p className="form-hint">'Hanya Hadir' = jam dihitung dari jadwal yg ada presensi hadir/telat. 'Semua Jadwal' = semua jam jadwal dibayar.</p>
                                        {errors.syarat_bayar_jam_mengajar && <p className="form-error">{errors.syarat_bayar_jam_mengajar}</p>}
                                    </div>
                                )}

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="form-label">Urutan Matrix</label>
                                        <input type="number" value={data.urutan} onChange={e => setData('urutan', e.target.value)} placeholder="Contoh: 1" className="input-field" />
                                        <p className="form-hint">Gaji Pokok biasanya urutan 1</p>
                                    </div>
                                    <div className="flex items-end pb-3">
                                        <label className="flex items-center cursor-pointer">
                                            <input type="checkbox" checked={data.tampil_di_matrix} onChange={e => setData('tampil_di_matrix', e.target.checked)} className="rounded border-border text-primary shadow-card focus:ring-primary" />
                                            <span className="ml-2 text-sm text-text-primary">Tampil di Matrix</span>
                                        </label>
                                    </div>
                                </div>

                                <div className="flex items-center space-x-4">
                                    <label className="flex items-center cursor-pointer">
                                        <input type="checkbox" checked={data.is_taxable} onChange={e => setData('is_taxable', e.target.checked)} className="rounded border-border text-primary shadow-card focus:ring-primary" />
                                        <span className="ml-2 text-sm text-text-primary">Taxable (Kena PPh21)</span>
                                    </label>
                                    <label className="flex items-center cursor-pointer">
                                        <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="rounded border-border text-primary shadow-card focus:ring-primary" />
                                        <span className="ml-2 text-sm text-text-primary">Aktif</span>
                                    </label>
                                </div>

                                <div className="pt-4 flex justify-end space-x-3">
                                    {isEditing && (
                                        <button type="button" onClick={handleCancel} className="btn-secondary">Batal</button>
                                    )}
                                    <button type="submit" disabled={processing} className="btn-primary">
                                        {processing ? 'Menyimpan...' : (isEditing ? 'Update Komponen' : 'Simpan Komponen')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div className="w-full lg:w-2/3">
                        <div className="card-table">
                            <div className="px-6 py-5 border-b border-border bg-white/50 flex justify-between items-center">
                                <h3 className="text-lg font-bold text-text-primary">Daftar Komponen (Master Data)</h3>
                                <Link
                                    href={route('komponen-gaji.matrix')}
                                    className="btn-secondary btn-sm border-success text-success hover:bg-success-light"
                                >
                                    <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    Buka Matrix Gaji
                                </Link>
                            </div>
                            <div className="table-wrap">
                                <table className="table-base">
                                    <thead className="bg-surface/50 border-b border-border">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Nama</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Kategori</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Nilai Default</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-text-secondary uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border/50">
                                        {komponens.length > 0 ? komponens.map((k) => (
                                            <tr key={k.id} className="hover:bg-surface/50 group">
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-bold text-text-primary">{k.nama}</div>
                                                    <div className="flex flex-wrap gap-1 mt-1">
                                                        {k.kode && <span className="badge-neutral text-[10px]">{k.kode}</span>}
                                                        {k.applies_to_status_kepegawaian && <span className="badge-warning text-[10px]">{k.applies_to_status_kepegawaian}</span>}
                                                        {k.syarat_bayar_jam_mengajar && <span className="badge-info text-[10px]">{k.syarat_bayar_jam_mengajar === 'hanya_hadir' ? 'Bayar Hadir' : 'Bayar Semua'}</span>}
                                                        {k.unit_sekolah_id && <span className="badge-info text-[10px]">Unit</span>}
                                                        {k.is_taxable == 1 && <span className="badge-neutral text-[10px]">Taxable</span>}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className={`badge ${k.tipe === 'pendapatan' ? 'badge-success' : 'badge-danger'}`}>
                                                        {k.tipe === 'pendapatan' ? '+' : '-'} {k.tipe}
                                                    </span>
                                                    <div className="text-xs text-text-muted mt-1 uppercase">{k.jenis.replace('_', ' ')}</div>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-text-muted font-medium">
                                                    {k.nilai_default ? (k.jenis === 'persentase' ? `${k.nilai_default}%` : formatRupiah(k.nilai_default)) : '-'}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col gap-1">
                                                        <span className={`badge w-max ${k.is_active ? 'badge-success' : 'badge-neutral'}`}>
                                                            {k.is_active ? 'Aktif' : 'Nonaktif'}
                                                        </span>
                                                        <span className={`badge w-max ${k.tampil_di_matrix ? 'badge-info' : 'badge-warning'}`}>
                                                            {k.tampil_di_matrix ? `Matrix (Urutan ${k.urutan})` : 'Sembunyi dari Matrix'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right text-sm font-medium">
                                                    <div className="flex justify-end gap-3 flex-wrap">
                                                        <Link
                                                            href={route('komponen-gaji.pegawai.index', k.id)}
                                                            className="link text-sm"
                                                            title="Atur nominal khusus per pegawai"
                                                        >
                                                            Atur Pegawai
                                                        </Link>
                                                        <button onClick={() => handleEdit(k)} className="link text-sm">Edit</button>
                                                        <button
                                                            onClick={() => {
                                                                if(confirm('Hapus komponen ini?')) {
                                                                    router.delete(route('komponen-gaji.destroy', k.id));
                                                                }
                                                            }}
                                                            className="text-danger hover:text-danger/80 text-sm font-medium"
                                                        >
                                                            Hapus
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-4 text-center text-sm text-text-muted">Belum ada komponen gaji yang dikonfigurasi.</td>
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
