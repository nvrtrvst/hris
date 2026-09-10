import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Database, Loader2, Plus, Save, Trash2, Pencil } from 'lucide-react';
import { formatRupiah } from '@/Utils/format';

const inputClass = 'input-field';
const selectClass = 'input-field';

const Field = ({ label, required, error, children }) => (
    <div>
        <label className="form-label text-xs">{label} {required && <span className="text-danger">*</span>}</label>
        {children}
        {error && <p className="form-error">{error}</p>}
    </div>
);

function TypeForm({ allowedSources, processing, onSubmit, initial, onCancel }) {
    const { data, setData, post, put, errors } = useForm(initial);

    const submit = (e) => {
        e.preventDefault();
        if (initial.id) {
            put(route('reference-types.update', initial.id), { onSuccess: onCancel });
        } else {
            post(route('reference-types.store'), { onSuccess: () => { onCancel?.(); } });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <Field label="Kode" required error={errors.kode}>
                <input type="text" value={data.kode} onChange={(e) => setData('kode', e.target.value)}
                    placeholder="contoh: pendidikan" className={inputClass} />
            </Field>
            <Field label="Nama" required error={errors.nama}>
                <input type="text" value={data.nama} onChange={(e) => setData('nama', e.target.value)}
                    placeholder="contoh: Pendidikan" className={inputClass} />
            </Field>
            <Field label="Source Field" required error={errors.source_field}>
                <select value={data.source_field} onChange={(e) => setData('source_field', e.target.value)} className={selectClass}>
                    <option value="">— Pilih —</option>
                    {allowedSources.map((f) => (
                        <option key={f} value={f}>{f}</option>
                    ))}
                </select>
            </Field>
            <Field label="Aktif">
                <label className="inline-flex items-center gap-2 mt-1">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-slate-300" />
                    <span className="text-sm">Aktif</span>
                </label>
            </Field>
            <div className="flex justify-end gap-2 border-t border-slate-200 dark:border-slate-700 pt-4">
                <button type="button" onClick={onCancel} className="btn-secondary">Batal</button>
                <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                    {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                    {initial.id ? 'Update' : 'Simpan'}
                </button>
            </div>
        </form>
    );
}

function ValueForm({ komponens, processing, onSubmit, onCancel }) {
    const { data, setData, post, errors } = useForm({
        komponen_gaji_id: '',
        reference_key: '',
        nominal: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('payroll.reference-values.store', onSubmit), {
            onSuccess: () => onCancel?.(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <Field label="Komponen Gaji" required error={errors.komponen_gaji_id}>
                <select value={data.komponen_gaji_id} onChange={(e) => setData('komponen_gaji_id', e.target.value)} className={selectClass}>
                    <option value="">— Pilih Komponen —</option>
                    {komponens.map((k) => (
                        <option key={k.id} value={k.id}>{k.kode} — {k.nama}</option>
                    ))}
                </select>
            </Field>
            <Field label="Reference Key" required error={errors.reference_key}>
                <input type="text" value={data.reference_key} onChange={(e) => setData('reference_key', e.target.value)}
                    placeholder="contoh: S1, S2, SMA" className={inputClass} />
            </Field>
            <Field label="Nominal" required error={errors.nominal}>
                <input type="number" step="0.01" value={data.nominal} onChange={(e) => setData('nominal', e.target.value)}
                    placeholder="contoh: 500000" className={inputClass} />
            </Field>
            <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={onCancel} className="btn-secondary text-xs">Batal</button>
                <button type="submit" disabled={processing} className="btn-primary text-xs flex items-center gap-1">
                    {processing ? <Loader2 className="h-3 w-3 animate-spin" /> : <Plus className="h-3 w-3" />} Simpan
                </button>
            </div>
        </function>
    );
}

export default function ReferenceTypes({ auth, types, allowedSources, komponens }) {
    const [editingType, setEditingType] = useState(null);
    const [showNewType, setShowNewType] = useState(false);
    const [expandedType, setExpandedType] = useState(null);

    const emptyType = { kode: '', nama: '', source_field: '', is_active: true };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Payroll Reference Types</h2>}
        >
            <Head title="Reference Types" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {/* Whitelisted source fields info */}
                    <div className="card p-4">
                        <p className="text-xs font-semibold text-text-muted mb-2">Whitelisted source fields (aman dipakai):</p>
                        <div className="flex flex-wrap gap-2">
                            {allowedSources.map((field) => (
                                <span key={field} className="px-2 py-1 text-xs bg-slate-100 dark:bg-slate-700 rounded font-mono">
                                    {field}
                                </span>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2">
                            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10">
                                <Database className="h-5 w-5 text-primary" />
                            </span>
                            <h3 className="text-base font-extrabold text-text-primary">Daftar Reference Types</h3>
                        </div>
                        <span className="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                            {types.length} type
                        </span>
                        <div className="ml-auto">
                            <button onClick={() => { setShowNewType(true); setEditingType(null); }} className="btn-primary text-xs flex items-center gap-1">
                                <Plus className="h-3.5 w-3.5" /> Tambah Type
                            </button>
                        </div>
                    </div>

                    {/* New type form */}
                    {showNewType && (
                        <div className="card p-5">
                            <TypeForm
                                allowedSources={allowedSources}
                                processing={false}
                                initial={emptyType}
                                onSubmit={null}
                                onCancel={() => setShowNewType(false)}
                            />
                        </div>
                    )}

                    {/* Types list */}
                    <div className="space-y-4">
                        {types.length === 0 && !showNewType && (
                            <div className="card p-12 text-center">
                                <Database className="mx-auto h-7 w-7 text-border" />
                                <p className="mt-2 text-sm font-semibold">Belum ada reference type</p>
                                <p className="text-xs text-text-muted">Tambah type untuk mengaktifkan lookup_reference di komponen gaji.</p>
                            </div>
                        )}

                        {types.map((t) => (
                            <div key={t.id} className="card overflow-hidden">
                                {/* Type header row */}
                                <div className="flex items-center justify-between px-6 py-4 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                                    <div className="flex items-center gap-4">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-sm font-bold text-primary">{t.kode}</span>
                                                <span className="text-sm font-semibold">{t.nama}</span>
                                                {!t.is_active && (
                                                    <span className="px-1.5 py-0.5 text-[10px] font-bold bg-slate-200 dark:bg-slate-600 rounded">NONAKTIF</span>
                                                )}
                                            </div>
                                            <p className="text-xs text-text-muted mt-0.5">source: <span className="font-mono">{t.source_field}</span> · {t.values?.length ?? 0} nilai</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button
                                            onClick={() => setExpandedType(expandedType === t.id ? null : t.id)}
                                            className="btn-secondary text-xs"
                                        >
                                            {expandedType === t.id ? 'Tutup' : 'Kelola Nilai'}
                                        </button>
                                        <button
                                            onClick={() => { setEditingType(editingType === t.id ? null : t.id); setShowNewType(false); }}
                                            className="text-slate-400 hover:text-primary"
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button
                                            onClick={() => {
                                                if (confirm(`Hapus type "${t.kode}"?`)) {
                                                    router.delete(route('reference-types.destroy', t.id));
                                                }
                                            }}
                                            className="text-slate-400 hover:text-red-600"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                {/* Edit form */}
                                {editingType === t.id && (
                                    <div className="px-6 py-4 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                                        <TypeForm
                                            allowedSources={allowedSources}
                                            processing={false}
                                            initial={{ ...t, is_active: !!t.is_active }}
                                            onCancel={() => setEditingType(null)}
                                        />
                                    </div>
                                )}

                                {/* Values table */}
                                {expandedType === t.id && (
                                    <div className="px-6 py-4 bg-slate-50 dark:bg-slate-900">
                                        <p className="text-xs font-semibold text-text-muted mb-3">Nilai untuk type <span className="font-mono text-primary">{t.kode}</span></p>
                                        <table className="min-w-full text-sm mb-4">
                                            <thead>
                                                <tr>
                                                    <th className="text-left py-2 px-3 text-xs font-bold uppercase text-text-muted">Komponen</th>
                                                    <th className="text-left py-2 px-3 text-xs font-bold uppercase text-text-muted">Key</th>
                                                    <th className="text-right py-2 px-3 text-xs font-bold uppercase text-text-muted">Nominal</th>
                                                    <th className="text-right py-2 px-3 text-xs font-bold uppercase text-text-muted">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                                {(t.values ?? []).map((v) => (
                                                    <ValueRow key={v.id} value={v} komponens={komponens} typeId={t.id} />
                                                ))}
                                                {(!t.values || t.values.length === 0) && (
                                                    <tr>
                                                        <td colSpan={4} className="py-6 text-center text-xs text-text-muted">Belum ada nilai. Tambah di bawah.</td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>

                                        {/* Add value form */}
                                        <div className="border-t border-slate-200 dark:border-slate-700 pt-3">
                                            <p className="text-xs font-semibold text-text-muted mb-2">+ Tambah Nilai</p>
                                            <ValueForm
                                                komponens={komponens}
                                                processing={false}
                                                onSubmit={t.id}
                                                onCancel={() => setExpandedType(null)}
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function ValueRow({ value, komponens, typeId }) {
    const { data, setData, put, processing, errors } = useForm({
        reference_key: value.reference_key,
        nominal: value.nominal,
    });

    const komponen = komponens?.find((k) => k.id === value.komponen_gaji_id);

    const submitUpdate = (e) => {
        e.preventDefault();
        put(route('payroll.reference-values.update', value.id), data);
    };

    return (
        <tr className="hover:bg-white dark:hover:bg-slate-800">
            <td className="py-2 px-3 text-xs">
                <span className="font-mono text-primary">{komponen?.kode ?? '—'}</span>
                <span className="text-text-muted ml-1">{komponen?.nama ?? ''}</span>
            </td>
            <td className="py-2 px-3 font-mono text-xs">{value.reference_key}</td>
            <td className="py-2 px-3 text-right text-xs tabular-nums">{formatRupiah(value.nominal)}</td>
            <td className="py-2 px-3 text-right">
                <form onSubmit={submitUpdate} className="inline-flex items-center gap-1">
                    <input type="number" step="0.01" value={data.nominal}
                        onChange={(e) => setData('nominal', e.target.value)}
                        className="input-field w-28 text-xs py-1" />
                    <button type="submit" disabled={processing} className="btn-primary text-xs px-2 py-1">
                        {processing ? <Loader2 className="h-3 w-3 animate-spin" /> : <Save className="h-3 w-3" />}
                    </button>
                    <button type="button" onClick={() => {
                        if (confirm('Hapus nilai ini?')) {
                            router.delete(route('payroll.reference-values.destroy', value.id));
                        }
                    }} className="text-red-400 hover:text-red-600">
                        <Trash2 className="h-3.5 w-3.5" />
                    </button>
                </form>
                {errors.nominal && <p className="form-error text-xs">{errors.nominal}</p>}
            </td>
        </tr>
    );
}
