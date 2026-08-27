import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import StatCard from '@/Components/StatCard';
import { Calendar, Megaphone, Paperclip, Pencil, Pin as PinIcon, Plus, Save, Trash2, X as XIcon } from 'lucide-react';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';
import { validateUpload } from '@/Utils/file';

const FILE_MAX = 5 * 1024 * 1024;
const FILE_ACCEPT = ['application/pdf', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];

export default function PengumumanIndex({ auth, announcements, units, userUnitId, flash }) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ title: '', body: '', is_pinned: false, unit_sekolah_id: userUnitId || '', published_at: new Date().toISOString().slice(0, 16), attachment: null, attachmentType: null, imagePreview: null, attachmentRemoved: false });
    const [attachmentError, setAttachmentError] = useState(null);
    const [editAttachmentUrl, setEditAttachmentUrl] = useState(null);
    const [editAttachmentType, setEditAttachmentType] = useState(null);

    const resetForm = () => {
        setForm({ title: '', body: '', is_pinned: false, unit_sekolah_id: userUnitId || '', published_at: new Date().toISOString().slice(0, 16), attachment: null, attachmentType: null, imagePreview: null, attachmentRemoved: false });
        setEditing(null);
        setShowForm(false);
        setAttachmentError(null);
        setEditAttachmentUrl(null);
        setEditAttachmentType(null);
    };
    const openEdit = (a) => {
        const hasImage = !!a.image_url;
        setForm({ title: a.title, body: a.body, is_pinned: a.is_pinned, unit_sekolah_id: a.unit_sekolah_id || '', published_at: a.published_at?.slice(0, 16) || '', attachment: null, attachmentType: null, imagePreview: hasImage ? a.image_url : null, attachmentRemoved: false });
        setEditAttachmentUrl(hasImage ? a.image_url : (a.file_url || null));
        setEditAttachmentType(hasImage ? 'image' : (a.file_url ? 'file' : null));
        setEditing(a.id);
        setShowForm(true);
    };
    const submit = (e) => {
        e.preventDefault();
        if (attachmentError) return;
        const method = editing ? 'put' : 'post';
        const routeName = editing ? route('pengumuman.update', editing) : route('pengumuman.store');
        const payload = { ...form };
        delete payload.imagePreview;
        delete payload.attachment;
        delete payload.attachmentType;
        if (form.attachmentRemoved) {
            payload.image = null;
            payload.file = null;
            payload.remove_file = 1;
        } else if (form.attachment) {
            if (form.attachmentType === 'image') {
                payload.image = form.attachment;
                payload.file = null;
            } else {
                payload.file = form.attachment;
                payload.image = null;
            }
        }
        router[method](routeName, payload, { preserveState: true, onSuccess: () => resetForm() });
    };
    const destroy = (id) => {
        if (confirm('Hapus pengumuman ini?')) router.delete(route('pengumuman.destroy', id), { preserveState: true });
    };

    const total = announcements?.total ?? announcements?.data?.length ?? 0;
    const items = announcements.data || announcements || [];
    const serverErrors = usePage().props.errors || {};

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
                            {Object.keys(serverErrors).length > 0 && (
                                <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
                                    {Object.values(serverErrors).map((m, i) => (<div key={i}>{m}</div>))}
                                </div>
                            )}
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
                                <label className="form-label text-xs">Lampiran (opsional)</label>
                                <p className="text-xs text-text-muted">Gambar JPG/PNG/WebP (maks 2 MB) atau dokumen PDF/DOC/XLS/PPT/ZIP (maks 5 MB).</p>
                                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip" onChange={(e) => {
                                    const f = e.target.files?.[0] || null;
                                    setAttachmentError(null);
                                    if (f) {
                                        const isImage = f.type.startsWith('image/');
                                        const maxBytes = isImage ? 2 * 1024 * 1024 : FILE_MAX;
                                        const accept = isImage ? ['image/jpeg', 'image/png', 'image/webp'] : FILE_ACCEPT;
                                        const err = validateUpload(f, { maxBytes, accept, label: isImage ? 'Gambar' : 'File' });
                                        if (err) {
                                            setAttachmentError(err);
                                            setForm((prev) => ({ ...prev, attachment: null, attachmentType: null, imagePreview: null }));
                                            e.target.value = '';
                                            return;
                                        }
                                        setForm((prev) => ({ ...prev, attachment: f, attachmentType: isImage ? 'image' : 'file', imagePreview: isImage ? URL.createObjectURL(f) : null }));
                                    } else {
                                        setForm((prev) => ({ ...prev, attachment: null, attachmentType: null, imagePreview: null }));
                                    }
                                }} className="block w-full text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20" />
                                {(form.attachment || (editing && !form.attachmentRemoved && editAttachmentUrl)) && (
                                    <div className="mt-2 flex items-center gap-2">
                                        {(form.attachmentType === 'image' || (editing && editAttachmentType === 'image' && !form.attachmentRemoved)) ? (
                                            <img src={form.imagePreview || editAttachmentUrl} alt="Pratinjau" className="h-24 w-auto rounded-lg border border-border object-cover" />
                                        ) : (
                                            <span className="truncate text-xs text-text-secondary">
                                                {form.attachment ? form.attachment.name : 'Lampiran saat ini'}
                                            </span>
                                        )}
                                        <button type="button" onClick={() => setForm((prev) => ({ ...prev, attachment: null, attachmentType: null, imagePreview: null, attachmentRemoved: true }))}
                                            className="rounded-lg bg-surface px-2 py-1 text-[10px] font-bold text-danger hover:bg-danger/10">
                                            Hapus
                                        </button>
                                    </div>
                                )}
                                {attachmentError && <p className="mt-1 text-xs font-medium text-rose-600">{attachmentError}</p>}
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
                                            {a.file_url && (
                                                <a href={a.file_url} target="_blank" rel="noopener noreferrer"
                                                    className="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/10">
                                                    <Paperclip className="h-3.5 w-3.5" /> Unduh lampiran
                                                </a>
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
