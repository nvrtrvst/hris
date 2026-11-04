import React, { useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, PageHeader } from '@/Components/AdminUI';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarPlus, Download, Pencil, Plus, RefreshCw, Trash2, Upload } from 'lucide-react';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';

const inputClass =
    'w-full rounded-button border border-border bg-white px-3 py-2 text-sm text-text-primary shadow-sm transition-colors placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20';

const tipeBadge = {
    nasional: 'border-blue-200 bg-blue-50 text-blue-700',
    cuti_bersama: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    sekolah: 'border-amber-200 bg-amber-50 text-amber-700',
    lainnya: 'border-slate-200 bg-slate-50 text-slate-600',
};

const tipeLabel = {
    nasional: 'Nasional',
    cuti_bersama: 'Cuti Bersama',
    sekolah: 'Sekolah',
    lainnya: 'Lainnya',
};

export default function Index({ auth, holidays, units, years, filters }) {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    const form = useForm({
        tanggal: '',
        nama: '',
        unit_sekolah_id: '',
        tipe: 'nasional',
        keterangan: '',
    });

    const importForm = useForm({ year: new Date().getFullYear() });
    const syncForm = useForm({ year: new Date().getFullYear() });

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setEditing(null);
        setShowModal(true);
    };

    const openEdit = (h) => {
        form.setData({
            tanggal: h.tanggal,
            nama: h.nama,
            unit_sekolah_id: h.unit_sekolah_id ? String(h.unit_sekolah_id) : '',
            tipe: h.tipe,
            keterangan: h.keterangan || '',
        });
        form.clearErrors();
        setEditing(h);
        setShowModal(true);
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            form.put(route('hari-libur.update', editing.id), {
                preserveScroll: true,
                onSuccess: () => setShowModal(false),
            });
        } else {
            form.post(route('hari-libur.store'), {
                preserveScroll: true,
                onSuccess: () => setShowModal(false),
            });
        }
    };

    const destroy = (h) => {
        if (!confirm(`Hapus hari libur "${h.nama}" (${h.tanggal})?`)) return;
        router.delete(route('hari-libur.destroy', h.id), { preserveScroll: true });
    };

    const applyFilter = (overrides) => {
        router.get(
            route('hari-libur.index'),
            { ...filters, ...overrides },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Kalender Hari Libur</h2>}
        >
            <Head title="Hari Libur" />

            <div className="mx-auto w-full max-w-7xl space-y-5">
                    <PageHeader
                        eyebrow="Master Data"
                        title="Daftar Hari Libur"
                        description="Hari libur nasional dan sekolah dikecualikan dari hitungan hari kerja."
                        actions={
                            <>
                                <Button onClick={openCreate}>
                                    <Plus className="h-4 w-4" /> Tambah
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={() => importForm.post(route('hari-libur.import'), { preserveScroll: true })}
                                    disabled={importForm.processing}
                                    title="Impor dari data lokal (offline)"
                                >
                                    <Upload className="h-4 w-4" /> Import Lokal {importForm.year}
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={() => syncForm.post(route('hari-libur.sync-api'), { preserveScroll: true })}
                                    disabled={syncForm.processing}
                                    title="Sinkron dari API publik"
                                >
                                    <RefreshCw className="h-4 w-4" /> Sync API {syncForm.year}
                                </Button>
                            </>
                        }
                    />

                    <Card className="flex flex-wrap items-end gap-3 p-4">
                        <div className="w-32">
                            <label className="mb-1 block text-xs font-bold text-text-muted">Tahun</label>
                            <select
                                className={inputClass}
                                value={filters.year || ''}
                                onChange={(e) => applyFilter({ year: e.target.value })}
                            >
                                <option value="">Semua</option>
                                {years.map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                        <div className="w-56">
                            <label className="mb-1 block text-xs font-bold text-text-muted">Unit</label>
                            <select
                                className={inputClass}
                                value={filters.unit_sekolah_id || ''}
                                onChange={(e) => applyFilter({ unit_sekolah_id: e.target.value })}
                            >
                                <option value="">Semua (termasuk nasional)</option>
                                {units.map((u) => (
                                    <option key={u.id} value={u.id}>{u.nama}</option>
                                ))}
                            </select>
                        </div>
                        <div className="w-32">
                            <label className="mb-1 block text-xs font-bold text-text-muted">Tahun Impor/Sync</label>
                            <select
                                className={inputClass}
                                value={importForm.year}
                                onChange={(e) => {
                                    importForm.setData('year', e.target.value);
                                    syncForm.setData('year', e.target.value);
                                }}
                            >
                                {years.map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                    </Card>

                    <Card className="overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Tanggal</th>
                                        <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nama</th>
                                        <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Tipe</th>
                                        <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Unit</th>
                                        <th className="hidden px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted lg:table-cell">Keterangan</th>
                                        <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border bg-white">
                                    {holidays.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-6 py-10 text-center text-sm text-text-muted">
                                                Belum ada data hari libur. Tambah manual, Import Lokal, atau Sync API.
                                            </td>
                                        </tr>
                                    )}
                                    {holidays.data.map((h) => (
                                        <tr key={h.id} className="transition-colors hover:bg-surface">
                                            <td className="whitespace-nowrap px-6 py-4 font-mono text-sm text-text-secondary">{h.tanggal}</td>
                                            <td className="px-6 py-4 text-sm font-bold text-text-primary">{h.nama}</td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <span className={`inline-flex rounded-full border px-2 py-0.5 text-[11px] font-bold ${tipeBadge[h.tipe] || tipeBadge.lainnya}`}>
                                                    {tipeLabel[h.tipe] || h.tipe}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-text-secondary">
                                                {h.unit_sekolah ? h.unit_sekolah.nama : <span className="font-semibold text-blue-700">Nasional</span>}
                                            </td>
                                            <td className="hidden px-6 py-4 text-sm text-text-muted lg:table-cell">{h.keterangan || '-'}</td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <button type="button" onClick={() => openEdit(h)} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary" title="Edit">
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                    <button type="button" onClick={() => destroy(h)} className="rounded-lg p-1.5 text-rose-500 transition-colors hover:bg-rose-50" title="Hapus">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="px-6 py-3">
                            <Pagination links={holidays.links} data={filters} />
                        </div>
                    </Card>
            </div>

            <Modal show={showModal} onClose={() => setShowModal(false)} maxWidth="lg">
                <form onSubmit={submit} className="p-6">
                    <div className="mb-4 flex items-center gap-2">
                        <CalendarPlus className="h-5 w-5 text-primary" />
                        <h3 className="text-lg font-bold text-text-primary">{editing ? 'Edit Hari Libur' : 'Tambah Hari Libur'}</h3>
                    </div>

                    <div className="space-y-4">
                        <div>
                            <label className="mb-1 block text-xs font-bold text-text-muted">Tanggal</label>
                            <input type="date" className={inputClass} value={form.data.tanggal} onChange={(e) => form.setData('tanggal', e.target.value)} />
                            {form.errors.tanggal && <p className="form-error">{form.errors.tanggal}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-bold text-text-muted">Nama</label>
                            <input type="text" className={inputClass} value={form.data.nama} onChange={(e) => form.setData('nama', e.target.value)} placeholder="Mis. Isra Mikraj" />
                            {form.errors.nama && <p className="form-error">{form.errors.nama}</p>}
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-bold text-text-muted">Unit</label>
                                <select className={inputClass} value={form.data.unit_sekolah_id} onChange={(e) => form.setData('unit_sekolah_id', e.target.value)}>
                                    <option value="">Nasional (semua unit)</option>
                                    {units.map((u) => (
                                        <option key={u.id} value={u.id}>{u.nama}</option>
                                    ))}
                                </select>
                                {form.errors.unit_sekolah_id && <p className="form-error">{form.errors.unit_sekolah_id}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-bold text-text-muted">Tipe</label>
                                <select className={inputClass} value={form.data.tipe} onChange={(e) => form.setData('tipe', e.target.value)}>
                                    {Object.keys(tipeLabel).map((t) => (
                                        <option key={t} value={t}>{tipeLabel[t]}</option>
                                    ))}
                                </select>
                                {form.errors.tipe && <p className="form-error">{form.errors.tipe}</p>}
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-bold text-text-muted">Keterangan</label>
                            <textarea className={inputClass} rows={2} value={form.data.keterangan} onChange={(e) => form.setData('keterangan', e.target.value)} />
                            {form.errors.keterangan && <p className="form-error">{form.errors.keterangan}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <Button variant="secondary" onClick={() => setShowModal(false)}>Batal</Button>
                        <Button type="submit" disabled={form.processing}>
                            {editing ? 'Simpan Perubahan' : 'Tambah'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminShell>
    );
}
