import React, { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { CalendarClock, Pencil, Trash2 } from 'lucide-react';
import { Button, Card, FormField, Input, Select } from '@/Components/AdminUI';

const STATUS_LABEL = {
    aktif: 'Aktif',
    akan_berlaku: 'Akan berlaku',
    lampau: 'Lampau',
};

const STATUS_CLASS = {
    aktif: 'text-emerald-700',
    akan_berlaku: 'text-amber-700',
    lampau: 'text-text-muted',
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

function formatDateTime(value) {
    if (!value) return '-';

    return new Date(value.replace(' ', 'T')).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
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
    const { data, setData, post, put, processing, errors } = useForm({
        mode: modeAktif,
        berlaku_mulai: todayIso(),
    });

    const resetForm = () => {
        setEditing(null);
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
        <Card id="unit-mode-presensi" className="scroll-mt-32 p-5">
            <div>
                <h3 className="flex items-center gap-2 text-sm font-bold text-primary">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                        <CalendarClock className="h-4 w-4 text-primary" />
                    </span>
                    Mode Presensi
                </h3>
                <p className="mt-1.5 text-xs leading-relaxed text-text-secondary">
                    Atur kapan pendidik wajib hadir. Tendik tidak terpengaruh.
                </p>
            </div>

            <div className="mt-4 rounded-xl border border-border bg-surface p-3">
                <p className="text-[10px] font-bold uppercase tracking-wide text-text-muted">Mode aktif</p>
                <p className="mt-1 text-sm font-bold text-text-primary">{modeAktifLabel}</p>
                <p className="mt-0.5 text-xs text-text-secondary">
                    {modeAktifSejak ? `Berlaku sejak ${formatDate(modeAktifSejak)}` : 'Default unit'}
                </p>
                {modeBerikutnya && (
                    <p className="mt-2 text-xs font-semibold text-primary">
                        Berikutnya {formatDate(modeBerikutnya.berlaku_mulai)}: {modeBerikutnya.mode_label}
                    </p>
                )}
            </div>

            <form onSubmit={submit} className="mt-4 space-y-3">
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
                    <Button type="submit" disabled={processing} className="flex-1">
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
                Perubahan berlaku sejak tanggal dipilih. Presensi dan alpa sebelum tanggal tersebut tidak berubah.
                Hari tanpa JP/piket tidak dihitung alpa atau working day.
            </p>

            <div className="mt-5">
                <div className="flex items-center justify-between gap-3">
                    <h4 className="text-xs font-bold text-text-primary">Riwayat</h4>
                    <span className="text-[10px] font-semibold text-text-muted">{histories.length} entri</span>
                </div>

                {histories.length === 0 ? (
                    <div className="mt-2 rounded-xl border border-border p-3">
                        <p className="text-sm font-semibold text-text-primary">Belum ada perubahan mode. Default: Kantor.</p>
                        <p className="mt-1 text-xs text-text-secondary">Tambahkan riwayat saat unit mulai memakai mode lain.</p>
                    </div>
                ) : (
                    <div className="mt-2 max-h-80 space-y-2 overflow-y-auto pr-1">
                        {histories.map((row) => (
                            <div key={row.id} className="rounded-xl border border-border p-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-[10px] font-semibold text-text-muted">{formatDate(row.berlaku_mulai)}</p>
                                        <p className="mt-0.5 text-sm font-bold text-text-primary">{row.mode_label}</p>
                                    </div>
                                    <span className={`text-[10px] font-bold ${STATUS_CLASS[row.status] ?? 'text-text-muted'}`}>
                                        {STATUS_LABEL[row.status] ?? row.status}
                                    </span>
                                </div>
                                <p className="mt-1 text-[10px] text-text-muted">
                                    {row.created_by || 'Sistem'} · {formatDateTime(row.created_at)}
                                </p>
                                {row.can_change && (
                                    <div className="mt-2 flex gap-2">
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
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </Card>
    );
}
