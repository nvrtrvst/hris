import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import Modal from '@/Components/Modal';

export default function Index({ auth, jabatans }) {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        nama: '',
        is_guru: false,
        approver_l1_jabatan_id: '',
        approver_l2_jabatan_id: '',
    });

    const openCreate = () => {
        setEditing(null);
        reset();
        setShowModal(true);
    };

    const openEdit = (j) => {
        setEditing(j);
        setData({
            nama: j.nama,
            is_guru: j.is_guru,
            approver_l1_jabatan_id: j.approver_l1_jabatan_id ?? '',
            approver_l2_jabatan_id: j.approver_l2_jabatan_id ?? '',
        });
        setShowModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editing) {
            put(route('jabatan.update', editing.id), {
                onSuccess: () => { setShowModal(false); reset(); },
            });
        } else {
            post(route('jabatan.store'), {
                onSuccess: () => { setShowModal(false); reset(); },
            });
        }
    };

    const handleDelete = (j) => {
        if (!confirm(`Hapus jabatan "${j.nama}"?`)) return;
        router.delete(route('jabatan.destroy', j.id));
    };

    const otherJabatans = jabatans.filter((j) => j.id !== editing?.id);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Kelola Jabatan</h2>}
        >
            <Head title="Jabatan" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="card">
                        <div className="p-8">
                            <div className="page-header mb-6">
                                <div>
                                    <h3 className="page-title">Daftar Jabatan</h3>
                                    <p className="page-subtitle">Kelola jabatan/posisi pegawai di lingkungan yayasan.</p>
                                </div>
                                <button onClick={openCreate}
                                    className="btn-primary"
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Tambah Jabatan
                                </button>
                            </div>
                            <div className="card-table">
                                <div className="overflow-x-auto rounded-card border border-border">
                                <table className="table-base">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Nama Jabatan</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Tipe</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Approval L1</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-text-muted uppercase tracking-wider">Approval L2</th>
                                            <th className="px-6 py-4 text-right text-xs font-bold text-text-muted uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border">
                                        {jabatans.map((j) => (
                                            <tr key={j.id} className="hover:bg-surface transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-semibold text-text-primary">{j.nama}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`badge ${j.is_guru ? 'badge-info' : 'badge-neutral'}`}>
                                                        {j.is_guru ? 'Guru' : 'Non-Guru'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">
                                                    {j.approver_l1?.nama || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-text-secondary">
                                                    {j.approver_l2?.nama || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                    <button onClick={() => openEdit(j)}
                                                        className="btn-secondary btn-sm"
                                                    >Edit</button>
                                                    <button onClick={() => handleDelete(j)}
                                                        className="btn-danger btn-sm"
                                                    >Hapus</button>
                                                </td>
                                            </tr>
                                        ))}
                                        {jabatans.length === 0 && (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-12 text-center text-text-muted">Belum ada data jabatan.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <Modal show={showModal} onClose={() => { setShowModal(false); reset(); }} maxWidth="md">
                <form onSubmit={handleSubmit} className="p-6">
                    <h3 className="text-lg font-bold text-text-primary mb-4">
                        {editing ? 'Edit Jabatan' : 'Tambah Jabatan'}
                    </h3>
                    <div className="form-section space-y-4">
                        <div>
                            <label className="form-label">Nama Jabatan <span className="text-red-500">*</span></label>
                            <input type="text" value={data.nama}
                                onChange={e => setData('nama', e.target.value)}
                                placeholder="cth. Guru Mata Pelajaran"
                                className="input-field"
                            />
                            {errors.nama && <p className="form-error">{errors.nama}</p>}
                        </div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" checked={data.is_guru}
                                onChange={e => setData('is_guru', e.target.checked)}
                                className="w-4 h-4 rounded border-border text-primary focus:ring-primary"
                            />
                            <div>
                                <span className="form-label">Jabatan Guru</span>
                                <p className="form-hint">Centang jika jabatan ini termasuk tenaga pendidik (guru).</p>
                            </div>
                        </label>
                        <div className="form-grid grid-cols-2 gap-4">
                            <div>
                                <label className="form-label">Approval L1 (Atasan)</label>
                                <select value={data.approver_l1_jabatan_id}
                                    onChange={e => setData('approver_l1_jabatan_id', e.target.value)}
                                    className="select-field"
                                >
                                    <option value="">Tidak Ada (Superadmin)</option>
                                    {otherJabatans.map((j) => (
                                        <option key={j.id} value={j.id}>{j.nama}</option>
                                    ))}
                                </select>
                                <p className="form-hint">Jabatan yg berhak approve L1.</p>
                                {errors.approver_l1_jabatan_id && <p className="form-error">{errors.approver_l1_jabatan_id}</p>}
                            </div>
                            <div>
                                <label className="form-label">Approval L2 (Final)</label>
                                <select value={data.approver_l2_jabatan_id}
                                    onChange={e => setData('approver_l2_jabatan_id', e.target.value)}
                                    className="select-field"
                                >
                                    <option value="">Tidak Ada (Final di L1)</option>
                                    {otherJabatans.map((j) => (
                                        <option key={j.id} value={j.id}>{j.nama}</option>
                                    ))}
                                </select>
                                <p className="form-hint">Jabatan yg berhak approve L2 (final).</p>
                                {errors.approver_l2_jabatan_id && <p className="form-error">{errors.approver_l2_jabatan_id}</p>}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center justify-end gap-3 mt-6 border-t border-border pt-4">
                        <button type="button" onClick={() => { setShowModal(false); reset(); }}
                            className="btn-secondary"
                        >Batal</button>
                        <button type="submit" disabled={processing}
                            className="btn-primary"
                        >
                            {processing ? 'Menyimpan...' : editing ? 'Simpan' : 'Tambah'}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
