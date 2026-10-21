import { useMemo, useState } from 'react';
import { CalendarDays, ChevronDown, ChevronUp } from 'lucide-react';

/**
 * Detail Presensi Harian (informatif): kelompok per tanggal (accordion
 * dengan ringkas hitungan), di dalamnya kartu per pegawai: masuk kantor
 * (jam + Tepat/Telat), pulang kantor, dan chip aktivitas per sesi.
 */
const NEUTRAL = 'bg-surface text-text-secondary ring-1 ring-border';

const MASUK_PILL = {
    telat: 'bg-warning-light text-warning ring-1 ring-warning/30',
    hadir: 'bg-success-light text-success ring-1 ring-success/30',
    alpa: 'bg-danger-light text-danger ring-1 ring-danger/30',
    izin: 'bg-info-light text-info ring-1 ring-info/30',
    sakit: 'bg-info-light text-info ring-1 ring-info/30',
    cuti: 'bg-info-light text-info ring-1 ring-info/30',
    libur: NEUTRAL,
};

const CHIP_STYLE = {
    ok: 'bg-success-light text-success ring-1 ring-success/30',
    telat: 'bg-warning-light text-warning ring-1 ring-warning/30',
    miss: 'bg-danger-light text-danger ring-1 ring-danger/30',
    izin: 'bg-info-light text-info ring-1 ring-info/30',
    late: 'bg-warning-light text-warning ring-1 ring-warning/30',
    '': NEUTRAL,
};

const CHIP_MARK = { ok: '✓', telat: 'T', miss: '✗', izin: 'I', late: '•', '': '·' };

const masukLabel = (row) => {
    const jam = row.masuk_jam ? ' ' + row.masuk_jam : '';
    switch (row.masuk_status) {
        case 'hadir': return 'Tepat' + jam;
        case 'telat': return 'Telat' + jam;
        case 'sakit': return 'Sakit';
        case 'izin': return 'Izin';
        case 'cuti': return 'Cuti';
        case 'alpa': return 'Alpa';
        case 'libur': return 'Libur';
        default: return 'Tidak terekam';
    }
};

const pulangLabel = (row) => {
    if (row.pulang_state === 'tercatat') return row.pulang_jam;
    if (row.pulang_state === 'belum') return 'Tidak Presensi Pulang';
    return '-';
};

const jenisLabel = { mengajar: 'Mengajar', piket: 'Piket', lembur: 'Lembur', tugas_luar: 'Tugas Luar' };

const chipLabel = (c) => (jenisLabel[c.jenis] || c.label) + (c.jam ? ' ' + c.jam : '');

export default function HarianDetail({ days, summary }) {
    const groups = useMemo(() => {
        if (!Array.isArray(days) || days.length === 0) return [];
        const map = new Map();
        for (const row of days) {
            if (!map.has(row.tanggal)) map.set(row.tanggal, { tanggal: row.tanggal, hari: row.hari, rows: [] });
            map.get(row.tanggal).rows.push(row);
        }
        return [...map.values()]
            .map((g) => ({ ...g, rows: g.rows.sort((a, b) => a.nama.localeCompare(b.nama)) }))
            .sort((a, b) => a.tanggal.localeCompare(b.tanggal));
    }, [days]);

    const [openMap, setOpenMap] = useState({});
    const isOpen = (tanggal, idx) => (tanggal in openMap) ? Boolean(openMap[tanggal]) : idx === 0;
    const toggle = (tanggal) => setOpenMap((prev) => {
        const gi = groups.findIndex((g) => g.tanggal === tanggal);
        const isCurrentOpen = (tanggal in prev) ? Boolean(prev[tanggal]) : gi === 0;

        return { ...prev, [tanggal]: !isCurrentOpen };
    });

    if (groups.length === 0) {
        return (
            <div role="status" className="rounded-xl border border-border bg-surface py-12 text-center">
                <p className="text-sm font-medium text-text-primary">Tidak ada data presensi.</p>
                <p className="mt-1 text-xs text-text-muted">Perkecil periode, ubah unit, atau kosongkan filter untuk menampilkan data.</p>
            </div>
        );
    }

    return (
        <div className="space-y-3" role="list" aria-label="Detail presensi harian">
            {groups.map((g, gi) => (
                <DaySection
                    key={g.tanggal}
                    day={g}
                    open={isOpen(g.tanggal, gi)}
                    onToggle={() => toggle(g.tanggal)}
                />
            ))}
            {summary && <SummaryFooter summary={summary} />}
        </div>
    );
}

