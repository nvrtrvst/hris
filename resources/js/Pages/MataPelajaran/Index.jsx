import React, { useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, PageHeader } from '@/Components/AdminUI';
import { Head, router, useForm } from '@inertiajs/react';
import { BookOpen, CalendarClock, GraduationCap, Loader2, Pencil, Plus, Save, Trash2, Users, X } from 'lucide-react';
import Modal from '@/Components/Modal';
import StatCard from '@/Components/StatCard';
import { avatarTone, initials } from '@/Utils/avatar';

function CountBadge({ count, tone, label, onClick, ariaLabel }) {
    const className = `inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-bold tabular-nums ${tone}`;

    if (onClick) {
        return (
            <button
                type="button"
                onClick={onClick}
                className={`${className} transition-opacity hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30`}
                aria-haspopup="dialog"
                aria-label={ariaLabel}
                title={ariaLabel}
            >
                {count} {label}
            </button>
        );
    }

    return <span className={className}>{count} {label}</span>;
}

export default function Index({ auth, mapels, stats }) {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [guruMapel, setGuruMapel] = useState(null);
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
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Referensi Mata Pelajaran</h2>}
        >
            <Head title="Mata Pelajaran" />

            <div className="mx-auto w-full max-w-7xl space-y-5">
                    <PageHeader
                        eyebrow="Master Data"
                        title="Daftar Mata Pelajaran"
                        description="Master mapel dipakai saat menugaskan guru dan menyusun jadwal mengajar."
                        actions={
                            <Button onClick={openCreate}>
                                <Plus className="h-4 w-4" /> Tambah Mapel
                            </Button>
                        }
                    />

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={BookOpen} label="Total Mapel" value={stats?.total_mapel ?? 0} />
                        <StatCard Icon={Users} label="Penugasan Guru" value={stats?.total_penugasan_guru ?? 0} sub="Jumlah guru × mapel yang diampu" />
                        <StatCard Icon={CalendarClock} label="Total Jadwal" value={stats?.total_jadwal ?? 0} sub="Jadwal mengajar memakai mapel ini" />
                    </div>

                    <Card className="overflow-hidden">
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
                                                    <CountBadge
                                                        count={m.guru?.length ?? 0}
                                                        tone="border-primary/20 bg-primary/10 text-primary"
                                                        label="guru"
                                                        onClick={m.guru?.length ? () => setGuruMapel(m) : undefined}
                                                        ariaLabel={m.guru?.length ? `Lihat ${m.guru.length} guru pengampu ${m.nama}` : undefined}
                                                    />
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
                                <Button className="mt-5" onClick={openCreate}>
                                    <Plus className="h-4 w-4" /> Tambah Mapel Pertama
                                </Button>
                            </div>
                        )}
                    </Card>
            </div>

            <Modal show={!!guruMapel} onClose={() => setGuruMapel(null)} maxWidth="lg">
                <div className="p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <p className="text-[11px] font-bold uppercase tracking-wider text-text-muted">Guru Pengampu</p>
                            <h3 className="mt-1 truncate text-lg font-extrabold text-text-primary">{guruMapel?.nama}</h3>
                            <p className="mt-1 text-xs text-text-muted">{(guruMapel?.guru || []).length} guru terdaftar</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setGuruMapel(null)}
                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-text-muted transition-colors hover:bg-surface hover:text-primary"
                            aria-label="Tutup rincian guru"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    {(guruMapel?.guru || []).length > 0 ? (
                        <ul className="mt-5 divide-y divide-border overflow-hidden rounded-xl border border-border">
                            {guruMapel.guru.map((guru) => (
                                <li key={guru.id} className="flex items-start gap-3 bg-white p-3.5 sm:items-center">
                                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xs font-extrabold ${avatarTone(guru.nama)}`}>
                                        {initials(guru.nama)}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-bold text-text-primary">{guru.nama}</p>
                                        <p className="mt-0.5 text-xs text-text-muted">
                                            {guru.nip ? `NIP ${guru.nip}` : guru.nuptk ? `NUPTK ${guru.nuptk}` : 'NIP/NUPTK belum diisi'}
                                            {guru.units?.length ? ` · ${guru.units.join(', ')}` : ''}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="mt-5 rounded-xl border border-dashed border-border px-4 py-8 text-center">
                            <p className="text-sm font-bold text-text-primary">Belum ada guru pengampu</p>
                            <p className="mt-1 text-xs text-text-muted">Tambahkan mapel ini dari halaman Data Pegawai.</p>
                        </div>
                    )}

                    <div className="mt-5 flex justify-end">
                        <Button variant="secondary" onClick={() => setGuruMapel(null)}>Tutup</Button>
                    </div>
                </div>
            </Modal>

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
                        <Button variant="secondary" onClick={() => { setShowModal(false); reset(); }}>Batal</Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan...</> : <><Save className="h-4 w-4" /> {editing ? 'Simpan' : 'Tambah'}</>}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminShell>
    );
}
