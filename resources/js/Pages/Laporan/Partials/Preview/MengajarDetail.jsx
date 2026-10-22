import { useMemo, useState } from 'react';
import { CalendarDays, ChevronDown, ChevronUp } from 'lucide-react';

/**
 * Detail Mengajar Harian (informatif): kelompok per tanggal (accordion dgn
 * ringkas hitungan jam), kartu per pegawai: ringkasan Seharusnya/Masuk/Kurang
 * + chip per sesi mengajar/piket beserta JP & selisih menit.
 */
const STATE_STYLE = {
    ok: 'bg-success-light text-success ring-1 ring-success/30',
    telat: 'bg-warning-light text-warning ring-1 ring-warning/30',
    miss: 'bg-danger-light text-danger ring-1 ring-danger/30',
    izin: 'bg-info-light text-info ring-1 ring-info/30',
    '': 'bg-surface text-text-secondary ring-1 ring-border',
};

const STATE_MARK = { ok: '✓', telat: 'T', miss: '✗', izin: 'I', '': '·' };

const STATE_LABEL = { ok: 'Hadir', telat: 'Telat', miss: 'Tidak Presensi', izin: 'Izin', '': 'Belum Mulai' };

const JENIS_LABEL = { mengajar: 'Mengajar', piket: 'Piket' };

export default function MengajarDetail({ days, summary }) {
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
                <p className="text-sm font-medium text-text-primary">Tidak ada jadwal mengajar/piket pada rentang ini.</p>
                <p className="mt-1 text-xs text-text-muted">Perkecil periode, ubah unit, atau kosongkan filter untuk menampilkan data.</p>
            </div>
        );
    }

    return (
        <div className="space-y-3" role="list" aria-label="Detail mengajar harian">
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
    const telatSeSesi = day.rows.reduce((acc, r) => acc + r.sesi.filter((s) => s.state === 'telat').length, 0);
    const missSesi = day.rows.reduce((acc, r) => acc + r.sesi.filter((s) => s.state === 'miss').length, 0);
    const izinHari = day.rows.filter((r) => r.izin).length;

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
                        <span className="rounded-full bg-warning-light px-2 py-0.5 font-medium text-warning">Telat {telatSeSesi} sesi</span>
                        <span className="rounded-full bg-danger-light px-2 py-0.5 font-medium text-danger">Tidak presensi {missSesi} sesi</span>
                        <span className="rounded-full bg-info-light px-2 py-0.5 font-medium text-info">Izin {izinHari} pegawai</span>
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
    const sesiAktif = row.sesi.filter((s) => ['ok', 'telat', 'miss'].includes(s.state));

    return (
        <div className="flex flex-col gap-2 px-4 py-3">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <div className="flex items-baseline gap-3">
                    <span className="text-sm font-semibold text-text-primary">{row.nama}</span>
                    <span className="text-xs text-text-muted">{row.unit}</span>
                    {row.izin && (
                        <span className="rounded-full bg-info-light px-2 py-0.5 text-xs font-semibold text-info ring-1 ring-info/30">
                            {row.izin.charAt(0).toUpperCase() + row.izin.slice(1)}
                        </span>
                    )}
                </div>
                <div className="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 text-xs tabular-nums">
                    <span className="text-text-secondary">Seharusnya: <b className="text-text-primary">{row.planned_text}</b></span>
                    <span className="text-text-secondary">Masuk: <b className={row.masuk_text === '-' ? 'text-text-muted' : 'text-success'}>{row.masuk_text}</b></span>
                    <span className="text-text-secondary">Kurang: <b className={row.kurang_text === '-' ? 'text-text-muted' : 'text-danger'}>{row.kurang_text}</b></span>
                </div>
            </div>
            <div className="flex flex-wrap gap-1.5">
                {row.sesi.map((s, i) => (
                    <span
                        key={`${s.jenis}-${i}`}
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATE_STYLE[s.state] || 'bg-surface text-text-secondary ring-1 ring-border'}`}
                        title={[s.label, s.jam, `${s.planned_text} seharusnya`, s.masuk_text !== '-' ? `masuk ${s.masuk_text}` : null, s.kurang_text !== '-' ? `kurang ${s.kurang_text}` : null]
                            .filter(Boolean).join(' | ')}
                    >
                        {STATE_MARK[s.state] || '·'} {JENIS_LABEL[s.jenis] || s.label}
                        {s.jam ? ` ${s.jam}` : ''} - {STATE_LABEL[s.state]}
                        {s.kurang_text && s.kurang_text !== '-' && (s.state === 'telat' || s.state === 'miss') ? ` (${s.kurang_text})` : ''}
                    </span>
                ))}
                {sesiAktif.length === 0 && row.sesi.length > 0 && (
                    <span className="text-xs text-text-muted">Semua sesi belum mulai / izin.</span>
                )}
            </div>
        </div>
    );
}

function SummaryFooter({ summary }) {
    return (
        <div className="flex flex-wrap gap-x-6 gap-y-1 rounded-xl border border-border bg-surface px-4 py-2.5 text-xs text-text-secondary">
            <span>Seharusnya: <b>{summary.plannedJp} JP</b></span>
            <span>Masuk: <b>{summary.masukJp} JP</b></span>
            <span className="text-danger">Kurang: <b>{summary.kurangJp} JP</b> (kurang {summary.kurangMin}m total)</span>
            <span>Telat: <b className="text-warning">{summary.sesiTelat}</b> sesi</span>
            <span className="text-danger">Tidak presensi: <b>{summary.sesiMiss}</b> sesi</span>
            <span>Izin: <b>{summary.izinCount}</b> hari</span>
        </div>
    );
}
