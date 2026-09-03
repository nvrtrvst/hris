import React from 'react';
import { subscribeRouter } from '@/Utils/routerEvents';
import useNowEveryMinute from '@/Utils/useNowEveryMinute';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { id } from 'date-fns/locale/id';
import { avatarTone, initials } from '@/Utils/avatar';
import {
    AlertTriangle,
    AlarmClock,
    CalendarDays,
    CalendarOff,
    CheckCircle2,
    Clock3,
    FileText,
    Filter,
    HeartPulse,
    History,
    Loader2,
    MapPin,
    RotateCcw,
    Search,
    ShieldAlert,
    UserX,
    Users,
    X,
} from 'lucide-react';

const STATUS_META = {
    hadir: { label: 'Hadir', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    telat: { label: 'Terlambat', badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
    sakit: { label: 'Sakit', badge: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-500' },
    izin: { label: 'Izin', badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    cuti: { label: 'Cuti', badge: 'bg-cyan-50 text-cyan-700 border-cyan-200', dot: 'bg-cyan-500' },
    alpa: { label: 'Alpa', badge: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' },
};

const StatusBadge = ({ status }) => {
    const meta = STATUS_META[status] || { label: status, badge: 'bg-gray-50 text-gray-700 border-gray-200', dot: 'bg-gray-400' };

    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border ${meta.badge}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${meta.dot}`} />
            {meta.label}
        </span>
    );
};

const LemburBadge = ({ status }) => {
    const map = {
        pending: 'bg-amber-50 text-amber-700 border-amber-200',
        disetujui: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        ditolak: 'bg-rose-50 text-rose-700 border-rose-200',
    };

    return (
        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border ${map[status] || map.pending}`}>
            <AlarmClock className="h-3 w-3" /> Lembur {status || 'Pending'}
        </span>
    );
};

const TugasLuarBadge = ({ status }) => {
    const map = {
        pending: 'bg-sky-50 text-sky-700 border-sky-200',
        disetujui: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        ditolak: 'bg-rose-50 text-rose-700 border-rose-200',
    };

    return (
        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border ${map[status] || map.pending}`}>
            Tugas Luar {status || 'Pending'}
        </span>
    );
};

const TugasLuarCell = ({ p }) => (
    <td className="hidden lg:table-cell px-4 py-3.5 whitespace-nowrap">
        {p.is_tugas_luar ? (
            <div>
                <TugasLuarBadge status={p.tugas_luar_status} />
                {p.tugas_luar_status === 'pending' && (
                    <div className="mt-1.5 flex gap-1">
                        <button
                            onClick={() => router.post(route('presensi.approveTugasLuar', p.id), {}, { preserveState: true })}
                            className="rounded-md bg-emerald-500 px-2 py-1 text-[10px] font-bold text-white transition-colors hover:bg-emerald-600"
                        >
                            Setuju
                        </button>
                        <button
                            onClick={() => router.post(route('presensi.rejectTugasLuar', p.id), {}, { preserveState: true })}
                            className="rounded-md bg-rose-500 px-2 py-1 text-[10px] font-bold text-white transition-colors hover:bg-rose-600"
                        >
                            Tolak
                        </button>
                    </div>
                )}
                {p.tujuan && (
                    <p className="mt-1 max-w-[160px] text-[10px] leading-tight text-text-secondary">{p.tujuan}</p>
                )}
            </div>
        ) : <span className="text-xs text-text-secondary">—</span>}
    </td>
);

const buildGroups = (data) => {
    const map = new Map();
    for (const p of data) {
        const key = `${p.pegawai_id}__${p.tanggal}`;
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(p);
    }

    return Array.from(map.values());
};

const RingkasBody = ({ data, auth, now, expanded, setExpanded, openReview, openAudit, setConfirmStatus }) => {
    const groups = buildGroups(data);

    return groups.map((group) => {
        const parent = group.find((g) => g.tipe_presensi === 'kantor' && !g.is_lembur && !g.is_tugas_luar) || group[0];
        const children = group.filter((g) => g !== parent);
        const key = `${parent.pegawai_id}__${parent.tanggal}`;
        const isOpen = Boolean(expanded[key]);
        const lembur = group.find((g) => g.is_lembur);
        const tugasLuar = group.find((g) => g.is_tugas_luar);
        const nama = parent.pegawai?.nama_lengkap || '-';

        return (
            <React.Fragment key={key}>
                <tr className="group hover:bg-surface/70 transition-colors">
                    <td className="px-4 py-3.5 whitespace-nowrap">
                        <div className="flex items-center gap-3">
                            <button type="button" onClick={() => setExpanded((s) => ({ ...s, [key]: !s[key] }))} className="rounded-md px-1 text-text-secondary transition-colors hover:text-primary" title={isOpen ? 'Tutup' : 'Buka'}>
                                {isOpen ? '▾' : '▸'}
                            </button>
                            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold ${avatarTone(nama)}`}>
                                {initials(nama)}
                            </span>
                            <div className="min-w-0">
                                <div className="text-sm font-bold text-primary truncate max-w-[180px]">{nama}</div>
                                <div className="text-[11px] text-text-secondary truncate max-w-[180px]">
                                    {parent.pegawai?.jabatans?.some(j => j.is_guru) ? (
                                        <span title={parent.pegawai?.mapels?.map(m => m.mata_pelajaran?.nama).filter(Boolean).join(', ')}>
                                            {parent.pegawai?.mapels?.map(m => m.mata_pelajaran?.nama).filter(Boolean).join(', ') || 'Guru'}
                                        </span>
                                    ) : (
                                        <span>{parent.pegawai?.jabatans?.map(j => j.nama).filter(Boolean).join(', ') || '—'}</span>
                                    )}
                                    {' • '}{parent.unit_sekolah?.nama || '—'}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td className="px-4 py-3.5 whitespace-nowrap">
                        <div className="text-sm font-semibold text-primary">{format(parseDate(parent.tanggal), 'd MMM yyyy', { locale: id })}</div>
                        <div className="text-[11px] text-text-secondary">{format(parseDate(parent.tanggal), 'EEEE', { locale: id })}</div>
                    </td>
                    <td className="hidden sm:table-cell px-4 py-3.5 whitespace-nowrap">
                        {children.length > 0
                            ? <span className="text-xs font-semibold text-text-secondary">Kantor dan Mengajar</span>
                            : parent.is_tugas_luar
                                ? <span className="text-xs font-semibold text-text-secondary">Tugas Luar</span>
                                : <span className="text-xs text-text-secondary">Kantor</span>}
                    </td>
                    <td className="px-4 py-3.5 whitespace-nowrap">
                        {parent.jam_masuk
                            ? <span className="font-mono text-sm font-bold text-primary">{parent.jam_masuk.substring(0, 5)}</span>
                            : <span className="text-sm text-text-secondary">—</span>}
                    </td>
                    <td className="px-4 py-3.5 whitespace-nowrap">
                        {parent.jam_keluar
                            ? <span className="font-mono text-sm font-bold text-primary">{parent.jam_keluar.substring(0, 5)}</span>
                            : <span className="text-sm text-text-secondary">—</span>}
                    </td>
                    <td className="hidden md:table-cell px-4 py-3.5 whitespace-nowrap">
                        <div className="flex flex-wrap gap-1.5">
                            {parent.foto_masuk_url
                                ? <a href={parent.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border"><img src={parent.foto_masuk_url} alt="Masuk" className="h-full w-full object-cover" loading="lazy" /></a>
                                : null}
                            {parent.foto_keluar_url
                                ? <a href={parent.foto_keluar_url} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border"><img src={parent.foto_keluar_url} alt="Keluar" className="h-full w-full object-cover" loading="lazy" /></a>
                                : null}
                            {(parent.foto_kegiatan_urls || []).map((u, i) => (
                                <a key={i} href={u} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border hover:ring-2 hover:ring-primary transition-shadow" title={`Bukti kegiatan ${i + 1}`}><img src={u} alt={`Bukti ${i + 1}`} className="h-full w-full object-cover" loading="lazy" /></a>
                            ))}
                        </div>
                    </td>
                    <td className="hidden lg:table-cell px-4 py-3.5 whitespace-nowrap">
                        {lembur ? <LemburBadge status={lembur.lembur_status} /> : <span className="text-xs text-text-secondary">—</span>}
                    </td>
                    <td className="hidden lg:table-cell px-4 py-3.5 whitespace-nowrap">
                        {tugasLuar ? <TugasLuarBadge status={tugasLuar.tugas_luar_status} /> : <span className="text-xs text-text-secondary">—</span>}
                    </td>
                    <td className="px-4 py-3.5 whitespace-nowrap">
                        {auth.permissions?.includes('manage_master_data') ? (
                            <select
                                className={`cursor-pointer rounded-lg border px-2.5 py-1.5 pr-7 text-[11px] font-bold uppercase shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/30 ${STATUS_META[parent.status]?.badge || 'bg-gray-50 text-gray-700 border-gray-200'}`}
                                value={parent.status}
                                onChange={(e) => setConfirmStatus({ id: parent.id, statusLama: parent.status, statusBaru: e.target.value })}
                            >
                                <option value="hadir">Hadir</option>
                                <option value="telat">Telat</option>
                                <option value="sakit">Sakit</option>
                                <option value="izin">Izin</option>
                                <option value="cuti">Cuti</option>
                                <option value="alpa">Alpa</option>
                            </select>
                        ) : (
                            <StatusBadge status={parent.status} />
                        )}
                    </td>
                    <td className="px-4 py-3.5 whitespace-nowrap text-right">
                        <div className="flex items-center justify-end gap-1.5">
                            <button onClick={() => openAudit(parent)} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary" title="Riwayat perubahan"><History className="h-4 w-4" /></button>
                        </div>
                    </td>
                </tr>
                {isOpen && children.map((c) => {
                    const isTL = c.is_tugas_luar;

                    return (
                        <tr key={c.id} className="bg-surface/40">
                            <td className="px-4 py-3 whitespace-nowrap pl-12">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-text-muted">{isTL ? 'Tugas Luar' : 'Mengajar'}</span>
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap"></td>
                            <td className="px-4 py-3 whitespace-nowrap">
                                {isTL ? (
                                    <div className="text-xs leading-tight">
                                        <div className="font-semibold text-primary">{c.tujuan || 'Tugas Luar'}</div>
                                        <div className="text-text-secondary">{c.keterangan || '—'}</div>
                                    </div>
                                ) : c.jadwal ? (
                                    <div className="text-xs leading-tight">
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-primary">{c.jadwal.mata_pelajaran?.nama || 'Jadwal'}</span>
                                            <JadwalStatusBadge p={c} now={now} />
                                        </div>
                                        <div className="text-text-secondary">{c.jadwal.kelas_label || '—'}</div>
                                        <div className="font-mono text-[11px] text-text-secondary">{c.jadwal.jam_mulai?.substring(0, 5)}–{c.jadwal.jam_selesai?.substring(0, 5)}</div>
                                    </div>
                                ) : <span className="text-xs text-text-secondary">—</span>}
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap">
                                {c.jam_masuk
                                    ? <span className="font-mono text-sm font-bold text-primary">{c.jam_masuk.substring(0, 5)}</span>
                                    : <span className="text-sm text-text-secondary">—</span>}
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap">
                                {c.jam_keluar
                                    ? <span className="font-mono text-sm font-bold text-primary">{c.jam_keluar.substring(0, 5)}</span>
                                    : <span className="text-sm text-text-secondary">—</span>}
                            </td>
                            <td className="px-4 py-3.5 whitespace-nowrap">
                                {isTL ? (
                                    (c.foto_masuk_url || (c.foto_kegiatan_urls || []).length > 0) ? (
                                        <div className="flex flex-wrap gap-1.5">
                                            {c.foto_masuk_url
                                                ? <a href={c.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border"><img src={c.foto_masuk_url} alt="Masuk" className="h-full w-full object-cover" loading="lazy" /></a>
                                                : null}
                                            {(c.foto_kegiatan_urls || []).map((u, i) => (
                                                <a key={i} href={u} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border hover:ring-2 hover:ring-primary transition-shadow" title={`Bukti kegiatan ${i + 1}`}><img src={u} alt={`Bukti ${i + 1}`} className="h-full w-full object-cover" loading="lazy" /></a>
                                            ))}
                                        </div>
                                    ) : <span className="text-[11px] text-text-muted">tanpa foto</span>
                                ) : <span className="text-[11px] text-text-muted">tanpa foto</span>}
                            </td>
                            <td className="px-4 py-3.5 whitespace-nowrap">
                                {c.is_lembur ? <LemburBadge status={c.lembur_status} /> : <span className="text-xs text-text-secondary">—</span>}
                            </td>
                            <td className="px-4 py-3.5 whitespace-nowrap">
                                {isTL ? (
                                    <div>
                                        <TugasLuarBadge status={c.tugas_luar_status} />
                                        {c.tugas_luar_status === 'pending' && (
                                            <div className="mt-1.5 flex gap-1">
                                                <button onClick={() => router.post(route('presensi.approveTugasLuar', c.id), {}, { preserveState: true })} className="rounded-md bg-emerald-500 px-2 py-1 text-[10px] font-bold text-white transition-colors hover:bg-emerald-600">Setuju</button>
                                                <button onClick={() => router.post(route('presensi.rejectTugasLuar', c.id), {}, { preserveState: true })} className="rounded-md bg-rose-500 px-2 py-1 text-[10px] font-bold text-white transition-colors hover:bg-rose-600">Tolak</button>
                                            </div>
                                        )}
                                        {c.tujuan && <p className="mt-1 max-w-[160px] text-[10px] leading-tight text-text-secondary">{c.tujuan}</p>}
                                    </div>
                                ) : <span className="text-xs text-text-secondary">—</span>}
                            </td>
                            <td className="px-4 py-3.5 whitespace-nowrap">
                                {auth.permissions?.includes('manage_master_data') ? (
                                    <select
                                        className={`cursor-pointer rounded-lg border px-2.5 py-1.5 pr-7 text-[11px] font-bold uppercase shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/30 ${STATUS_META[c.status]?.badge || 'bg-gray-50 text-gray-700 border-gray-200'}`}
                                        value={c.status}
                                        onChange={(e) => setConfirmStatus({ id: c.id, statusLama: c.status, statusBaru: e.target.value })}
                                    >
                                        <option value="hadir">Hadir</option>
                                        <option value="telat">Telat</option>
                                        <option value="sakit">Sakit</option>
                                        <option value="izin">Izin</option>
                                        <option value="cuti">Cuti</option>
                                        <option value="alpa">Alpa</option>
                                    </select>
                                ) : (
                                    <StatusBadge status={c.status} />
                                )}
                            </td>
                            <td className="px-4 py-3.5 whitespace-nowrap text-right">
                                <div className="flex items-center justify-end gap-1.5">
                                    {c.lokasi_perlu_review && (
                                        <button onClick={() => openReview(c)} className="rounded-lg p-1.5 text-amber-600 transition-colors hover:bg-amber-50" title="Detail review anti-spoof"><ShieldAlert className="h-4 w-4" /></button>
                                    )}
                                    <button onClick={() => openAudit(c)} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary" title="Riwayat perubahan"><History className="h-4 w-4" /></button>
                                </div>
                            </td>
                        </tr>
                    );
                })}
            </React.Fragment>
        );
    });
};

const StatCard = ({ label, value, Icon, iconBg, iconCls, onClick, active }) => (
    <button
        type="button"
        onClick={onClick}
        className={`stat-card group hover:shadow-card-hover transition-shadow text-left w-full ${active ? 'ring-2 ring-primary' : ''}`}
    >
        <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${iconBg} transition-transform group-hover:scale-105`}>
            <Icon className={`h-5 w-5 ${iconCls}`} />
        </div>
        <div className="min-w-0">
            <p className="text-2xl font-extrabold leading-none text-primary tabular-nums">{value}</p>
            <p className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
        </div>
    </button>
);

const StatusDistribution = ({ stats }) => {
    const items = [
        { key: 'hadir', label: 'Hadir', val: stats.hadir, color: 'bg-emerald-500' },
        { key: 'telat', label: 'Terlambat', val: stats.telat, color: 'bg-amber-500' },
        { key: 'sakit', label: 'Sakit', val: stats.sakit, color: 'bg-purple-500' },
        { key: 'izin', label: 'Izin', val: stats.izin, color: 'bg-blue-500' },
        { key: 'cuti', label: 'Cuti', val: stats.cuti, color: 'bg-cyan-500' },
        { key: 'alpa', label: 'Alpa', val: stats.alpa, color: 'bg-rose-500' },
    ];
    const total = stats.total || 0;
    const segs = items.filter((x) => x.val > 0);

    return (
        <div className="border-b border-border bg-surface/60 px-4 py-3 sm:px-6">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2 text-xs font-semibold text-text-secondary">
                    <span>Distribusi Status</span>
                    <span className="rounded-full bg-border px-2 py-0.5 text-[11px] font-bold text-text-primary">{total} record</span>
                </div>
                <div className="flex flex-wrap gap-x-4 gap-y-1">
                    {items.map((it) => (
                        <span key={it.key} className="flex items-center gap-1.5 text-[11px] text-text-secondary">
                            <span className={`h-2.5 w-2.5 rounded-full ${it.color}`} />
                            {it.label} <b className="text-text-primary">{it.val}</b>
                        </span>
                    ))}
                </div>
            </div>
            <div className="mt-2 flex h-2.5 w-full overflow-hidden rounded-full bg-border">
                {segs.length === 0 ? (
                    <div className="h-full w-full bg-border" />
                ) : (
                    segs.map((seg) => (
                        <div
                            key={seg.key}
                            className={seg.color}
                            style={{ width: `${(seg.val / total) * 100}%` }}
                            title={`${seg.label}: ${seg.val}`}
                        />
                    ))
                )}
            </div>
        </div>
    );
};

const parseDate = (s) => {
    const [y, m, d] = String(s).split('-').map(Number);

    return new Date(y, (m || 1) - 1, d || 1);
};

const kerjaDurasi = (masuk, keluar) => {
    if (!masuk || !keluar) return null;
    const toMin = (x) => {
        const p = String(x).split(':');

        return (+p[0]) * 60 + (+p[1] || 0);
    };
    const m = toMin(keluar) - toMin(masuk);

    return m > 0 ? `${Math.floor(m / 60)}j${String(m % 60).padStart(2, '0')}m` : null;
};

const toMinutes = (hms) => {
    if (!hms) return 0;
    const p = String(hms).split(':');

    return (+p[0]) * 60 + (+p[1] || 0);
};

// Indikator status mengajar — konsisten dengan Dashboard mobile:
// "Mengajar" (berlangsung) selama jam mengajar belum habis, "Selesai"
// setelah jam_selesai lewat. Hanya untuk record yang sudah ada jam_masuk;
// record tanpa absen cukup terbaca dari kolom Status.
// Prop `now` (Date) membuat badge live-update tiap menit via useNowEveryMinute.
const JadwalStatusBadge = ({ p, now }) => {
    if (!p?.jadwal || !p.jam_masuk) return null;
    const current = now || new Date();
    const nowMinutes = current.getHours() * 60 + current.getMinutes();
    // Badge "Mengajar" hanya bermakna untuk record hari ini — record hari
    // sebelumnya/lama selalu sudah selesai, apa pun jam sekarang.
    const todayStr = `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}-${String(current.getDate()).padStart(2, '0')}`;
    const isHariIni = String(p.tanggal || '').startsWith(todayStr);
    const sudahLewat = !isHariIni || toMinutes(p.jadwal.jam_selesai) <= nowMinutes;

    if (sudahLewat) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                Selesai
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700">
            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500" aria-hidden="true" />
            Mengajar
        </span>
    );
};

const fmtDateInput = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export default function Index({ auth, presensis, pegawai, filters = {}, units, stats }) {
    const isAdmin = auth.permissions?.includes('view_presensi');
    const { flash = {} } = usePage().props;
    const [processing, setProcessing] = React.useState(false);
    // Jam "sekarang" yang live — dipakai badge Mengajar/Selesai biar berganti otomatis.
    const now = useNowEveryMinute();

    React.useEffect(() => subscribeRouter({
        start: () => setProcessing(true),
        finish: () => setProcessing(false),
    }), []);

    const [startDate, setStartDate] = React.useState(filters?.start_date || '');
    const [endDate, setEndDate] = React.useState(filters?.end_date || '');
    const [unitId, setUnitId] = React.useState(filters?.unit_id || '');
    const [lemburFilter, setLemburFilter] = React.useState(filters?.lembur_filter || '');
    const [lokasiFilter, setLokasiFilter] = React.useState(filters?.lokasi_filter || '');
    const [suspiciousFilter, setSuspiciousFilter] = React.useState(filters?.suspicious_filter || '');
    const [statusFilter, setStatusFilter] = React.useState(filters?.status_filter || '');
    const [jadwalFilter, setJadwalFilter] = React.useState(filters?.jadwal_filter || '');
    const [jenisFilter, setJenisFilter] = React.useState(filters?.jenis_filter || '');
    const [viewMode, setViewMode] = React.useState('ringkas');
    const [expanded, setExpanded] = React.useState({});
    const [search, setSearch] = React.useState(filters?.search || '');
    const [sortKey, setSortKey] = React.useState('nama');
    const [sortDir, setSortDir] = React.useState('asc');

    const [confirmStatus, setConfirmStatus] = React.useState(null);
    const [persentaseBayar, setPersentaseBayar] = React.useState(100);
    const [auditModal, setAuditModal] = React.useState({ show: false, loading: false, data: [], presensi: null });
    const [auditPegawai, setAuditPegawai] = React.useState('');
    const [reviewModal, setReviewModal] = React.useState({ show: false, loading: false, data: null });

    const hasFilter = Boolean(search || statusFilter || jadwalFilter || jenisFilter || unitId || lemburFilter || lokasiFilter || suspiciousFilter || startDate || endDate);

    // Default: Hari Ini jika tidak ada filter tanggal dari URL
    React.useEffect(() => {
        if (!startDate && !endDate) {
            applyPreset('hari');
        }
    }, []);

    // Preset periode yang sedang aktif (cocokkan start/end dgn preset).
    const today = new Date();
    const todayStr = fmtDateInput(today);
    const firstMonthStr = fmtDateInput(new Date(today.getFullYear(), today.getMonth(), 1));
    const last7Str = fmtDateInput(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 6));
    const activePreset = startDate && endDate
        ? (startDate === todayStr && endDate === todayStr ? 'hari'
            : startDate === firstMonthStr && endDate === todayStr ? 'bulan'
            : startDate === last7Str && endDate === todayStr ? '7hari'
            : null)
        : null;

    const buildParams = React.useCallback((overrides = {}) => ({
        start_date: startDate,
        end_date: endDate,
        unit_id: unitId,
        lembur_filter: lemburFilter,
        lokasi_filter: lokasiFilter,
        suspicious_filter: suspiciousFilter,
        status_filter: statusFilter,
        jadwal_filter: jadwalFilter,
        jenis_filter: jenisFilter,
        search,
        ...overrides,
    }), [startDate, endDate, unitId, lemburFilter, lokasiFilter, suspiciousFilter, statusFilter, jadwalFilter, jenisFilter, search]);

    const applyFilters = React.useCallback((overrides = {}) => {
        router.get(route('presensi.index'), buildParams(overrides), { preserveState: true, preserveScroll: true });
    }, [buildParams]);

    // Klik kartu statistik → langsung filter ke data tersebut.
    const applyStatFilter = (overrides) => {
        if ('status_filter' in overrides) setStatusFilter(overrides.status_filter);
        if ('lembur_filter' in overrides) setLemburFilter(overrides.lembur_filter);
        if ('lokasi_filter' in overrides) setLokasiFilter(overrides.lokasi_filter);
        if ('suspicious_filter' in overrides) setSuspiciousFilter(overrides.suspicious_filter);
        applyFilters(overrides);
    };

    // Search otomatis dengan debounce
    React.useEffect(() => {
        if (search === (filters?.search || '')) return;
        const timer = setTimeout(() => applyFilters({ search }), 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    React.useEffect(() => {
        if (confirmStatus) setPersentaseBayar(confirmStatus.persentase_bayar_jam ?? 100);
    }, [confirmStatus]);

    const applyPreset = (mode) => {
        const end = new Date();
        const start = new Date(end);
        if (mode === 'bulan') {
            start.setDate(1);
        } else if (mode === '7hari') {
            start.setDate(end.getDate() - 6);
        } else {
            start.setDate(end.getDate());
        }
        const sd = fmtDateInput(start);
        const ed = fmtDateInput(end);
        // Update state supaya input tanggal & header ikut merefleksikan preset.
        setStartDate(sd);
        setEndDate(ed);
        applyFilters({ start_date: sd, end_date: ed });
    };

    const resetFilters = () => {
        setStartDate('');
        setEndDate('');
        setUnitId('');
        setLemburFilter('');
        setLokasiFilter('');
        setSuspiciousFilter('');
        setStatusFilter('');
        setJadwalFilter('');
        setJenisFilter('');
        setSearch('');
        router.get(route('presensi.index'), {}, { preserveState: true });
    };

    const openAudit = (p) => {
        setAuditPegawai(p.pegawai?.nama_lengkap || '');
        setAuditModal({ show: true, loading: true, data: [], presensi: null });
        fetch(route('presensi.audit', p.id))
            .then((r) => r.json())
            .then((res) => setAuditModal({ show: true, loading: false, data: res.audits || [], presensi: res.presensi || null }))
            .catch(() => setAuditModal({ show: true, loading: false, data: [], presensi: null }));
    };

    const openReview = (p) => {
        setReviewModal({ show: true, loading: true, data: null });
        fetch(route('presensi.review', p.id))
            .then((r) => r.json())
            .then((res) => setReviewModal({ show: true, loading: false, data: res.presensi || null }))
            .catch(() => setReviewModal({ show: true, loading: false, data: null }));
    };

    const s = stats || { total: 0, hadir: 0, telat: 0, sakit: 0, izin: 0, cuti: 0, alpa: 0, lembur_pending: 0, perlu_review: 0 };

    const statsCards = [
        { key: 'total', label: 'Total', value: s.total, Icon: Users, iconBg: 'bg-primary/10', iconCls: 'text-primary', filter: { status_filter: '', lembur_filter: '', lokasi_filter: '', suspicious_filter: '' }, active: !statusFilter && !lemburFilter && !lokasiFilter && !suspiciousFilter },
        { key: 'hadir', label: 'Hadir', value: s.hadir, Icon: CheckCircle2, iconBg: 'bg-emerald-100', iconCls: 'text-emerald-600', filter: { status_filter: 'hadir', lembur_filter: '', lokasi_filter: '', suspicious_filter: '' }, active: statusFilter === 'hadir' },
        { key: 'telat', label: 'Terlambat', value: s.telat, Icon: Clock3, iconBg: 'bg-amber-100', iconCls: 'text-amber-600', filter: { status_filter: 'telat', lembur_filter: '', lokasi_filter: '', suspicious_filter: '' }, active: statusFilter === 'telat' },
        { key: 'sakit', label: 'Sakit', value: s.sakit, Icon: HeartPulse, iconBg: 'bg-purple-100', iconCls: 'text-purple-600', filter: { status_filter: 'sakit', lembur_filter: '', lokasi_filter: '', suspicious_filter: '' }, active: statusFilter === 'sakit' },
        { key: 'izin', label: 'Izin', value: s.izin, Icon: FileText, iconBg: 'bg-blue-100', iconCls: 'text-blue-600', filter: { status_filter: 'izin', lembur_filter: '', lokasi_filter: '', suspicious_filter: '' }, active: statusFilter === 'izin' },
        { key: 'cuti', label: 'Cuti', value: s.cuti, Icon: CalendarOff, iconBg: 'bg-cyan-100', iconCls: 'text-cyan-600', filter: { status_filter: 'cuti', lembur_filter: '', lokasi_filter: '', suspicious_filter: '' }, active: statusFilter === 'cuti' },
        { key: 'alpa', label: 'Alpa', value: s.alpa, Icon: UserX, iconBg: 'bg-rose-100', iconCls: 'text-rose-600', filter: { status_filter: 'alpa', lembur_filter: '', lokasi_filter: '', suspicious_filter: '' }, active: statusFilter === 'alpa' },
        { key: 'lembur_pending', label: 'Lembur Pending', value: s.lembur_pending, Icon: AlarmClock, iconBg: 'bg-orange-100', iconCls: 'text-orange-600', filter: { status_filter: '', lembur_filter: 'lembur_pending', lokasi_filter: '', suspicious_filter: '' }, active: lemburFilter === 'lembur_pending' },
        { key: 'perlu_review', label: 'Perlu Review', value: s.perlu_review, Icon: ShieldAlert, iconBg: 'bg-red-100', iconCls: 'text-red-600', filter: { status_filter: '', lembur_filter: '', lokasi_filter: 'perlu_review', suspicious_filter: '' }, active: lokasiFilter === 'perlu_review' },
    ];

    // flex-1 + min-w/basis: tiap kontrol mengisi penuh baris, baris terakhir
    // ikut meregang sampai ujung kanan (tidak ada lahan kosong seperti grid).
    const filterSelect = 'select-field text-xs h-9 flex-1 min-w-[150px] basis-44';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">{isAdmin ? 'Manajemen Presensi' : 'Riwayat Presensi'}</h2>}
        >
            <Head title="Presensi" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {/* Flash */}
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between gap-3">
                        <div>
                            <h3 className="text-xl font-bold text-primary">
                                {isAdmin ? 'Rekap Kehadiran Pegawai' : `Riwayat Absensi${pegawai ? ` — ${pegawai.nama_lengkap}` : ''}`}
                            </h3>
                            <p className="mt-1 text-sm text-text-secondary">Pantau kehadiran, verifikasi lokasi, dan kelola status presensi.</p>
                        </div>
                        <p className="flex items-center gap-2 text-xs font-medium text-text-secondary">
                            <CalendarDays className="h-4 w-4 text-primary" />
                            {jadwalFilter === 'sedang_berlangsung' ? (
                                <span className="inline-flex items-center gap-1.5 text-amber-700">
                                    <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500" aria-hidden="true" />
                                    Kelas sedang berlangsung (hari ini)
                                </span>
                            ) : startDate || endDate
                                ? `${startDate || '…'} s/d ${endDate || '…'}`
                                : 'Semua periode'}
                        </p>
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {statsCards.map((card) => <StatCard key={card.label} {...card} onClick={() => applyStatFilter(card.filter)} />)}
                    </div>

                    {/* Filter bar */}
                    <div className="card p-5">
                        {/* Row 1: Search + Quick Filters */}
                        <div className="flex flex-wrap items-center gap-3">
                            {isAdmin && (
                                <div className="relative min-w-[240px] flex-1 basis-72">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Cari nama pegawai…"
                                        className="input-field h-9 pl-9 text-xs w-full"
                                    />
                                </div>
                            )}
                            <select className={filterSelect} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                                <option value="">Semua Status</option>
                                {Object.entries(STATUS_META).map(([key, m]) => <option key={key} value={key}>{m.label}</option>)}
                            </select>
                            {isAdmin && auth.permissions?.includes('view_all_units') && (
                                <select className={filterSelect} value={unitId} onChange={(e) => setUnitId(e.target.value)}>
                                    <option value="">Semua Unit</option>
                                    {units?.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                </select>
                            )}
                            {isAdmin && (
                                <select className={filterSelect} value={jenisFilter} onChange={(e) => setJenisFilter(e.target.value)}>
                                    <option value="">Semua Jenis</option>
                                    <option value="pendidik">Pendidik (Guru)</option>
                                    <option value="kependidikan">Tenaga Kependidikan</option>
                                </select>
                            )}
                        </div>

                        {/* Row 2: Extended Filters (admin only) */}
                        {isAdmin && (
                            <div className="mt-3 flex flex-wrap items-center gap-3">
                                <select className={filterSelect} value={jadwalFilter} onChange={(e) => {
                                    setJadwalFilter(e.target.value);
                                    if (e.target.value === 'sedang_berlangsung') {
                                        setStartDate('');
                                        setEndDate('');
                                    }
                                }}>
                                    <option value="">Semua Jadwal</option>
                                    <option value="sedang_berlangsung">Sedang Berlangsung</option>
                                </select>
                                <select className={filterSelect} value={lemburFilter} onChange={(e) => setLemburFilter(e.target.value)}>
                                    <option value="">Semua Presensi</option>
                                    <option value="lembur_semua">Semua Lembur</option>
                                    <option value="lembur_pending">Lembur Pending</option>
                                    <option value="lembur_disetujui">Lembur Disetujui</option>
                                    <option value="lembur_ditolak">Lembur Ditolak</option>
                                </select>
                                <select className={filterSelect} value={lokasiFilter} onChange={(e) => setLokasiFilter(e.target.value)}>
                                    <option value="">Semua Lokasi</option>
                                    <option value="review_semua">Perlu Review (Semua)</option>
                                    <option value="perlu_review">Perlu Review GPS</option>
                                    <option value="pulang_awal">Pulang Awal</option>
                                </select>
                                <select className={filterSelect} value={suspiciousFilter} onChange={(e) => setSuspiciousFilter(e.target.value)}>
                                    <option value="">Semua GPS</option>
                                    <option value="1">Posisi Mencurigakan</option>
                                </select>
                            </div>
                        )}

                        {/* Row 3: Periode (preset + rentang custom) + Actions */}
                        <div className="mt-4 flex flex-col gap-4 border-t border-border/60 pt-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                                <span className="text-[11px] font-bold uppercase tracking-wider text-text-secondary">Periode</span>

                                {/* Segmented preset control dengan state aktif */}
                                <div className="inline-flex rounded-xl border border-border bg-surface p-1">
                                    {[{ k: 'hari', label: 'Hari Ini' }, { k: '7hari', label: '7 Hari' }, { k: 'bulan', label: 'Bulan Ini' }].map((p) => {
                                        const active = activePreset === p.k;
                                        return (
                                            <button
                                                key={p.k}
                                                type="button"
                                                onClick={() => applyPreset(p.k)}
                                                className={`rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors ${
                                                    active ? 'bg-primary text-white shadow-sm' : 'text-text-secondary hover:text-primary'
                                                }`}
                                            >
                                                {p.label}
                                            </button>
                                        );
                                    })}
                                </div>

                                {/* Rentang manual */}
                                <div className="flex items-center gap-2">
                                    <input type="date" className="input-field text-xs h-9" value={startDate} onChange={(e) => setStartDate(e.target.value)} aria-label="Tanggal mulai" />
                                    <span className="text-xs text-text-secondary">–</span>
                                    <input type="date" className="input-field text-xs h-9" value={endDate} onChange={(e) => setEndDate(e.target.value)} aria-label="Tanggal selesai" />
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                {hasFilter && (
                                    <button type="button" onClick={resetFilters} className="btn-secondary btn-sm flex items-center gap-1.5">
                                        <RotateCcw className="h-3.5 w-3.5" /> Reset
                                    </button>
                                )}
                                <button type="button" onClick={() => applyFilters()} disabled={processing} className="btn-primary btn-sm flex items-center gap-1.5">
                                    {processing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Filter className="h-3.5 w-3.5" />}
                                    Terapkan
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* View mode + Tugas Luar */}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="inline-flex rounded-xl border border-border p-1">
                            <button
                                type="button"
                                onClick={() => setViewMode('detail')}
                                className={`rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors ${viewMode === 'detail' ? 'bg-primary text-white' : 'text-text-secondary hover:text-primary'}`}
                            >
                                Detail
                            </button>
                            <button
                                type="button"
                                onClick={() => setViewMode('ringkas')}
                                className={`rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors ${viewMode === 'ringkas' ? 'bg-primary text-white' : 'text-text-secondary hover:text-primary'}`}
                            >
                                Ringkas
                            </button>
                        </div>
                        {isAdmin && (
                            <Link href={route('tugas-luar.index')} className="btn-secondary btn-sm flex items-center gap-1.5">
                                <CalendarDays className="h-3.5 w-3.5" /> Kelola Tugas Luar
                            </Link>
                        )}
                    </div>

                    {/* ─── ADMIN: Table ─── */}
                    {(() => {
                        const sortGetters = {
                            nama: (p) => p.pegawai?.nama_lengkap || '',
                            tanggal: (p) => p.tanggal || '',
                            masuk: (p) => p.jam_masuk || '',
                            keluar: (p) => p.jam_keluar || '',
                            status: (p) => p.status || '',
                        };
                        const sorted = [...presensis.data].sort((a, b) => {
                            const fn = sortGetters[sortKey];
                            if (!fn) return 0;
                            const av = fn(a), bv = fn(b);
                            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
                            return sortDir === 'asc' ? cmp : -cmp;
                        });
                        const toggleSort = (key) => {
                            if (sortKey === key) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
                            else { setSortKey(key); setSortDir('asc'); }
                        };
                        const SortIcon = ({ col }) => {
                            if (sortKey !== col) return <span className="ml-1 text-text-secondary/40">↕</span>;
                            return <span className="ml-1 text-primary">{sortDir === 'asc' ? '↑' : '↓'}</span>;
                        };
                    return isAdmin ? (
                        <div className="card p-0 overflow-hidden">
                            <StatusDistribution stats={s} />
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface/80 sticky top-0 z-10 backdrop-blur-sm">
                                        <tr>
                                            <th onClick={() => toggleSort('nama')} className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider cursor-pointer select-none hover:text-primary">Pegawai & Unit <SortIcon col="nama" /></th>
                                            <th onClick={() => toggleSort('tanggal')} className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider cursor-pointer select-none hover:text-primary">Tanggal <SortIcon col="tanggal" /></th>
                                            <th className="hidden sm:table-cell px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Jadwal</th>
                                            <th onClick={() => toggleSort('masuk')} className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider cursor-pointer select-none hover:text-primary">Masuk <SortIcon col="masuk" /></th>
                                            <th onClick={() => toggleSort('keluar')} className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider cursor-pointer select-none hover:text-primary">Keluar <SortIcon col="keluar" /></th>
                                            <th className="hidden md:table-cell px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Foto</th>
                                            <th className="hidden lg:table-cell px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Lembur</th>
                                            <th className="hidden lg:table-cell px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Tugas Luar</th>
                                            <th onClick={() => toggleSort('status')} className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider cursor-pointer select-none hover:text-primary">Status <SortIcon col="status" /></th>
                                            <th className="px-4 py-3.5 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Lokasi & Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className={`divide-y divide-border/50 ${processing ? 'opacity-60 pointer-events-none transition-opacity' : ''}`}>
                                        {sorted.length === 0 ? null : viewMode === 'ringkas' ? (
                                            <RingkasBody data={sorted} auth={auth} now={now} expanded={expanded} setExpanded={setExpanded} openReview={openReview} openAudit={openAudit} setConfirmStatus={setConfirmStatus} />
                                        ) : sorted.map((p) => {
                                            const durasi = kerjaDurasi(p.jam_masuk, p.jam_keluar);
                                            const flagReview = p.lokasi_perlu_review || p.posisi_mencurigakan || p.motion_suspect;
                                            const nama = p.pegawai?.nama_lengkap || '-';

                                            return (
                                                <tr key={p.id} className="group hover:bg-surface/70 transition-colors">
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <div className="flex items-center gap-3">
                                                            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold ${avatarTone(nama)}`}>
                                                                {initials(nama)}
                                                            </span>
                                                            <div className="min-w-0">
                                                                <div className="text-sm font-bold text-primary truncate max-w-[180px]">{nama}</div>
                                                                <div className="text-[11px] text-text-secondary truncate max-w-[180px]">
                                                                    {p.pegawai?.jabatans?.some(j => j.is_guru) ? (
                                                                        <span title={p.pegawai?.mapels?.map(m => m.mata_pelajaran?.nama).filter(Boolean).join(', ')}>
                                                                            {p.pegawai?.mapels?.map(m => m.mata_pelajaran?.nama).filter(Boolean).join(', ') || 'Guru'}
                                                                        </span>
                                                                    ) : (
                                                                        <span>{p.pegawai?.jabatans?.map(j => j.nama).filter(Boolean).join(', ') || '—'}</span>
                                                                    )}
                                                                    {' • '}{p.unit_sekolah?.nama || '—'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        <div className="text-sm font-semibold text-primary">{format(parseDate(p.tanggal), 'd MMM yyyy', { locale: id })}</div>
                                                        <div className="text-[11px] text-text-secondary">{format(parseDate(p.tanggal), 'EEEE', { locale: id })}</div>
                                                    </td>
                                                    <td className="hidden sm:table-cell px-4 py-3.5 whitespace-nowrap">
                                                        {p.jadwal ? (
                                                            <div className="text-xs leading-tight">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="font-semibold text-primary">{p.jadwal.mata_pelajaran?.nama || 'Jadwal'}</span>
                                                                    <JadwalStatusBadge p={p} now={now} />
                                                                </div>
                                                                <div className="text-text-secondary">{p.jadwal.kelas_label || '—'}</div>
                                                                <div className="font-mono text-[11px] text-text-secondary">{p.jadwal.jam_mulai?.substring(0, 5)}–{p.jadwal.jam_selesai?.substring(0, 5)}</div>
                                                            </div>
                                                        ) : p.is_lembur ? (
                                                            <span className="text-xs font-bold uppercase text-orange-600">Lembur</span>
                                                        ) : p.tipe_presensi === 'kantor' ? (
                                                            <span className="text-xs font-semibold text-text-secondary">Kantor</span>
                                                        ) : <span className="text-xs text-text-secondary">—</span>}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {p.jam_masuk ? (
                                                            <div className="flex flex-col">
                                                                <span className="font-mono text-sm font-bold text-primary">{p.jam_masuk.substring(0, 5)}</span>
                                                                {p.jarak_masuk_meter != null && (
                                                                    <span className="text-[10px] text-text-secondary flex items-center gap-0.5"><MapPin className="h-2.5 w-2.5" />{p.jarak_masuk_meter}m</span>
                                                                )}
                                                            </div>
                                                        ) : <span className="text-sm text-text-secondary">—</span>}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {p.jam_keluar ? (
                                                            <div className="flex flex-col">
                                                                <span className="font-mono text-sm font-bold text-primary">{p.jam_keluar.substring(0, 5)}</span>
                                                                {durasi && <span className="text-[10px] font-semibold text-emerald-600">{durasi}</span>}
                                                            </div>
                                                        ) : <span className="text-sm text-text-secondary">—</span>}
                                                    </td>
                                                    <td className="hidden md:table-cell px-4 py-3.5 whitespace-nowrap">
                                                        <div className="flex gap-1.5">
                                                            {p.foto_masuk_url ? (
                                                                <a href={p.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border hover:ring-2 hover:ring-primary transition-shadow" title="Foto masuk">
                                                                    <img src={p.foto_masuk_url} alt="Masuk" className="h-full w-full object-cover" loading="lazy" />
                                                                </a>
                                                            ) : p.foto_masuk_status ? (
                                                                <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-[8px] font-semibold uppercase text-slate-400">{p.foto_masuk_status}</span>
                                                            ) : null}
                                                            {p.foto_keluar_url ? (
                                                                <a href={p.foto_keluar_url} target="_blank" rel="noopener noreferrer" className="block h-9 w-9 overflow-hidden rounded-lg border border-border hover:ring-2 hover:ring-primary transition-shadow" title="Foto keluar">
                                                                    <img src={p.foto_keluar_url} alt="Keluar" className="h-full w-full object-cover" loading="lazy" />
                                                                </a>
                                                            ) : p.foto_keluar_status ? (
                                                                <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-[8px] font-semibold uppercase text-slate-400">{p.foto_keluar_status}</span>
                                                            ) : null}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {p.is_lembur ? (
                                                            <div>
                                                                <LemburBadge status={p.lembur_status} />
                                                                {p.lembur_status === 'pending' && (
                                                                    <div className="mt-1.5 flex gap-1">
                                                                        <button
                                                                            onClick={() => router.post(route('presensi.approveLembur', p.id), {}, { preserveState: true })}
                                                                            className="rounded-md bg-emerald-500 px-2 py-1 text-[10px] font-bold text-white transition-colors hover:bg-emerald-600"
                                                                        >
                                                                            Setuju
                                                                        </button>
                                                                        <button
                                                                            onClick={() => router.post(route('presensi.rejectLembur', p.id), {}, { preserveState: true })}
                                                                            className="rounded-md bg-rose-500 px-2 py-1 text-[10px] font-bold text-white transition-colors hover:bg-rose-600"
                                                                        >
                                                                            Tolak
                                                                        </button>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ) : <span className="text-xs text-text-secondary">—</span>}
                                                    </td>
                                                    <TugasLuarCell p={p} />
                                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                                        {auth.permissions?.includes('manage_master_data') ? (
                                                            <select
                                                                className={`cursor-pointer rounded-lg border px-2.5 py-1.5 pr-7 text-[11px] font-bold uppercase shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/30 ${STATUS_META[p.status]?.badge || 'bg-gray-50 text-gray-700 border-gray-200'}`}
                                                                value={p.status}
                                                                onChange={(e) => setConfirmStatus({ id: p.id, statusLama: p.status, statusBaru: e.target.value })}
                                                            >
                                                                <option value="hadir">Hadir</option>
                                                                <option value="telat">Telat</option>
                                                                <option value="sakit">Sakit</option>
                                                                <option value="izin">Izin</option>
                                                                <option value="cuti">Cuti</option>
                                                                <option value="alpa">Alpa</option>
                                                            </select>
                                                        ) : (
                                                            <StatusBadge status={p.status} />
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 whitespace-nowrap text-right">
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            {flagReview && (
                                                                <button onClick={() => openReview(p)} className="rounded-lg p-1.5 text-amber-600 transition-colors hover:bg-amber-50" title="Detail review anti-spoof">
                                                                    <ShieldAlert className="h-4 w-4" />
                                                                </button>
                                                            )}
                                                            <button onClick={() => openAudit(p)} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary" title="Riwayat perubahan">
                                                                <History className="h-4 w-4" />
                                                            </button>
                                                            {p.jarak_masuk_meter != null && (
                                                                <span className="ml-1 font-mono text-[11px] font-semibold text-text-secondary">{p.jarak_masuk_meter}m</span>
                                                            )}
                                                        </div>
                                                        {(p.lokasi_perlu_review || p.posisi_mencurigakan) && (
                                                            <div className="mt-1 flex justify-end gap-1">
                                                                {p.lokasi_perlu_review && <span className="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[9px] font-bold uppercase text-rose-600"><AlertTriangle className="h-2.5 w-2.5" /> Review</span>}
                                                                {p.posisi_mencurigakan && <span className="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase text-amber-600">Suspek</span>}
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {sorted.length === 0 && (
                                            <tr>
                                                <td colSpan="10" className="px-6 py-16">
                                                    <div className="flex flex-col items-center text-center">
                                                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                                            <CalendarDays className="h-8 w-8 text-border" />
                                                        </div>
                                                        <p className="mt-4 text-base font-bold text-primary">Belum ada data presensi</p>
                                                        <p className="mt-1 text-sm text-text-secondary">Coba ubah periode atau bersihkan filter.</p>
                                                        {hasFilter && (
                                                            <button onClick={resetFilters} className="btn-secondary btn-sm mt-4 flex items-center gap-1.5">
                                                                <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {presensis.data.map((p) => (
                                <div key={p.id} className="card p-5 transition-shadow hover:shadow-card-hover">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/5 text-primary">
                                                    <CalendarDays className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <div className="text-sm font-bold text-primary">{format(parseDate(p.tanggal), 'EEEE, d MMMM yyyy', { locale: id })}</div>
                                                    <div className="mt-0.5 flex items-center gap-3 text-xs text-text-secondary">
                                                        <span className="inline-flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Masuk <b className="font-mono text-primary">{p.jam_masuk?.substring(0, 5) || '—'}</b></span>
                                                        <span className="inline-flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-rose-400" /> Keluar <b className="font-mono text-primary">{p.jam_keluar?.substring(0, 5) || '—'}</b></span>
                                                        {p.tipe_presensi === 'mengajar' && (
                                                            <span className="flex items-center gap-2 text-[11px] text-text-muted">
                                                                {p.jadwal?.mata_pelajaran?.nama || ''}
                                                                <JadwalStatusBadge p={p} now={now} />
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={p.status} />
                                            {p.is_lembur && <LemburBadge status={p.lembur_status} />}
                                        </div>
                                    </div>
                                    {(p.foto_masuk_url || p.foto_keluar_url) && (
                                        <div className="mt-3 flex items-center gap-4 border-t border-border pt-3">
                                            {p.foto_masuk_url && (
                                                <a href={p.foto_masuk_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-xs text-text-secondary transition-colors hover:text-primary">
                                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Foto Masuk
                                                </a>
                                            )}
                                            {p.foto_keluar_url && (
                                                <a href={p.foto_keluar_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-xs text-text-secondary transition-colors hover:text-primary">
                                                    <span className="h-1.5 w-1.5 rounded-full bg-rose-400" /> Foto Keluar
                                                </a>
                                            )}
                                        </div>
                                    )}

                                    {(p.foto_kegiatan_urls || []).length > 0 && (
                                        <div className="mt-3 border-t border-border pt-3">
                                            <p className="mb-2 text-xs font-semibold text-text-secondary">Foto Kegiatan ({p.foto_kegiatan_urls.length})</p>
                                            <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                                {p.foto_kegiatan_urls.map((u, i) => (
                                                    <a key={i} href={u} target="_blank" rel="noopener noreferrer" className="block h-16 w-full overflow-hidden rounded-lg border border-border">
                                                        <img src={u} alt={`Bukti ${i + 1}`} className="h-full w-full object-cover" loading="lazy" />
                                                    </a>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                            {presensis.data.length === 0 && (
                                <div className="card p-12 text-center">
                                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                        <CalendarDays className="h-8 w-8 text-border" />
                                    </div>
                                    <p className="mt-4 text-base font-bold text-primary">Belum ada riwayat presensi</p>
                                    <p className="mt-1 text-sm text-text-secondary">Data akan muncul setelah Anda melakukan absen pertama.</p>
                                </div>
                            )}
                        </div>
                    );
                    })()}

                    {/* Pagination */}
                    {presensis.total > 0 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-text-secondary">
                                Menampilkan <b className="text-primary">{presensis.from || 0}</b>–<b className="text-primary">{presensis.to || 0}</b> dari <b className="text-primary">{presensis.total}</b> data
                            </p>
                            <Pagination links={presensis.links} pagination={{ current_page: presensis.current_page, last_page: presensis.last_page }} />
                        </div>
                    )}

                    {/* ─── MODAL: Riwayat Perubahan ─── */}
                    <Modal show={auditModal.show} onClose={() => setAuditModal({ show: false, loading: false, data: [], presensi: null })} maxWidth="lg">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-surface text-primary">
                                        <History className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold text-primary">Riwayat Perubahan</h3>
                                        {auditPegawai && <p className="mt-0.5 text-sm text-text-secondary">{auditPegawai}</p>}
                                    </div>
                                </div>
                                <button onClick={() => setAuditModal({ show: false, loading: false, data: [], presensi: null })} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary">
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                            {auditModal.presensi && (auditModal.presensi.foto_masuk_url || auditModal.presensi.foto_keluar_url) && (
                                <div className="mb-5 rounded-lg bg-surface p-4">
                                    <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Bukti Foto</p>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        {[{ label: 'Foto Masuk', url: auditModal.presensi.foto_masuk_url, status: auditModal.presensi.foto_masuk_status, error: auditModal.presensi.foto_masuk_error, tone: 'bg-emerald-100 text-emerald-700' }, { label: 'Foto Keluar', url: auditModal.presensi.foto_keluar_url, status: auditModal.presensi.foto_keluar_status, error: auditModal.presensi.foto_keluar_error, tone: 'bg-rose-100 text-rose-700' }].map((f, i) => (
                                            f.url && (
                                                <div key={i}>
                                                    <a href={f.url} target="_blank" rel="noopener noreferrer" className="block overflow-hidden rounded-lg border border-border transition-shadow hover:ring-2 hover:ring-primary" title={`${f.label} — buka di tab baru`}>
                                                        <img src={f.url} alt={f.label} className="aspect-[3/4] w-full object-cover" loading="lazy" />
                                                    </a>
                                                    <div className="mt-1.5 flex items-center justify-between px-0.5">
                                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${f.tone}`}>{f.label}</span>
                                                        {f.status && f.status !== 'success' && <span className="text-[10px] font-semibold text-warning">{f.status}{f.error ? ` — ${f.error}` : ''}</span>}
                                                    </div>
                                                </div>
                                            )
                                        ))}
                                    </div>
                                </div>
                            )}

                            {auditModal.presensi && (auditModal.presensi.foto_kegiatan_urls || []).length > 0 && (
                                <div className="mb-5 rounded-lg bg-surface p-4">
                                    <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Foto Kegiatan ({auditModal.presensi.foto_kegiatan_urls.length})</p>
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        {auditModal.presensi.foto_kegiatan_urls.map((u, i) => (
                                            <a key={i} href={u} target="_blank" rel="noopener noreferrer" className="block overflow-hidden rounded-lg border border-border transition-shadow hover:ring-2 hover:ring-primary" title="Buka di tab baru">
                                                <img src={u} alt={`Bukti ${i + 1}`} className="aspect-[3/4] w-full object-cover" loading="lazy" />
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {auditModal.loading ? (
                                <div className="space-y-4 py-4">
                                    {[1, 2, 3].map((i) => (
                                        <div key={i} className="flex animate-pulse gap-4">
                                            <div className="mt-2 h-2 w-2 flex-shrink-0 rounded-full bg-border" />
                                            <div className="flex-1 space-y-2">
                                                <div className="h-3 w-24 rounded bg-surface" />
                                                <div className="h-4 w-40 rounded bg-surface" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : auditModal.data.length === 0 ? (
                                <div className="py-12 text-center">
                                    <History className="mx-auto mb-3 h-12 w-12 text-border" />
                                    <p className="text-sm text-text-secondary">Belum ada perubahan.</p>
                                </div>
                            ) : (
                                <div className="relative">
                                    <div className="absolute bottom-2 left-[7px] top-2 w-0.5 bg-border/60" />
                                    <div className="space-y-5">
                                        {auditModal.data.map((a, i) => {
                                            const isStatus = a.field === 'status';
                                            const aksiLabel = a.aksi === 'approve_lembur' ? 'Setujui Lembur'
                                                : a.aksi === 'reject_lembur' ? 'Tolak Lembur'
                                                : a.aksi === 'ubah_status' ? 'Ubah Status'
                                                : a.aksi;
                                            const tone = a.aksi === 'approve_lembur' ? 'bg-emerald-50 text-emerald-700'
                                                : a.aksi === 'reject_lembur' ? 'bg-rose-50 text-rose-700'
                                                : 'bg-amber-50 text-amber-700';
                                            const dotTone = a.aksi === 'approve_lembur' ? 'bg-emerald-400'
                                                : a.aksi === 'reject_lembur' ? 'bg-rose-400'
                                                : 'bg-amber-300';
                                            const badgeOf = (val) => STATUS_META[val]?.badge || 'bg-gray-50 text-gray-700 border-gray-200';

                                            return (
                                                <div key={a.id} className="flex gap-4">
                                                    <div className={`z-10 mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border-2 ${tone} border-current`}>
                                                        <div className={`h-1.5 w-1.5 rounded-full ${dotTone}`} />
                                                    </div>
                                                    <div className="min-w-0 flex-1 pb-1">
                                                        <div className="mb-1 flex items-center gap-2">
                                                            <span className={`inline-flex items-center rounded px-2 py-0.5 text-[11px] font-bold uppercase ${tone}`}>{aksiLabel}</span>
                                                            <span className="text-[11px] text-text-secondary">{format(new Date(a.created_at), 'd MMM HH:mm', { locale: id })}</span>
                                                        </div>
                                                        {isStatus && (
                                                            <div className="mt-1.5 flex items-center gap-2">
                                                                <span className={`inline-flex items-center rounded border px-2 py-0.5 text-xs font-bold ${badgeOf(a.nilai_lama)}`}>{a.nilai_lama?.toUpperCase() || '—'}</span>
                                                                <span className="text-text-secondary">→</span>
                                                                <span className={`inline-flex items-center rounded border px-2 py-0.5 text-xs font-bold ${badgeOf(a.nilai_baru)}`}>{a.nilai_baru?.toUpperCase() || '—'}</span>
                                                            </div>
                                                        )}
                                                        <div className="mt-1 flex items-center gap-1.5">
                                                            <span className="text-[11px] text-text-secondary">{a.user?.name || '-'}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    </Modal>

                    {/* ─── MODAL: Review Anti-Spoof ─── */}
                    <Modal show={reviewModal.show} onClose={() => setReviewModal({ show: false, loading: false, data: null })} maxWidth="lg">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                        <ShieldAlert className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold text-primary">Detail Review Anti-Spoof</h3>
                                        {reviewModal.data && <p className="mt-0.5 text-sm text-text-secondary">{reviewModal.data.pegawai_nama} • {reviewModal.data.tanggal}</p>}
                                    </div>
                                </div>
                                <button onClick={() => setReviewModal({ show: false, loading: false, data: null })} className="rounded-lg p-1.5 text-text-secondary transition-colors hover:bg-surface hover:text-primary">
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                            {reviewModal.loading ? (
                                <div className="space-y-4 py-4">
                                    {[1, 2, 3].map((i) => (
                                        <div key={i} className="flex animate-pulse gap-4">
                                            <div className="flex-1 space-y-2">
                                                <div className="h-3 w-24 rounded bg-surface" />
                                                <div className="h-4 w-40 rounded bg-surface" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : !reviewModal.data ? (
                                <div className="py-12 text-center">
                                    <p className="text-sm text-text-secondary">Data detail tidak tersedia.</p>
                                </div>
                            ) : (() => {
                                const d = reviewModal.data;
                                const flags = [
                                    d.lokasi_perlu_review && { label: 'Lokasi Perlu Review', cls: 'bg-rose-50 text-rose-700 border-rose-200' },
                                    d.posisi_mencurigakan && { label: 'Posisi Mencurigakan', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
                                    d.motion_suspect && { label: 'Motion Suspect (emulator)', cls: 'bg-purple-50 text-purple-700 border-purple-200' },
                                ].filter(Boolean);

                                return (
                                    <div className="space-y-5">
                                        <div className="flex flex-wrap gap-2">
                                            {flags.length ? flags.map((f, i) => (
                                                <span key={i} className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold ${f.cls}`}>{f.label}</span>
                                            )) : <span className="text-xs text-text-secondary">Tidak ada flag aktif.</span>}
                                        </div>

                                        <div className="rounded-lg bg-surface p-4">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Bukti Foto (overlay nama • waktu • lokasi)</p>
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                {[{ label: 'Foto Masuk', url: d.foto_masuk_url, status: d.foto_masuk_status, error: d.foto_masuk_error, tone: 'bg-emerald-100 text-emerald-700' }, { label: 'Foto Keluar', url: d.foto_keluar_url, status: d.foto_keluar_status, error: d.foto_keluar_error, tone: 'bg-rose-100 text-rose-700' }].map((f, i) => (
                                                    <div key={i}>
                                                        {f.url ? (
                                                            <a href={f.url} target="_blank" rel="noopener noreferrer" className="block overflow-hidden rounded-lg border border-border transition-shadow hover:ring-2 hover:ring-primary" title={`${f.label} — buka di tab baru`}>
                                                                <img src={f.url} alt={f.label} className="aspect-[3/4] w-full object-cover" loading="lazy" />
                                                            </a>
                                                        ) : (
                                                            <div className="flex aspect-[3/4] w-full items-center justify-center rounded-lg border border-dashed border-border bg-white">
                                                                <span className="text-xs text-text-secondary">{f.label} tidak tersedia</span>
                                                            </div>
                                                        )}
                                                        <div className="mt-1.5 flex items-center justify-between px-0.5">
                                                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${f.tone}`}>{f.label}</span>
                                                            {f.status && f.status !== 'success' && <span className="text-[10px] font-semibold text-warning">{f.status}{f.error ? ` — ${f.error}` : ''}</span>}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            <p className="mt-2 text-[11px] leading-relaxed text-text-secondary">Foto di-burn-in nama, unit, waktu (HH:mm:ss) dan koordinat saat pengambilan. EXIF GPS (jika tersedia) juga dibandingkan di bawah.</p>
                                        </div>

                                        {(d.foto_kegiatan_urls || []).length > 0 && (
                                            <div className="rounded-lg bg-surface p-4">
                                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Foto Kegiatan ({d.foto_kegiatan_urls.length})</p>
                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                    {d.foto_kegiatan_urls.map((u, i) => (
                                                        <a key={i} href={u} target="_blank" rel="noopener noreferrer" className="block overflow-hidden rounded-lg border border-border transition-shadow hover:ring-2 hover:ring-primary" title="Buka di tab baru">
                                                            <img src={u} alt={`Bukti ${i + 1}`} className="aspect-[3/4] w-full object-cover" loading="lazy" />
                                                        </a>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        <div className="rounded-lg bg-surface p-4">
                                            <p className="mb-3 text-xs font-bold uppercase tracking-wide text-text-secondary">Data GPS</p>
                                            <div className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-text-secondary">Akurasi</span>
                                                    <b className="text-primary">{d.akurasi_masuk ?? '-'} m</b>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-text-secondary">Kecepatan</span>
                                                    <b className="text-primary">{d.kecepatan_masuk ?? '-'} m/s</b>
                                                </div>
                                                <div className="col-span-2 flex items-center justify-between gap-2">
                                                    <span className="text-text-secondary">Koordinat</span>
                                                    {d.latitude_masuk != null && d.longitude_masuk != null ? (
                                                        <a
                                                            href={`https://www.google.com/maps?q=${d.latitude_masuk},${d.longitude_masuk}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            title="Buka di Google Maps"
                                                            className="inline-flex items-center gap-1 font-mono text-primary underline decoration-dotted underline-offset-2 transition-colors hover:text-primary/80"
                                                        >
                                                            <MapPin className="h-3.5 w-3.5" />
                                                            {d.latitude_masuk}, {d.longitude_masuk}
                                                        </a>
                                                    ) : (
                                                        <b className="font-mono text-primary">-</b>
                                                    )}
                                                </div>
                                                {d.captured_at && (
                                                    <div className="col-span-2 flex items-center justify-between">
                                                        <span className="text-text-secondary">Waktu Capture</span>
                                                        <b className="text-primary">{format(new Date(d.captured_at), 'dd/MM/yyyy, HH.mm.ss', { locale: id })}</b>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <div className="rounded-lg bg-surface p-4">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Trajectory (Awal → A → B)</p>
                                            {d.trajectory?.length ? (
                                                <div className="space-y-2">
                                                    {d.trajectory.map((t, i) => (
                                                        <div key={i} className="flex items-center justify-between text-xs">
                                                            <span className="font-bold uppercase text-primary">{t.label || '?'}</span>
                                                            <span className="font-mono text-text-secondary">{t.lat}, {t.lng}</span>
                                                            <span className="text-text-secondary">akurasi {t.accuracy ?? '-'}m</span>
                                                            <span className="text-text-secondary">{t.captured_at ? new Date(t.captured_at).toLocaleTimeString('id-ID') : '-'}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : <p className="text-xs text-text-secondary">Tidak ada data trajectory (client lama / tidak didukung).</p>}
                                        </div>

                                        <div className="rounded-lg bg-surface p-4">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">Motion (Accelerometer)</p>
                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div><span className="text-text-secondary">Sample count:</span> <b className="text-primary">{d.motion_sample_count}</b></div>
                                                <div><span className="text-text-secondary">Varians:</span> <b className="text-primary">{d.motion_variance ?? '-'}</b></div>
                                            </div>
                                            {d.motion_suspect && <p className="mt-2 rounded-lg bg-purple-50 px-3 py-2 text-xs text-purple-600">Varians sangat rendah / nol — indikasi emulator atau device virtual.</p>}
                                        </div>

                                        {d.ip_geo && (
                                            <div className="rounded-lg bg-surface p-4">
                                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">IP Geolocation</p>
                                                <div className="grid grid-cols-2 gap-3 text-sm">
                                                    <div><span className="text-text-secondary">IP:</span> <b className="font-mono text-primary">{d.ip_geo.ip}</b></div>
                                                    <div><span className="text-text-secondary">Lokasi IP:</span> <b className="text-primary">{d.ip_geo.city ? `${d.ip_geo.city}, ${d.ip_geo.country}` : '-'}</b></div>
                                                    <div className="col-span-2"><span className="text-text-secondary">Jarak GPS vs IP:</span> <b className={d.ip_geo.distance_km > 500 ? 'text-danger' : 'text-primary'}>{d.ip_geo.distance_km ?? '-'} km</b></div>
                                                </div>
                                            </div>
                                        )}

                                        {d.exif_meta && (
                                            <div className="rounded-lg bg-surface p-4">
                                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-text-secondary">EXIF Foto</p>
                                                <div className="grid grid-cols-2 gap-3 text-sm">
                                                    <div><span className="text-text-secondary">EXIF GPS:</span> <b className="font-mono text-primary">{d.exif_meta.gps_lat ? `${d.exif_meta.gps_lat.toFixed(5)}, ${d.exif_meta.gps_lng?.toFixed(5)}` : '-'}</b></div>
                                                    <div><span className="text-text-secondary">DateTimeOriginal:</span> <b className="text-primary">{d.exif_meta.datetime_original || '-'}</b></div>
                                                    {d.exif_meta.mismatch && <div className="col-span-2"><span className="font-bold text-danger">⚠ Mismatch {d.exif_meta.mismatch_distance_m}m dengan koordinat reported</span></div>}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })()}
                        </div>
                    </Modal>

                    {/* ─── MODAL: Konfirmasi Ubah Status ─── */}
                    <Modal show={confirmStatus !== null} onClose={() => setConfirmStatus(null)} maxWidth="sm">
                        <div className="p-6">
                            <div className="mb-5 flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                    <AlertTriangle className="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold text-primary">Konfirmasi Ubah Status</h3>
                                    <p className="mt-0.5 text-sm text-text-secondary">Pastikan perubahan status presensi sudah sesuai.</p>
                                </div>
                            </div>
                            <div className="mb-6 rounded-lg bg-surface p-4">
                                <div className="flex items-center justify-between">
                                    <div className="text-center">
                                        <div className="mb-1 text-xs uppercase tracking-wide text-text-secondary">Status Saat Ini</div>
                                        <StatusBadge status={confirmStatus?.statusLama} />
                                    </div>
                                    <span className="mx-4 text-text-secondary">→</span>
                                    <div className="text-center">
                                        <div className="mb-1 text-xs uppercase tracking-wide text-text-secondary">Akan Diubah</div>
                                        <StatusBadge status={confirmStatus?.statusBaru} />
                                    </div>
                                </div>
                                {confirmStatus?.statusBaru === 'sakit' && (
                                    <div className="mt-4 border-t border-border pt-4">
                                        <label className="form-label text-xs">Persentase Bayar Jam Sakit</label>
                                        <div className="mt-1 flex gap-2">
                                            {[0, 50, 100].map((v) => (
                                                <button key={v} type="button"
                                                    onClick={() => setPersentaseBayar(v)}
                                                    className={`rounded-lg border px-4 py-2 text-sm font-semibold transition-colors ${persentaseBayar === v ? 'border-primary bg-primary text-white' : 'border-border bg-white text-text-secondary hover:bg-surface'}`}
                                                >{v}%</button>
                                            ))}
                                        </div>
                                        <p className="form-hint mt-1">Persentase gaji yang tetap dibayarkan untuk hari sakit.</p>
                                    </div>
                                )}
                            </div>
                            <div className="flex justify-end gap-3">
                                <button onClick={() => setConfirmStatus(null)} className="btn-secondary btn-sm min-w-[80px]">Batal</button>
                                <button
                                    onClick={() => {
                                        router.put(route('presensi.update', confirmStatus.id), { status: confirmStatus.statusBaru, persentase_bayar_jam: persentaseBayar }, { preserveState: false });
                                        setConfirmStatus(null);
                                    }}
                                    className="btn-primary btn-sm min-w-[100px]"
                                >
                                    Ya, Ubah
                                </button>
                            </div>
                        </div>
                    </Modal>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
