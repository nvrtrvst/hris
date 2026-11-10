import React, { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { CalendarClock, Pencil, Trash2 } from 'lucide-react';
import {
    Button,
    Card,
    FormField,
    Input,
    Select,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/AdminUI';

const STATUS_LABEL = {
    aktif: 'Aktif',
    akan_berlaku: 'Akan berlaku',
    lampau: 'Lampau',
};

function todayIso() {
    return new Date().toLocaleDateString('en-CA');
}

function formatDate(value) {
    if (!value) return '-';

    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function ModePresensiSection({
    unit,
    histories = [],
    modeOptions = [],
    modeAktif,
    modeAktifLabel,
    modeAktifSejak,
    modeBerikutnya,
}) {
    const [editing, setEditing] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        mode: modeAktif,
        berlaku_mulai: todayIso(),
    });

    const resetForm = () => {
        setEditing(null);
        reset();
        setData({
            mode: modeAktif,
            berlaku_mulai: todayIso(),
        });
    };

    const submit = (event) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: (page) => {
                setEditing(null);
                setData({
                    mode: page.props.mode_aktif ?? modeAktif,
                    berlaku_mulai: todayIso(),
                });
            },
        };

        if (editing) {
            put(route('unit-sekolah.update-presensi-mode', [unit.id, editing.id]), options);
            return;
        }

        post(route('unit-sekolah.store-presensi-mode', unit.id), options);
    };

    const startEdit = (row) => {
        setEditing(row);
        setData({
            mode: row.mode,
            berlaku_mulai: row.berlaku_mulai,
        });
    };

    const destroy = (row) => {
        if (!confirm('Hapus entri riwayat mode ini?')) return;

        router.delete(route('unit-sekolah.destroy-presensi-mode', [unit.id, row.id]), {
            preserveScroll: true,
            onSuccess: (page) => {
                setEditing(null);
                setData({
                    mode: page.props.mode_aktif ?? modeAktif,
                    berlaku_mulai: todayIso(),
                });
            },
        });
    };

    return (
        <Card className="p-5 sm:p-6">
            <div className="mb-5">
                <h3 className="flex items-center gap-2 text-sm font-bold text-primary">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                        <CalendarClock className="h-4 w-4 text-primary" />
                    </span>
                    Mode Presensi Unit
                </h3>
                <p className="mt-1.5 text-xs text-text-secondary">
                    Atur kapan pendidik wajib hadir. Tendik tidak terpengaruh.
                </p>
            </div>

            <div className="rounded-xl border border-border bg-surface p-4">
                <p className="text-[10px] font-bold uppercase tracking-wide text-text-muted">Mode aktif</p>
                <p className="mt-1 text-sm font-bold text-text-primary">{modeAktifLabel}</p>
                <p className="mt-1 text-xs text-text-secondary">
                    {modeAktifSejak ? `Berlaku sejak ${formatDate(modeAktifSejak)}` : 'Default unit'}
                </p>
                {modeBerikutnya && (
                    <p className="mt-2 text-xs font-semibold text-primary">
                        Akan berlaku mulai {formatDate(modeBerikutnya.berlaku_mulai)}: {modeBerikutnya.mode_label}
                    </p>
                )}
            </div>

            <form onSubmit={submit} className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_180px_auto] sm:items-end">
                <FormField label="Mode" htmlFor="mode-presensi" error={errors.mode}>
                    <Select
                        id="mode-presensi"
                        value={data.mode}
                        onChange={(event) => setData('mode', event.target.value)}
                    >
                        {modeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                </FormField>

                <FormField label="Berlaku mulai" htmlFor="berlaku-mulai" error={errors.berlaku_mulai}>
                    <Input
                        id="berlaku-mulai"
                        type="date"
                        min={todayIso()}
                        value={data.berlaku_mulai}
                        onChange={(event) => setData('berlaku_mulai', event.target.value)}
                    />
                </FormField>

                <div className="flex gap-2">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Tambah Riwayat'}
                    </Button>
                    {editing && (
                        <Button type="button" variant="secondary" onClick={resetForm} disabled={processing}>
                            Batal
                        </Button>
                    )}
                </div>
            </form>

            <p className="mt-2 text-[10px] leading-relaxed text-text-muted">
                Perubahan hanya berlaku sejak tanggal yang dipilih. Presensi dan alpa sebelum tanggal tersebut tidak berubah.
                Hari tanpa JP/piket tidak dihitung alpa atau working day.
            </p>

            <div className="mt-5 overflow-hidden rounded-xl border border-border">
                {histories.length === 0 ? (
                    <div className="p-4">
                        <p className="text-sm font-semibold text-text-primary">Belum ada perubahan mode. Default: Kantor.</p>
                        <p className="mt-1 text-xs text-text-secondary">Tambahkan riwayat di atas saat unit mulai memakai mode lain.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Berlaku Mulai</TableHead>
                                    <TableHead>Mode</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Diubah Oleh</TableHead>
                                    <TableHead>Dicatat</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {histories.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-semibold">{formatDate(row.berlaku_mulai)}</TableCell>
                                        <TableCell>{row.mode_label}</TableCell>
                                        <TableCell>{STATUS_LABEL[row.status] ?? row.status}</TableCell>
                                        <TableCell>{row.created_by || '-'}</TableCell>
                                        <TableCell className="whitespace-nowrap text-xs text-text-secondary">
                                            {row.created_at ? new Date(row.created_at.replace(' ', 'T')).toLocaleString('id-ID') : '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {row.can_change ? (
                                                <div className="inline-flex items-center gap-2">
                                                    <Button type="button" variant="secondary" size="sm" onClick={() => startEdit(row)}>
                                                        <Pencil className="h-3.5 w-3.5" /> Ubah
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        size="sm"
                                                        className="border-rose-200 text-rose-700 hover:bg-rose-50"
                                                        onClick={() => destroy(row)}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-text-muted">-</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </Card>
    );
}
