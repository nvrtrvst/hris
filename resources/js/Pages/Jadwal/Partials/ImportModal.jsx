import React from 'react';
import Modal from '@/Components/Modal';
import { Button, Select } from '@/Components/AdminUI';
import { Download, Info, Loader2, Upload } from 'lucide-react';

export default function ImportModal({
    show,
    onClose,
    handleImportSubmit,
    importMode,
    setImportMode,
    importData,
    setImportData,
    importErrors,
    importProcessing,
    units,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={handleImportSubmit}>
                <div className="max-h-[90dvh] overflow-y-auto px-5 py-5 sm:px-6">
                    <div className="mb-4 flex items-center gap-3 border-b border-border pb-4">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-100">
                            <Upload className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <h3 className="page-title">Import Jadwal</h3>
                            <p className="page-subtitle">Upload massal sesuai format unit Anda.</p>
                        </div>
                    </div>

                    <div className="mb-4 inline-flex rounded-xl border border-border bg-surface p-1">
                        {[
                            { k: 'excel', label: 'Excel (Semua Unit)' },
                            { k: 'pdf', label: 'PDF (Format SMK)' },
                        ].map((m) => (
                            <button
                                key={m.k}
                                type="button"
                                onClick={() => setImportMode(m.k)}
                                className={`rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors ${importMode === m.k ? 'bg-primary text-white shadow-sm' : 'text-text-secondary hover:text-primary'}`}
                            >
                                {m.label}
                            </button>
                        ))}
                    </div>

                    <div className="space-y-4">
                        {importMode === 'excel' && (
                            <div className="space-y-2">
                                <a
                                    href={route('jadwal.template', importData.unit_sekolah_id ? { unit_sekolah_id: importData.unit_sekolah_id } : {})}
                                    className={`flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold ${importData.unit_sekolah_id ? 'border-info/30 bg-info-light text-info hover:bg-info/10' : 'border-border bg-surface text-text-secondary'}`}
                                >
                                    <Download className="h-4 w-4" /> Unduh Template Excel
                                </a>
                                {!importData.unit_sekolah_id && (
                                    <p className="text-xs text-amber-600">
                                        Pilih unit dulu sebelum unduh. Daftar guru dan durasi JP di template mengikuti unit.
                                    </p>
                                )}
                            </div>
                        )}

                        <div>
                            <label className="form-label mb-1">Unit Sekolah <span className="text-danger">*</span></label>
                            <Select
                                value={importData.unit_sekolah_id}
                                onChange={(e) => setImportData('unit_sekolah_id', e.target.value)}
                                required
                            >
                                <option value="">-- Pilih Unit --</option>
                                {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                            </Select>
                            {importErrors.unit_sekolah_id && <p className="form-error">{importErrors.unit_sekolah_id}</p>}
                        </div>

                        <div>
                            <label className="form-label mb-1">
                                {importMode === 'excel' ? 'File Excel (.xlsx/.xls/.csv)' : 'File PDF (.pdf)'} <span className="text-danger">*</span>
                            </label>
                            <input
                                type="file"
                                accept={importMode === 'excel' ? '.xlsx,.xls,.csv' : '.pdf'}
                                onChange={(e) => setImportData('file', e.target.files[0])}
                                className="mt-1 block w-full text-sm text-text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-100"
                                required
                            />
                            {importErrors.file && <p className="form-error">{importErrors.file}</p>}
                        </div>

                        {importMode === 'excel' && (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="form-label mb-1">Tahun Ajaran (opsional)</label>
                                    <input
                                        type="text"
                                        value={importData.tahun_ajaran}
                                        onChange={(e) => setImportData('tahun_ajaran', e.target.value)}
                                        placeholder="mis. 2026/2027"
                                        className="input-field"
                                    />
                                </div>
                                <div>
                                    <label className="form-label mb-1">Semester (opsional)</label>
                                    <Select
                                        value={importData.semester}
                                        onChange={(e) => setImportData('semester', e.target.value)}
                                    >
                                        <option value="">Default</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                    </Select>
                                </div>
                            </div>
                        )}

                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="import_delete_existing"
                                checked={importData.delete_existing}
                                onChange={(e) => setImportData('delete_existing', e.target.checked)}
                                className="h-4 w-4 rounded border-border text-primary focus:ring-primary/20"
                            />
                            <label htmlFor="import_delete_existing" className="text-sm text-text-secondary">
                                Hapus jadwal lama sebelum import (untuk periode yang sama)
                            </label>
                        </div>

                        <div className="flex items-start gap-2 rounded-xl border border-info/30 bg-info-light p-3 text-xs text-info">
                            <Info className="mt-0.5 h-4 w-4 shrink-0" />
                            <span>
                                {importMode === 'excel'
                                    ? 'Isi sesuai template: Hari, Kelas, Nama Guru, Mata Pelajaran, Jam Mulai, Jam Selesai, Jenis Jadwal, Tahun Ajaran, Semester. Guru dan mapel harus sudah terdaftar. Duplikat dan bentrok waktu dilewati/dilaporkan per baris.'
                                    : 'File PDF harus berupa jadwal pelajaran dengan format tabel SMK. Duplikat akan dilewati otomatis.'}
                            </span>
                        </div>

                        {importErrors.import && (
                            <div className="whitespace-pre-line rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{importErrors.import}</div>
                        )}
                    </div>

                    <div className="mt-4 flex justify-end gap-3 border-t border-border pt-4">
                        <Button type="button" variant="secondary" onClick={onClose} disabled={importProcessing}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={importProcessing || !importData.file || !importData.unit_sekolah_id}>
                            {importProcessing ? <><Loader2 className="h-4 w-4 animate-spin" /> Mengimport...</> : <><Upload className="h-4 w-4" /> Upload & Proses</>}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
