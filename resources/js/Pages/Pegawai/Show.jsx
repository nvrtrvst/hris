import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { kepagawaianBadge, STATUS_AKTIF_BADGE } from '@/Utils/statusMeta';
import Modal from '@/Components/Modal';
import {
    AlertTriangle,
    ArrowLeft,
    BadgeCheck,
    Banknote,
    Eye,
    EyeOff,
    Briefcase,
    Building2,
    CalendarDays,
    Download,
    FileCheck,
    FileText,
    GraduationCap,
    History,
    Loader2,
    Mail,
    MapPin,
    Paperclip,
    Pencil,
    Phone,
    Trash2,
    Upload,
    User,
    UserX,
    X,
} from 'lucide-react';

const InfoRow = ({ label, children }) => (
    <div>
        <p className="text-[11px] font-semibold uppercase tracking-wide text-text-muted">{label}</p>
        <div className="mt-0.5 text-sm font-medium text-text-primary">{children}</div>
    </div>
);

const SectionCard = ({ Icon, title, action, children }) => (
    <div className="card p-6">
        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h4 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                    <Icon className="h-4 w-4 text-primary" />
                </span>
                {title}
            </h4>
            {action}
        </div>
        {children}
    </div>
);

const JENIS_DOKUMEN = ['SK', 'Ijazah', 'KTP', 'Sertifikat', 'Kontrak', 'Surat Lain', 'Lainnya'];

const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '—');

