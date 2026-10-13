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
    Users,
} from 'lucide-react';

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

const inputCls = 'block w-full rounded-2xl border-slate-200 bg-slate-50 px-3 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
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

export default function Piket({ piket, guruUnit = [], slotKosong = [], mapelUnit = [] }) {
    const { auth, flash = {}, errors = {} } = usePage().props;
    const now = useNowEveryMinute();
    const hhmm = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const [sending, setSending] = useState(false);
    const [locErr, setLocErr] = useState(null);
    const [terlambat, setTerlambat] = useState(0);
    const [absenForm, setAbsenForm] = useState({ nama_guru: '', keterangan_absen: '' });
    const [kkForm, setKkForm] = useState({ slot_jadwal_id: '', kelas: '', guru_pegawai_id: '', mapel_id: '', ada_amanat: false, jenis_amanat: 'tugas', detail_amanat: '', tersampaikan: false });
    const kkSlot = slotKosong.find((s) => String(s.jadwal_id) === String(kkForm.slot_jadwal_id));
    const kkManual = kkForm.slot_jadwal_id === 'manual' || slotKosong.length === 0;
    const [insidenForm, setInsidenForm] = useState({ jenis_insiden: '', tindakan: '' });

    if (!piket) {
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
    const items = laporan?.items ?? {};
    const done = (kode) => Boolean(items[kode]?.done_at);
    const doneCount = wajib.filter(done).length;
    const submitted = laporan?.status === 'submitted';
    const inWin = (w) => hhmm >= w[0] && hhmm <= w[1];
    const pagiOpen = inWin(windows.pagi);
    const kbmMulai = hhmm >= windows.kbm[0];
    const soreMulai = hhmm >= windows.sore[0];
    const errMsg = locErr || errors.geofence || errors.kode || errors.laporan;

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

    const submit = () => {
        setSending(true);
        router.post(route('presensi.piket.submit'), {}, { preserveScroll: true, onFinish: () => setSending(false) });
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Laporan Piket" />

            <div className="mb-5 px-1">
                <Link href={route('presensi.dashboard')} className="mb-2 inline-flex items-center text-sm font-semibold text-primary transition-colors active:scale-95">
                    <ArrowLeft className="mr-1 h-4 w-4" /> Beranda
                </Link>
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Piket Hari Ini</h1>
                <p className="mt-0.5 text-sm text-slate-500">
                    {format(new Date(), 'EEEE, d MMMM yyyy', { locale: id })} ({slot.jam_mulai}-{slot.jam_selesai})
                    {slot.unit ? ` ${slot.unit}` : ''}
                </p>
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
                        <SeksiHead icon={DoorOpen} title="Pagi" win={windows.pagi} state={done('gerbang') && done('cek_absen') ? 'done' : pagiOpen ? 'open' : 'locked'} />
                        <Card className="space-y-4">
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
                                            <li key={i} className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <span className="font-bold">{e.nama_guru}</span> ({e.created_at?.substring(0, 5)}): {e.keterangan}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {done('cek_absen') && (
                                    <div className="mt-2 grid grid-cols-1 gap-2">
                                        <input value={absenForm.nama_guru} onChange={(e) => setAbsenForm({ ...absenForm, nama_guru: e.target.value })} placeholder="Nama guru (konfirmasi susulan)" className={inputCls} maxLength={100} aria-label="Nama guru" />
                                        <div className="flex gap-2">
                                            <input value={absenForm.keterangan_absen} onChange={(e) => setAbsenForm({ ...absenForm, keterangan_absen: e.target.value })} placeholder="Keterangan" className={`${inputCls} flex-1`} maxLength={255} aria-label="Keterangan" />
                                            <button type="button" disabled={sending} onClick={() => kirim({ kode: 'cek_absen', mode: 'tambah', ...absenForm })} className="min-h-12 shrink-0 rounded-xl bg-white px-4 text-sm font-bold text-primary ring-1 ring-slate-200 active:scale-95 disabled:opacity-50">
                                                Tambah
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </Card>
                    </section>

                    <section aria-label="Tugas jam belajar">
                        <SeksiHead icon={BookOpen} title="Jam Belajar" win={windows.kbm} state={done('kelas_kosong') && done('buku') && done('insiden') ? 'done' : kbmMulai ? 'open' : 'locked'} />
                        <Card className="space-y-4">
                            <div>
                                <p className="text-sm font-bold text-slate-800">Isi kelas kosong</p>
                                {!done('kelas_kosong') && kbmMulai && (
                                    <div className="mt-2 space-y-2">
                                        <div>
                                            <label htmlFor="kk-slot" className={labelCls}>Kelas kosong</label>
                                            <select id="kk-slot" value={kkForm.slot_jadwal_id} onChange={(e) => setKkForm({ ...kkForm, slot_jadwal_id: e.target.value })} className={inputCls}>
                                                <option value="">Pilih dari jadwal hari ini</option>
                                                {slotKosong.map((s) => (
                                                    <option key={s.jadwal_id} value={s.jadwal_id}>
                                                        {s.kelas || 'Tanpa kelas'}{s.mapel ? ` — ${s.mapel}` : ''} ({s.guru_nama}, {s.jam})
                                                    </option>
                                                ))}
                                                <option value="manual">Lainnya / input manual</option>
                                            </select>
                                        </div>
                                        {kkSlot && (
                                            <div className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <span className="font-bold">{kkSlot.kelas || 'Tanpa kelas'}</span>
                                                {kkSlot.mapel ? ` — ${kkSlot.mapel}` : ''} ({kkSlot.guru_nama})
                                            </div>
                                        )}
                                        {kkManual && (
                                            <div className="grid grid-cols-3 gap-2">
                                                <div>
                                                    <label htmlFor="kk-kelas" className={labelCls}>Kelas</label>
                                                    <input id="kk-kelas" value={kkForm.kelas} onChange={(e) => setKkForm({ ...kkForm, kelas: e.target.value })} placeholder="cth. VII-A" className={inputCls} maxLength={100} />
                                                </div>
                                                <div>
                                                    <label htmlFor="kk-mapel" className={labelCls}>Mapel</label>
                                                    <select id="kk-mapel" value={kkForm.mapel_id} onChange={(e) => setKkForm({ ...kkForm, mapel_id: e.target.value })} className={inputCls}>
                                                        <option value="">Pilih</option>
                                                        {mapelUnit.map((m) => <option key={m.id} value={m.id}>{m.nama}</option>)}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label htmlFor="kk-guru" className={labelCls}>Guru absen</label>
                                                    <select id="kk-guru" value={kkForm.guru_pegawai_id} onChange={(e) => setKkForm({ ...kkForm, guru_pegawai_id: e.target.value })} className={inputCls}>
                                                        <option value="">Pilih</option>
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
                                            <li key={i} className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <span className="font-bold">{e.kelas}</span>{e.mapel ? ` — ${e.mapel}` : ''} ({e.guru_nama})
                                                {e.ada_amanat ? `: amanat ${e.jenis_amanat === 'tugas' ? 'tugas' : e.jenis_amanat === 'pelajari_halaman' ? 'pelajari halaman' : 'lainnya'}${e.tersampaikan ? ', tersampaikan' : ', belum tersampaikan'}` : ': tanpa amanat'}
                                                {e.validasi === 'perlu_cek' && <Badge tone="amber" className="ml-1.5">Perlu cek</Badge>}
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
                                            <li key={i} className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                <span className="font-bold">{e.jenis}</span> ({e.created_at?.substring(0, 5)}): {e.tindakan}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {items.insiden?.nihil && <p className="mt-1 text-xs font-semibold text-emerald-700">Nihil ({items.insiden.done_at?.substring(0, 5)}).</p>}
                            </div>
                        </Card>
                    </section>

                    <section aria-label="Kirim laporan">
                        <SeksiHead icon={Send} title="Sore" win={windows.sore} state={soreMulai ? 'open' : 'locked'} />
                        <button type="button" disabled={!soreMulai || sending || doneCount < wajib.length} onClick={submit} className="min-h-14 w-full rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:opacity-50">
                            {sending ? 'Mengirim...' : doneCount < wajib.length ? `Lengkapi dulu (${doneCount}/${wajib.length})` : 'Kirim laporan piket'}
                        </button>
                        {!soreMulai && <p className="mt-1 flex items-center gap-1 text-xs text-slate-500"><Users className="h-3.5 w-3.5" /> Pengiriman dibuka {windows.sore[0]}.</p>}
                    </section>
                </div>
            )}
        </MobileLayout>
    );
}
