import React, { useEffect, useState } from 'react';
import { Card, LemburStatusBadge, Toggle } from '@/Components/MobileUI';

const toMinutes = (value) => {
    if (!value) return null;
    const [hour, minute] = String(value).split(':').map(Number);
    if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
    return hour * 60 + minute;
};

export default function LemburModeSlot({ lemburWindow, record = null, active = false, onToggle, className = '' }) {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = setInterval(() => setNow(new Date()), 30000);
        return () => clearInterval(timer);
    }, []);

    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    const batasMinutes = toMinutes(lemburWindow?.batas_akhir);
    const available = Boolean(record) || Boolean(lemburWindow?.tersedia) || (batasMinutes !== null && nowMinutes >= batasMinutes);

    if (record) {
        const open = Boolean(record.jam_masuk && !record.jam_keluar);

        return (
            <Card press={false} className={`flex min-h-24 items-center justify-between py-3.5 ${className}`}>
                <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-900">{open ? 'Lembur berlangsung' : 'Lembur selesai'}</p>
                    <p className="mt-0.5 text-xs text-slate-500">Menunggu approval admin, masuk hitungan payroll</p>
                </div>
                <LemburStatusBadge status={record.lembur_status} className="ml-3 shrink-0" />
            </Card>
        );
    }

    const note = lemburWindow?.batas_akhir
        ? `Tersedia setelah ${lemburWindow.batas_akhir}${lemburWindow.unit_terakhir ? ` (${lemburWindow.unit_terakhir})` : ''}.`
        : (lemburWindow?.alasan || 'Jam kerja unit belum lengkap. Hubungi admin.');

    return (
        <Card press={false} className={`flex min-h-24 items-center justify-between py-3.5 ${className}`}>
            <div className="min-w-0">
                <p className="text-sm font-bold text-slate-900">Mode lembur</p>
                {available ? (
                    <p className="mt-0.5 text-xs text-slate-500">Di luar jam kerja - perlu persetujuan admin.</p>
                ) : (
                    <p role="note" className="mt-0.5 text-xs leading-snug text-slate-500">{note}</p>
                )}
            </div>

            {available && (
                <span className="ml-3 shrink-0">
                    <Toggle
                        checked={active}
                        onChange={onToggle}
                        tone="amber"
                        aria-label="Aktifkan mode lembur"
                    />
                </span>
            )}
        </Card>
    );
}
