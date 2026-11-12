import React, { useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import EmptyState from '@/Components/AdminUI/ui/EmptyState';
import { Button, Card, PageHeader, Select } from '@/Components/AdminUI';
import { Head, useForm } from '@inertiajs/react';
import { CalendarRange, Pencil, Plus, Trash2 } from 'lucide-react';
import Modal from '@/Components/Modal';

const STATUS = {
    aktif: { label: 'Aktif', tone: 'bg-emerald-50 text-emerald-700' },
    akan_datang: { label: 'Akan datang', tone: 'bg-sky-50 text-sky-700' },
    selesai: { label: 'Selesai', tone: 'bg-slate-100 text-slate-600' },
};

const inputClass =
    'w-full rounded-button border border-border bg-white px-3 py-2 text-sm text-text-primary shadow-sm transition-colors placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20';

const fmtTanggal = (iso) => {
    if (!iso) return '-';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
};

export default function Index({ auth, periods, units }) {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    const form = useForm({
        unit_sekolah_id: units.length === 1 ? String(units[0].id) : '',
        nama: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        keterangan: '',
    });

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        if (units.length === 1) form.setData('unit_sekolah_id', String(units[0].id));
        setEditing(null);
        setShowModal(true);
    };

    const openEdit = (period) => {
        form.setData({
            unit_sekolah_id: period.unit_sekolah ? String(period.unit_sekolah.id) : '',
            nama: period.nama,
            tanggal_mulai: period.tanggal_mulai,
            tanggal_selesai: period.tanggal_selesai,
            keterangan: period.keterangan || '',
        });
        form.clearErrors();
        setEditing(period);
        setShowModal(true);
    };

    const submit = (event) => {
        event.preventDefault();
        if (editing) {
            form.put(route('pekan-ujian.update', editing.id), {
                preserveScroll: true,
                onSuccess: () => setShowModal(false),
            });
            return;
        }

        form.post(route('pekan-ujian.store'), {
            preserveScroll: true,
            onSuccess: () => setShowModal(false),
        });
    };

    const destroy = (period) => {
        if (!confirm(`Hapus ${period.nama} (${fmtTanggal(period.tanggal_mulai)} - ${fmtTanggal(period.tanggal_selesai)})?`)) return;
        form.delete(route('pekan-ujian.destroy', period.id), { preserveScroll: true });
    };

    return (
        <AdminShell user={auth.user} header={<h2 className="page-title">Pekan UAS</h2>}>
            <Head title="Pekan UAS" />

            <div className="mx-auto w-full max-w-7xl space-y-5">
                <PageHeader
                    eyebrow="Master Data"
                    title="Pekan Ujian"
                    description="Selama pekan UAS, jadwal mengajar tidak aktif dan penugasan pengawas menjadi jadwal utama."
                    actions={
                        <Button onClick={openCreate}>
                            <Plus className="h-4 w-4" /> Tambah Pekan UAS
                        </Button>
                    }
                />

                <Card className="overflow-hidden">
                    {periods.length === 0 ? (
                        <EmptyState
                            icon={CalendarRange}
                            title="Belum ada pekan UAS"
                            description="Tambah periode per unit supaya jadwal mengajar dinonaktifkan dan jadwal pengawas bisa disusun."
                            action={<Button onClick={openCreate}>Tambah Pekan UAS</Button>}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface">
                                    <tr>
                                        <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-secondary">Unit</th>
                                        <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-secondary">Nama</th>
                                        <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-secondary">Rentang</th>
                                        <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-secondary">Status</th>
                                        <th className="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-text-secondary">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border bg-white">
                                    {periods.map((period) => {
                                        const status = STATUS[period.status] || STATUS.selesai;

                                        return (
                                            <tr key={period.id} className="transition-colors hover:bg-surface/60">
                                                <td className="whitespace-nowrap px-5 py-4 text-sm font-semibold text-text-primary">
                                                    {period.unit_sekolah?.singkatan || period.unit_sekolah?.nama || '-'}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <p className="text-sm font-bold text-text-primary">{period.nama}</p>
                                                    {period.keterangan && <p className="mt-0.5 text-xs text-text-secondary">{period.keterangan}</p>}
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-4 font-mono text-sm text-text-secondary">
                                                    {fmtTanggal(period.tanggal_mulai)} - {fmtTanggal(period.tanggal_selesai)}
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-4">
                                                    <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold ${status.tone}`}>
                                                        {status.label}
                                                    </span>
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-4">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <button
                                                            type="button"
                                                            onClick={() => openEdit(period)}
                                                            className="inline-flex h-11 w-11 items-center justify-center rounded-lg text-text-secondary transition-colors hover:bg-surface hover:text-primary focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/15"
                                                            aria-label={`Edit ${period.nama}`}
                                                            title="Edit"
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </button>
                                                        {period.status === 'akan_datang' && (
                                                            <button
                                                                type="button"
                                                                onClick={() => destroy(period)}
                                                                className="inline-flex h-11 w-11 items-center justify-center rounded-lg text-rose-600 transition-colors hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-200"
                                                                aria-label={`Hapus ${period.nama}`}
                                                                title="Hapus"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>

            <Modal show={showModal} onClose={() => setShowModal(false)} maxWidth="lg">
                <form onSubmit={submit} className="p-5 sm:p-6">
                    <h2 className="text-base font-bold text-text-primary">{editing ? 'Edit Pekan UAS' : 'Tambah Pekan UAS'}</h2>
                    <p className="mt-1 text-xs text-text-secondary">
                        Jadwal mengajar unit ini tidak dihitung selama rentang tanggal di bawah.
                    </p>

                    <div className="mt-4 space-y-3">
                        <div>
                            <label htmlFor="pekan-unit" className="mb-1 block text-xs font-bold text-text-secondary">Unit</label>
                            <Select
                                id="pekan-unit"
                                value={form.data.unit_sekolah_id}
                                onChange={(e) => form.setData('unit_sekolah_id', e.target.value)}
                                disabled={units.length === 1}
                            >
                                <option value="">Pilih unit</option>
                                {units.map((unit) => (
                                    <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                ))}
                            </Select>
                            {form.errors.unit_sekolah_id && <p className="mt-1 text-[11px] font-semibold text-rose-600">{form.errors.unit_sekolah_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="pekan-nama" className="mb-1 block text-xs font-bold text-text-secondary">Nama periode</label>
                            <input
                                id="pekan-nama"
                                type="text"
                                value={form.data.nama}
                                onChange={(e) => form.setData('nama', e.target.value)}
                                placeholder="Contoh: UAS Ganjil 2026/2027"
                                className={inputClass}
                            />
                            {form.errors.nama && <p className="mt-1 text-[11px] font-semibold text-rose-600">{form.errors.nama}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label htmlFor="pekan-mulai" className="mb-1 block text-xs font-bold text-text-secondary">Tanggal mulai</label>
                                <input
                                    id="pekan-mulai"
                                    type="date"
                                    value={form.data.tanggal_mulai}
                                    onChange={(e) => form.setData('tanggal_mulai', e.target.value)}
                                    className={inputClass}
                                />
                                {form.errors.tanggal_mulai && <p className="mt-1 text-[11px] font-semibold text-rose-600">{form.errors.tanggal_mulai}</p>}
                            </div>
                            <div>
                                <label htmlFor="pekan-selesai" className="mb-1 block text-xs font-bold text-text-secondary">Tanggal selesai</label>
                                <input
                                    id="pekan-selesai"
                                    type="date"
                                    value={form.data.tanggal_selesai}
                                    onChange={(e) => form.setData('tanggal_selesai', e.target.value)}
                                    className={inputClass}
                                />
                                {form.errors.tanggal_selesai && <p className="mt-1 text-[11px] font-semibold text-rose-600">{form.errors.tanggal_selesai}</p>}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="pekan-keterangan" className="mb-1 block text-xs font-bold text-text-secondary">Keterangan (opsional)</label>
                            <textarea
                                id="pekan-keterangan"
                                value={form.data.keterangan}
                                onChange={(e) => form.setData('keterangan', e.target.value)}
                                rows={3}
                                className={inputClass}
                            />
                            {form.errors.keterangan && <p className="mt-1 text-[11px] font-semibold text-rose-600">{form.errors.keterangan}</p>}
                        </div>
                    </div>

                    <div className="mt-5 flex justify-end gap-2 border-t border-border pt-4">
                        <Button type="button" variant="secondary" onClick={() => setShowModal(false)}>Batal</Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminShell>
    );
}
