import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Building2, CalendarClock, Clock3, Globe, MapPin, Pencil, Plus, Ruler, School, Users } from 'lucide-react';
import StatCard from '@/Components/StatCard';
import { avatarTone, initials } from '@/Utils/avatar';

function UnitBadge({ Icon, label }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-surface px-2.5 py-1 text-[11px] font-semibold text-text-secondary">
            <Icon className="h-3.5 w-3.5 text-text-muted" />
            {label}
        </span>
    );
}

export default function Index({ auth, units, stats }) {
    const hasUnits = (units || []).length > 0;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Kelola Unit Sekolah</h2>}
        >
            <Head title="Unit Sekolah" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-xl font-extrabold text-text-primary">Daftar Unit & Lokasi GPS</h3>
                            <p className="text-sm text-text-muted">Titik koordinat geofence, radius absen, dan pengaturan presensi tiap unit.</p>
                        </div>
                        <Link href={route('unit-sekolah.create')} className="btn-primary inline-flex shrink-0 items-center gap-2">
                            <Plus className="h-4 w-4" /> Tambah Unit
                        </Link>
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard Icon={School} label="Total Unit" value={stats?.total_unit ?? 0} />
                        <StatCard Icon={Users} label="Total Pegawai" value={stats?.total_pegawai ?? 0} sub="Terdaftar di unit-unit ini" />
                        <StatCard Icon={CalendarClock} label="Total Jadwal" value={stats?.total_jadwal ?? 0} sub="Jadwal mengajar & reguler" />
                    </div>

                    {/* Table */}
                    <div className="card overflow-hidden">
                        {hasUnits ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Unit</th>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Geofence</th>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Pengaturan Presensi</th>
                                            <th className="px-6 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-text-muted">Pegawai</th>
                                            <th className="px-6 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-text-muted">Jadwal</th>
                                            <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border bg-white">
                                        {units.map((unit) => (
                                            <tr key={unit.id} className="transition-colors hover:bg-surface">
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        {unit.logo_url ? (
                                                            <img src={unit.logo_url} alt={`Logo ${unit.nama}`}
                                                                className="h-10 w-10 shrink-0 rounded-xl border border-border object-contain bg-white p-0.5" />
                                                        ) : (
                                                            <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(unit.nama)}`}>
                                                                {initials(unit.nama)}
                                                            </span>
                                                        )}
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-bold text-text-primary">{unit.nama}</p>
                                                            <p className="text-xs text-text-muted">{unit.singkatan}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <p className="font-mono text-xs text-text-secondary">
                                                        {unit.latitude}, {unit.longitude}
                                                    </p>
                                                    <span className="mt-1 inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                                        <Ruler className="h-3 w-3" /> {unit.radius_meter} m
                                                    </span>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {unit.durasi_jp && <UnitBadge Icon={Clock3} label={`${unit.durasi_jp} mnt/JP`} />}
                                                        {unit.toleransi_menit > 0 && <UnitBadge Icon={MapPin} label={`Telat ${unit.toleransi_menit} mnt`} />}
                                                        <UnitBadge Icon={Clock3} label={`Tap +${unit.toleransi_tap_menit} mnt`} />
                                                        {unit.max_jam_minggu && <UnitBadge Icon={CalendarClock} label={`Maks ${unit.max_jam_minggu} jam`} />}
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-center">
                                                    <span className="inline-flex min-w-8 items-center justify-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-extrabold text-primary tabular-nums">
                                                        {unit.pegawais_count ?? 0}
                                                    </span>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-center">
                                                    <span className="inline-flex min-w-8 items-center justify-center rounded-full bg-sky-50 px-2.5 py-1 text-xs font-extrabold text-sky-700 tabular-nums border border-sky-200">
                                                        {unit.jadwals_count ?? 0}
                                                    </span>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    <Link href={route('unit-sekolah.edit', unit.id)}
                                                        className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                                        <Pencil className="h-3.5 w-3.5" /> Edit
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
                                <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
                                    <Building2 className="h-7 w-7 text-primary" />
                                </span>
                                <h4 className="mt-4 text-base font-bold text-text-primary">Belum ada unit sekolah</h4>
                                <p className="mt-1 max-w-sm text-sm text-text-muted">
                                    Tambahkan unit pertama untuk mulai mengatur titik GPS geofence dan radius toleransi absen.
                                </p>
                                <Link href={route('unit-sekolah.create')} className="btn-primary mt-5 inline-flex items-center gap-2">
                                    <Plus className="h-4 w-4" /> Tambah Unit Pertama
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* Info footer */}
                    <div className="flex items-start gap-2 rounded-xl border border-border bg-white p-4">
                        <Globe className="mt-0.5 h-4 w-4 shrink-0 text-text-muted" />
                        <p className="text-xs leading-relaxed text-text-muted">
                            Koordinat & radius ini dipakai validasi <b>geofence</b> absen mobile (Haversine). Pegawai hanya bisa
                            presensi jika jarak GPS mereka ≤ radius dari titik pusat unit. Pengaturan waktu & toleransi dipakai
                            untuk penentuan status hadir/telat dan batas tap jadwal.
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
