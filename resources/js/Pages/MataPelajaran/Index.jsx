import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { BookOpen, CalendarClock, GraduationCap, Loader2, Pencil, Plus, Save, Trash2, Users } from 'lucide-react';
import Modal from '@/Components/Modal';
import StatCard from '@/Components/StatCard';
import { avatarTone, initials } from '@/Utils/avatar';

function CountBadge({ count, tone, label }) {
    return (
        <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-bold tabular-nums ${tone}`}>
            {count} {label}
        </span>
    );
}

export default function Index({ auth, mapels, stats, flash }) {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm({ nama: '' });

    const openCreate = () => {
        setEditing(null);
        reset();
        setShowModal(true);
    };

    const openEdit = (m) => {
        setEditing(m);
        setData({ nama: m.nama });
        setShowModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editing) {
            put(route('mata-pelajaran.update', editing.id), {
                onSuccess: () => { setShowModal(false); reset(); },
            });
        } else {
            post(route('mata-pelajaran.store'), {
                onSuccess: () => { setShowModal(false); reset(); },
            });
        }
    };

    const handleDelete = (m) => {
        if (!confirm(`Hapus mata pelajaran "${m.nama}"?`)) return;
        router.delete(route('mata-pelajaran.destroy', m.id));
    };

    const hasMapels = (mapels || []).length > 0;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Referensi Mata Pelajaran</h2>}
        >
            <Head title="Mata Pelajaran" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-xl font-extrabold text-text-primary">Daftar Mata Pelajaran</h3>
                            <p className="text-sm text-text-muted">Master mapel dipakai saat menugaskan guru & menyusun jadwal mengajar.</p>
                        </div>
                        <button onClick={openCreate} className="btn-primary inline-flex shrink-0 items-center gap-2">
                            <Plus className="h-4 w-4" /> Tambah Mapel
                        </button>
                    </div>

                    {/* Flash */}
                    {flash?.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash?.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={BookOpen} label="Total Mapel" value={stats?.total_mapel ?? 0} />
                        <StatCard Icon={Users} label="Penugasan Guru" value={stats?.total_penugasan_guru ?? 0} sub="Jumlah guru × mapel yang diampu" />
                        <StatCard Icon={CalendarClock} label="Total Jadwal" value={stats?.total_jadwal ?? 0} sub="Jadwal mengajar memakai mapel ini" />
                    </div>

                    {/* Table */}
                    <div className="card overflow-hidden">
                        {hasMapels ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Mata Pelajaran</th>
                                            <th className="px-6 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-text-muted">Guru Pengampu</th>
                                            <th className="px-6 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-text-muted">Jadwal</th>
                                            <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border bg-white">
                                        {mapels.map((m) => (
                                            <tr key={m.id} className="transition-colors hover:bg-surface">
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(m.nama)}`}>
                                                            {initials(m.nama)}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-bold text-text-primary">{m.nama}</p>
                                                            <p className="text-xs text-text-muted">Mapel #{m.id}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-center">
                                                    <CountBadge count={m.pegawais_count ?? 0} tone="border-primary/20 bg-primary/10 text-primary" label="guru" />
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-center">
                                                    <CountBadge count={m.jadwals_count ?? 0} tone="border-sky-200 bg-sky-50 text-sky-700" label="jadwal" />
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    <div className="inline-flex items-center gap-2">
                                                        <button onClick={() => openEdit(m)} className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                                            <Pencil className="h-3.5 w-3.5" /> Edit
                                                        </button>
                                                        <button onClick={() => handleDelete(m)} className="btn-danger btn-sm inline-flex items-center gap-1.5">
                                                            <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
                                <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
                                    <GraduationCap className="h-7 w-7 text-primary" />
                                </span>
                                <h4 className="mt-4 text-base font-bold text-text-primary">Belum ada mata pelajaran</h4>
                                <p className="mt-1 max-w-sm text-sm text-text-muted">
                                    Tambahkan mapel pertama untuk mulai menyusun jadwal mengajar dan menugaskan guru.
                                </p>
                                <button onClick={openCreate} className="btn-primary mt-5 inline-flex items-center gap-2">
                                    <Plus className="h-4 w-4" /> Tambah Mapel Pertama
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal Create/Edit */}
            <Modal show={showModal} onClose={() => { setShowModal(false); reset(); }} maxWidth="md">
                <form onSubmit={handleSubmit} className="p-6">
                    <h3 className="flex items-center gap-2 text-lg font-extrabold text-text-primary">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10">
                            <BookOpen className="h-5 w-5 text-primary" />
                        </span>
                        {editing ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran'}
                    </h3>
                    <p className="mt-1.5 text-xs text-text-muted">
                        Master ini dipakai saat menugaskan mapel ke guru (Pegawai) dan saat membuat Jadwal.
                    </p>

                    <div className="mt-6 space-y-4">
                        <div>
                            <label className="form-label text-xs">Nama Mata Pelajaran <span className="text-danger">*</span></label>
                            <input type="text" value={data.nama} onChange={(e) => setData('nama', e.target.value)}
                                placeholder="Contoh: Matematika" className="input-field" autoFocus />
                            {errors.nama && <p className="form-error">{errors.nama}</p>}
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
