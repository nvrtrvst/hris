import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { Bell, Plus, Trash2, Send, Clock, Calendar, Filter, AlertCircle, Repeat, Users, Building2, CheckCircle, RotateCcw } from 'lucide-react';

const TYPE_OPTIONS = [
    { value: 'presensi', label: 'Presensi', color: 'bg-blue-100 text-blue-700' },
    { value: 'cuti', label: 'Cuti', color: 'bg-purple-100 text-purple-700' },
    { value: 'deadline', label: 'Deadline', color: 'bg-orange-100 text-orange-700' },
    { value: 'custom', label: 'Custom', color: 'bg-gray-100 text-gray-700' },
];

export default function Index({ auth, reminders, units, filters, pegawaiOptions = [] }) {
    const { flash } = usePage().props;
    const [showCreate, setShowCreate] = useState(false);
    const [typeFilter, setTypeFilter] = useState(filters?.type || 'semua');

    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        message: '',
        type: 'custom',
        unit_sekolah_id: '',
        target_all: true,
        target_user_ids: [],
        is_recurring: false,
        recurring_schedule: 'daily',
        recurring_time: '07:00',
        scheduled_at: '',
    });

    const openCreate = () => {
        reset();
        setShowCreate(true);
    };

    const closeCreate = () => {
        setShowCreate(false);
        reset();
    };

    const submitCreate = (e) => {
        e.preventDefault();
        post(route('reminders.store'), {
            onSuccess: () => closeCreate(),
        });
    };

    const handleDelete = (id) => {
        if (!confirm('Hapus reminder ini?')) return;
        router.delete(route('reminders.destroy', id), {
            preserveState: true,
        });
    };

    const handleSendNow = (id) => {
        if (!confirm('Kirim reminder ini sekarang?')) return;
        router.post(route('reminders.send', id), {}, {
            preserveState: true,
        });
    };

    const handleRevoke = (id) => {
        if (!confirm('Tarik kembali reminder ini? Notifikasi akan dihapus dari mobile pegawai.')) return;
        router.post(route('reminders.revoke', id), {}, {
            preserveState: true,
        });
    };

    const handleFilterType = (type) => {
        setTypeFilter(type);
        router.get(route('reminders.index'), { type }, {
            preserveState: true,
            replace: true,
        });
    };

    const getTypeBadge = (type) => {
        const opt = TYPE_OPTIONS.find(t => t.value === type);
        return opt || TYPE_OPTIONS[3];
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Kelola Reminder</h2>}>
            <Head title="Reminder" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 className="text-xl font-extrabold text-text-primary">Reminder</h3>
                            <p className="text-sm text-text-muted">Kirim pengingat otomatis atau manual ke pegawai.</p>
                        </div>
                        <button onClick={openCreate} className="btn-primary inline-flex items-center gap-2">
                            <Plus className="w-4 h-4" /> Buat Reminder
                        </button>
                    </div>

                    {/* Filter */}
                    <div className="card p-4">
                        <div className="flex items-center gap-3 flex-wrap">
                            <Filter className="w-4 h-4 text-text-muted" />
                            <button onClick={() => handleFilterType('semua')}
                                className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors ${typeFilter === 'semua' ? 'bg-primary text-white' : 'bg-surface text-text-muted hover:bg-gray-100'}`}>
                                Semua
                            </button>
                            {TYPE_OPTIONS.map(t => (
                                <button key={t.value} onClick={() => handleFilterType(t.value)}
                                    className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors ${typeFilter === t.value ? 'bg-primary text-white' : 'bg-surface text-text-muted hover:bg-gray-100'}`}>
                                    {t.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* List */}
                    <div className="card overflow-hidden">
                        {reminders.data.length === 0 ? (
                            <div className="text-center py-12">
                                <Bell className="mx-auto h-12 w-12 text-text-muted/30" />
                                <p className="mt-3 text-sm text-text-muted">Belum ada reminder.</p>
                            </div>
                        ) : (
                            <div className="divide-y divide-border">
                                {reminders.data.map((r) => {
                                    const badge = getTypeBadge(r.type);
                                    return (
                                        <div key={r.id} className="px-6 py-4 hover:bg-surface/50 transition-colors">
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <h4 className="text-sm font-bold text-text-primary truncate">{r.title}</h4>
                                                        <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold ${badge.color}`}>
                                                            {badge.label}
                                                        </span>
                                                        {r.sent_at && (
                                                            <span className="px-2 py-0.5 rounded-full text-[11px] font-bold bg-green-100 text-green-700 flex items-center gap-1">
                                                                <CheckCircle className="w-3 h-3" /> Terkirim
                                                            </span>
                                                        )}
                                                        {!r.sent_at && r.scheduled_at && (
                                                            <span className="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 flex items-center gap-1">
                                                                <Clock className="w-3 h-3" /> Terjadwal
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-text-secondary line-clamp-2">{r.message}</p>
                                                    <div className="flex items-center gap-3 mt-2 text-[11px] text-text-muted">
                                                        {r.unit_sekolah ? (
                                                            <span className="flex items-center gap-1"><Building2 className="w-3 h-3" /> {r.unit_sekolah.nama}</span>
                                                        ) : (
                                                            <span className="flex items-center gap-1"><Users className="w-3 h-3" /> Semua unit</span>
                                                        )}
                                                        {r.scheduled_at && (
                                                            <span className="flex items-center gap-1"><Calendar className="w-3 h-3" /> {new Date(r.scheduled_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                                                        )}
                                                        {r.is_recurring && (
                                                            <span className="flex items-center gap-1"><Repeat className="w-3 h-3" /> {r.recurring_schedule}</span>
                                                        )}
                                                        <span>Oleh: {r.creator?.name || '-'}</span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-1 flex-shrink-0">
                                                    {!r.sent_at && (
                                                        <button onClick={() => handleSendNow(r.id)}
                                                            className="p-2 rounded-lg text-primary hover:bg-primary/10 transition-colors" title="Kirim Sekarang">
                                                            <Send className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                    {r.sent_at && (
                                                        <button onClick={() => handleRevoke(r.id)}
                                                            className="p-2 rounded-lg text-amber-600 hover:bg-amber-50 transition-colors" title="Tarik Kembali">
                                                            <RotateCcw className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                    <button onClick={() => handleDelete(r.id)}
                                                        className="p-2 rounded-lg text-danger hover:bg-danger/10 transition-colors" title="Hapus">
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {reminders.links && reminders.data.length > 0 && (
                            <div className="pagination px-6 py-4 border-t border-border bg-surface/50">
                                <Pagination links={reminders.links} data={{ type: typeFilter }} />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Create Modal */}
            <Modal show={showCreate} onClose={closeCreate} maxWidth="lg">
                <div className="bg-white rounded-card overflow-hidden">
                    <div className="page-card-header px-6 py-4 bg-surface border-border">
                        <h2 className="text-lg font-bold text-text-primary flex items-center gap-2">
                            <Bell className="w-5 h-5 text-primary" /> Buat Reminder Baru
                        </h2>
                    </div>

                    <form onSubmit={submitCreate} className="p-6 space-y-4">
                        <div>
                            <label className="form-label text-sm font-semibold">Judul <span className="text-danger">*</span></label>
                            <input type="text" value={data.title} onChange={e => setData('title', e.target.value)}
                                className="input-field w-full" placeholder="Misal: Reminder Presensi Hari Ini" required />
                            {errors.title && <p className="text-danger text-xs mt-1">{errors.title}</p>}
                        </div>

                        <div>
                            <label className="form-label text-sm font-semibold">Pesan <span className="text-danger">*</span></label>
                            <textarea value={data.message} onChange={e => setData('message', e.target.value)}
                                className="input-field w-full" rows="3" placeholder="Isi pesan reminder..." required />
                            {errors.message && <p className="text-danger text-xs mt-1">{errors.message}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="form-label text-sm font-semibold">Jenis <span className="text-danger">*</span></label>
                                <select value={data.type} onChange={e => setData('type', e.target.value)} className="select-field w-full">
                                    {TYPE_OPTIONS.map(t => (
                                        <option key={t.value} value={t.value}>{t.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="form-label text-sm font-semibold">Unit Sekolah</label>
                                <select value={data.unit_sekolah_id} onChange={e => setData('unit_sekolah_id', e.target.value)} className="select-field w-full">
                                    <option value="">Semua Unit</option>
                                    {units.map(u => (
                                        <option key={u.id} value={u.id}>{u.nama}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="form-label text-sm font-semibold">Target</label>
                                <select value={data.target_all ? 'all' : 'custom'} onChange={e => setData('target_all', e.target.value === 'all')} className="select-field w-full">
                                    <option value="all">Semua Pegawai Aktif</option>
                                    <option value="custom">Pilih Pegawai</option>
                                </select>
                            </div>
                            <div>
                                <label className="form-label text-sm font-semibold">Kirim</label>
                                <select value={data.scheduled_at ? 'scheduled' : 'now'} onChange={e => {
                                    if (e.target.value === 'now') {
                                        setData('scheduled_at', '');
                                    }
                                }} className="select-field w-full">
                                    <option value="now">Langsung Kirim</option>
                                    <option value="scheduled">Terjadwal</option>
                                </select>
                            </div>
                        </div>

                        {!data.target_all && (
                            <div>
                                <label className="form-label text-sm font-semibold">Pilih Pegawai</label>
                                <div className="max-h-48 overflow-y-auto space-y-1 rounded-xl border border-border p-2">
                                    {pegawaiOptions.length === 0 ? (
                                        <p className="text-xs text-text-muted">Tidak ada pegawai aktif.</p>
                                    ) : (
                                        pegawaiOptions.map((p) => (
                                            <label key={p.id} className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="h-4 w-4 accent-[#0F3D3E]"
                                                    checked={data.target_user_ids.includes(p.id)}
                                                    onChange={(e) => {
                                                        if (e.target.checked) {
                                                            setData('target_user_ids', [...data.target_user_ids, p.id]);
                                                        } else {
                                                            setData('target_user_ids', data.target_user_ids.filter((x) => x !== p.id));
                                                        }
                                                    }}
                                                />
                                                {p.nama}
                                            </label>
                                        ))
                                    )}
                                </div>
                                {errors.target_user_ids && <p className="text-danger text-xs mt-1">{errors.target_user_ids}</p>}
                            </div>
                        )}

                        <div className="flex items-center gap-2">
                            <input type="checkbox" checked={data.is_recurring} onChange={e => setData('is_recurring', e.target.checked)}
                                className="h-4 w-4 accent-[#0F3D3E]" id="recurring" />
                            <label htmlFor="recurring" className="text-sm text-text-secondary">Pengulangan (Recurring)</label>
                        </div>

                        {data.is_recurring && (
                            <>
                                <div>
                                    <label className="form-label text-sm font-semibold">Jadwal Pengulangan</label>
                                    <select value={data.recurring_schedule} onChange={e => setData('recurring_schedule', e.target.value)} className="select-field w-full">
                                        <option value="daily">Harian</option>
                                        <option value="weekly">Mingguan</option>
                                        <option value="monthly">Bulanan</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="form-label text-sm font-semibold">Jam Kirim</label>
                                    <input type="time" value={data.recurring_time}
                                        onChange={e => setData('recurring_time', e.target.value)}
                                        className="input-field w-full" required />
                                </div>
                                <div className="rounded-xl bg-surface p-3 text-xs text-text-muted">
                                    <p className="font-semibold text-text-secondary mb-1">Hari Kerja Otomatis</p>
                                    <p>
                                        {(() => {
                                            const unit = units.find(u => String(u.id) === String(data.unit_sekolah_id));
                                            const hasSabtu = unit?.jam_kerja_sabtu_mulai;
                                            return hasSabtu
                                                ? 'Senin — Sabtu (unit ini kerja Sabtu)'
                                                : 'Senin — Jumat';
                                        })()}
                                    </p>
                                </div>
                            </>
                        )}

                        {errors.unit_sekolah_id && <p className="text-danger text-xs">{errors.unit_sekolah_id}</p>}

                        <div className="flex justify-end gap-3 pt-4 border-t border-border">
                            <button type="button" onClick={closeCreate} className="btn-secondary">Batal</button>
                            <button type="submit" disabled={processing} className="btn-primary">
                                <Bell className="w-4 h-4 mr-2" />
                                {processing ? 'Mengirim...' : 'Buat & Kirim'}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
