import React from 'react';
import { Card } from '@/Components/AdminUI';

function hariKerja(unit) {
    const tambahan = [];
    if (unit.jam_kerja_sabtu_mulai) tambahan.push('Sabtu');
    if (unit.jam_kerja_minggu_mulai) tambahan.push('Minggu');

    if (tambahan.length === 2) return 'Setiap hari';
    if (tambahan.length === 0) return 'Senin–Jumat';

    return `Senin–Jumat + ${tambahan[0]}`;
}

function SummaryRow({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4 py-2.5">
            <dt className="text-xs text-text-secondary">{label}</dt>
            <dd className="text-right text-xs font-semibold text-text-primary">{value}</dd>
        </div>
    );
}

export default function UnitSummaryCard({ unit, lokasiCount = 0, tembusanCount = 0 }) {
    const jamMasuk = unit.jam_masuk_kantor ? unit.jam_masuk_kantor.slice(0, 5) : null;
    const jamPulang = unit.jam_pulang_kantor ? unit.jam_pulang_kantor.slice(0, 5) : null;
    const jamKantor = jamMasuk && jamPulang ? `${jamMasuk}–${jamPulang}` : (jamMasuk ?? jamPulang ?? 'Belum diatur');

    return (
        <Card className="p-5">
            <h3 className="text-sm font-bold text-primary">Ringkasan Unit</h3>
            <p className="mt-1 text-xs text-text-secondary">Nilai pengaturan yang tersimpan.</p>

            <dl className="mt-3 divide-y divide-border">
                <SummaryRow label="Jam kantor" value={jamKantor} />
                <SummaryRow label="Hari kerja" value={hariKerja(unit)} />
                <SummaryRow label="Radius absen" value={`${unit.radius_meter ?? '-'} m`} />
                <SummaryRow label="Lokasi tambahan" value={lokasiCount} />
                <SummaryRow label="Jabatan tembusan" value={tembusanCount} />
            </dl>
        </Card>
    );
}
