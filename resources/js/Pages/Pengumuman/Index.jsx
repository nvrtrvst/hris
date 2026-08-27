import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import StatCard from '@/Components/StatCard';
import { Calendar, Megaphone, Pencil, Pin as PinIcon, Plus, Save, Trash2, X as XIcon } from 'lucide-react';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';
import { validateUpload } from '@/Utils/file';

export default function PengumumanIndex({ auth, announcements, units, userUnitId, flash }) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ title: '', body: '', is_pinned: false, unit_sekolah_id: userUnitId || '', published_at: new Date().toISOString().slice(0, 16), image: null, imagePreview: null });
    const [fileError, setFileError] = useState(null);

    const resetForm = () => {
        setForm({ title: '', body: '', is_pinned: false, unit_sekolah_id: userUnitId || '', published_at: new Date().toISOString().slice(0, 16), image: null, imagePreview: null });
        setEditing(null);
        setShowForm(false);
    };
    const openEdit = (a) => {
        setForm({ title: a.title, body: a.body, is_pinned: a.is_pinned, unit_sekolah_id: a.unit_sekolah_id || '', published_at: a.published_at?.slice(0, 16) || '', image: null, imagePreview: a.image_url || null });
        setEditing(a.id);
        setShowForm(true);
    };
    const submit = (e) => {
        e.preventDefault();
        if (fileError) return;
        const method = editing ? 'put' : 'post';
        const routeName = editing ? route('pengumuman.update', editing) : route('pengumuman.store');
        const payload = { ...form };
        delete payload.imagePreview;
        router[method](routeName, payload, { preserveState: true, onSuccess: () => resetForm() });
    };
    const destroy = (id) => {
        if (confirm('Hapus pengumuman ini?')) router.delete(route('pengumuman.destroy', id), { preserveState: true });
    };

    const total = announcements?.total ?? announcements?.data?.length ?? 0;
    const items = announcements.data || announcements || [];

    return (
        <AuthenticatedLayout header={<h2 className="page-title">Pengumuman</h2>}>
            <Head title="Pengumuman" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-xl font-extrabold text-text-primary">Pengumuman</h3>
                            <p className="text-sm text-text-muted">Bagikan informasi penting kepada seluruh pegawai.</p>
                        </div>
                        <button onClick={() => { resetForm(); setShowForm(!showForm); }} className="btn-primary inline-flex shrink-0 items-center gap-2">
                            {showForm ? <><XIcon className="h-4 w-4" /> Tutup form</> : <><Plus className="h-4 w-4" /> Tambah pengumuman</>}
                        </button>
                    </div>

                    {/* Flash */}
                    {flash?.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash?.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={Megaphone} label="Total Pengumuman" value={total} />
                        <StatCard Icon={PinIcon} label="Disematkan (Pin)" value={items.filter((a) => a.is_pinned).length} />
                    </div>

                    {/* Form */}
                    {showForm && (
                        <form onSubmit={submit} className="card space-y-4 p-6">
                            <h4 className="text-sm font-extrabold uppercase tracking-wide text-primary">
                                {editing ? 'Edit Pengumuman' : 'Buat Pengumuman Baru'}
                            </h4>
                            <div>
                                <label className="form-label text-xs">Judul <span className="text-danger">*</span></label>
                                <input required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })}
                                    placeholder="Judul pengumuman" className="input-field" />
                            </div>
                            <div>
                                <label className="form-label text-xs">Isi Pengumuman <span className="text-danger">*</span></label>
                                <textarea required value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })}
                                    rows={4} placeholder="Isi pengumuman..." className="input-field" />
                            </div>
                            <div>
                                <label className="form-label text-xs">Gambar (opsional)</label>
                                <p className="text-xs text-text-muted">Maksimal 2 MB, format JPG/PNG/WebP.</p>
                                <input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => {
                                    const f = e.target.files?.[0] || null;
                                    setFileError(null);
                                    if (f) {
                                        const err = validateUpload(f, { maxBytes: 2 * 1024 * 1024, accept: ['image/jpeg', 'image/png', 'image/webp'], label: 'Gambar' });
                                        if (err) {
                                            setFileError(err);
                                            setForm((prev) => ({ ...prev, image: null, imagePreview: null }));
                                            e.target.value = '';
                                            return;
                                        }
                                    }
                                    setForm((prev) => ({ ...prev, image: f, imagePreview: f ? URL.createObjectURL(f) : (editing ? prev.imagePreview : null) }));
                                }} className="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20" />
                                {form.imagePreview && (
                                    <img src={form.imagePreview} alt="Pratinjau" className="mt-2 h-32 w-auto rounded-lg border border-border object-cover" />
                                )}
                                {fileError && <p className="mt-1 text-xs font-medium text-rose-600">{fileError}</p>}
                            </div>
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <label className="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-text-primary">
                                    <input type="checkbox" checked={form.is_pinned}
                                        onChange={(e) => setForm({ ...form, is_pinned: e.target.checked })}
                                        className="h-4 w-4 rounded border-border text-primary focus:ring-primary" />
                                    Sematkan (Pin)
                                </label>
                                {!userUnitId && (
                                    <div className="relative flex-1 sm:max-w-xs">
                                        <select value={form.unit_sekolah_id}
                                            onChange={(e) => setForm({ ...form, unit_sekolah_id: e.target.value })}
                                            className="select-field">
                                            <option value="">Semua unit</option>
                                            {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                        </select>
                                    </div>
                                )}
                                <div className="relative flex-1 sm:max-w-xs">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input type="datetime-local" value={form.published_at}
                                        onChange={(e) => setForm({ ...form, published_at: e.target.value })}
                                        className="input-field pl-9" />
                                </div>
                            </div>
                            <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
                                <button type="button" onClick={resetForm} className="btn-secondary">Batal</button>
                                <button type="submit" className="btn-primary inline-flex items-center gap-2">
                                    <Save className="h-4 w-4" /> {editing ? 'Simpan' : 'Publikasikan'}
                                </button>
                            </div>
                        </form>
                    )}

                    {/* List */}
                    <div className="space-y-2">
                        {items.length === 0 ? (
                            <div className="card flex flex-col items-center px-6 py-12 text-center">
                                <Megaphone className="h-8 w-8 text-border" />
                                <p className="mt-3 text-sm font-bold text-text-primary">Belum ada pengumuman</p>
                                <p className="mt-1 text-xs text-text-muted">Bagikan pengumuman pertama untuk mulai berkomunikasi dengan pegawai.</p>
                            </div>
                        ) : (
                            items.map((a) => (
                                <div key={a.id} className={`card p-5 transition-all hover:shadow-card ${a.is_pinned ? 'border-primary/40 bg-primary/[0.02]' : ''}`}>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {a.is_pinned && (
                                                    <span className="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">
                                                        <PinIcon className="h-3 w-3" /> Pin
                                                    </span>
                                                )}
                                                <h3 className="text-sm font-extrabold text-text-primary">{a.title}</h3>
                                            </div>
                                            {a.image_url && (
                                                <img src={a.image_url} alt={a.title} className="mt-2 w-full max-h-60 rounded-lg border border-border object-cover" />
                                            )}
                                            <p className="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-text-secondary">{a.body}</p>
                                            <p className="mt-2 flex items-center gap-1 text-xs text-text-muted">
                                                <Calendar className="h-3 w-3" />
                                                {a.published_at ? format(new Date(a.published_at), 'd MMM yyyy, HH:mm', { locale: idLocale }) : '-'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-1.5">
                                            <button onClick={() => openEdit(a)} title="Edit"
                                                className="rounded-lg bg-surface p-2 text-text-secondary transition-colors hover:text-primary">
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button onClick={() => destroy(a.id)} title="Hapus"
                                                className="rounded-lg bg-rose-50 p-2 text-rose-500 transition-colors hover:bg-rose-100">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {announcements.links && items.length > 0 && (
                        <Pagination links={announcements.links} className="justify-center" />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
