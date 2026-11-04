import React, { useMemo, useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, Input, PageHeader } from '@/Components/AdminUI';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Download, FileSpreadsheet, Info, Loader2, Save, Search, SlidersHorizontal, Users as UsersIcon } from 'lucide-react';
import { formatRupiah } from '@/Utils/format';

export default function PegawaiKomponen({ auth, komponen, pegawais }) {
    const { data, setData, post, processing } = useForm({
        pegawai_data: pegawais.map((p) => ({ id: p.id, nominal: p.nominal ?? '' }))
    });

    const { data: importData, setData: setImportData, post: postImport, processing: importing, errors: importErrors } = useForm({
        file: null
    });

    const [searchTerm, setSearchTerm] = useState('');
    const [scopeFilter, setScopeFilter] = useState('semua'); // semua | spesifik | default

    const handleNominalChange = (index, value) => {
        const newData = [...data.pegawai_data];
        newData[index].nominal = value;
        setData('pegawai_data', newData);
    };

    const handleBatchSave = (e) => {
        e.preventDefault();
        post(route('komponen-gaji.pegawai.batch', komponen.id), {
            preserveScroll: true
        });
    };

    const handleImport = (e) => {
        e.preventDefault();
        postImport(route('komponen-gaji.pegawai.import', komponen.id), {
            preserveScroll: true,
            onSuccess: () => setImportData('file', null),
        });
    };

    const rows = useMemo(() => {
        const q = searchTerm.trim().toLowerCase();

        return pegawais
            .map((p, originalIndex) => ({ ...p, originalIndex, isSpesifik: p.nominal !== null && p.nominal !== '' }))
            .filter((p) => {
                if (scopeFilter === 'spesifik' && !p.isSpesifik) return false;
                if (scopeFilter === 'default' && p.isSpesifik) return false;
                if (!q) return true;

                return p.nama_lengkap.toLowerCase().includes(q)
                    || (p.nuptk || '').toLowerCase().includes(q);
            });
    }, [pegawais, searchTerm, scopeFilter]);

    const jmlSpesifik = pegawais.filter((p) => p.nominal !== null && p.nominal !== '').length;
    const totalSpesifik = pegawais.reduce((sum, p) => sum + (Number(p.nominal) || 0), 0);
    const scopeCounts = {
        semua: pegawais.length,
        spesifik: jmlSpesifik,
        default: pegawais.length - jmlSpesifik,
    };

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Penyesuaian Pegawai: {komponen.nama}</h2>}
        >
            <Head title={`Atur ${komponen.nama}`} />

            <div className="mx-auto w-full max-w-7xl space-y-5">
                <PageHeader
                    eyebrow="Penggajian"
                    title={`Penyesuaian ${komponen.nama}`}
                    description="Atur nominal khusus per pegawai. Nilai kosong memakai default komponen."
                    actions={
                        <Link href={route('komponen-gaji.index')} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            <ArrowLeft className="h-4 w-4" /> Kembali
                        </Link>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <span className={`inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide ${komponen.tipe === 'pendapatan' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'}`}>
                        {komponen.tipe === 'pendapatan' ? '+' : '−'} {komponen.tipe}
                    </span>
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-white px-3 py-1 text-[11px] font-semibold text-text-secondary">
                        <Info className="h-3.5 w-3.5 text-primary" />
                        Nilai default: <strong className="text-text-primary tabular-nums">{komponen.nilai_default ? formatRupiah(komponen.nilai_default) : '0'}</strong>
                    </span>
                </div>

                    {/* Stats ringkas */}
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Card className="flex items-center gap-3 p-4">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                <UsersIcon className="h-5 w-5 text-primary" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-xl font-extrabold leading-none text-text-primary tabular-nums">{pegawais.length}</p>
                                <p className="mt-1 truncate text-[11px] font-semibold uppercase tracking-wide text-text-secondary">Pegawai Aktif</p>
                            </div>
                        </Card>
                        <Card className="flex items-center gap-3 p-4">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                                <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-xl font-extrabold leading-none text-text-primary tabular-nums">{jmlSpesifik}</p>
                                <p className="mt-1 truncate text-[11px] font-semibold uppercase tracking-wide text-text-secondary">Nominal Spesifik</p>
                            </div>
                        </Card>
                        <Card className="flex items-center gap-3 p-4">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100">
                                <SlidersHorizontal className="h-5 w-5 text-sky-600" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-xl font-extrabold leading-none text-text-primary tabular-nums">{pegawais.length - jmlSpesifik}</p>
                                <p className="mt-1 truncate text-[11px] font-semibold uppercase tracking-wide text-text-secondary">Pakai Default</p>
                            </div>
                        </Card>
                        <Card className="flex items-center gap-3 p-4">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100">
                                <FileSpreadsheet className="h-5 w-5 text-amber-600" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-lg font-extrabold leading-none text-text-primary tabular-nums">{formatRupiah(totalSpesifik)}</p>
                                <p className="mt-1 truncate text-[11px] font-semibold uppercase tracking-wide text-text-secondary">Total Nominal Spesifik</p>
                            </div>
                        </Card>
                    </div>

                    {/* Import massal */}
                    <Card className="p-6">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-start gap-4">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                    <FileSpreadsheet className="h-5 w-5 text-primary" />
                                </span>
                                <div>
                                    <h3 className="text-base font-extrabold text-text-primary">Import Massal via Excel</h3>
                                    <p className="mt-0.5 text-sm text-text-muted">Unduh template, isi nominal potongan/tambahan per NIK, lalu upload kembali ke sini.</p>
                                    {importErrors?.file && <p className="mt-2 text-sm font-semibold text-danger">{importErrors.file}</p>}
                                </div>
                            </div>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <a href={route('komponen-gaji.pegawai.template', komponen.id)} className="btn-secondary inline-flex items-center justify-center gap-1.5">
                                    <Download className="h-4 w-4" /> Download Template Excel
                                </a>
                                <form onSubmit={handleImport} className="flex items-center gap-2">
                                    <input
                                        type="file"
                                        accept=".csv, .xlsx, .xls"
                                        onChange={(e) => setImportData('file', e.target.files[0])}
                                        className="block w-full max-w-52 text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20"
                                    />
                                    <Button type="submit" size="sm" disabled={importing || !importData.file}>
                                        {importing ? <><Loader2 className="h-3.5 w-3.5 animate-spin" /> Import…</> : <><FileSpreadsheet className="h-3.5 w-3.5" /> Upload</>}
                                    </Button>
                                </form>
                            </div>
                        </div>
                    </Card>

                    {/* Input manual */}
                    <form onSubmit={handleBatchSave}>
                        <Card className="overflow-hidden">
                            <div className="flex flex-col gap-3 border-b border-border bg-surface px-6 py-4 xl:flex-row xl:items-center xl:justify-between">
                                <div>
                                    <h3 className="flex items-center gap-2 text-base font-extrabold text-text-primary">
                                        <UsersIcon className="h-4 w-4 text-primary" /> Input Manual
                                    </h3>
                                    <p className="mt-0.5 text-xs text-text-muted">
                                        Kosongkan input untuk memakai nilai default komponen.
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {/* Segmented scope */}
                                    <div className="flex rounded-lg border border-border bg-white p-0.5">
                                        {[{ k: 'semua', label: `Semua (${scopeCounts.semua})` }, { k: 'spesifik', label: `Spesifik (${scopeCounts.spesifik})` }, { k: 'default', label: `Default (${scopeCounts.default})` }].map((t) => (
                                            <button
                                                key={t.k}
                                                type="button"
                                                onClick={() => setScopeFilter(t.k)}
                                                className={`rounded-md px-2.5 py-1 text-[11px] font-bold transition-colors ${scopeFilter === t.k ? 'bg-primary text-white' : 'text-text-secondary hover:text-primary'}`}
                                            >
                                                {t.label}
                                            </button>
                                        ))}
                                    </div>
                                    <div className="relative w-full sm:w-60">
                                        <Search className="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                        <Input
                                            type="text"
                                            placeholder="Cari nama / NIK..."
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            aria-label="Cari pegawai"
                                            className="h-9 pl-9 text-xs"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full table-fixed divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="hidden px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted md:table-cell md:w-32">NIK</th>
                                            <th className="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted sm:px-6">Nama Pegawai</th>
                                            <th className="hidden px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted md:table-cell md:w-28">Unit</th>
                                            <th className="w-32 px-4 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted sm:px-6 md:w-52">Nominal Spesifik (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border bg-white">
                                        {rows.map((p) => (
                                            <tr key={p.id} className={`transition-colors hover:bg-surface ${!p.isSpesifik ? 'opacity-80' : ''}`}>
                                                <td className="hidden whitespace-nowrap px-6 py-3 font-mono text-xs text-text-muted md:table-cell">{p.nuptk || '—'}</td>
                                                <td className="px-4 py-3 sm:px-6">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="text-sm font-semibold text-text-primary">{p.nama_lengkap}</span>
                                                        {p.isSpesifik ? (
                                                            <span className="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Spesifik</span>
                                                        ) : (
                                                            <span className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-500">Default</span>
                                                        )}
                                                    </div>
                                                    <p className="mt-0.5 font-mono text-[11px] text-text-muted md:hidden">{p.nuptk || '—'} · {p.unit}</p>
                                                </td>
                                                <td className="hidden whitespace-nowrap px-6 py-3 text-sm text-text-muted md:table-cell">{p.unit}</td>
                                                <td className="px-4 py-3 text-right sm:px-6">
                                                    <Input
                                                        type="number"
                                                        value={data.pegawai_data[p.originalIndex].nominal}
                                                        onChange={(e) => handleNominalChange(p.originalIndex, e.target.value)}
                                                        placeholder="Gunakan Default"
                                                        className={`w-full text-right sm:w-44 ${p.isSpesifik ? 'border-emerald-300 focus:border-emerald-400 focus:ring-emerald-200' : ''}`}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                        {rows.length === 0 && (
                                            <tr>
                                                <td colSpan="4" className="px-6 py-12 text-center">
                                                    <Search className="mx-auto h-7 w-7 text-border" />
                                                    <p className="mt-2 text-sm font-semibold text-text-primary">Tidak ada pegawai yang cocok</p>
                                                    <p className="text-xs text-text-muted">Coba ubah kata kunci atau filter.</p>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex flex-col gap-3 border-t border-border bg-surface/50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-xs text-text-muted">
                                    {rows.length > 0
                                        ? <><strong className="text-text-primary">{rows.length}</strong> pegawai tampil · <strong className="text-emerald-600">{jmlSpesifik}</strong> punya nominal spesifik</>
                                        : 'Tidak ada data untuk ditampilkan.'}
                                </p>
                                <Button type="submit" disabled={processing}>
                                    {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Simpan Semua Perubahan</>}
                                </Button>
                            </div>
                        </Card>
                    </form>
            </div>
        </AdminShell>
    );
}
