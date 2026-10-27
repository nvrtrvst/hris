import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge, Empty } from '@/Components/MobileUI';
import useNowEveryMinute from '@/Utils/useNowEveryMinute';
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    DoorOpen,
    Lock,
    Send,
    X,
} from 'lucide-react';

// Endpoint JSON bisa membalas HTML saat sesi habis atau server error;
// jangan biarkan .json() melempar "Unexpected token '<'".
async function bacaJson(response, pesanGagal) {
    const tipe = response.headers.get('content-type') || '';
    if (!tipe.includes('application/json')) {
        throw new Error(pesanGagal);
    }

    const data = await response.json();
    if (!response.ok || !data.success) {
        throw new Error(data.message || pesanGagal);
    }

    return data;
}

const getPos = () =>
    new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('GPS tidak tersedia di perangkat ini.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy ?? 0 }),
            () => reject(new Error('GPS tidak terdeteksi. Pastikan GPS aktif dan coba di tempat terbuka.')),
            { enableHighAccuracy: true, timeout: 15000 }
        );
    });

const inputCls = 'block w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-base focus:border-emerald-500 focus:ring-emerald-500';
const labelCls = 'mb-1 block text-sm font-semibold text-slate-700';

function SeksiHead({ icon: Icon, title, win, state }) {
    return (
        <div className="mb-3 flex items-center justify-between gap-3">
            <div className="flex items-center gap-2">
                <Icon className="h-5 w-5 text-primary" />
                <h2 className="text-base font-bold text-slate-900">{title}</h2>
            </div>
            {state === 'done' ? (
                <Badge tone="emerald" icon={CheckCircle2}>Selesai</Badge>
            ) : state === 'open' ? (
                <Badge tone="sky">Dibuka {win[0]}-{win[1]}</Badge>
            ) : (
                <Badge tone="slate" icon={Lock}>Dibuka {win[0]}</Badge>
            )}
        </div>
    );
}

const STATUS_SISWA = {
    hadir: { label: 'H', cls: 'bg-emerald-500 text-white border-emerald-500' },
    telat: { label: 'T', cls: 'bg-amber-500 text-white border-amber-500' },
    izin: { label: 'I', cls: 'bg-sky-500 text-white border-sky-500' },
    sakit: { label: 'S', cls: 'bg-purple-500 text-white border-purple-500' },
    alpa: { label: 'A', cls: 'bg-rose-500 text-white border-rose-500' },
};
const URUT_STATUS = ['hadir', 'telat', 'izin', 'sakit', 'alpa'];

const labelKelas = (c) => `${c.grade ?? ''} ${c.name ?? ''}${c.section ? ` ${c.section}` : ''}`.trim();

// Ringkas status tersimpan: "H 20 · I 1 · S 2".
const ringkasStatus = (details = []) => {
    const hitung = details.reduce((acc, d) => ({ ...acc, [d.status]: (acc[d.status] ?? 0) + 1 }), {});

    return URUT_STATUS.filter((s) => hitung[s]).map((s) => `${STATUS_SISWA[s].label} ${hitung[s]}`).join(' · ');
};

