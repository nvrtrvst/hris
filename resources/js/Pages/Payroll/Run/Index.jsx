import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, FormField, Input, PageHeader, Select } from '@/Components/AdminUI';
import { ArrowLeft, ArrowRight, CalendarDays, ClipboardList, FileText, Info, Loader2, Lock, Sparkles, TrendingUp, UserCheck } from 'lucide-react';

const MONTHS = [
    { val: '01', label: 'Januari' }, { val: '02', label: 'Februari' }, { val: '03', label: 'Maret' },
    { val: '04', label: 'April' }, { val: '05', label: 'Mei' }, { val: '06', label: 'Juni' },
    { val: '07', label: 'Juli' }, { val: '08', label: 'Agustus' }, { val: '09', label: 'September' },
    { val: '10', label: 'Oktober' }, { val: '11', label: 'November' }, { val: '12', label: 'Desember' },
];

const STEPS = [
    { label: 'Pilih Periode', Icon: CalendarDays, active: true },
    { label: 'Review Worksheet', Icon: ClipboardList },
    { label: 'Finalisasi', Icon: Lock },
];

const PULLS = [
    { Icon: UserCheck, title: 'Kehadiran & Izin', desc: 'Hadir, telat, sakit, izin, cuti — dipotong sesuai status.' },
    { Icon: TrendingUp, title: 'Jam Mengajar', desc: 'Total JP dari jadwal mengajar + jam lembur disetujui.' },
    { Icon: Sparkles, title: 'Komponen Otomatis', desc: 'Masa bakti, tunjangan kehadiran, persentase & fixed.' },
];

export default function RunPayrollIndex({ auth, units = [], canPickAll = false }) {
    const now = new Date();
    const monthInit = String(now.getMonth() + 1).padStart(2, '0');
    const { data, setData, post, processing, errors } = useForm({
        month: monthInit,
        year: now.getFullYear().toString(),
        unit_sekolah_id: units[0]?.id ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('penggajian.run.init'));
    };

    return (
        <AdminShell user={auth.user} header={<h2 className="page-title">Run Payroll</h2>}>
            <Head title="Run Payroll - Wizard" />

            <div className="mx-auto w-full max-w-3xl space-y-5">
                <PageHeader
                    eyebrow="Penggajian"
                    title="Run Payroll"
                    description="Tarik data kehadiran, jam mengajar, dan komponen gaji untuk periode terpilih."
                    actions={
                        <Link href={route('penggajian.index')} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            <ArrowLeft className="h-4 w-4" /> Kembali
                        </Link>
                    }
                />

                {/* Wizard steps */}
                    <div className="flex items-center justify-center gap-2 sm:gap-3">
                        {STEPS.map((s, i) => (
                            <React.Fragment key={s.label}>
                                <div className={`flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold ${
                                    s.active ? 'bg-primary text-white shadow-card' : 'bg-white border border-border text-text-secondary'
                                }`}>
                                    <s.Icon className="h-3.5 w-3.5" />
                                    <span className="hidden sm:inline">{s.label}</span>
                                    <span className="sm:hidden">{i + 1}</span>
                                </div>
                                {i < STEPS.length - 1 && <ArrowRight className="h-4 w-4 text-border" />}
                            </React.Fragment>
                        ))}
                    </div>

                    <Card className="p-6 sm:p-8">
                        <div className="mb-8 text-center">
                            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
                                <Sparkles className="h-7 w-7 text-primary" />
                            </div>
                            <h2 className="text-2xl font-extrabold text-primary">Run Payroll</h2>
                            <p className="mx-auto mt-2 max-w-md text-sm text-text-secondary">
                                Sistem akan menarik data kehadiran, jam mengajar, dan menghitung komponen gaji secara otomatis.
                            </p>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            <div className="flex items-start gap-2 rounded-xl border border-info/30 bg-info-light p-3 text-xs leading-relaxed text-info">
                                <Info className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>
                                    Periode <strong>{MONTHS.find((m) => m.val === data.month)?.label} {data.year}</strong> — jika draft untuk periode ini sudah ada, draft lama akan <strong>dihitung ulang dari nol</strong>.
                                </span>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <FormField label="Pilih Bulan" error={errors.month}>
                                    <Select
                                        value={data.month}
                                        onChange={(e) => setData('month', e.target.value)}
                                    >
                                        {MONTHS.map((m) => <option key={m.val} value={m.val}>{m.label}</option>)}
                                    </Select>
                                </FormField>
                                <FormField label="Tahun" error={errors.year}>
                                    <Input
                                        type="number"
                                        min="2020"
                                        max="2100"
                                        value={data.year}
                                        onChange={(e) => setData('year', e.target.value)}
                                    />
                                </FormField>
                            </div>

                            <FormField label="Unit Sekolah" error={errors.unit_sekolah_id}>
                                <Select
                                    value={data.unit_sekolah_id}
                                    onChange={(e) => setData('unit_sekolah_id', e.target.value)}
                                >
                                    {canPickAll && <option value="">Semua Unit</option>}
                                    {units.map((u) => (
                                        <option key={u.id} value={u.id}>{u.singkatan ? `${u.singkatan} — ${u.nama}` : u.nama}</option>
                                    ))}
                                </Select>
                            </FormField>

                            <Button type="submit" size="lg" disabled={processing} className="w-full py-4 text-base">
                                {processing ? (
                                    <>
                                        <Loader2 className="h-5 w-5 animate-spin" />
                                        Menarik data & menghitung…
                                    </>
                                ) : (
                                    <>
                                        Mulai Penggajian
                                        <ArrowRight className="h-5 w-5" />
                                    </>
                                )}
                            </Button>
                        </form>
                    </Card>

                    {/* Info yang ditarik */}
                    <Card className="p-5">
                        <p className="mb-4 flex items-center gap-2 text-sm font-bold text-primary">
                            <FileText className="h-4 w-4" /> Data yang dihitung otomatis
                        </p>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {PULLS.map((p) => (
                                <div key={p.title} className="rounded-xl border border-border bg-surface/50 p-3">
                                    <p.Icon className="h-4 w-4 text-primary" />
                                    <p className="mt-1.5 text-xs font-bold text-text-primary">{p.title}</p>
                                    <p className="mt-0.5 text-[11px] leading-relaxed text-text-secondary">{p.desc}</p>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>
        </AdminShell>
    );
}