function DaySection({ day, open, onToggle }) {
    const telat = day.rows.filter((r) => r.masuk_status === 'telat').length;
    const belum = day.rows.filter((r) => r.pulang_state === 'belum').length;
    const takSlide = day.rows.reduce((acc, r) => acc + r.aktivitas.filter((c) => c.state === 'miss').length, 0);

    return (
        <div className="card overflow-hidden" role="listitem">
            <button
                type="button"
                aria-expanded={open}
                onClick={onToggle}
                className="flex w-full flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-left hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <span className="flex items-center gap-2 text-sm font-semibold text-text-primary">
                    <CalendarDays className="h-4 w-4 text-text-muted" />
                    {day.hari}, {day.tanggal}
                </span>
                {!open && (
                    <span className="flex flex-wrap items-center gap-2 text-xs">
                        <span className="rounded-full bg-warning-light px-2 py-0.5 font-medium text-warning">Telat {telat}</span>
                        <span className="rounded-full bg-danger-light px-2 py-0.5 font-medium text-danger">Belum pulang {belum}</span>
                        <span className="rounded-full bg-danger-light px-2 py-0.5 font-medium text-danger">Tak slide {takSlide}</span>
                        <span className="text-text-muted">{day.rows.length} pegawai</span>
                    </span>
                )}
                <span className="text-text-muted">{open ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}</span>
            </button>
            {open && (
                <div className="divide-y divide-border border-t border-border">
                    {day.rows.map((row) => <PegawaiRow key={row.pegawai_id} row={row} />)}
                </div>
            )}
        </div>
    );
}

function PegawaiRow({ row }) {
    return (
        <div className="flex flex-col gap-1.5 px-4 py-2.5 md:flex-row md:items-start md:gap-4">
            <div className="md:w-56 md:shrink-0">
                <p className="text-sm font-medium text-text-primary">{row.nama}</p>
                <p className="text-xs text-text-muted">{row.unit}</p>
            </div>
            <div className="flex flex-1 flex-col gap-1.5">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                    <span className={`rounded-full px-2 py-0.5 font-semibold ${MASUK_PILL[row.masuk_status] || NEUTRAL}`}>
                        {masukLabel(row)}
                    </span>
                    <span className="text-text-secondary">
                        Pulang: <span className={row.pulang_state === 'belum' ? 'font-semibold text-warning' : 'text-text-primary'}>{pulangLabel(row)}</span>
                    </span>
                </div>
                {row.aktivitas.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {row.aktivitas.map((c, i) => (
                            <span
                                key={`${c.jenis}-${i}`}
                                className={`rounded-full px-2 py-0.5 text-xs font-medium ${CHIP_STYLE[c.state] || NEUTRAL}`}
                            >
                                {CHIP_MARK[c.state] || '·'} {chipLabel(c)}
                            </span>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function SummaryFooter({ summary }) {
    return (
        <div className="flex flex-wrap gap-x-6 gap-y-1 rounded-xl border border-border bg-surface px-4 py-2.5 text-xs text-text-secondary">
            <span>Telat: <b>{summary.telat}</b></span>
            <span>Belum pulang: <b>{summary.belumPulang}</b></span>
            <span>Tak slide: <b>{summary.takSlide}</b></span>
            <span>Izin/Sakit/Cuti: <b>{summary.izinCount}</b></span>
        </div>
    );
}
