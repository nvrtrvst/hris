import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Briefcase, CheckCircle2, Loader2, Pencil, Plus, Save, ShieldCheck, Trash2, Users } from 'lucide-react';
import Modal from '@/Components/Modal';
import StatCard from '@/Components/StatCard';
import { avatarTone, initials } from '@/Utils/avatar';

const Field = ({ label, required, error, hint, children, className = '' }) => (
    <div className={className}>
        <label className="form-label text-xs">{label} {required && <span className="text-danger">*</span>}</label>
        {children}
        {error && <p className="form-error">{error}</p>}
        {hint && !error && <p className="form-hint">{hint}</p>}
    </div>
);

function ApproverChip({ label, name }) {
    return (
        <div className="flex items-center gap-1.5">
            <ShieldCheck className="h-3.5 w-3.5 text-text-muted" />
            <span className="text-[11px] font-semibold uppercase tracking-wide text-text-muted">{label}</span>
            <span className="max-w-32 truncate rounded-full border border-border bg-surface px-2 py-0.5 text-xs font-semibold text-text-secondary">
                {name || '—'}
            </span>
        </div>
    );
}

export default function Index({ auth, jabatans, stats, flash }) {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        nama: '',
        is_guru: false,
        is_payroll_operator: false,
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
            is_payroll_operator: j.is_payroll_operator ?? false,
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
    const totalNonGuru = (stats?.total_jabatan ?? 0) - (stats?.total_guru ?? 0);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Kelola Jabatan</h2>}
        >
            <Head title="Jabatan" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-xl font-extrabold text-text-primary">Daftar Jabatan</h3>
                            <p className="text-sm text-text-muted">Kelola jabatan/posisi pegawai, tipe guru, dan rantai approval izin.</p>
                        </div>
                        <button onClick={openCreate} className="btn-primary inline-flex shrink-0 items-center gap-2">
                            <Plus className="h-4 w-4" /> Tambah Jabatan
                        </button>
                    </div>

                    {/* Flash */}
                    {flash?.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash?.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={Briefcase} label="Total Jabatan" value={stats?.total_jabatan ?? 0} sub={`${stats?.total_guru ?? 0} guru · ${totalNonGuru} non-guru`} />
                        <StatCard Icon={CheckCircle2} label="Jabatan Guru" value={stats?.total_guru ?? 0} sub="Tenaga pendidik" />
                        <StatCard Icon={Users} label="Total Pegawai" value={stats?.total_pegawai ?? 0} sub="Pegawai yang memegang jabatan" />
                    </div>

                    {/* Table */}
                    <div className="card overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface">
                                    <tr>
                                        <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nama Jabatan</th>
                                        <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Tipe</th>
                                        <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Rantai Approval</th>
                                        <th className="px-6 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-text-muted">Pegawai</th>
                                        <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border bg-white">
                                    {jabatans.map((j) => (
                                        <tr key={j.id} className="transition-colors hover:bg-surface">
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(j.nama)}`}>
                                                        {initials(j.nama)}
                                                    </span>
                                                    <p className="truncate text-sm font-bold text-text-primary">{j.nama}</p>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                {j.is_guru ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700">
                                                        <CheckCircle2 className="h-3 w-3" /> Guru
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-[11px] font-bold text-gray-600">
                                                        Non-Guru
                                                    </span>
                                                )}
                                                {j.is_payroll_operator && (
                                                    <span className="ml-1.5 inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                                        Payroll
                                                    </span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <div className="flex flex-col gap-1">
                                                    <ApproverChip label="L1" name={j.approver_l1?.nama} />
                                                    <ApproverChip label="L2" name={j.approver_l2?.nama} />
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-center">
                                                <span className="inline-flex min-w-8 items-center justify-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-extrabold text-primary tabular-nums">
                                                    {j.pegawai_count ?? 0}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    <button onClick={() => openEdit(j)} className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                                        <Pencil className="h-3.5 w-3.5" /> Edit
                                                    </button>
                                                    <button onClick={() => handleDelete(j)} className="btn-danger btn-sm inline-flex items-center gap-1.5">
                                                        <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {jabatans.length === 0 && (
                                        <tr>
                                            <td colSpan="5" className="px-6 py-14 text-center">
                                                <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
                                                    <Briefcase className="h-7 w-7 text-primary" />
                                                </span>
                                                <p className="mt-3 text-sm font-bold text-text-primary">Belum ada data jabatan</p>
                                                <p className="text-sm text-text-muted">Tambahkan jabatan pertama untuk mulai mengatur struktur organisasi.</p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Create/Edit */}
            <Modal show={showModal} onClose={() => { setShowModal(false); reset(); }} maxWidth="md">
                <form onSubmit={handleSubmit} className="p-6">
                    <h3 className="flex items-center gap-2 text-lg font-extrabold text-text-primary">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10">
                            <Briefcase className="h-5 w-5 text-primary" />
                        </span>
                        {editing ? 'Edit Jabatan' : 'Tambah Jabatan'}
                    </h3>

                    <div className="mt-6 space-y-4">
                        <Field label="Nama Jabatan" required error={errors.nama}>
                            <input type="text" value={data.nama} onChange={(e) => setData('nama', e.target.value)}
                                placeholder="cth. Guru Mata Pelajaran" className="input-field" autoFocus />
                        </Field>

                        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-surface p-4">
                            <input type="checkbox" checked={data.is_guru}
                                onChange={(e) => setData('is_guru', e.target.checked)}
                                className="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary" />
                            <div>
                                <span className="form-label text-xs">Jabatan Guru</span>
                                <p className="form-hint">Centang jika jabatan ini termasuk tenaga pendidik (guru).</p>
                            </div>
                        </label>

                        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-surface p-4">
                            <input type="checkbox" checked={data.is_payroll_operator}
                                onChange={(e) => setData('is_payroll_operator', e.target.checked)}
                                className="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary" />
                            <div>
                                <span className="form-label text-xs">Operator Payroll</span>
                                <p className="form-hint">Centang untuk memberi akses menjalankan payroll (Run/Riwayat/Laporan) untuk unit jabatan ini.</p>
                            </div>
                        </label>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Approval L1 (Atasan)" error={errors.approver_l1_jabatan_id}
                                hint="Jabatan yang berhak approve L1.">
                                <select value={data.approver_l1_jabatan_id}
                                    onChange={(e) => setData('approver_l1_jabatan_id', e.target.value)}
                                    className="select-field">
                                    <option value="">Tidak Ada (Superadmin)</option>
                                    {otherJabatans.map((j) => (
                                        <option key={j.id} value={j.id}>{j.nama}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Approval L2 (Final)" error={errors.approver_l2_jabatan_id}
                                hint="Jabatan yang berhak approve L2 (final).">
                                <select value={data.approver_l2_jabatan_id}
                                    onChange={(e) => setData('approver_l2_jabatan_id', e.target.value)}
                                    className="select-field">
                                    <option value="">Tidak Ada (Final di L1)</option>
                                    {otherJabatans.map((j) => (
                                        <option key={j.id} value={j.id}>{j.nama}</option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3 border-t border-border pt-4">
                        <button type="button" onClick={() => { setShowModal(false); reset(); }} className="btn-secondary">Batal</button>
                        <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> {editing ? 'Simpan' : 'Tambah'}</>}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
