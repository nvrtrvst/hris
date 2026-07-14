import React, { useMemo } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell } from 'recharts';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, SectionTitle, Badge, Empty } from '@/Components/MobileUI';
import { CalendarCheck, Clock, MapPin, AlertTriangle, TrendingUp, FileText } from 'lucide-react';

export default function Dashboard({ auth, pegawai, presensi, presensiSeminggu }) {
    const { flash } = usePage().props;
    const today = format(new Date(), 'EEEE, d MMMM yyyy', { locale: id });

    const jabatan = pegawai?.units?.[0]?.pivot?.jabatan?.nama_jabatan || 'Pegawai';
    const unit = pegawai?.units?.[0]?.nama_unit || '-';
    const namaDepan = auth?.user?.name?.split(' ')[0] || 'Pegawai';

    const jadwalHariIni = presensi?.jadwal_hari_ini || [];
    const izinHariIni = presensi?.izin_hari_ini || null;
    const statusBadge = presensi?.status_badge || { label: 'Belum Presensi', color: 'slate' };
    const lokasiPerluReview = presensi?.lokasi_perlu_review || false;

    const stats = {
        hadir: presensi?.hadir || 0,
        telat: presensi?.telat || 0,
        sakit_izin: (presensi?.sakit || 0) + (presensi?.izin || 0),
        alpa: presensi?.alpa || 0,
    };

    const getStatusTone = (color) => {
        const map = { green: 'emerald', yellow: 'amber', red: 'rose', blue: 'sky', slate: 'slate' };
        return map[color] || 'slate';
    };

    const chartData = useMemo(() => {
        const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        return days.map((day) => {
            const d = presensiSeminggu?.[day] || { hadir: 0, telat: 0, izin: 0, sakit: 0, alpa: 0 };
            return { day: day.slice(0, 3), total: d.hadir + d.telat + d.izin + d.sakit + d.alpa, hadir: d.hadir, telat: d.telat, izin: d.izin, sakit: d.sakit, alpa: d.alpa };
        });
    }, [presensiSeminggu]);

    const statCards = [
        { label: 'Hadir', value: stats.hadir, tone: 'emerald', icon: CalendarCheck },
        { label: 'Telat', value: stats.telat, tone: 'amber', icon: Clock },
        { label: 'Izin/Sakit', value: stats.sakit_izin, tone: 'sky', icon: FileText },
        { label: 'Alpa', value: stats.alpa, tone: 'rose', icon: AlertTriangle },
    ];

    return (
        <MobileLayout user={auth.user}>
            <Head title="Dashboard" />

            {/* Greeting */}
            <div className="mb-5 px-1">
                <p className="text-sm font-medium text-slate-500">{today}</p>
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">
                    Halo, {namaDepan} 👋
                </h1>
                <p className="mt-0.5 text-sm text-slate-500">{jabatan} • {unit}</p>
            </div>

            {flash.message && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {flash.message}
                </div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                    {flash.error}
                </div>
            )}

            {/* Today presensi */}
            <Card className="relative overflow-hidden">
                <div className="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-emerald-100/60 blur-2xl" />
                <div className="relative">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-extrabold text-slate-800">Presensi Hari Ini</h2>
                        <Badge tone={getStatusTone(statusBadge.color)}>
                            {statusBadge.label}
                        </Badge>
                    </div>

                    {jadwalHariIni.length === 0 && !izinHariIni ? (
                        <Empty icon={Clock} title="Tidak ada jadwal hari ini" subtitle="Nikmati waktumu, sampai jumpa besok!" />
                    ) : izinHariIni ? (
                        <div className="rounded-2xl bg-emerald-50 p-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                                    <FileText className="h-6 w-6" />
                                </div>
                                <div>
                                    <p className="font-bold text-slate-800 capitalize">{izinHariIni.jenis_izin}</p>
                                    <p className="text-sm text-slate-500">Disetujui • {format(new Date(izinHariIni.tanggal_mulai), 'd MMM', { locale: id })}</p>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {jadwalHariIni.map((jadwal, idx) => (
                                <div key={idx} className="rounded-2xl bg-slate-50 p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="font-bold text-slate-800">{jadwal.mata_pelajaran}</p>
                                            <p className="text-xs text-slate-500">{jadwal.kelas} • {jadwal.hari}</p>
                                        </div>
                                        <Badge tone={getStatusTone(jadwal.status_color)}>
                                            {jadwal.status}
                                        </Badge>
                                    </div>
                                    <div className="mt-3 flex items-center gap-4 text-sm text-slate-600">
                                        <span className="inline-flex items-center gap-1"><Clock className="h-4 w-4 text-emerald-400" /> {jadwal.jam_mulai}–{jadwal.jam_selesai}</span>
                                        {jadwal.jam_masuk && (
                                            <span className="inline-flex items-center gap-1"><MapPin className="h-4 w-4 text-emerald-400" /> {jadwal.jam_masuk}</span>
                                        )}
                                        {jadwal.jam_keluar && (
                                            <span className="inline-flex items-center gap-1 text-rose-400"><MapPin className="h-4 w-4" /> {jadwal.jam_keluar}</span>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {lokasiPerluReview && (
                                <div className="flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-3">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-500" />
                                    <p className="text-xs font-medium text-amber-700">Lokasi presensi Anda berada di luar area kantor dan perlu ditinjau oleh admin.</p>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </Card>

            {/* Stats */}
            <SectionTitle icon={TrendingUp} className="mt-6">Ringkasan Bulan Ini</SectionTitle>
            <div className="grid grid-cols-2 gap-3">
                {statCards.map((s) => (
                    <Card key={s.label} className="flex items-center gap-3 py-4">
                        <div className={`flex h-11 w-11 items-center justify-center rounded-2xl ${
                            s.tone === 'emerald' ? 'bg-emerald-100 text-emerald-600' :
                            s.tone === 'amber' ? 'bg-amber-100 text-amber-600' :
                            s.tone === 'sky' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'
                        }`}>
                            <s.icon className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-2xl font-extrabold leading-none text-slate-800">{s.value}</p>
                            <p className="text-xs font-medium text-slate-500">{s.label}</p>
                        </div>
                    </Card>
                ))}
            </div>

            {/* Weekly chart */}
            <SectionTitle icon={TrendingUp} className="mt-6">Kehadiran 7 Hari</SectionTitle>
            <Card className="pb-2">
                <ResponsiveContainer width="100%" height={180}>
                    <BarChart data={chartData} barCategoryGap="25%">
                        <XAxis dataKey="day" tick={{ fontSize: 11, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                        <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                        <Tooltip
                            cursor={{ fill: 'rgba(99,102,241,0.06)' }}
                            contentStyle={{ borderRadius: 16, border: 'none', boxShadow: '0 8px 30px -12px rgba(79,70,229,0.4)', fontSize: 12 }}
                        />
                        <Bar dataKey="total" radius={[8, 8, 8, 8]}>
                            {chartData.map((entry, index) => (
                                <Cell key={index} fill={entry.total > 0 ? '#6366f1' : '#e2e8f0'} />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </Card>
        </MobileLayout>
    );
}
