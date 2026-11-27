import PemenuhanPanel from './Partials/PemenuhanPanel';
import { useEffect, useMemo, useRef, useState } from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, Input, Label, PageHeader, Select } from '@/Components/AdminUI';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Download,
    FileSpreadsheet,
    Loader2,
    Search,
    Users,
    X,
} from 'lucide-react';

const today = () => dateValue(new Date());

const dateValue = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const presetRange = (preset) => {
    const now = new Date();

    if (preset === 'week') {
        const day = now.getDay() || 7;
        const start = new Date(now);
        start.setDate(now.getDate() - day + 1);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);

        return { from: dateValue(start), to: dateValue(end) };
    }

    if (preset === 'month') {
        return {
            from: dateValue(new Date(now.getFullYear(), now.getMonth(), 1)),
            to: dateValue(new Date(now.getFullYear(), now.getMonth() + 1, 0)),
        };
    }

    return { from: dateValue(now), to: dateValue(now) };
};

const fmtDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
};

const fmtJp = (value) => {
    const number = Number(value) || 0;

    return Number.isInteger(number) ? String(number) : number.toFixed(2).replace('.', ',');
};

const statusClass = {
    belum_terpantau: 'badge-danger',
    kelas_kosong: 'badge-warning',
    perlu_cek: 'badge-info',
    digantikan: 'badge-info',
    penugasan: 'badge-info',
};

const statusLabel = {
    belum_terpantau: 'Belum terpantau',
    kelas_kosong: 'Kelas kosong',
    perlu_cek: 'Perlu cek',
    digantikan: 'Digantikan',
    penugasan: 'Penugasan',
};

