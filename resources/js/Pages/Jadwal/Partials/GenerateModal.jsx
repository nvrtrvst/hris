import React from 'react';
import Modal from '@/Components/Modal';
import { Button, Input, Select } from '@/Components/AdminUI';
import { Info, Loader2, Sparkles } from 'lucide-react';

export default function GenerateModal({
    show,
    onClose,
    handleGenerate,
    genData,
    setGenData,
    units,
    selectedUnitPegCount,
    mapel,
    errors,
    generating,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="max-h-[90dvh] overflow-y-auto px-5 py-5 sm:px-6">
                <div className="mb-4 flex items-center gap-3 border-b border-border pb-4">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-warning-light">
                        <Sparkles className="h-5 w-5 text-warning" />
                    </div>
                    <div>
                        <h3 className="page-title">Generate Jadwal Otomatis</h3>
                        <p className="page-subtitle">Algoritma mengisi jadwal mengajar per pegawai tanpa bentrok</p>
                    </div>
                </div>

                <div className="mb-4 flex items-start gap-2 rounded-xl border border-warning/30 bg-warning-light p-3 text-xs text-warning">
                    <Info className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Jadwal yang <strong>sudah ada tidak akan dihapus</strong>. Generate hanya menambah jadwal baru jika tidak ada bentrok. Anda bisa generate berulang kali.</span>
                </div>

                <form onSubmit={handleGenerate} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="form-label mb-1">Unit Sekolah</label>
                            <Select
                                value={genData.unit_sekolah_id}
                                onChange={(e) => setGenData({ ...genData, unit_sekolah_id: e.target.value })}
                            >
                                <option value="">Semua Unit</option>
                                {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                            </Select>
                            {selectedUnitPegCount > 0 && (
                                <p className="mt-1 text-xs text-gray-500">≈ {selectedUnitPegCount} pegawai aktif</p>
                            )}
                        </div>
                        <div>
                            <label className="form-label mb-1">Semester</label>
                            <Select
                                value={genData.semester}
                                onChange={(e) => setGenData({ ...genData, semester: e.target.value })}
                            >
                                <option value="1">1 (Ganjil)</option>
                                <option value="2">2 (Genap)</option>
                            </Select>
                        </div>
                    </div>

                    <div>
                        <label className="form-label mb-1">Mata Pelajaran <span className="text-danger">*</span></label>
                        <Select
                            value={genData.mata_pelajaran_id}
                            onChange={(e) => setGenData({ ...genData, mata_pelajaran_id: e.target.value })}
                            className={errors?.mata_pelajaran_id ? 'border-rose-300' : ''}
                            required
                        >
                            <option value="">Pilih Mapel</option>
                            {mapel.map((m) => <option key={m.id} value={m.id}>{m.nama}</option>)}
                        </Select>
                        {errors?.mata_pelajaran_id && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.mata_pelajaran_id}</p>}
                    </div>

                    <div>
                        <label className="form-label mb-1">Tahun Ajaran</label>
                        <Select
                            value={genData.tahun_ajaran}
                            onChange={(e) => setGenData({ ...genData, tahun_ajaran: e.target.value })}
                        >
                            {['2024/2025', '2025/2026', '2026/2027', '2027/2028', '2028/2029'].map((ta) => (
                                <option key={ta} value={ta}>{ta}</option>
                            ))}
                        </Select>
                    </div>

                    <div>
                        <label className="form-label mb-1">Rentang Jam</label>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="relative">
                                <span className="absolute -top-2 left-3 bg-white px-1 text-[11px] text-text-muted">Mulai</span>
                                <Input
                                    type="time"
                                    value={genData.waktu_mulai}
                                    onChange={(e) => setGenData({ ...genData, waktu_mulai: e.target.value })}
                                    className="pt-2"
                                />
                            </div>
                            <div className="relative">
                                <span className="absolute -top-2 left-3 bg-white px-1 text-[11px] text-text-muted">Selesai</span>
                                <Input
                                    type="time"
                                    value={genData.waktu_selesai}
                                    onChange={(e) => setGenData({ ...genData, waktu_selesai: e.target.value })}
                                    className="pt-2"
                                />
                            </div>
                        </div>
                        <p className="form-hint">Blok waktu default: 07:00-09:00, 09:30-11:30, 13:00-15:00. Filter ini mempersempit blok yang digunakan.</p>
                    </div>

                    <div className="flex justify-end gap-3 border-t border-border pt-4">
                        <Button type="button" variant="secondary" onClick={onClose} disabled={generating}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={generating} className="bg-accent text-primary-800 hover:bg-yellow-500">
                            {generating ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Memproses...
                                </>
                            ) : 'Generate Jadwal'}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