function AbsenSiswaCard({ jadwalId, sesiTersimpan = [], doneItem, boleh, submitted }) {
    const [daftarKelas, setDaftarKelas] = useState([]);
    const [loadingKelas, setLoadingKelas] = useState(false);
    const [selected, setSelected] = useState(null);
    const [siswa, setSiswa] = useState([]);
    const [loadingSiswa, setLoadingSiswa] = useState(false);
    const [saving, setSaving] = useState(false);
    const [err, setErr] = useState('');
    const [cariKelas, setCariKelas] = useState('');
    const [cariSiswa, setCariSiswa] = useState('');
    const [lastSaved, setLastSaved] = useState(null);
    const [konfirmasi, setKonfirmasi] = useState(null);
    const [alasan, setAlasan] = useState('');

    const muatKelas = (force = false) => {
        if (loadingKelas) return;
        if (! force && daftarKelas.length > 0) return;
        setLoadingKelas(true);
        setErr('');
        fetch(`${route('presensi.jadwal.kelas')}?jadwal_id=${encodeURIComponent(jadwalId)}`)
            .then((r) => bacaJson(r, 'Gagal memuat daftar kelas. Coba lagi.'))
            .then((d) => setDaftarKelas(d.kelas || []))
            .catch((e) => setErr(e.message))
            .finally(() => setLoadingKelas(false));
    };

    const pilihKelas = (c) => {
        setSelected(c);
        setSiswa([]);
        setLoadingSiswa(true);
        setErr('');
        const params = new URLSearchParams({
            jadwal_id: String(jadwalId),
            tingkat: String(c.grade ?? ''),
            kelas: c.name ?? '',
            jurusan: c.major_name ?? '',
            class_id: c.id ?? '',
        });
        const tersimpan = sesiTersimpan.find((s) => String(s.class_id) === String(c.id));
        setLastSaved(tersimpan ?? null);
        setKonfirmasi(null);
        setAlasan('');
        setCariSiswa('');
        const sebelumnya = new Map((tersimpan?.details ?? []).map((d) => [d.nis, d]));

        fetch(`${route('presensi.jadwal.siswa')}?${params.toString()}`)
            .then((r) => bacaJson(r, 'Gagal memuat data siswa. Coba lagi.'))
            .then((d) => setSiswa((d.siswa || []).map((s) => ({
                nis: s.nis,
                nama: s.nama,
                status: sebelumnya.get(s.nis)?.status ?? 'hadir',
                keterangan: sebelumnya.get(s.nis)?.keterangan ?? '',
            }))))
            .catch((e) => setErr(e.message))
            .finally(() => setLoadingSiswa(false));
    };

    const ubahStatus = (nis, status) => {
        setSiswa((prev) => prev.map((s) => (s.nis === nis ? { ...s, status } : s)));
    };

    const ubahKeterangan = (nis, keterangan) => {
        setSiswa((prev) => prev.map((s) => (s.nis === nis ? { ...s, keterangan } : s)));
    };

    const semuaHadir = () => setSiswa((prev) => prev.map((s) => ({ ...s, status: 'hadir', keterangan: '' })));

    const hitung = () => siswa.reduce((acc, s) => {
        acc[s.status] = (acc[s.status] ?? 0) + 1;

        return acc;
    }, { hadir: 0, telat: 0, izin: 0, sakit: 0, alpa: 0 });

    const siapkanSimpan = () => {
        const tanpaAlasan = siswa.find((s) => ['izin', 'sakit'].includes(s.status) && !s.keterangan.trim());
        if (tanpaAlasan) {
            setErr(`Keterangan wajib untuk ${tanpaAlasan.nama} (${tanpaAlasan.status}).`);
            return;
        }

        setErr('');
        setKonfirmasi(hitung());
    };

    const kirim = async () => {
        if (lastSaved && alasan.trim().length < 5) {
            setErr('Isi alasan perubahan (min. 5 karakter) karena kelas sudah pernah diisi.');
            return;
        }

        setErr('');
        setSaving(true);
        try {
            const pos = await getPos();
            const payload = {
                class_id: String(selected.id),
                class_label: labelKelas(selected),
                alasan: alasan || null,
                siswa: siswa.map((s) => ({ nis: s.nis, nama: s.nama, status: s.status, keterangan: s.keterangan })),
                ...pos,
            };
            router.post(route('presensi.piket.absen-siswa'), payload, {
                preserveScroll: true,
                onSuccess: () => router.post(route('presensi.piket.item'), { kode: 'absen_siswa', mode: 'centang', ...pos }, { preserveScroll: true }),
                onFinish: () => setSaving(false),
            });
        } catch (e) {
            setErr(e.message);
            setSaving(false);
        }
    };

    const nihil = async () => {
        setErr('');
        setSaving(true);
        try {
            const pos = await getPos();
            router.post(route('presensi.piket.item'), { kode: 'absen_siswa', mode: 'nihil', ...pos }, {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            });
        } catch (e) {
            setErr(e.message);
            setSaving(false);
        }
    };

    return (
        <div className="border-t border-slate-100 pt-3">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-bold text-slate-800">Absen siswa</p>
                {doneItem && <Badge tone="emerald" icon={CheckCircle2}>{doneItem.done_at?.substring(0, 5)}</Badge>}
            </div>

            {!boleh && !doneItem && (
                <p className="mt-1 flex items-center gap-1 text-xs text-slate-500"><Clock3 className="h-3.5 w-3.5" /> Dibuka sejak pagi.</p>
            )}

            {sesiTersimpan.length > 0 && (
                <ul className="mt-2 space-y-1.5">
                    {sesiTersimpan.map((s) => (
                        <li key={s.class_id} className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                            <div className="flex items-center justify-between gap-2">
                                <span className="min-w-0 truncate font-bold">{s.class_label}</span>
                                {!submitted && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            muatKelas();
                                            setSelected({ id: s.class_id, name: s.class_label, grade: '', section: '' });
                                            setSiswa(s.details.map((d) => ({ nis: d.nis, nama: d.nama, status: d.status, keterangan: d.keterangan ?? '' })));
                                            setErr('');
                                        }}
                                        className="shrink-0 rounded-lg bg-white px-2 py-1 font-bold text-primary ring-1 ring-slate-200 active:scale-95"
                                    >
                                        Edit
                                    </button>
                                )}
                            </div>
                            <p className="mt-0.5 text-slate-500">{ringkasStatus(s.details)} · {s.oleh || 'petugas'} {s.done_at}</p>
                        </li>
                    ))}
                </ul>
            )}

            {err && (
                        <div className="mt-2 flex items-center justify-between gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                            <span className="min-w-0">{err}</span>
                            {!selected && <button type="button" onClick={() => muatKelas(true)} className="shrink-0 rounded-lg bg-white px-2 py-1 font-bold text-rose-700 ring-1 ring-rose-200">Coba lagi</button>}
                        </div>
                    )}

            {!submitted && boleh && !selected && (
                <div className="mt-2 space-y-2">
                    <button type="button" onClick={muatKelas} className="min-h-12 w-full rounded-xl bg-white px-4 text-sm font-bold text-primary ring-1 ring-slate-200 active:scale-95">
                        {loadingKelas ? 'Memuat kelas...' : sesiTersimpan.length > 0 ? 'Absen kelas lain' : 'Pilih kelas'}
                    </button>
                    {daftarKelas.length > 0 && (
                        <input
                            value={cariKelas}
                            onChange={(e) => setCariKelas(e.target.value)}
                            placeholder="Cari kelas…"
                            className="input-field h-11 w-full text-xs"
                        />
                    )}
                    {daftarKelas.length > 0 && (
                        <div className="grid grid-cols-1 gap-2 min-[380px]:grid-cols-2">
                            {daftarKelas.filter((c) => labelKelas(c).toLowerCase().includes(cariKelas.trim().toLowerCase())).map((c) => (
                                <button key={c.id} type="button" onClick={() => pilihKelas(c)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs font-bold text-slate-700 active:scale-95">
                                    {labelKelas(c)}
                                    {c.major_name && <span className="mt-0.5 block text-[11px] font-normal text-slate-500">{c.major_name}</span>}
                                </button>
                            ))}
                        </div>
                    )}
                    {!doneItem && (
                        <button type="button" disabled={saving} onClick={nihil} className="min-h-11 w-full rounded-xl bg-white px-4 text-xs font-bold text-slate-600 ring-1 ring-slate-200 active:scale-95 disabled:opacity-50">
                            Tidak ada kelas hari ini (Nihil)
                        </button>
                    )}
                </div>
            )}

            {!submitted && boleh && selected && (
                <div className="mt-2 space-y-2">
                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-xs font-bold text-slate-700">{labelKelas(selected)}</p>
                            <button type="button" onClick={() => { setSelected(null); setSiswa([]); setErr(''); setKonfirmasi(null); }} className="min-h-11 rounded-lg bg-white px-3 text-[11px] font-bold text-slate-500 ring-1 ring-slate-200 active:scale-95">Ganti kelas</button>
                        </div>
                        {lastSaved && (
                            <p className="text-[11px] text-amber-700">Terakhir diubah oleh {lastSaved.oleh || 'petugas'} pukul {lastSaved.done_at}. Perubahan butuh alasan.</p>
                        )}
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-[11px] text-slate-500">{siswa.length} siswa</p>
                            <button type="button" onClick={semuaHadir} className="min-h-11 rounded-lg bg-white px-3 text-[11px] font-bold text-primary ring-1 ring-slate-200 active:scale-95">Semua hadir</button>
                        </div>
                        <input
                            value={cariSiswa}
                            onChange={(e) => setCariSiswa(e.target.value)}
                            placeholder="Cari nama atau NIS…"
                            className="input-field h-11 w-full text-xs"
                        />
                    </div>

                    {loadingSiswa ? (
                        <p className="py-4 text-center text-xs text-slate-500">Memuat siswa...</p>
                    ) : siswa.length === 0 ? (
                        <p className="py-4 text-center text-xs text-slate-500">Tidak ada data siswa untuk kelas ini.</p>
                    ) : (
                        <ul className="max-h-[45dvh] space-y-1.5 overflow-y-auto pr-0.5">
                            {siswa.filter((s) => s.nama.toLowerCase().includes(cariSiswa.trim().toLowerCase()) || String(s.nis).includes(cariSiswa.trim())).map((s) => (
                                <li key={s.nis} className="rounded-xl bg-slate-50 px-3 py-2">
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-xs font-bold text-slate-800">{s.nama}</p>
                                            <p className="text-[11px] text-slate-500">NIS {s.nis}</p>
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            {URUT_STATUS.map((st) => (
                                                <button
                                                    key={st}
                                                    type="button"
                                                    aria-label={`${s.nama}: ${st}`}
                                                    aria-pressed={s.status === st}
                                                    onClick={() => ubahStatus(s.nis, st)}
                                                    className={`h-11 w-11 rounded-lg border text-[10px] font-bold leading-tight transition ${s.status === st ? STATUS_SISWA[st].cls : 'border-slate-200 bg-white text-slate-500'}`}
                                                >
                                                    {STATUS_SISWA[st].label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    {['izin', 'sakit'].includes(s.status) && (
                                        <input
                                            value={s.keterangan}
                                            onChange={(e) => ubahKeterangan(s.nis, e.target.value)}
                                            placeholder="Keterangan (wajib)"
                                            maxLength={255}
                                            aria-label={`Keterangan ${s.nama}`}
                                            className="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    {konfirmasi ? (
                        <div className="rounded-xl border border-slate-200 bg-white p-3">
                            <p className="text-xs font-bold text-slate-800">Ringkasan {labelKelas(selected)}</p>
                            <p className="mt-1 text-[11px] text-slate-600">
                                Hadir {konfirmasi.hadir} · Telat {konfirmasi.telat} · Izin {konfirmasi.izin} · Sakit {konfirmasi.sakit} · Alpa {konfirmasi.alpa}
                            </p>
                            {konfirmasi.alpa > 0 && <p className="mt-1 text-[11px] font-semibold text-rose-600">Ada {konfirmasi.alpa} siswa alpa. Periksa kembali.</p>}
                            {lastSaved && (
                                <input
                                    value={alasan}
                                    onChange={(e) => setAlasan(e.target.value)}
                                    placeholder="Alasan perubahan (wajib, min. 5 karakter)"
                                    className="input-field mt-2 h-11 w-full text-xs"
                                />
                            )}
                            <div className="mt-2 flex gap-2">
                                <button type="button" onClick={() => setKonfirmasi(null)} className="btn-secondary min-h-11 flex-1">Periksa lagi</button>
                                <button type="button" disabled={saving || (lastSaved && alasan.trim().length < 5)} onClick={kirim} className="btn-primary min-h-11 flex-1">
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </button>
                            </div>
                        </div>
                    ) : (
                        <button type="button" disabled={saving || loadingSiswa || siswa.length === 0} onClick={siapkanSimpan} className="min-h-12 w-full rounded-xl bg-primary px-4 text-sm font-bold text-white active:scale-95 disabled:opacity-50">
                            Periksa & simpan
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

function RoundCard({ round, hhmm, sending, onDone }) {
    const [alasan, setAlasan] = useState('');
    const selesai = Boolean(round.selesai_at);
    const belumDibuka = hhmm < round.jendela_mulai;
    const terlambat = hhmm > round.jendela_selesai;

    return (
        <div className="rounded-xl border border-slate-200 px-3 py-2">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="text-sm font-bold text-slate-800">Ronde {round.putaran}{round.area ? ` · ${round.area}` : ''}</p>
                    <p className="text-[11px] text-slate-500">Jendela {round.jendela_mulai}–{round.jendela_selesai}</p>
                </div>
                <span className={`shrink-0 text-[11px] font-bold ${selesai ? 'text-emerald-700' : terlambat ? 'text-rose-600' : 'text-slate-500'}`}>
                    {selesai ? `Selesai ${round.selesai_at}` : belumDibuka ? 'Terkunci' : terlambat ? 'Lewat jendela' : 'Dibuka'}
                </span>
            </div>

            {!selesai && !belumDibuka && (
                <div className="mt-2 space-y-2">
                    {terlambat && (
                        <input
                            value={alasan}
                            onChange={(e) => setAlasan(e.target.value)}
                            placeholder="Alasan terlewat (wajib, min. 10 karakter)"
                            className="input-field h-11 w-full text-xs"
                        />
                    )}
                    <button
                        type="button"
                        disabled={sending || (terlambat && alasan.trim().length < 10)}
                        onClick={() => onDone(round.id, terlambat ? alasan : null)}
                        className="min-h-11 w-full rounded-xl bg-primary text-sm font-bold text-white disabled:opacity-50"
                    >
                        Selesaikan ronde
                    </button>
                </div>
            )}
        </div>
    );
}

function PosItem({ pos, log, sending, onSave }) {
    const [nihil, setNihil] = useState(false);
    const [jumlah, setJumlah] = useState('');
    const [catatan, setCatatan] = useState('');

    if (log?.done_at) {
        const bagian = [log.nihil ? 'Nihil' : null, log.jumlah != null ? `${log.jumlah} siswa` : null, log.catatan]
            .filter(Boolean)
            .join(' · ');

        return (
            <div className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                <span className="font-bold">{pos.nama}</span> — tercatat {log.done_at}
                {bagian ? `: ${bagian}` : ''}
                {log.sumber === 'override' && <span className="ml-1 text-slate-500">(diisi admin)</span>}
            </div>
        );
    }

    return (
        <div className="rounded-xl border border-slate-200 px-3 py-2">
            <p className="text-sm font-bold text-slate-800">
                {pos.nama}
                {pos.is_wajib && <span className="ml-1 text-xs font-semibold text-rose-600">wajib</span>}
            </p>
            <p className="text-[11px] text-slate-500">Jendela {pos.jam_mulai}–{pos.jam_selesai}</p>
            <label className="mt-2 flex items-center gap-2 text-xs font-medium text-slate-700">
                <input type="checkbox" checked={nihil} onChange={(e) => setNihil(e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary" />
                Nihil (tidak ada temuan)
            </label>
            <div className="mt-2 flex gap-2">
                <input type="number" min="0" value={jumlah} onChange={(e) => setJumlah(e.target.value)} placeholder="Jumlah" className="input-field h-11 w-24" disabled={nihil} />
                <input type="text" value={catatan} onChange={(e) => setCatatan(e.target.value)} placeholder="Catatan (opsional)" className="input-field h-11 flex-1" />
                <button type="button" disabled={sending} onClick={() => onSave(pos.id, { nihil, jumlah: jumlah === '' ? null : Number(jumlah), catatan: catatan || null })} className="min-h-11 shrink-0 rounded-xl bg-primary px-4 text-sm font-bold text-white disabled:opacity-50">
                    Catat
                </button>
            </div>
        </div>
    );
}

export default function Piket({ piket, pegawai = null, butuhMasuk = false, guruUnit = [], guruUnitAll = [], slotKosong = [], mapelUnit = [], absensiSiswa = [] }) {
    const { auth, flash = {}, errors = {} } = usePage().props;
    const now = useNowEveryMinute();
    const hhmm = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const [sending, setSending] = useState(false);
    const [locErr, setLocErr] = useState(null);
    const [terlambat, setTerlambat] = useState(0);
    const [absenForm, setAbsenForm] = useState({ guru_pegawai_id: '', nama_guru: '', keterangan_absen: '' });
    const [absenManual, setAbsenManual] = useState(false);
    const [kkForm, setKkForm] = useState({ slot_jadwal_id: '', kelas: '', guru_pegawai_id: '', mapel_id: '', ada_amanat: false, jenis_amanat: 'tugas', detail_amanat: '', tersampaikan: false });
    const kkSlot = slotKosong.find((s) => String(s.jadwal_id) === String(kkForm.slot_jadwal_id));
    const kkManual = kkForm.slot_jadwal_id === 'manual' || slotKosong.length === 0;
    const [insidenForm, setInsidenForm] = useState({ jenis_insiden: '', tindakan: '' });

    if (!piket) {
        if (butuhMasuk) {
            return (
                <MobileLayout user={auth.user}>
                    <Head title="Piket" />
                    <Card>
                        <div className="flex items-start gap-3">
                            <Lock className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                            <div>
                                <p className="text-sm font-bold text-slate-800">Lakukan presensi masuk dulu.</p>
                                <p className="mt-0.5 text-xs text-slate-500">Checklist piket terbuka setelah presensi masuk hari ini tercatat.</p>
                            </div>
                        </div>
                        <Link href={route('presensi.absen')} className="mt-4 flex min-h-12 items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition-transform active:scale-[0.99]">
                            Buka presensi
                        </Link>
                    </Card>
                </MobileLayout>
            );
        }
        return (
            <MobileLayout user={auth.user}>
                <Head title="Piket" />
                <Empty icon={ClipboardCheck} title="Tidak ada jadwal piket" subtitle="Halaman ini hanya untuk petugas piket hari ini." />
                <Link href={route('presensi.dashboard')} className="mt-4 inline-flex items-center text-sm font-semibold text-primary">
                    <ArrowLeft className="mr-1 h-4 w-4" /> Kembali ke beranda
                </Link>
            </MobileLayout>
        );
    }

    const { slot, windows, laporan, wajib, label } = piket;
    const v2 = Boolean(piket.v2);
    const area = piket.area;
    const team = piket.team ?? [];
    const posList = piket.pos ?? [];
    const posLogs = piket.pos_logs ?? {};
    const blockReason = piket.block_reason;
    const templates = piket.templates ?? [];
    const rounds = piket.rounds ?? [];
    const areaRounds = area ? rounds.filter((r) => r.area_id === area.id) : [];
    const tplAktif = (kode) => !v2 || templates.some((t) => t.kode === kode);
    const items = laporan?.items ?? {};
    const done = (kode) => Boolean(items[kode]?.done_at);
    const doneCount = wajib.filter(done).length;
    const submitted = laporan?.status === 'submitted';
    const inWin = (w) => hhmm >= w[0] && hhmm <= w[1];
    // Jalur remedial: admin bisa membuka seksi Pagi di luar jendelanya.
    const pagiOpen = inWin(windows.pagi) || Boolean(laporan?.unlocked_at);
    const kbmMulai = hhmm >= windows.kbm[0];
    const submitAfter = piket.submit_after ?? windows.sore[0];
    const teachingWindows = piket.teaching_windows ?? [];
    const soreMulai = hhmm >= submitAfter;
    const errMsg = locErr || errors.geofence || errors.kode || errors.laporan || errors.guru_pegawai_id || errors.keterangan_absen;

    const kirim = async (payload, { geo = true } = {}) => {
        setLocErr(null);
        setSending(true);
        try {
            const pos = geo ? await getPos() : {};
            router.post(route('presensi.piket.item'), { ...payload, ...pos }, {
                preserveScroll: true,
                onFinish: () => setSending(false),
            });
        } catch (e) {
            setLocErr(e.message);
            setSending(false);
        }
    };

    const kirimPos = async (posId, payload) => {
        setLocErr(null);
        setSending(true);
        try {
            const geo = await getPos();
            router.post(route('presensi.piket.pos'), { pos_id: posId, ...payload, ...geo }, {
                preserveScroll: true,
                onFinish: () => setSending(false),
            });
        } catch (e) {
            setLocErr(e.message);
            setSending(false);
        }
    };

    const kirimRound = async (roundId, alasan) => {
        setLocErr(null);
        setSending(true);
        try {
            const geo = await getPos();
            router.post(route('presensi.piket.round', roundId), { alasan, ...geo }, {
                preserveScroll: true,
                onFinish: () => setSending(false),
            });
        } catch (e) {
            setLocErr(e.message);
            setSending(false);
        }
    };

    const submit = () => {
        setSending(true);
        router.post(route('presensi.piket.submit'), {}, { preserveScroll: true, onFinish: () => setSending(false) });
    };

    const hapusEntry = (kode, index) => {
        if (!window.confirm('Hapus entry ini?')) return;
        setSending(true);
        router.post(route('presensi.piket.hapus'), { kode, index }, { preserveScroll: true, onFinish: () => setSending(false) });
    };

    // Tombol ✕ kecil di tiap entry list (cek guru / kelas kosong / insiden);
    // sembunyi setelah laporan dikirim (server juga menolak).
    const HapusBtn = ({ kode, index }) => submitted ? null : (
        <button
            type="button"
            aria-label="Hapus entry"
            disabled={sending}
            onClick={() => hapusEntry(kode, index)}
            className="ml-2 shrink-0 rounded-full p-1 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 active:scale-90 disabled:opacity-50"
        >
            <X className="h-3.5 w-3.5" />
        </button>
    );

    return (
        <MobileLayout user={auth.user}>
            <Head title="Laporan Piket" />

            <div className="mb-5 px-1">
                <Link href={route('presensi.dashboard')} className="mb-2 inline-flex items-center text-sm font-semibold text-primary transition-colors active:scale-95">
                    <ArrowLeft className="mr-1 h-4 w-4" /> Beranda
                </Link>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Piket Hari Ini</h1>
                        <p className="mt-0.5 text-sm text-slate-500">
                            {format(new Date(), 'EEEE, d MMMM yyyy', { locale: id })} ({slot.jam_mulai}-{slot.jam_selesai}){area ? ` · ${area.nama}` : ''}
                            {slot.unit ? ` ${slot.unit}` : ''}
                        </p>
                    </div>
                    <Link href={route('presensi.piket.riwayat')} className="shrink-0 rounded-xl bg-white px-3 py-2 text-xs font-bold text-primary ring-1 ring-slate-200 transition-transform active:scale-95">
                        Riwayat
                    </Link>
                </div>
            </div>

            {flash.message && <div role="status" className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
            {errMsg && <div role="alert" className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{errMsg}</div>}

            <Card className="mb-4">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-bold text-slate-800">Progress {doneCount}/{wajib.length}</p>
                    {submitted && <Badge tone="emerald" icon={CheckCircle2}>Terkirim</Badge>}
                </div>
                <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100">
                    <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${Math.round((doneCount / wajib.length) * 100)}%` }} />
                </div>
            </Card>

            {v2 && team.length > 0 && (
                <Card className="mb-4">
                    <p className="text-sm font-bold text-slate-800">Tim piket</p>
                    <ul className="mt-2 space-y-1.5">
                        {team.map((t) => (
                            <li key={t.pegawai_id} className="flex items-center justify-between gap-2 text-xs text-slate-600">
                                <span className="min-w-0 truncate">
                                    <span className={`font-semibold ${t.pegawai_id === pegawai?.id ? 'text-primary' : 'text-slate-800'}`}>{t.nama}</span>
                                    {t.area ? ` · ${t.area}` : ''}{t.peran === 'gerbang' ? ' · gerbang' : ''}
                                </span>
                                <span className="shrink-0 tabular-nums">{t.submitted ? 'terkirim' : `${t.selesai} item`}</span>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            {v2 && posList.length > 0 && (
                <Card className="mb-4 space-y-2">
                    <p className="text-sm font-bold text-slate-800">Pos hari ini</p>
                    {posList.map((pos) => (
                        <PosItem key={pos.id} pos={pos} log={posLogs[pos.id]} sending={sending} onSave={kirimPos} />
                    ))}
                    {blockReason && (
                        <p className="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{blockReason}</p>
                    )}
                </Card>
            )}

            {submitted ? (
                <Card>
                    <div className="flex items-start gap-3">
                        <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                        <div>
                            <p className="text-sm font-bold text-slate-800">Laporan terkirim dan terkunci.</p>
                            <p className="mt-0.5 text-xs text-slate-500">Terima kasih, tugas piket hari ini tercatat lengkap.</p>
                        </div>
                    </div>
                </Card>
            ) : (
                <div className="space-y-4">
                    <section aria-label="Tugas pagi">
                        <SeksiHead icon={DoorOpen} title="Pagi" win={windows.pagi} state={done('gerbang') && done('cek_absen') && done('absen_siswa') ? 'done' : pagiOpen ? 'open' : 'locked'} />
                        {!inWin(windows.pagi) && pagiOpen && !submitted && (
                            <p className="mb-2 rounded-xl bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800">Admin membuka jalur telat — item Pagi bisa dicatat sampai laporan dikirim.</p>
                        )}
                        <Card className="space-y-4">
                            {!v2 && (
                            <div>
                                <p className="text-sm font-bold text-slate-800">Jaga gerbang {done('gerbang') && <span className="ml-1 text-xs font-semibold text-emerald-700">({items.gerbang.done_at?.substring(0, 5)}, {items.gerbang.terlambat_count ?? 0} terlambat)</span>}</p>
                                {!done('gerbang') && (
                                    <div className="mt-2 flex items-center gap-2">
                                        <div className="flex-1">
                                            <label htmlFor="terlambat" className={labelCls}>Siswa terlambat</label>
                                            <input id="terlambat" type="number" min="0" value={terlambat} onChange={(e) => setTerlambat(e.target.value)} className={inputCls} disabled={!pagiOpen || sending} />
                                        </div>
                                        <button type="button" disabled={!pagiOpen || sending} onClick={() => kirim({ kode: 'gerbang', mode: 'centang', terlambat_count: Number(terlambat) || 0 })} className="mt-6 min-h-12 rounded-xl bg-primary px-4 text-sm font-bold text-white active:scale-95 disabled:opacity-50">
                                            {sending ? '...' : 'Catat'}
                                        </button>
                                    </div>
                                )}
                                {!pagiOpen && !done('gerbang') && <p className="mt-1 flex items-center gap-1 text-xs text-slate-500"><Clock3 className="h-3.5 w-3.5" /> Dibuka {windows.pagi[0]}-{windows.pagi[1]}.</p>}
                            </div>
                            )}

                            {tplAktif('cek_absen') && (
                            <div className="border-t border-slate-100 pt-3">
                                <p className="text-sm font-bold text-slate-800">Cek guru berhalangan</p>
                                {!done('cek_absen') && (
                                    <button type="button" disabled={!pagiOpen || sending} onClick={() => kirim({ kode: 'cek_absen', mode: 'centang' })} className="mt-2 min-h-12 w-full rounded-xl bg-primary px-4 text-sm font-bold text-white active:scale-95 disabled:opacity-50">
                                        {sending ? '...' : 'Catat cek pagi'}
                                    </button>
                                )}
                                {(items.cek_absen?.entries ?? []).length > 0 && (
                                    <ul className="mt-2 space-y-1.5">
                                        {(items.cek_absen.entries ?? []).map((e, i) => (
                                            <li key={i} className="flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <span className="min-w-0"><span className="font-bold">{e.nama_guru}</span> ({e.created_at?.substring(0, 5)}): {e.keterangan}</span>
                                                <HapusBtn kode="cek_absen" index={i} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {done('cek_absen') && (
                                    <div className="mt-2 grid grid-cols-1 gap-2">
                                        <select
                                            value={absenManual ? 'manual' : absenForm.guru_pegawai_id}
                                            onChange={(e) => {
                                                const value = e.target.value;
                                                if (value === 'manual') {
                                                    setAbsenManual(true);
                                                    setAbsenForm({ ...absenForm, guru_pegawai_id: '', nama_guru: '' });
                                                    return;
                                                }
                                                setAbsenManual(false);
                                                setAbsenForm({ ...absenForm, guru_pegawai_id: value, nama_guru: '' });
                                            }}
                                            className={inputCls}
                                            aria-label="Guru berhalangan"
                                        >
                                            <option value="">Pilih guru</option>
                                            {guruUnitAll.map((g) => <option key={g.id} value={g.id}>{g.nama_lengkap}</option>)}
                                            <option value="manual">Guru tidak ada di daftar</option>
                                        </select>
                                        {absenManual && (
                                            <input value={absenForm.nama_guru} onChange={(e) => setAbsenForm({ ...absenForm, nama_guru: e.target.value })} placeholder="Nama guru (manual)" className={inputCls} maxLength={100} aria-label="Nama guru manual" />
                                        )}
                                        <div className="flex gap-2">
                                            <input value={absenForm.keterangan_absen} onChange={(e) => setAbsenForm({ ...absenForm, keterangan_absen: e.target.value })} placeholder="Keterangan" className={`${inputCls} flex-1`} maxLength={255} aria-label="Keterangan" />
                                            <button
                                                type="button"
                                                disabled={sending || !absenForm.keterangan_absen.trim() || (!absenForm.guru_pegawai_id && !absenForm.nama_guru.trim())}
                                                onClick={() => kirim({ kode: 'cek_absen', mode: 'tambah', ...absenForm })}
                                                className="min-h-12 shrink-0 rounded-xl bg-white px-4 text-sm font-bold text-primary ring-1 ring-slate-200 active:scale-95 disabled:opacity-50"
                                            >
                                                Tambah
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>

                            )}
                            {tplAktif('absen_siswa') && (
                            <AbsenSiswaCard
                                jadwalId={slot.id}
                                sesiTersimpan={absensiSiswa}
                                doneItem={items.absen_siswa}
                                boleh={hhmm >= windows.pagi[0]}
                                submitted={submitted}
                            />
                            )}
                        </Card>
                    </section>

                    <section aria-label="Tugas jam belajar">
                        <SeksiHead icon={BookOpen} title="Jam Belajar" win={windows.kbm} state={done('kelas_kosong') && done('buku') && done('insiden') ? 'done' : kbmMulai ? 'open' : 'locked'} />
                        <Card className="space-y-4">
                            {v2 && areaRounds.length > 0 && (
                                <div className="space-y-2 border-b border-slate-100 pb-3">
                                    <p className="text-sm font-bold text-slate-800">Ronde keliling area</p>
                                    {areaRounds.map((r) => (
                                        <RoundCard key={r.id} round={r} hhmm={hhmm} sending={sending} onDone={kirimRound} />
                                    ))}
                                </div>
                            )}
                            <div>
                                <p className="text-sm font-bold text-slate-800">Isi kelas kosong</p>
                                {!done('kelas_kosong') && kbmMulai && (
                                    <div className="mt-2 space-y-2">
                                        <div>
                                            <label htmlFor="kk-slot" className={labelCls}>Kelas kosong</label>
                                            <select id="kk-slot" value={kkForm.slot_jadwal_id} onChange={(e) => setKkForm({ ...kkForm, slot_jadwal_id: e.target.value })} className={inputCls}>
                                                <option value="">Pilih dari jadwal hari ini</option>
                                                {slotKosong.length === 0 && <option value="" disabled>Tidak ada slot kosong, pakai input manual</option>}
                                                {slotKosong.map((s) => (
                                                    <option key={s.jadwal_id} value={s.jadwal_id}>
                                                        {s.kelas || 'Tanpa kelas'}{s.mapel ? `, ${s.mapel}` : ''} ({s.guru_nama}, {s.jam})
                                                    </option>
                                                ))}
                                                <option value="manual">Lainnya / input manual</option>
                                            </select>
                                        </div>
                                        {kkSlot && (
                                            <div className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <span className="font-bold">{kkSlot.kelas || 'Tanpa kelas'}</span>
                                                {kkSlot.mapel ? `, ${kkSlot.mapel}` : ''} ({kkSlot.guru_nama})
                                            </div>
                                        )}
                                        {kkManual && (
                                            <div className="grid grid-cols-1 gap-2 min-[420px]:grid-cols-3">
                                                <div>
                                                    <label htmlFor="kk-kelas" className={labelCls}>Kelas</label>
                                                    <input id="kk-kelas" value={kkForm.kelas} onChange={(e) => setKkForm({ ...kkForm, kelas: e.target.value })} placeholder="cth. VII-A" className={inputCls} maxLength={100} />
                                                </div>
                                                <div>
                                                    <label htmlFor="kk-mapel" className={labelCls}>Mapel</label>
                                                    <select id="kk-mapel" value={kkForm.mapel_id} onChange={(e) => setKkForm({ ...kkForm, mapel_id: e.target.value })} className={inputCls}>
                                                        <option value="">Pilih</option>
                                                        {mapelUnit.length === 0 && <option value="" disabled>Belum ada data mapel</option>}
                                                        {mapelUnit.map((m) => <option key={m.id} value={m.id}>{m.nama}</option>)}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label htmlFor="kk-guru" className={labelCls}>Guru absen</label>
                                                    <select id="kk-guru" value={kkForm.guru_pegawai_id} onChange={(e) => setKkForm({ ...kkForm, guru_pegawai_id: e.target.value })} className={inputCls}>
                                                        <option value="">Pilih</option>
                                                        {guruUnit.length === 0 && <option value="" disabled>Belum ada guru lain di unit ini</option>}
                                                        {guruUnit.map((g) => <option key={g.id} value={g.id}>{g.nama_lengkap}</option>)}
                                                    </select>
                                                </div>
                                            </div>
                                        )}
                                        <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="checkbox" checked={kkForm.ada_amanat} onChange={(e) => setKkForm({ ...kkForm, ada_amanat: e.target.checked })} className="h-5 w-5 rounded border-slate-300 text-primary focus:ring-primary/25" />
                                            Ada amanat dari guru
                                        </label>
                                        {kkForm.ada_amanat && (
                                            <div className="space-y-2 rounded-xl bg-slate-50 p-3">
                                                <div>
                                                    <label htmlFor="kk-jenis" className={labelCls}>Jenis amanat</label>
                                                    <select id="kk-jenis" value={kkForm.jenis_amanat} onChange={(e) => setKkForm({ ...kkForm, jenis_amanat: e.target.value })} className={inputCls}>
                                                        <option value="tugas">Beri tugas</option>
                                                        <option value="pelajari_halaman">Pelajari halaman</option>
                                                        <option value="lainnya">Lainnya</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label htmlFor="kk-detail" className={labelCls}>Isi amanat</label>
                                                    <textarea id="kk-detail" rows="2" value={kkForm.detail_amanat} onChange={(e) => setKkForm({ ...kkForm, detail_amanat: e.target.value })} placeholder="cth. Kerjakan Latihan 3 hal. 45" className={inputCls} maxLength={1000} />
                                                </div>
                                                <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                                                    <input type="checkbox" checked={kkForm.tersampaikan} onChange={(e) => setKkForm({ ...kkForm, tersampaikan: e.target.checked })} className="h-5 w-5 rounded border-slate-300 text-primary focus:ring-primary/25" />
                                                    Amanat tersampaikan ke kelas
                                                </label>
                                            </div>
                                        )}
                                        <div className="flex gap-2">
                                            <button type="button" disabled={sending || (!kkSlot && !(kkForm.kelas && kkForm.guru_pegawai_id))} onClick={() => { const { slot_jadwal_id: _drop, ...rest } = kkForm; kirim({ kode: 'kelas_kosong', mode: 'tambah', ...rest, ...(kkSlot ? { slot_jadwal_id: kkSlot.jadwal_id } : {}) }); }} className="min-h-12 flex-1 rounded-xl bg-primary px-4 text-sm font-bold text-white active:scale-95 disabled:opacity-50">
                                                {sending ? '...' : 'Catat kelas'}
                                            </button>
                                            <button type="button" disabled={sending} onClick={() => kirim({ kode: 'kelas_kosong', mode: 'nihil' })} className="min-h-12 shrink-0 rounded-xl bg-white px-4 text-sm font-bold text-primary ring-1 ring-slate-200 active:scale-95 disabled:opacity-50">
                                                Nihil
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {!kbmMulai && !done('kelas_kosong') && <p className="mt-1 flex items-center gap-1 text-xs text-slate-500"><Clock3 className="h-3.5 w-3.5" /> Dibuka {windows.kbm[0]}.</p>}
                                {(items.kelas_kosong?.entries ?? []).length > 0 && (
                                    <ul className="mt-2 space-y-1.5">
                                        {(items.kelas_kosong.entries ?? []).map((e, i) => (
                                            <li key={i} className="flex items-start justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <span className="min-w-0">
                                                    <span className="font-bold">{e.kelas}</span>{e.mapel ? `, ${e.mapel}` : ''} ({e.guru_nama})
                                                    {e.ada_amanat ? `: amanat ${e.jenis_amanat === 'tugas' ? 'tugas' : e.jenis_amanat === 'pelajari_halaman' ? 'pelajari halaman' : 'lainnya'}${e.tersampaikan ? ', tersampaikan' : ', belum tersampaikan'}` : ': tanpa amanat'}
                                                    {e.validasi === 'perlu_cek' && <Badge tone="amber" className="ml-1.5">Perlu cek</Badge>}
                                                </span>
                                                <HapusBtn kode="kelas_kosong" index={i} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {items.kelas_kosong?.nihil && <p className="mt-1 text-xs font-semibold text-emerald-700">Nihil ({items.kelas_kosong.done_at?.substring(0, 5)}).</p>}
                            </div>

                            <div className="border-t border-slate-100 pt-3">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-sm font-bold text-slate-800">Buku piket terisi</p>
                                    {done('buku') ? <Badge tone="emerald" icon={CheckCircle2}>{items.buku.done_at?.substring(0, 5)}</Badge> : (
                                        <button type="button" disabled={!kbmMulai || sending} onClick={() => kirim({ kode: 'buku', mode: 'centang' })} className="min-h-12 rounded-xl bg-primary px-4 text-sm font-bold text-white active:scale-95 disabled:opacity-50">
                                            {sending ? '...' : 'Catat'}
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div className="border-t border-slate-100 pt-3">
                                <p className="text-sm font-bold text-slate-800">Insiden</p>
                                {!done('insiden') && kbmMulai && (
                                    <div className="mt-2 space-y-2">
                                        <div className="grid grid-cols-2 gap-2">
                                            <div>
                                                <label htmlFor="ins-jenis" className={labelCls}>Jenis</label>
                                                <input id="ins-jenis" value={insidenForm.jenis_insiden} onChange={(e) => setInsidenForm({ ...insidenForm, jenis_insiden: e.target.value })} placeholder="cth. Sakit" className={inputCls} maxLength={100} />
                                            </div>
                                            <div>
                                                <label htmlFor="ins-tindakan" className={labelCls}>Tindakan</label>
                                                <input id="ins-tindakan" value={insidenForm.tindakan} onChange={(e) => setInsidenForm({ ...insidenForm, tindakan: e.target.value })} placeholder="cth. Antar ke UKS" className={inputCls} maxLength={1000} />
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <button type="button" disabled={sending} onClick={() => kirim({ kode: 'insiden', mode: 'tambah', ...insidenForm })} className="min-h-12 flex-1 rounded-xl bg-primary px-4 text-sm font-bold text-white active:scale-95 disabled:opacity-50">
                                                {sending ? '...' : 'Catat insiden'}
                                            </button>
                                            <button type="button" disabled={sending} onClick={() => kirim({ kode: 'insiden', mode: 'nihil' })} className="min-h-12 shrink-0 rounded-xl bg-white px-4 text-sm font-bold text-primary ring-1 ring-slate-200 active:scale-95 disabled:opacity-50">
                                                Nihil
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {(items.insiden?.entries ?? []).length > 0 && (
                                    <ul className="mt-2 space-y-1.5">
                                        {(items.insiden.entries ?? []).map((e, i) => (
                                            <li key={i} className="flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <span className="min-w-0"><span className="font-bold">{e.jenis}</span> ({e.created_at?.substring(0, 5)}): {e.tindakan}</span>
                                                <HapusBtn kode="insiden" index={i} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {items.insiden?.nihil && <p className="mt-1 text-xs font-semibold text-emerald-700">Nihil ({items.insiden.done_at?.substring(0, 5)}).</p>}
                            </div>
                        </Card>
                    </section>

                    <section aria-label="Kirim laporan">
                        <SeksiHead icon={Send} title="Sore" win={[submitAfter, windows.sore[1]]} state={soreMulai ? 'open' : 'locked'} />
                        {blockReason && <p className="mb-2 rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{blockReason}</p>}
                        <button type="button" disabled={!soreMulai || sending || doneCount < wajib.length || Boolean(blockReason)} onClick={submit} className="min-h-14 w-full rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:opacity-50">
                            {sending ? 'Mengirim...' : doneCount < wajib.length ? `Lengkapi dulu (${doneCount}/${wajib.length})` : 'Kirim laporan piket'}
                        </button>
                        {!soreMulai && <p className="mt-1 flex items-center gap-1 text-xs text-slate-500"><Clock3 className="h-3.5 w-3.5" /> Pengiriman dibuka {submitAfter}.</p>}
                    </section>
                </div>
            )}
        </MobileLayout>
    );
}
