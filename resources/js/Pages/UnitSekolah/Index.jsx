import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Badge, Card, EmptyState, PageHeader, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/AdminUI';
import { Head, Link } from '@inertiajs/react';
import { Building2, CalendarClock, Clock3, Globe, MapPin, Pencil, Plus, Ruler, School, Users } from 'lucide-react';
import StatCard from '@/Components/StatCard';
import { avatarTone, initials } from '@/Utils/avatar';

function UnitBadge({ Icon, label }) {
    return (
        <Badge variant="neutral">
            <Icon className="h-3 w-3" />
            {label}
        </Badge>
    );
}

export default function Index({ auth, units, stats }) {
    const hasUnits = (units || []).length > 0;

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Kelola Unit Sekolah</h2>}
        >
            <Head title="Unit Sekolah" />

            <div className="mx-auto max-w-7xl space-y-5">
                <PageHeader
                    eyebrow="Pengaturan"
                    title="Daftar Unit & Lokasi GPS"
                    description="Atur titik geofence, radius absen, dan pengaturan presensi tiap unit."
                    actions={
                        <Link href={route('unit-sekolah.create')} className="inline-flex h-10 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-bold text-white transition-colors hover:bg-primary-600">
                            <Plus className="h-4 w-4" /> Tambah Unit
                        </Link>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard Icon={School} label="Total Unit" value={stats?.total_unit ?? 0} />
                    <StatCard Icon={Users} label="Total Pegawai" value={stats?.total_pegawai ?? 0} sub="Terdaftar di unit-unit ini" />
                    <StatCard Icon={CalendarClock} label="Total Jadwal" value={stats?.total_jadwal ?? 0} sub="Jadwal mengajar & reguler" />
                </div>

                <Card className="overflow-hidden">
                    {hasUnits ? (
                        <>
                        <Table className="hidden md:block">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Unit</TableHead>
                                    <TableHead className="hidden lg:table-cell">Geofence</TableHead>
                                    <TableHead className="hidden lg:table-cell">Pengaturan Presensi</TableHead>
                                    <TableHead className="text-center">Pegawai</TableHead>
                                    <TableHead className="hidden text-center sm:table-cell">Jadwal</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {units.map((unit) => (
                                    <TableRow key={unit.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                {unit.logo_url ? (
                                                    <img src={unit.logo_url} alt={`Logo ${unit.nama}`} className="h-10 w-10 shrink-0 rounded-xl border border-border bg-white object-contain p-0.5" />
                                                ) : (
                                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(unit.nama)}`}>
                                                        {initials(unit.nama)}
                                                    </span>
                                                )}
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-bold text-primary">{unit.nama}</p>
                                                    <p className="text-xs text-text-muted">{unit.singkatan}</p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            <p className="font-mono text-xs text-text-secondary">{unit.latitude}, {unit.longitude}</p>
                                            <Badge variant="success" className="mt-1">
                                                <Ruler className="h-3 w-3" /> {unit.radius_meter} m
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            <div className="flex flex-wrap gap-1.5">
                                                {unit.durasi_jp && <UnitBadge Icon={Clock3} label={`${unit.durasi_jp} mnt/JP`} />}
                                                {unit.toleransi_menit > 0 && <UnitBadge Icon={MapPin} label={`Telat ${unit.toleransi_menit} mnt`} />}
                                                <UnitBadge Icon={Clock3} label={`Slide +${unit.toleransi_slide_menit} mnt`} />
                                                {unit.max_jam_minggu && <UnitBadge Icon={CalendarClock} label={`Maks ${unit.max_jam_minggu} jam`} />}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <span className="inline-flex min-w-8 items-center justify-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-extrabold text-primary tabular-nums">
                                                {unit.pegawais_count ?? 0}
                                            </span>
                                        </TableCell>
                                        <TableCell className="hidden text-center sm:table-cell">
                                            <span className="inline-flex min-w-8 items-center justify-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-extrabold text-sky-700 tabular-nums">
                                                {unit.jadwals_count ?? 0}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Link href={route('unit-sekolah.edit', unit.id)} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                                <Pencil className="h-3.5 w-3.5" /> Edit
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="divide-y divide-border md:hidden">
                            {units.map((unit) => (
                                <div key={unit.id} className="p-4">
                                    <div className="flex items-start gap-3">
                                        {unit.logo_url ? (
                                            <img src={unit.logo_url} alt={`Logo ${unit.nama}`} className="h-10 w-10 shrink-0 rounded-xl border border-border bg-white object-contain p-0.5" />
                                        ) : (
                                            <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(unit.nama)}`}>
                                                {initials(unit.nama)}
                                            </span>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-bold text-primary">{unit.nama}</p>
                                            <p className="text-xs text-text-muted">{unit.singkatan}</p>
                                            <p className="mt-1 font-mono text-[11px] text-text-secondary">{unit.latitude}, {unit.longitude}</p>
                                        </div>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-1.5">
                                        <Badge variant="success"><Ruler className="h-3 w-3" /> {unit.radius_meter} m</Badge>
                                        <Badge variant="neutral"><Users className="h-3 w-3" /> {unit.pegawais_count ?? 0} pegawai</Badge>
                                        <Badge variant="info"><CalendarClock className="h-3 w-3" /> {unit.jadwals_count ?? 0} jadwal</Badge>
                                    </div>
                                    <Link href={route('unit-sekolah.edit', unit.id)} className="mt-3 flex h-10 w-full items-center justify-center gap-1.5 rounded-lg border border-border bg-white text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                        <Pencil className="h-3.5 w-3.5" /> Edit Unit
                                    </Link>
                                </div>
                            ))}
                        </div>
                        </>
                    ) : (
                        <EmptyState
                            icon={Building2}
                            title="Belum ada unit sekolah"
                            description="Tambahkan unit pertama untuk mulai mengatur titik GPS geofence dan radius toleransi absen."
                            action={
                                <Link href={route('unit-sekolah.create')} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-bold text-white hover:bg-primary-600">
                                    <Plus className="h-3.5 w-3.5" /> Tambah Unit Pertama
                                </Link>
                            }
                        />
                    )}
                </Card>

                <Card className="flex items-start gap-2 p-4">
                    <Globe className="mt-0.5 h-4 w-4 shrink-0 text-text-muted" />
                    <p className="text-xs leading-relaxed text-text-muted">
                        Koordinat dan radius dipakai validasi <b>geofence</b> absen mobile (Haversine). Pegawai hanya bisa presensi jika jarak GPS mereka di dalam radius dari titik pusat unit. Pengaturan waktu dan toleransi dipakai untuk status hadir/telat serta batas slide jadwal.
                    </p>
                </Card>
            </div>
        </AdminShell>
    );
}
