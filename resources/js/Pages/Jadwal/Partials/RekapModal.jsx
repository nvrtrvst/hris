import React from 'react';
import Modal from '@/Components/Modal';
import { CalendarRange } from 'lucide-react';
import { durasiMenit, fmtDurasi } from '@/Pages/Jadwal/Partials/jadwalMeta';

export default function RekapModal({ show, onClose, jadwals }) {
    const grouped = {};
    jadwals.filter((j) => j.kelas_label && j.jenis_jadwal === 'mengajar').forEach((j) => {
        if (!grouped[j.kelas_label]) grouped[j.kelas_label] = {};
        const mapelId = j.mata_pelajaran?.id || 0;
        const mapelName = j.mata_pelajaran?.nama || 'Tanpa Mapel';
        if (!grouped[j.kelas_label][mapelId]) {
            grouped[j.kelas_label][mapelId] = { nama: mapelName, guru: {} };
        }
        const pegNama = j.pegawai?.nama_lengkap || 'Tanpa Nama';
        const durasi = durasiMenit(j.jam_mulai, j.jam_selesai);
        grouped[j.kelas_label][mapelId].guru[pegNama] = (grouped[j.kelas_label][mapelId].guru[pegNama] || 0) + durasi;
    });

    const entries = Object.entries(grouped).sort((a, b) => a[0].localeCompare(b[0]));

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <div className="max-h-[90vh] overflow-y-auto px-6 py-5">
                <div className="mb-5 flex items-center gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <CalendarRange className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-primary">Rekap Kelas</h3>
                        <p className="text-sm text-text-secondary">Mapel, guru, dan total jam per kelas.</p>
                    </div>
                </div>

                {entries.length === 0 ? (
                    <div className="p-6 text-center text-text-muted">Belum ada jadwal mengajar dengan kelas.</div>
                ) : (
                    <div className="space-y-6">
                        {entries.map(([kelas, mapels]) => {
                            const mapelEntries = Object.entries(mapels);
                            const totalJam = mapelEntries.reduce((sum, [, m]) => sum + Object.values(m.guru).reduce((a, b) => a + b, 0), 0);

                            return (
                                <div key={kelas} className="overflow-hidden rounded-card border border-border bg-surface">
                                    <div className="flex items-center justify-between border-b border-border bg-primary/5 px-4 py-3">
                                        <h4 className="text-sm font-bold text-primary">Kls {kelas}</h4>
                                        <span className="text-xs text-text-muted">{fmtDurasi(totalJam)} / {mapelEntries.length} mapel</span>
                                    </div>
                                    <div className="divide-y divide-border">
                                        {mapelEntries.sort((a, b) => a[1].nama.localeCompare(b[1].nama)).map(([mapelId, m]) => (
                                            <div key={mapelId} className="px-4 py-2.5">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium text-text-primary">{m.nama}</span>
                                                    <span className="text-xs text-text-muted">{fmtDurasi(Object.values(m.guru).reduce((a, b) => a + b, 0))}</span>
                                                </div>
                                                <div className="mt-1 flex flex-wrap gap-1.5">
                                                    {Object.entries(m.guru).sort((a, b) => b[1] - a[1]).map(([guru, menit]) => (
                                                        <span key={guru} className="inline-flex items-center gap-1 rounded-full border border-border bg-white px-2 py-0.5 text-xs">
                                                            <span className="text-text-primary">{guru}</span>
                                                            <span className="text-text-muted">{menit}m</span>
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <div className="mt-6 flex justify-end border-t border-border pt-4">
                    <button type="button" onClick={onClose} className="btn-secondary">
                        Tutup
                    </button>
                </div>
            </div>
        </Modal>
    );
}
