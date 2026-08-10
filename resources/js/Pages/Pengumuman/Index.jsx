import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Megaphone, PinIcon, Pencil, Trash2, Plus } from 'lucide-react';

export default function PengumumanIndex({ auth, announcements, units, userUnitId }) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ title: '', body: '', is_pinned: false, unit_sekolah_id: userUnitId || '', published_at: new Date().toISOString().slice(0, 16) });

    const resetForm = () => { setForm({ title: '', body: '', is_pinned: false, unit_sekolah_id: userUnitId || '', published_at: new Date().toISOString().slice(0, 16) }); setEditing(null); setShowForm(false); };
    const openEdit = (a) => { setForm({ title: a.title, body: a.body, is_pinned: a.is_pinned, unit_sekolah_id: a.unit_sekolah_id || '', published_at: a.published_at?.slice(0, 16) || '' }); setEditing(a.id); setShowForm(true); };
    const submit = (e) => {
        e.preventDefault();
        const method = editing ? 'put' : 'post';
        const routeName = editing ? route('pengumuman.update', editing) : route('pengumuman.store');
        router[method](routeName, form, { preserveState: true, onSuccess: () => resetForm() });
    };
    const destroy = (id) => { if (confirm('Hapus pengumuman ini?')) router.delete(route('pengumuman.destroy', id), { preserveState: true }); };

    return (
        <AuthenticatedLayout header={<h2 className="font-semibold text-xl text-primary">Pengumuman</h2>}>
            <Head title="Pengumuman" />
            <div className="py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <button onClick={() => { resetForm(); setShowForm(!showForm); }} className="mb-4 inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white"><Plus className="h-4 w-4" /> Tambah pengumuman</button>
                    {showForm && (
                        <form onSubmit={submit} className="mb-6 rounded-xl border bg-white p-4 space-y-3">
                            <input required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="Judul" className="w-full rounded-lg border px-3 py-2 text-sm" />
                            <textarea required value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} rows={4} placeholder="Isi pengumuman..." className="w-full rounded-lg border px-3 py-2 text-sm" />
                            <div className="flex items-center gap-4 text-sm">
                                <label className="flex items-center gap-2"><input type="checkbox" checked={form.is_pinned} onChange={(e) => setForm({ ...form, is_pinned: e.target.checked })} /> Pin</label>
                                {!userUnitId && (
                                    <select value={form.unit_sekolah_id} onChange={(e) => setForm({ ...form, unit_sekolah_id: e.target.value })} className="rounded-lg border px-2 py-1 text-sm">
                                        <option value="">Semua unit</option>
                                        {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                    </select>
                                )}
                                <input type="datetime-local" value={form.published_at} onChange={(e) => setForm({ ...form, published_at: e.target.value })} className="rounded-lg border px-2 py-1 text-sm" />
                            </div>
                            <button type="submit" className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white">{editing ? 'Simpan' : 'Publikasikan'}</button>
                        </form>
                    )}
                    <div className="space-y-2">
                        {announcements.data?.length === 0 ? (
                            <div className="rounded-xl bg-white p-8 text-center"><Megaphone className="mx-auto h-8 w-8 text-slate-300" /><p className="mt-3 font-bold text-slate-500">Belum ada pengumuman</p></div>
                        ) : (announcements.data || announcements).map((a) => (
                            <div key={a.id} className={`rounded-xl border p-4 ${a.is_pinned ? 'border-primary/30 bg-primary/[0.02]' : 'bg-white'}`}>
                                <div className="flex items-start justify-between">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">{a.is_pinned && <PinIcon className="h-4 w-4 text-primary" />}<h3 className="font-bold text-slate-900">{a.title}</h3></div>
                                        <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">{a.body}</p>
                                        <p className="mt-2 text-xs text-slate-400">{a.published_at}</p>
                                    </div>
                                    <div className="flex shrink-0 gap-1 ml-3">
                                        <button onClick={() => openEdit(a)} className="rounded-lg bg-slate-100 p-2 text-slate-500"><Pencil className="h-4 w-4" /></button>
                                        <button onClick={() => destroy(a.id)} className="rounded-lg bg-rose-50 p-2 text-rose-500"><Trash2 className="h-4 w-4" /></button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                    <Pagination links={announcements.links} className="mt-4 justify-center" />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}