export default function Show({ auth, pegawai, canViewKontrak = false }) {
    const { delete: destroy } = useForm();

    const handleDelete = () => {
        if (confirm('Apakah Anda yakin ingin menonaktifkan pegawai ini?')) {
            destroy(route('pegawai.destroy', pegawai.id), { data: { alasan_nonaktif: 'Dinonaktifkan oleh sistem' } });
        }
    };

    // ── NIK asli (khusus permission view_sensitive_data, via endpoint ber-audit) ──
    const canViewSensitive = auth.permissions?.includes('view_sensitive_data');
    const [nikRevealed, setNikRevealed] = useState(false);
    const [nikAsli, setNikAsli] = useState(null);
    const [nikLoading, setNikLoading] = useState(false);
    const [nikError, setNikError] = useState(null);

    const toggleNik = async () => {
        if (!canViewSensitive) return;
        if (nikAsli) {
            setNikRevealed((v) => !v);
            return;
        }
        setNikLoading(true);
        setNikError(null);
        try {
            const { data } = await window.axios.get(route('pegawai.nik-asli', pegawai.id));
            setNikAsli(data.nik);
            setNikRevealed(true);
        } catch {
            setNikError('Tidak dapat menampilkan NIK.');
        } finally {
            setNikLoading(false);
        }
    };

    // ── Upload dokumen ──
    const [showUpload, setShowUpload] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        nama_dokumen: '',
        jenis: 'SK',
        keterangan: '',
        file: null,
    });

    const submitDokumen = (e) => {
        e.preventDefault();
        post(route('pegawai.dokumen.store', pegawai.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowUpload(false);
            },
        });
    };

    const openUpload = () => {
        clearErrors();
        reset();
        setShowUpload(true);
    };

    const hapusDokumen = (d) => {
        if (confirm(`Hapus dokumen "${d.nama_dokumen || d.jenis || 'Dokumen'}"?`)) {
            destroy(route('pegawai.dokumen.destroy', [pegawai.id, d.id]));
        }
    };

    const kep = kepagawaianBadge(pegawai.status_kepegawaian);
    const isNonaktif = pegawai.status_aktif !== 'aktif';
    const kontrakSisa = pegawai.tanggal_akhir_kontrak
        ? Math.max(0, Math.ceil((new Date(pegawai.tanggal_akhir_kontrak) - new Date()) / (1000 * 60 * 60 * 24)))
        : null;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Detail Pegawai</h2>}
        >
            <Head title={`Detail Pegawai - ${pegawai.nama_lengkap}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Link href={route('pegawai.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Pegawai
                    </Link>

                    {/* Hero profile */}
                    <div className="card p-6">
                        <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-5">
                            <div className="flex items-start gap-5">
                                <div className="relative shrink-0">
                                    {pegawai.foto_url ? (
                                        <img className="h-20 w-20 rounded-full object-cover border-4 border-primary/10" src={pegawai.foto_url} alt="" />
                                    ) : (
                                        <span className="flex h-20 w-20 items-center justify-center rounded-full bg-primary/10 text-3xl font-extrabold text-primary">
                                            {pegawai.nama_lengkap.charAt(0)}
                                        </span>
                                    )}
                                    <span className={`absolute -bottom-1 -right-1 h-6 w-6 rounded-full border-4 border-white ${isNonaktif ? 'bg-gray-300' : 'bg-emerald-500'}`} title={isNonaktif ? 'Non-aktif' : 'Aktif'} />
                                </div>
                                <div className="min-w-0">
                                    <h3 className="text-2xl font-extrabold text-primary">{pegawai.nama_lengkap}</h3>
                                    <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-secondary">
                                        {pegawai.nik_masked && (
                                            <span className="inline-flex items-center gap-1">
                                                <User className="h-3 w-3" />
                                                NIK: {nikRevealed && nikAsli ? nikAsli : pegawai.nik_masked}
                                                {canViewSensitive && (
                                                    <button
                                                        type="button"
                                                        onClick={toggleNik}
                                                        disabled={nikLoading}
                                                        className="ml-0.5 inline-flex h-5 w-5 items-center justify-center rounded text-primary hover:bg-primary/10 disabled:opacity-50"
                                                        title={nikRevealed ? 'Sembunyikan NIK' : 'Tampilkan NIK asli'}
                                                        aria-label={nikRevealed ? 'Sembunyikan NIK' : 'Tampilkan NIK asli'}
                                                    >
                                                        {nikLoading ? (
                                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                        ) : nikRevealed ? (
                                                            <EyeOff className="h-3.5 w-3.5" />
                                                        ) : (
                                                            <Eye className="h-3.5 w-3.5" />
                                                        )}
                                                    </button>
                                                )}
                                            </span>
                                        )}
                                        {nikError && <span className="text-rose-600">{nikError}</span>}
                                        {pegawai.nip && <span className="inline-flex items-center gap-1"><BadgeCheck className="h-3 w-3" /> NIP: {pegawai.nip}</span>}
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">                                                    <span className={`inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide ${kep.badge}`}>
                                                        {kep.label}
                                                    </span>
                                                        <span className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide ${STATUS_AKTIF_BADGE(pegawai.status_aktif)}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${isNonaktif ? 'bg-gray-400' : 'bg-emerald-500'}`} />
                                            {pegawai.status_aktif}
                                        </span>
                                        {pegawai.wajib_kantor && (
                                            <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-700">
                                                <CalendarDays className="h-3 w-3" /> Wajib Kantor
                                            </span>
                                        )}
                                        {canViewKontrak && kontrakSisa !== null && (
                                            <span className={`inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold ${kontrakSisa <= 30 ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-border bg-surface text-text-secondary'}`}>
                                                Kontrak sisa {kontrakSisa} hari
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 shrink-0">
                                <Link href={route('pegawai.keuangan', pegawai.id)} className="btn-secondary btn-sm flex items-center gap-1.5">
                                    <Banknote className="h-3.5 w-3.5" /> Keuangan & Gaji
                                </Link>
                                <Link href={route('pegawai.edit', pegawai.id)} className="btn-secondary btn-sm flex items-center gap-1.5">
                                    <Pencil className="h-3.5 w-3.5" /> Edit Data
                                </Link>
                                <button type="button" onClick={handleDelete} className="btn-danger btn-sm flex items-center gap-1.5">
                                    <UserX className="h-3.5 w-3.5" /> Nonaktifkan
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Grid utama */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {/* Data Pribadi */}
                        <SectionCard Icon={User} title="Data Pribadi">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <InfoRow label="Tempat, Tanggal Lahir">
                                    {pegawai.tempat_lahir ? `${pegawai.tempat_lahir}, ${fmtDate(pegawai.tanggal_lahir)}` : fmtDate(pegawai.tanggal_lahir)}
                                </InfoRow>
                                <InfoRow label="Jenis Kelamin">{pegawai.jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan'}</InfoRow>
                                <InfoRow label="Agama & Status">{[pegawai.agama, pegawai.status_pernikahan].filter(Boolean).join(' • ') || '—'}</InfoRow>
                                <InfoRow label="Kontak">
                                    <span className="inline-flex items-center gap-1"><Phone className="h-3 w-3 text-primary" /> {pegawai.no_hp || '—'}</span>
                                </InfoRow>
                                <InfoRow label="Email">
                                    <span className="inline-flex items-center gap-1"><Mail className="h-3 w-3 text-primary" /> {pegawai.user?.email || '—'}</span>
                                </InfoRow>
                                <InfoRow label="Alamat">
                                    <span className="inline-flex items-start gap-1"><MapPin className="mt-0.5 h-3 w-3 shrink-0 text-primary" /> {pegawai.alamat_ktp || '—'}</span>
                                </InfoRow>
                            </div>

                            {/* Akun login mobile */}
                            <div className="mt-5 rounded-xl border border-info/30 bg-info-light p-4">
                                <p className="text-xs font-bold uppercase tracking-wide text-info">Akun Login Mobile</p>
                                {pegawai.user ? (
                                    <div className="mt-1.5 space-y-1 text-sm text-text-primary">
                                        <p><span className="font-semibold text-info">Email:</span> {pegawai.user.email}</p>
                                        <p><span className="font-semibold text-info">Username:</span> {pegawai.user.username || '-'}</p>
                                        <p className="text-[11px] text-text-muted">Login pakai email atau username di portal mobile.</p>
                                    </div>
                                ) : (
                                    <p className="mt-1.5 flex items-center gap-1.5 text-sm font-medium text-warning">
                                        <AlertTriangle className="h-4 w-4" /> Belum terhubung dengan akun login.
                                    </p>
                                )}
                            </div>
                        </SectionCard>

                        {/* Data Kepegawaian */}
                        <SectionCard Icon={Briefcase} title="Data Kepegawaian">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <InfoRow label="Pendidikan Terakhir">{pegawai.pendidikan_terakhir || '—'}</InfoRow>
                                <InfoRow label="Mulai Bekerja">{fmtDate(pegawai.tanggal_mulai_kerja)}</InfoRow>
                                <InfoRow label="Jatah Cuti Tahunan">{pegawai.jatah_cuti_tahunan ?? 12} hari</InfoRow>
                                {canViewKontrak && pegawai.tanggal_akhir_kontrak && <InfoRow label="Akhir Kontrak">{fmtDate(pegawai.tanggal_akhir_kontrak)}</InfoRow>}
                            </div>

                            {/* Unit & Jabatan */}
                            <div className="mt-5">
                                <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-text-muted">Unit & Jabatan</p>
                                {pegawai.units && pegawai.units.length > 0 ? (
                                    <div className="space-y-2">
                                        {pegawai.units.map((unit) => {
                                            const jabatan = pegawai.jabatans?.find((j) => j.pivot.unit_sekolah_id === unit.id)?.nama;
                                            const mapels = pegawai.mapels?.filter((m) => m.pivot.unit_sekolah_id === unit.id) || [];

                                            return (
                                                <div key={unit.id} className="rounded-xl border border-border bg-surface/50 p-3">
                                                    <div className="flex items-center gap-1.5 text-sm font-bold text-primary">
                                                        <Building2 className="h-3.5 w-3.5 text-primary/60" /> {unit.nama}
                                                        <span className="font-normal text-text-muted">— {jabatan || '-'}</span>
                                                    </div>
                                                    {mapels.length > 0 && (
                                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                                            {mapels.map((m) => (
                                                                <span key={m.id} className="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold text-blue-700">
                                                                    <GraduationCap className="h-3 w-3" /> {m.nama}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="text-sm text-text-secondary">—</p>
                                )}
                            </div>

                            {/* Sisa cuti */}
                            <div className="mt-5 flex items-center justify-between rounded-xl bg-primary p-4 text-white">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-white/70">Sisa Cuti Tahunan</p>
                                    <p className="mt-0.5 text-2xl font-extrabold tabular-nums">{pegawai.sisa_cuti ?? 0} <span className="text-sm font-semibold text-white/70">hari</span></p>
                                </div>
                                <CalendarDays className="h-8 w-8 text-accent" />
                            </div>

                            {pegawai.atasan_langsung && (
                                <div className="mt-5 flex items-center gap-3 rounded-xl border border-border bg-surface/50 p-3">
                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                                        <User className="h-4 w-4 text-primary" />
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-semibold uppercase tracking-wide text-text-muted">Atasan Langsung</p>
                                        <p className="text-sm font-bold text-primary">{pegawai.atasan_langsung.nama_lengkap}</p>
                                    </div>
                                </div>
                            )}

                            {pegawai.alasan_nonaktif && (
                                <div className="mt-5 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 p-4">
                                    <UserX className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                                    <div>
                                        <p className="text-xs font-bold uppercase tracking-wide text-rose-600">Alasan Nonaktif</p>
                                        <p className="mt-0.5 text-sm font-medium text-text-primary">{pegawai.alasan_nonaktif}</p>
                                    </div>
                                </div>
                            )}
                        </SectionCard>
                    </div>

                    {/* Dokumen */}
                    <SectionCard
                        Icon={Paperclip}
                        title={`Dokumen (${(pegawai.dokumen || []).length})`}
                        action={
                            <button type="button" onClick={openUpload} className="btn-primary btn-sm flex items-center gap-1.5">
                                <Upload className="h-3.5 w-3.5" /> Upload Dokumen
                            </button>
                        }
                    >
                        {pegawai.dokumen && pegawai.dokumen.length > 0 ? (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {pegawai.dokumen.map((d) => (
                                    <div key={d.id} className="group flex items-center gap-3 rounded-xl border border-border bg-surface/50 p-3 transition-colors hover:bg-surface">
                                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                            <FileCheck className="h-4 w-4 text-primary" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-sm font-bold text-primary">{d.nama_dokumen || 'Dokumen'}</div>
                                            <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-text-secondary">
                                                {d.jenis && (
                                                    <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-700">
                                                        {d.jenis}
                                                    </span>
                                                )}
                                                {d.keterangan && <span className="truncate">• {d.keterangan}</span>}
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                                            <a
                                                href={route('pegawai.dokumen.download', [pegawai.id, d.id])}
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-text-secondary transition-colors hover:bg-primary/10 hover:text-primary"
                                                title="Unduh"
                                            >
                                                <Download className="h-4 w-4" />
                                            </a>
                                            <button
                                                type="button"
                                                onClick={() => hapusDokumen(d)}
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-text-secondary transition-colors hover:bg-rose-50 hover:text-rose-600"
                                                title="Hapus"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center py-8 text-center">
                                <FileText className="mb-2 h-8 w-8 text-border" />
                                <p className="text-sm text-text-secondary">Belum ada dokumen terlampir.</p>
                                <p className="mt-1 text-xs text-text-muted">Unggah SK, ijazah, KTP, atau dokumen lain (PDF/gambar, maks 5MB).</p>
                            </div>
                        )}

                        {/* Modal upload */}
                        <Modal show={showUpload} onClose={() => setShowUpload(false)} maxWidth="md">
                            <form onSubmit={submitDokumen} className="p-6">
                                <div className="mb-5 flex items-center justify-between">
                                    <div>
                                        <h3 className="text-lg font-extrabold text-primary">Upload Dokumen</h3>
                                        <p className="text-xs text-text-muted">Untuk {pegawai.nama_lengkap} • PDF/gambar maks 5MB</p>
                                    </div>
                                    <button type="button" onClick={() => setShowUpload(false)} className="rounded-lg p-2 text-text-secondary transition-colors hover:bg-surface hover:text-text-primary">
                                        <X className="h-5 w-5" />
                                    </button>
                                </div>

                                <div className="space-y-4">
                                    <div>
                                        <label className="form-label text-xs">Nama Dokumen <span className="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            value={data.nama_dokumen}
                                            onChange={(e) => setData('nama_dokumen', e.target.value)}
                                            placeholder="cth. SK Pengangkatan 2024"
                                            className="input-field"
                                        />
                                        {errors.nama_dokumen && <p className="input-error">{errors.nama_dokumen}</p>}
                                    </div>

                                    <div>
                                        <label className="form-label text-xs">Jenis Dokumen <span className="text-danger">*</span></label>
                                        <select value={data.jenis} onChange={(e) => setData('jenis', e.target.value)} className="input-field">
                                            {JENIS_DOKUMEN.map((j) => (
                                                <option key={j} value={j}>{j}</option>
                                            ))}
                                        </select>
                                        {errors.jenis && <p className="input-error">{errors.jenis}</p>}
                                    </div>

                                    <div>
                                        <label className="form-label text-xs">File <span className="text-danger">*</span></label>
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            onChange={(e) => setData('file', e.target.files?.[0] || null)}
                                            className="input-field file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-primary hover:file:bg-primary/20"
                                        />
                                        {errors.file && <p className="input-error">{errors.file}</p>}
                                    </div>

                                    <div>
                                        <label className="form-label text-xs">Keterangan <span className="text-xs font-normal text-text-muted">(opsional)</span></label>
                                        <textarea
                                            value={data.keterangan}
                                            onChange={(e) => setData('keterangan', e.target.value)}
                                            rows="2"
                                            maxLength="500"
                                            className="input-field"
                                        />
                                        {errors.keterangan && <p className="input-error">{errors.keterangan}</p>}
                                    </div>
                                </div>

                                <div className="mt-6 flex items-center justify-end gap-2">
                                    <button type="button" onClick={() => setShowUpload(false)} className="btn-secondary btn-sm">Batal</button>
                                    <button type="submit" disabled={processing} className="btn-primary btn-sm flex items-center gap-1.5">
                                        {processing ? 'Mengunggah…' : (<><Upload className="h-3.5 w-3.5" /> Unggah</>)}
                                    </button>
                                </div>
                            </form>
                        </Modal>
                    </SectionCard>

                    {/* Riwayat perubahan */}
                    <SectionCard Icon={History} title="Riwayat Perubahan Data">
                        {pegawai.riwayat && pegawai.riwayat.length > 0 ? (
                            <div className="relative">
                                <div className="absolute bottom-2 left-[7px] top-2 w-0.5 bg-border/60" />
                                <div className="space-y-5">
                                    {pegawai.riwayat.map((riw) => (
                                        <div key={riw.id} className="flex gap-4">
                                            <div className="z-10 mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border-2 border-amber-300 bg-white">
                                                <div className="h-1.5 w-1.5 rounded-full bg-amber-400" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="rounded bg-amber-50 px-2 py-0.5 text-[11px] font-bold uppercase text-amber-700">
                                                        {(riw.jenis_perubahan || 'perubahan').replace(/_/g, ' ')}
                                                    </span>
                                                    <span className="text-[11px] text-text-secondary">
                                                        {new Date(riw.created_at).toLocaleString('id-ID')}
                                                    </span>
                                                </div>
                                                <p className="mt-1.5 text-sm text-text-secondary">
                                                    Dari: <span className="font-semibold text-text-primary">{riw.nilai_lama || '-'}</span>
                                                    {' '}→{' '}
                                                    Menjadi: <span className="font-semibold text-text-primary">{riw.nilai_baru || '-'}</span>
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center py-8 text-center">
                                <History className="mb-2 h-8 w-8 text-border" />
                                <p className="text-sm text-text-secondary">Belum ada riwayat perubahan.</p>
                            </div>
                        )}
                    </SectionCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
