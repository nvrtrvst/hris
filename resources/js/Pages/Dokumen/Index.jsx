import React, { useEffect, useRef, useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import Modal from '@/Components/Modal';
import FilterSheet from '@/Components/FilterSheet';
import Pagination from '@/Components/Pagination';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    FormField,
    IconButton,
    Input,
    PageHeader,
    Select,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/AdminUI';
import { Head, router, useForm } from '@inertiajs/react';
import { subscribeRouter } from '@/Utils/routerEvents';
import { AlertTriangle, Download, FileText, Pencil, Plus, Search, Trash2, X } from 'lucide-react';

const statusLabels = {
    '': 'Semua status',
    expired: 'Kedaluwarsa',
    expiring: 'Habis dalam 30 hari',
    none: 'Tanpa kedaluwarsa',
    perlu_perhatian: 'Perlu perhatian',
};

const formatDate = (value) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
};

const expiryMeta = (value) => {
    if (!value) return null;

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const date = new Date(`${value}T00:00:00`);
    const days = Math.ceil((date - today) / 86400000);

    if (days < 0) return { variant: 'danger', label: 'Kedaluwarsa' };
    if (days <= 30) return { variant: 'warning', label: '≤30 hari' };

    return null;
};

function DocActions({ dokumen, onEdit, onDelete }) {
    return (
        <div className="flex justify-end gap-1">
            <IconButton
                label="Unduh"
                variant="ghost"
                onClick={() => {
                    window.location.href = route('dokumen.download', dokumen.id);
                }}
            >
                <Download className="h-4 w-4" />
            </IconButton>
            {dokumen.can_manage && (
                <>
                    <IconButton label="Edit" variant="ghost" onClick={() => onEdit(dokumen)}>
                        <Pencil className="h-4 w-4" />
                    </IconButton>
                    <IconButton
                        label="Hapus"
                        variant="ghost"
                        className="text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                        onClick={() => onDelete(dokumen)}
                    >
                        <Trash2 className="h-4 w-4" />
                    </IconButton>
                </>
            )}
        </div>
    );
}

