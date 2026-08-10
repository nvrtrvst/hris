import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ auth, pegawais, units, mapel }) {
    const [jumlahJp, setJumlahJp] = React.useState(1);
    const [apiKelas, setApiKelas] = React.useState([]);
    const { data, setData, post, processing, errors } = useForm({
        pegawai_id: '',
        unit_sekolah_id: '',
        kelas_label: '',
        mata_pelajaran_id: '',
        hari: 'Senin',
        jam_mulai: '',
        jam_selesai: '',
        jenis_jadwal: 'mengajar',
        tahun_ajaran: '2026/2027',
        semester: '1',
    });

    const selectedUnit = React.useMemo(() => units.find(u => u.id == data.unit_sekolah_id), [data.unit_sekolah_id, units]);
    const durasiJp = selectedUnit?.durasi_jp || 45;

    React.useEffect(() => {
        if (data.jenis_jadwal !== 'mengajar' || !data.unit_sekolah_id || !selectedUnit?.nama) { setApiKelas([]); return; }
        fetch(route('jadwal.kelas-by-unit', { q: selectedUnit.nama }))
            .then(r => r.json())
            .then(res => setApiKelas(res.data?.classes || []))
            .catch(() => setApiKelas([]));
    }, [data.unit_sekolah_id, data.jenis_jadwal]);

    React.useEffect(() => {
        if (!data.jam_mulai || !jumlahJp) return;
        const [h, m] = data.jam_mulai.split(':').map(Number);
        const totalMenit = h * 60 + m + jumlahJp * durasiJp;
        const jamSelesai = String(Math.floor(totalMenit / 60) % 24).padStart(2, '0') + ':' + String(totalMenit % 60).padStart(2, '0');
        setData('jam_selesai', jamSelesai);
    }, [data.jam_mulai, jumlahJp, durasiJp]);

    const submit = (e) => {
        e.preventDefault();
        post(route('jadwal.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Tambah Jadwal Pegawai</h2>}
        >
            <Head title="Tambah Jadwal" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('jadwal.index')} className="link inline-flex items-center text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Kembali ke Jadwal Mingguan
                        </Link>
                    </div>
                    <div className="page-card">
                        <div className="p-8">
                            
                            {errors.conflict && (
                                <div className="mb-6 bg-danger-light border-l-4 border-danger p-4 rounded-r-md">
                                    <div className="flex">
                                        <div className="flex-shrink-0">
                                            <svg className="h-5 w-5 text-danger" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <p className="text-sm text-danger font-medium">
                                                {errors.conflict}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <form onSubmit={submit} className="form-section space-y-6">
                                <div>
                                    <h3 className="section-title text-text-primary border-b border-border pb-2 mb-4">Informasi Pegawai & Unit</h3>
                                    <div className="form-grid">
                                        <div className="md:col-span-2">
                                            <label className="form-label">Pegawai <span className="text-red-500">*</span></label>
                                            <select value={data.pegawai_id} onChange={e => setData('pegawai_id', e.target.value)} className={`select-field ${errors.pegawai_id ? 'input-error' : ''}`}>
                                                <option value="">Pilih Pegawai</option>
                                                {pegawais.map(p => (
                                                    <option key={p.id} value={p.id}>{p.nama_lengkap}</option>
                                                ))}
                                            </select>
                                            {errors.pegawai_id && <p className="form-error">{errors.pegawai_id}</p>}
                                        </div>
                                        <div>
                                            <label className="form-label">Unit Sekolah <span className="text-red-500">*</span></label>
                                            <select value={data.unit_sekolah_id} onChange={e => setData('unit_sekolah_id', e.target.value)} className="select-field">
                                                <option value="">Pilih Unit</option>
                                                {units.map(u => (
                                                    <option key={u.id} value={u.id}>{u.nama}</option>
                                                ))}
                                            </select>
                                            {errors.unit_sekolah_id && <p className="form-error">{errors.unit_sekolah_id}</p>}
                                        </div>
                                        <div>
                                            <label className="form-label">Jenis Jadwal <span className="text-red-500">*</span></label>
                                            <select value={data.jenis_jadwal} onChange={e => setData('jenis_jadwal', e.target.value)} className="select-field">
                                                <option value="mengajar">Mengajar</option>
                                                <option value="piket">Piket</option>
                                                <option value="ekskul">Ekstrakurikuler</option>
                                                <option value="shift_satpam">Shift Satpam</option>
                                                <option value="shift_kebersihan">Shift Kebersihan</option>
                                                <option value="lainnya">Lainnya</option>
                                            </select>
                                            {errors.jenis_jadwal && <p className="form-error">{errors.jenis_jadwal}</p>}
                                        </div>
                                    </div>
                                </div>

                                {data.jenis_jadwal === 'mengajar' && (
                                    <div className="bg-primary-50 p-4 rounded-card border border-primary/10">
                                        <h3 className="section-title text-primary mb-4">Detail Mengajar</h3>
                                        <div className="form-grid">
                                            <div>
                                                <label className="form-label text-primary">Kelas</label>
                                                <select value={data.kelas_label} onChange={e => setData('kelas_label', e.target.value)} className="select-field">
                                                    <option value="">Pilih Kelas (Opsional)</option>
                                                    {apiKelas.map((k, i) => (
                                                        <option key={k.id || i} value={`${k.grade || k.tingkat || ''} - ${k.name || k.nama || ''}`}>{k.grade || k.tingkat || ''} - {k.name || k.nama || ''}</option>
                                                    ))}
                                                </select>
                                                {apiKelas.length === 0 && data.unit_sekolah_id && data.jenis_jadwal === 'mengajar' && (
                                                    <p className="form-hint text-warning mt-1">Data kelas tidak tersedia dari aplikasi keuangan.</p>
                                                )}
                                            </div>
                                            <div>
                                                <label className="form-label text-primary">Mata Pelajaran <span className="text-red-500">*</span></label>
                                                <select value={data.mata_pelajaran_id} onChange={e => setData('mata_pelajaran_id', e.target.value)} className={`select-field ${errors.mata_pelajaran_id ? 'input-error' : ''}`} required>
                                                    <option value="">Pilih Mapel</option>
                                                    {mapel.map(m => (
                                                        <option key={m.id} value={m.id}>{m.nama}</option>
                                                    ))}
                                                </select>
                                                {errors.mata_pelajaran_id && <p className="form-error">{errors.mata_pelajaran_id}</p>}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div>
                                    <h3 className="section-title text-text-primary border-b border-border pb-2 mb-4 mt-8">Waktu Pelaksanaan</h3>
                                    <div className="form-grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <label className="form-label">Hari <span className="text-red-500">*</span></label>
                                            <select value={data.hari} onChange={e => setData('hari', e.target.value)} className="select-field">
                                                {['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'].map(h => (
                                                    <option key={h} value={h}>{h}</option>
                                                ))}
                                            </select>
                                            {errors.hari && <p className="form-error">{errors.hari}</p>}
                                        </div>
                                        <div>
                                            <label className="form-label">Jam Mulai <span className="text-red-500">*</span></label>
                                            <input type="time" value={data.jam_mulai} onChange={e => setData('jam_mulai', e.target.value)} className="input-field" />
                                            {errors.jam_mulai && <p className="form-error">{errors.jam_mulai}</p>}
                                        </div>
                                        <div>
                                            <label className="form-label">Jumlah JP <span className="text-red-500">*</span></label>
                                            <select value={jumlahJp} onChange={e => setJumlahJp(Number(e.target.value))} className="select-field">
                                                {Array.from({ length: 10 }, (_, i) => i + 1).map(n => (
                                                    <option key={n} value={n}>{n} JP ({n * durasiJp} menit)</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="form-label">Jam Selesai</label>
                                            <input type="time" value={data.jam_selesai} onChange={e => setData('jam_selesai', e.target.value)} className="input-field" />
                                            <p className="form-hint">Otomatis dari JP & Durasi JP unit. Manual jika perlu override.</p>
                                            {errors.jam_selesai && <p className="form-error">{errors.jam_selesai}</p>}
                                        </div>
                                        
                                        <div>
                                            <label className="form-label">Tahun Ajaran <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.tahun_ajaran} onChange={e => setData('tahun_ajaran', e.target.value)} placeholder="Contoh: 2026/2027" className="input-field" />
                                            {errors.tahun_ajaran && <p className="form-error">{errors.tahun_ajaran}</p>}
                                        </div>
                                        <div>
                                            <label className="form-label">Semester <span className="text-red-500">*</span></label>
                                            <select value={data.semester} onChange={e => setData('semester', e.target.value)} className="select-field">
                                                <option value="1">1 (Ganjil)</option>
                                                <option value="2">2 (Genap)</option>
                                            </select>
                                            {errors.semester && <p className="form-error">{errors.semester}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-end mt-8 border-t border-border pt-6">
                                    <Link href={route('jadwal.index')} className="btn-secondary mr-6">Batal</Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="btn-primary"
                                    >
                                        {processing ? 'Menyimpan...' : 'Simpan Jadwal'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