export default function Pengawasan({ auth, units = [], defaults = {} }) {
    const initial = useRef(false);
    const previewAbort = useRef(null);
    const [appliedParams, setAppliedParams] = useState(null);
    const [unitId, setUnitId] = useState(String(defaults.unit_sekolah_id || ''));
    const [from, setFrom] = useState(defaults.from || today());
    const [to, setTo] = useState(defaults.to || today());
    const [jenisPegawai, setJenisPegawai] = useState(defaults.jenis_pegawai || 'all');
    const [preview, setPreview] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const params = useMemo(() => {
        const query = { from, to, jenis_pegawai: jenisPegawai };
        if (unitId) query.unit_sekolah_id = unitId;

        return query;
    }, [unitId, from, to, jenisPegawai]);

    const loadPreview = async (query = params) => {
        previewAbort.current?.abort();
        const controller = new AbortController();
        previewAbort.current = controller;
        setLoading(true);
        setError('');

        try {
            const res = await axios.get(route('laporan.pengawasan.preview'), { params: query, signal: controller.signal });
            setPreview(res.data);
            setAppliedParams(query);
        } catch (e) {
            if (axios.isCancel(e)) return;
            setPreview(null);
            setError(e?.response?.data?.message || 'Gagal memuat laporan. Periksa periode dan unit.');
        } finally {
            if (previewAbort.current === controller) setLoading(false);
        }
    };

    useEffect(() => {
        if (initial.current) return;
        initial.current = true;
        loadPreview();
        return () => previewAbort.current?.abort();
    }, []);

    const applyPreset = (preset) => {
        const range = presetRange(preset);
        setFrom(range.from);
        setTo(range.to);
    };

    const download = (type) => {
        const query = new URLSearchParams(appliedParams || params).toString();
        window.location.href = `${route(`laporan.pengawasan.${type}`)}?${query}`;
    };

    const summary = preview?.summary;
    return (
        <AdminShell user={auth.user} header={<h2 className="page-title">Laporan Kehadiran dan Pemenuhan Tugas</h2>}>
            <Head title="Laporan Kehadiran dan Pemenuhan Tugas" />
            <div className="mx-auto w-full max-w-7xl space-y-5">
                <PageHeader
                    className="print:hidden"
                    eyebrow="Laporan"
                    title="Laporan Kehadiran dan Pemenuhan Tugas"
                    description="Ringkasan seluruh pegawai, pengecualian kehadiran, JP guru, dan kelas yang belum terpantau."
                />

                <Card className="p-5">
                        <div className="flex flex-wrap gap-2">
                            <Button variant="secondary" size="sm" onClick={() => applyPreset('day')}>Hari ini</Button>
                            <Button variant="secondary" size="sm" onClick={() => applyPreset('week')}>Minggu ini</Button>
                            <Button variant="secondary" size="sm" onClick={() => applyPreset('month')}>Bulan ini</Button>
                        </div>

                        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <div>
                                <Label htmlFor="unit">Unit</Label>
                                <Select id="unit" value={unitId} onChange={(e) => setUnitId(e.target.value)}>
                                    {units.length > 1 && <option value="">Semua unit</option>}
                                    {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </Select>
                            </div>
                            <div>
                                <Label htmlFor="from">Dari</Label>
                                <Input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="to">Sampai</Label>
                                <Input id="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="jenis">Jenis Pegawai</Label>
                                <Select id="jenis" value={jenisPegawai} onChange={(e) => setJenisPegawai(e.target.value)}>
                                    <option value="all">Semua pegawai</option>
                                    <option value="guru">Pendidik</option>
                                    <option value="non_guru">Tenaga kependidikan</option>
                                </Select>
                            </div>
                            <div className="flex items-end">
                                <Button onClick={() => loadPreview()} disabled={loading} className="w-full">
                                    {loading ? <><Loader2 className="h-4 w-4 animate-spin" /> Memuat...</> : <><Search className="h-4 w-4" /> Tampilkan</>}
                                </Button>
                            </div>
                        </div>

                        {error && (
                            <div role="alert" className="mt-4 flex items-start justify-between gap-3 rounded-xl border border-danger/30 bg-danger-light px-4 py-3 text-sm font-semibold text-danger">
                                <span>{error}</span>
                                <button type="button" aria-label="Tutup pesan" onClick={() => setError('')} className="shrink-0 rounded-md p-1 hover:bg-danger/10">
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        )}

                        <div className="mt-5 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row">
                            <Button variant="secondary" onClick={() => download('pdf')} disabled={!preview || loading}>
                                <Download className="h-4 w-4" /> Unduh PDF
                            </Button>
                            <Button variant="secondary" onClick={() => download('excel')} disabled={!preview || loading}>
                                <FileSpreadsheet className="h-4 w-4" /> Unduh Excel
                            </Button>
                        </div>
                    </Card>

                    {loading && (
                        <Card role="status" className="flex items-center justify-center gap-3 p-12">
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                            <span className="text-sm font-semibold text-text-secondary">Memuat laporan...</span>
                        </Card>
                    )}

                    {!loading && preview && summary && (
                        <>
                            <PemenuhanPanel data={preview} onSaved={() => loadPreview(appliedParams || params)} />
                            <Card className="overflow-hidden">
                                <div className="border-b border-border bg-surface px-4 py-3">
                                    <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Kelas &amp; Mengajar</h3>
                                    <p className="text-xs text-text-muted">Bukti presensi, guru pengganti, penugasan, dan catatan piket.</p>
                                </div>
                                {preview.class_issues.length === 0 ? (
                                    <div className="p-6 text-center text-xs text-text-muted">Tidak ada kelas bermasalah pada filter ini.</div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-border text-xs">
                                            <thead className="bg-surface">
                                                <tr>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Tanggal</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Jam</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Kelas</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Guru</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Status</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Sumber</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {preview.class_issues.map((row, index) => (
                                                    <tr key={`${row.tanggal}-${row.jam}-${index}`}>
                                                        <td className="whitespace-nowrap px-3 py-2">{fmtDate(row.tanggal)}</td>
                                                        <td className="whitespace-nowrap px-3 py-2">{row.jam}</td>
                                                        <td className="px-3 py-2">{row.kelas || '-'}</td>
                                                        <td className="px-3 py-2">{row.guru || '-'}</td>
                                                        <td className="px-3 py-2"><span className={`badge ${statusClass[row.status] || 'badge-info'}`}>{statusLabel[row.status] || row.status}</span></td>
                                                        <td className="px-3 py-2 text-text-muted">{row.sumber}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </Card>
                        </>
                    )}

                    {!loading && !preview && !error && (
                        <Card className="p-6 text-center text-sm text-text-muted">
                            Pilih periode lalu klik Tampilkan.
                        </Card>
                    )}
            </div>
        </AdminShell>
    );
}