export default function Index({
    auth,
    dokumens,
    units = [],
    kategoris = [],
    stats = {},
    filters = {},
    canManageYayasan = false,
}) {
    const canManage = auth.permissions?.includes('manage_dokumen');
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState(filters.q || '');
    const [filterSheetOpen, setFilterSheetOpen] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);

    useEffect(() => subscribeRouter({
        start: () => setLoading(true),
        finish: () => setLoading(false),
    }), []);

    const defaultUnit = canManageYayasan ? '' : String(auth.user?.unit_sekolah_id || '');

    const form = useForm({
        judul: '',
        kategori: 'legal',
        unit_sekolah_id: defaultUnit,
        nomor: '',
        penerbit: '',
        tanggal_dokumen: '',
        tanggal_kedaluwarsa: '',
        keterangan: '',
        file: null,
    });

    const hasFilter = Boolean(search || filters.kategori || filters.unit_sekolah_id || filters.status);
    const activeFilterCount = [filters.kategori, filters.unit_sekolah_id, filters.status].filter(Boolean).length;

    const applyFilters = (overrides = {}) => {
        router.get(
            route('dokumen.index'),
            {
                q: search,
                kategori: filters.kategori || '',
                unit_sekolah_id: filters.unit_sekolah_id || '',
                status: filters.status || '',
                ...overrides,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const latestFilters = useRef({
        kategori: filters.kategori || '',
        unit_sekolah_id: filters.unit_sekolah_id || '',
        status: filters.status || '',
    });
    latestFilters.current = {
        kategori: filters.kategori || '',
        unit_sekolah_id: filters.unit_sekolah_id || '',
        status: filters.status || '',
    };

    useEffect(() => {
        if (search === (filters.q || '')) return undefined;

        const timer = setTimeout(() => {
            applyFilters({ q: search, ...latestFilters.current });
        }, 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const resetFilters = () => {
        setSearch('');
        router.get(route('dokumen.index'), {}, { preserveState: true });
    };

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        form.setData({
            judul: '',
            kategori: 'legal',
            unit_sekolah_id: defaultUnit,
            nomor: '',
            penerbit: '',
            tanggal_dokumen: '',
            tanggal_kedaluwarsa: '',
            keterangan: '',
            file: null,
        });
        setEditing(null);
        setShowModal(true);
    };

    const openEdit = (dokumen) => {
        form.clearErrors();
        form.setData({
            judul: dokumen.judul,
            kategori: dokumen.kategori,
            unit_sekolah_id: dokumen.unit_sekolah_id ? String(dokumen.unit_sekolah_id) : '',
            nomor: dokumen.nomor || '',
            penerbit: dokumen.penerbit || '',
            tanggal_dokumen: dokumen.tanggal_dokumen || '',
            tanggal_kedaluwarsa: dokumen.tanggal_kedaluwarsa || '',
            keterangan: dokumen.keterangan || '',
            file: null,
        });
        setEditing(dokumen);
        setShowModal(true);
    };

    const submit = (event) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setShowModal(false),
        };

        if (editing) {
            form.put(route('dokumen.update', editing.id), options);
        } else {
            form.post(route('dokumen.store'), options);
        }
    };

    const destroy = (dokumen) => {
        if (!confirm(`Hapus dokumen "${dokumen.judul}"?`)) return;

        router.delete(route('dokumen.destroy', dokumen.id), { preserveScroll: true });
    };

    const emptyState = hasFilter ? (
        <EmptyState
            icon={Search}
            title="Tidak ada dokumen yang cocok"
            description="Tidak ada dokumen yang sesuai dengan filter. Hapus filter untuk melihat semua dokumen."
            action={<Button variant="secondary" onClick={resetFilters}>Hapus filter</Button>}
        />
    ) : (
        <EmptyState
            icon={FileText}
            title="Belum ada dokumen"
            description="Unggah dokumen pertama untuk mulai membangun arsip yayasan."
            action={canManage ? <Button onClick={openCreate}><Plus className="h-4 w-4" /> Unggah dokumen</Button> : undefined}
        />
    );

    return (
        <AdminShell user={auth.user} header={<h2 className="page-title">Dokumen</h2>}>
            <Head title="Dokumen" />

            <div className="mx-auto w-full max-w-7xl space-y-4">
                <PageHeader
                    eyebrow="Arsip"
                    title="Dokumen"
                    description="Dokumen legal, kelembagaan, dan operasional yayasan dalam satu tempat."
                    actions={canManage ? (
                        <Button onClick={openCreate}>
                            <Plus className="h-4 w-4" /> Unggah dokumen
                        </Button>
                    ) : undefined}
                />

                {(stats.expired > 0 || stats.expiring > 0) && (
                    <div className="flex flex-col gap-3 rounded-card border border-amber-200 bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-2 text-sm text-amber-900">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                            <p>
                                {stats.expired > 0 && <><strong>{stats.expired}</strong> dokumen sudah kedaluwarsa</>}
                                {stats.expired > 0 && stats.expiring > 0 && '. '}
                                {stats.expiring > 0 && <><strong>{stats.expiring}</strong> dokumen habis dalam 30 hari</>}
                                {'.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {stats.expired > 0 && (
                                <Button size="sm" variant="secondary" onClick={() => applyFilters({ status: 'expired' })}>
                                    Lihat kedaluwarsa
                                </Button>
                            )}
                            {stats.expiring > 0 && (
                                <Button size="sm" variant="secondary" onClick={() => applyFilters({ status: 'expiring' })}>
                                    Lihat ≤30 hari
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                <Card className="p-4">
                    <label htmlFor="dokumen-search" className="mb-1.5 block text-[11px] font-bold text-text-secondary">
                        Cari dokumen
                    </label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                        <Input
                            id="dokumen-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari judul, nomor, atau penerbit"
                            className="pl-9"
                        />
                    </div>
                </Card>

                <FilterSheet
                    open={filterSheetOpen}
                    onOpenChange={setFilterSheetOpen}
                    activeCount={activeFilterCount}
                    onReset={resetFilters}
                    onApply={() => setFilterSheetOpen(false)}
                    title="Filter dokumen"
                >
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label htmlFor="filter-kategori" className="mb-1.5 block text-[11px] font-bold text-text-secondary">
                                Kategori
                            </label>
                            <Select
                                id="filter-kategori"
                                value={filters.kategori || ''}
                                onChange={(event) => applyFilters({ kategori: event.target.value })}
                            >
                                <option value="">Semua kategori</option>
                                {kategoris.map((kategori) => (
                                    <option key={kategori.value} value={kategori.value}>{kategori.label}</option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <label htmlFor="filter-unit" className="mb-1.5 block text-[11px] font-bold text-text-secondary">
                                Unit
                            </label>
                            <Select
                                id="filter-unit"
                                value={filters.unit_sekolah_id || ''}
                                onChange={(event) => applyFilters({ unit_sekolah_id: event.target.value })}
                            >
                                <option value="">Semua unit</option>
                                <option value="yayasan">Yayasan</option>
                                {units.map((unit) => (
                                    <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <label htmlFor="filter-status" className="mb-1.5 block text-[11px] font-bold text-text-secondary">
                                Status
                            </label>
                            <Select
                                id="filter-status"
                                value={filters.status || ''}
                                onChange={(event) => applyFilters({ status: event.target.value })}
                            >
                                {Object.entries(statusLabels).map(([value, label]) => (
                                    <option key={value || 'all'} value={value}>{label}</option>
                                ))}
                            </Select>
                        </div>
                    </div>
                </FilterSheet>

                <div className={loading ? 'pointer-events-none opacity-60' : undefined}>
                    {dokumens.data.length === 0 ? (
                        <Card>{emptyState}</Card>
                    ) : (
                        <>
                            <Card className="hidden overflow-hidden md:block">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Dokumen</TableHead>
                                            <TableHead>Kategori</TableHead>
                                            <TableHead>Unit</TableHead>
                                            <TableHead>Kedaluwarsa</TableHead>
                                            <TableHead className="text-right">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {dokumens.data.map((dokumen) => {
                                            const expiry = expiryMeta(dokumen.tanggal_kedaluwarsa);

                                            return (
                                                <TableRow key={dokumen.id}>
                                                    <TableCell>
                                                        <div className="font-bold text-text-primary">{dokumen.judul}</div>
                                                        <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-text-muted">
                                                            {dokumen.nomor && <span>{dokumen.nomor}</span>}
                                                            <span className="font-semibold uppercase">{dokumen.file_extension}</span>
                                                            {dokumen.penerbit && <span>{dokumen.penerbit}</span>}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-sm text-text-secondary">{dokumen.kategori_label}</TableCell>
                                                    <TableCell className="text-sm text-text-secondary">
                                                        {dokumen.unit_sekolah?.nama || 'Yayasan'}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="text-sm text-text-secondary">{formatDate(dokumen.tanggal_kedaluwarsa)}</div>
                                                        {expiry && <Badge variant={expiry.variant} className="mt-1">{expiry.label}</Badge>}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <DocActions dokumen={dokumen} onEdit={openEdit} onDelete={destroy} />
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                                <div className="px-4 py-3">
                                    <Pagination links={dokumens.links} data={filters} />
                                </div>
                            </Card>

                            <div className="space-y-3 md:hidden">
                                {dokumens.data.map((dokumen) => {
                                    const expiry = expiryMeta(dokumen.tanggal_kedaluwarsa);

                                    return (
                                        <Card key={dokumen.id} className="p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="font-bold text-text-primary">{dokumen.judul}</p>
                                                    <p className="mt-0.5 text-[11px] text-text-muted">
                                                        {dokumen.kategori_label}
                                                        {' · '}
                                                        {dokumen.unit_sekolah?.nama || 'Yayasan'}
                                                    </p>
                                                </div>
                                                {expiry && <Badge variant={expiry.variant}>{expiry.label}</Badge>}
                                            </div>
                                            <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-text-muted">
                                                {dokumen.nomor && <span>{dokumen.nomor}</span>}
                                                <span className="font-semibold uppercase">{dokumen.file_extension}</span>
                                                <span>Kedaluwarsa: {formatDate(dokumen.tanggal_kedaluwarsa)}</span>
                                            </div>
                                            <div className="mt-3 border-t border-border/60 pt-2">
                                                <DocActions dokumen={dokumen} onEdit={openEdit} onDelete={destroy} />
                                            </div>
                                        </Card>
                                    );
                                })}
                                <Pagination links={dokumens.links} data={filters} variant="mobile" pagination={dokumens} />
                            </div>
                        </>
                    )}
                </div>
            </div>

            <Modal show={showModal} onClose={() => setShowModal(false)} maxWidth="2xl">
                <form onSubmit={submit} className="p-6">
                    <div className="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 className="text-lg font-bold text-text-primary">
                                {editing ? 'Edit dokumen' : 'Unggah dokumen'}
                            </h3>
                            <p className="mt-0.5 text-xs text-text-secondary">
                                PDF, DOC/DOCX, XLS/XLSX, atau gambar. Maks 10 MB.
                            </p>
                        </div>
                        <IconButton label="Tutup" variant="ghost" onClick={() => setShowModal(false)}>
                            <X className="h-4 w-4" />
                        </IconButton>
                    </div>

                    <div className="space-y-4">
                        <FormField label="Judul dokumen" htmlFor="judul" error={form.errors.judul} required>
                            <Input
                                id="judul"
                                value={form.data.judul}
                                onChange={(event) => form.setData('judul', event.target.value)}
                                placeholder="Nama dokumen"
                                aria-invalid={Boolean(form.errors.judul)}
                            />
                        </FormField>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <FormField label="Kategori" htmlFor="kategori" error={form.errors.kategori} required>
                                <Select
                                    id="kategori"
                                    value={form.data.kategori}
                                    onChange={(event) => form.setData('kategori', event.target.value)}
                                >
                                    {kategoris.map((kategori) => (
                                        <option key={kategori.value} value={kategori.value}>{kategori.label}</option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Unit" htmlFor="unit_sekolah_id" error={form.errors.unit_sekolah_id}>
                                {canManageYayasan ? (
                                    <Select
                                        id="unit_sekolah_id"
                                        value={form.data.unit_sekolah_id}
                                        onChange={(event) => form.setData('unit_sekolah_id', event.target.value)}
                                    >
                                        <option value="">Yayasan</option>
                                        {units.map((unit) => (
                                            <option key={unit.id} value={unit.id}>{unit.nama}</option>
                                        ))}
                                    </Select>
                                ) : (
                                    <Input
                                        id="unit_sekolah_id"
                                        value={auth.user?.unit_sekolah?.nama || 'Unit saya'}
                                        disabled
                                    />
                                )}
                            </FormField>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <FormField label="Nomor dokumen" htmlFor="nomor" error={form.errors.nomor}>
                                <Input
                                    id="nomor"
                                    value={form.data.nomor}
                                    onChange={(event) => form.setData('nomor', event.target.value)}
                                    placeholder="Nomor dokumen"
                                />
                            </FormField>
                            <FormField label="Penerbit" htmlFor="penerbit" error={form.errors.penerbit}>
                                <Input
                                    id="penerbit"
                                    value={form.data.penerbit}
                                    onChange={(event) => form.setData('penerbit', event.target.value)}
                                    placeholder="Nama penerbit"
                                />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <FormField label="Tanggal dokumen" htmlFor="tanggal_dokumen" error={form.errors.tanggal_dokumen}>
                                <Input
                                    id="tanggal_dokumen"
                                    type="date"
                                    value={form.data.tanggal_dokumen}
                                    onChange={(event) => form.setData('tanggal_dokumen', event.target.value)}
                                />
                            </FormField>
                            <FormField label="Tanggal kedaluwarsa" htmlFor="tanggal_kedaluwarsa" error={form.errors.tanggal_kedaluwarsa}>
                                <Input
                                    id="tanggal_kedaluwarsa"
                                    type="date"
                                    value={form.data.tanggal_kedaluwarsa}
                                    onChange={(event) => form.setData('tanggal_kedaluwarsa', event.target.value)}
                                />
                            </FormField>
                        </div>

                        <FormField label="Keterangan" htmlFor="keterangan" error={form.errors.keterangan}>
                            <textarea
                                id="keterangan"
                                rows={3}
                                value={form.data.keterangan}
                                onChange={(event) => form.setData('keterangan', event.target.value)}
                                placeholder="Catatan tambahan"
                                className="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-text-primary shadow-sm transition-colors placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                        </FormField>

                        <FormField
                            label="File"
                            htmlFor="file"
                            error={form.errors.file}
                            required={!editing}
                            description={editing ? 'Kosongkan jika file tidak diganti.' : 'Format PDF, DOC/DOCX, XLS/XLSX, JPG, PNG, atau WebP. Maks 10 MB.'}
                        >
                            <input
                                id="file"
                                type="file"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                                onChange={(event) => form.setData('file', event.target.files?.[0] || null)}
                                className="block w-full text-sm text-text-primary file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/15"
                                aria-invalid={Boolean(form.errors.file)}
                            />
                            {editing && (
                                <p className="mt-1 text-[11px] text-text-muted">File saat ini: {editing.file_name}</p>
                            )}
                        </FormField>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <Button variant="secondary" onClick={() => setShowModal(false)}>Batal</Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : editing ? 'Simpan perubahan' : 'Unggah'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminShell>
    );
}
