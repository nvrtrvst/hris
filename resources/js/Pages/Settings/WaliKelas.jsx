import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Save, UserRoundCheck } from 'lucide-react';

import { labelKelas } from '@/Utils/kelas';

export default function WaliKelas({ auth, units = [], mappings = [], pegawais = [], classes = [], filters = {}, defaults = {} }) {
    const { flash } = usePage().props;
    const [manualClass, setManualClass] = useState(false);
    const [editingId, setEditingId] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        unit_sekolah_id: filters.unit_id || '',
        class_id: '',
        class_label: '',
        pegawai_id: '',
        tahun_ajaran: defaults.tahun_ajaran || '',
        semester: defaults.semester || '1',
    });

    useEffect(() => {
        setEditingId(null);
        setManualClass(false);
        setData({
            unit_sekolah_id: filters.unit_id || '',
            class_id: '',
            class_label: '',
            pegawai_id: '',
            tahun_ajaran: defaults.tahun_ajaran || '',
            semester: defaults.semester || '1',
        });
    }, [filters.unit_id, filters.tahun_ajaran, filters.semester, defaults.tahun_ajaran, defaults.semester]);

    const currentUnit = units.find((u) => String(u.id) === String(filters.unit_id));

    const classOptions = useMemo(
        () => classes.map((c) => ({ id: String(c.id ?? ''), label: labelKelas(c) })).filter((c) => c.label),
        [classes],
    );

    const submitFilter = (e) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        router.get(route('wali-kelas.index'), {
            unit_id: form.get('unit_id') || undefined,
            tahun_ajaran: form.get('tahun_ajaran') || undefined,
            semester: form.get('semester') || undefined,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('wali-kelas.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingId(null);
                setManualClass(false);
                reset('class_id', 'class_label', 'pegawai_id');
            },
        });
    };

    const pilihKelas = (value) => {
        if (value === 'manual') {
            setManualClass(true);
            setData({ ...data, class_id: '', class_label: '' });
            return;
        }
        const selected = classOptions.find((c) => c.id === value);
        setManualClass(false);
        setData({ ...data, class_id: value, class_label: selected?.label || '' });
    };

    const mulaiEdit = (mapping) => {
        setEditingId(mapping.id);
        const known = classOptions.some((c) => c.label === mapping.class_label);
        setManualClass(!known);
        setData({
            unit_sekolah_id: String(mapping.unit_sekolah_id),
            class_id: mapping.class_id || '',
            class_label: mapping.class_label,
            pegawai_id: String(mapping.pegawai_id),
            tahun_ajaran: mapping.tahun_ajaran,
            semester: mapping.semester,
        });
    };

    const nonaktifkan = (mapping) => {
        if (!window.confirm(`Nonaktifkan wali kelas ${mapping.class_label}?`)) return;
        router.delete(route('wali-kelas.nonaktifkan', mapping.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Wali Kelas</h2>}>
            <Head title="Wali Kelas" />

            <div className="mx-auto w-full max-w-6xl space-y-4">
                {flash?.message && (
                    <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        {flash.message}
                    </div>
                )}

                <form onSubmit={submitFilter} className="page-card p-4 sm:p-5">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <label className="block">
                            <span className="form-label">Unit</span>
                            <select name="unit_id" defaultValue={filters.unit_id || ''} className="select-field w-full" required>
                                {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                            </select>
                        </label>
                        <label className="block">
                            <span className="form-label">Tahun ajaran</span>
                            <input name="tahun_ajaran" defaultValue={filters.tahun_ajaran || defaults.tahun_ajaran || ''} className="input-field w-full" placeholder="2026/2027" />
                        </label>
                        <label className="block">
                            <span className="form-label">Semester</span>
                            <select name="semester" defaultValue={filters.semester || defaults.semester || '1'} className="select-field w-full">
                                <option value="1">1</option>
                                <option value="2">2</option>
                            </select>
                        </label>
                        <div className="flex items-end">
                            <button type="submit" className="btn-primary w-full">Tampilkan</button>
                        </div>
                    </div>
                </form>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
                    <form onSubmit={submit} className="page-card p-4 sm:p-5 lg:col-span-2">
                        <div className="mb-4 flex items-center gap-2">
                            <UserRoundCheck className="h-5 w-5 text-primary" />
                            <h3 className="section-title">{editingId ? 'Ubah Wali Kelas' : 'Tambah Wali Kelas'}</h3>
                        </div>

                        <p className="mb-3 text-xs text-text-muted">
                            Unit: <span className="font-semibold text-text-primary">{currentUnit?.nama || '-'}</span>
                        </p>

                        <div className="space-y-3">
                            <label className="block">
                                <span className="form-label">Kelas</span>
                                <select value={manualClass ? 'manual' : data.class_id} onChange={(e) => pilihKelas(e.target.value)} className="select-field w-full">
                                    <option value="">Pilih kelas</option>
                                    {classOptions.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                                    <option value="manual">Kelas tidak ada di daftar</option>
                                </select>
                                {manualClass && (
                                    <input value={data.class_label} onChange={(e) => setData('class_label', e.target.value)} className="input-field mt-2 w-full" placeholder="Nama kelas" maxLength={100} required />
                                )}
                                {errors.class_label && <p className="form-error">{errors.class_label}</p>}
                            </label>

                            <label className="block">
                                <span className="form-label">Pegawai</span>
                                <select value={data.pegawai_id} onChange={(e) => setData('pegawai_id', e.target.value)} className="select-field w-full" required>
                                    <option value="">Pilih pegawai</option>
                                    {pegawais.map((p) => <option key={p.id} value={p.id}>{p.nama_lengkap}</option>)}
                                </select>
                                {pegawais.length === 0 && <p className="form-hint">Belum ada pegawai aktif di unit ini.</p>}
                                {errors.pegawai_id && <p className="form-error">{errors.pegawai_id}</p>}
                            </label>

                            <div className="grid grid-cols-2 gap-3">
                                <label className="block">
                                    <span className="form-label">Tahun ajaran</span>
                                    <input value={data.tahun_ajaran} onChange={(e) => setData('tahun_ajaran', e.target.value)} className="input-field w-full" required maxLength={20} />
                                </label>
                                <label className="block">
                                    <span className="form-label">Semester</span>
                                    <select value={data.semester} onChange={(e) => setData('semester', e.target.value)} className="select-field w-full">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <button type="submit" disabled={processing || !filters.unit_id || pegawais.length === 0} className="btn-primary mt-4 inline-flex w-full items-center justify-center gap-2">
                            <Save className="h-4 w-4" /> Simpan
                        </button>
                    </form>

                    <div className="page-card card-table lg:col-span-3">
                        <div className="border-b border-border px-4 py-3">
                            <h3 className="section-title">Daftar Wali Kelas</h3>
                            <p className="mt-0.5 text-xs text-text-muted">{mappings.length} mapping</p>
                        </div>
                        <div className="table-wrap">
                            <table className="table-base">
                                <thead>
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Wali</th>
                                        <th>Periode</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {mappings.length === 0 && (
                                        <tr><td colSpan={5} className="py-6 text-center text-sm text-text-muted">Belum ada mapping untuk filter ini.</td></tr>
                                    )}
                                    {mappings.map((m) => (
                                        <tr key={m.id}>
                                            <td className="font-semibold">{m.class_label}</td>
                                            <td>{m.pegawai?.nama_lengkap || '-'}</td>
                                            <td className="whitespace-nowrap text-xs">{m.tahun_ajaran} · {m.semester}</td>
                                            <td>
                                                <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase ${m.aktif ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-border bg-surface text-text-muted'}`}>
                                                    {m.aktif ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap text-right">
                                                <button type="button" onClick={() => mulaiEdit(m)} className="text-xs font-bold text-primary hover:underline">Ubah</button>
                                                {m.aktif && (
                                                    <button type="button" onClick={() => nonaktifkan(m)} className="ml-3 text-xs font-bold text-rose-600 hover:underline">Nonaktifkan</button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
