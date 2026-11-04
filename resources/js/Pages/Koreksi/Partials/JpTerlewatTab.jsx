import React, { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { id as idLocale } from 'date-fns/locale/id';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import { Button, Card, Input, Select } from '@/Components/AdminUI';
import { AlertCircle, BookOpen, CheckCircle2, Search } from 'lucide-react';

const fmtTanggal = (value) => (value ? format(new Date(value), 'd MMM yyyy', { locale: idLocale }) : '-');
const fmtJam = (value) => (value ? String(value).substring(0, 5) : '-');

const statusLabel = (value) => {
    if (value === 'belum_presensi') return 'Belum presensi';
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : '-';
};

const SumberBadge = ({ sumber, label }) => {
    const tone = sumber === 'belum_presensi'
        ? 'border-amber-200 bg-amber-50 text-amber-800'
        : sumber === 'alpa_otomatis'
            ? 'border-rose-200 bg-rose-50 text-rose-700'
            : 'border-slate-200 bg-slate-50 text-slate-700';

    return <span className={`inline-flex rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase ${tone}`}>{label}</span>;
};

const KoreksiJpModal = ({ item, onClose }) => {
    const { data, setData, post, processing, errors, reset } = useForm({
        jadwal_id: item?.jadwal_id ?? '',
        tanggal: item?.tanggal ?? '',
        jam_masuk: item?.jam_mulai ?? '',
        jam_keluar: item?.jam_selesai ?? '',
        status: 'hadir',
        alasan: '',
    });

    useEffect(() => {
        if (!item) return;
        setData({
            jadwal_id: item.jadwal_id,
            tanggal: item.tanggal,
            jam_masuk: item.jam_mulai,
            jam_keluar: item.jam_selesai,
            status: 'hadir',
            alasan: '',
        });
    }, [item]);

    const submit = (event) => {
        event.preventDefault();
        post(route('koreksi-presensi.koreksi-jp'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal show={Boolean(item)} onClose={() => { if (!processing) onClose(); }} maxWidth="lg" closeable={!processing}>
            {item && (
                <form onSubmit={submit} className="max-h-[90dvh] overflow-y-auto p-5 sm:p-6">
                    <div className="mb-5 flex items-start gap-3 border-b border-border pb-4">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <BookOpen className="h-5 w-5" />
                        </div>
                        <div className="min-w-0">
                            <h3 className="text-base font-bold text-text-primary">Koreksi JP Mengajar</h3>
                            <p className="mt-0.5 text-sm text-text-secondary">
                                {item.pegawai?.nama_lengkap || '-'} - {fmtTanggal(item.tanggal)} - {item.mapel || 'JP'} {item.kelas ? `(${item.kelas})` : ''}
                            </p>
                        </div>
                    </div>

                    <div className="mb-4 grid grid-cols-2 gap-3 rounded-card border border-border bg-surface p-3 text-sm">
                        <div>
                            <p className="text-[11px] font-semibold uppercase text-text-muted">Sumber</p>
                            <div className="mt-1"><SumberBadge sumber={item.sumber} label={item.sumber_label} /></div>
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold uppercase text-text-muted">Status lama</p>
                            <p className="mt-1 font-bold text-text-primary">{statusLabel(item.status_lama)}</p>
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold uppercase text-text-muted">Jam JP</p>
                            <p className="mt-1 font-mono font-bold text-text-primary">{item.jam_mulai}-{item.jam_selesai}</p>
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold uppercase text-text-muted">Jam lama</p>
                            <p className="mt-1 font-mono text-text-secondary">
                                {fmtJam(item.jam_masuk_lama)}-{fmtJam(item.jam_keluar_lama)}
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label htmlFor="koreksi-jp-masuk" className="form-label">Jam masuk</label>
                            <input
                                id="koreksi-jp-masuk"
                                type="time"
                                className="input-field"
                                value={data.jam_masuk}
                                onChange={(event) => setData('jam_masuk', event.target.value)}
                                required
                            />
                            {errors.jam_masuk && <p className="mt-1 text-xs text-danger">{errors.jam_masuk}</p>}
                        </div>
                        <div>
                            <label htmlFor="koreksi-jp-keluar" className="form-label">Jam keluar</label>
                            <input
                                id="koreksi-jp-keluar"
                                type="time"
                                className="input-field"
                                value={data.jam_keluar}
                                onChange={(event) => setData('jam_keluar', event.target.value)}
                                required
                            />
                            {errors.jam_keluar && <p className="mt-1 text-xs text-danger">{errors.jam_keluar}</p>}
                        </div>
                        <div>
                            <label htmlFor="koreksi-jp-status" className="form-label">Status</label>
                            <select
                                id="koreksi-jp-status"
                                className="select-field"
                                value={data.status}
                                onChange={(event) => setData('status', event.target.value)}
                            >
                                <option value="hadir">Hadir</option>
                                <option value="telat">Telat</option>
                            </select>
                            {errors.status && <p className="mt-1 text-xs text-danger">{errors.status}</p>}
                        </div>
                    </div>

                    <div className="mt-4">
                        <label htmlFor="koreksi-jp-alasan" className="form-label">Alasan koreksi</label>
                        <textarea
                            id="koreksi-jp-alasan"
                            className="input-field"
                            rows={3}
                            maxLength={500}
                            value={data.alasan}
                            onChange={(event) => setData('alasan', event.target.value)}
                            placeholder="Contoh: lupa absen JP karena mendampingi siswa."
                            required
                        />
                        {errors.alasan && <p className="mt-1 text-xs text-danger">{errors.alasan}</p>}
                    </div>

                    {(errors.jadwal_id || errors.tanggal) && (
                        <p className="mt-3 flex items-start gap-1.5 text-xs text-danger">
                            <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            {errors.jadwal_id || errors.tanggal}
                        </p>
                    )}

                    <div className="mt-5 flex justify-end gap-3 border-t border-border pt-4">
                        <Button type="button" variant="secondary" onClick={onClose} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <CheckCircle2 className="h-4 w-4" />
                            {processing ? 'Menyimpan...' : 'Simpan koreksi'}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
};

export default function JpTerlewatTab({ jpTerlewat, jpFilters, jpStats, units = [] }) {
    const [startDate, setStartDate] = useState(jpFilters?.start_date ?? '');
    const [endDate, setEndDate] = useState(jpFilters?.end_date ?? '');
    const [unitId, setUnitId] = useState(jpFilters?.unit_id ?? '');
    const [search, setSearch] = useState(jpFilters?.search ?? '');
    const [selected, setSelected] = useState(null);

    useEffect(() => {
        setStartDate(jpFilters?.start_date ?? '');
        setEndDate(jpFilters?.end_date ?? '');
        setUnitId(jpFilters?.unit_id ?? '');
        setSearch(jpFilters?.search ?? '');
    }, [jpFilters?.start_date, jpFilters?.end_date, jpFilters?.unit_id, jpFilters?.search]);

    const rows = jpTerlewat?.data ?? [];
    const filterData = {
        tab: 'jp',
        start_date: startDate,
        end_date: endDate,
        unit_id: unitId || '',
        search,
    };

    const applyFilter = (event) => {
        event?.preventDefault();
        router.get(route('koreksi-presensi.index'), filterData, { preserveState: true, preserveScroll: true, replace: true });
    };

    const resetFilter = () => {
        setStartDate('');
        setEndDate('');
        setUnitId('');
        setSearch('');
        router.get(route('koreksi-presensi.index'), { tab: 'jp' }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <div className="space-y-4">
            <Card className="p-3 sm:p-4">
                <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                    {[
                        { label: 'Total JP terlewat', value: jpStats?.total ?? 0, tone: 'text-primary' },
                        { label: 'Belum presensi', value: jpStats?.belum_presensi ?? 0, tone: 'text-amber-600' },
                        { label: 'Alpa otomatis', value: jpStats?.alpa_otomatis ?? 0, tone: 'text-rose-600' },
                        { label: 'Alpa manual', value: jpStats?.alpa_manual ?? 0, tone: 'text-slate-600' },
                    ].map(({ label, value, tone }) => (
                        <div key={label} className="rounded-lg px-2 py-2">
                            <p className={`text-lg font-bold tabular-nums ${tone}`}>{value}</p>
                            <p className="text-[10px] font-semibold text-text-secondary">{label}</p>
                        </div>
                    ))}
                </div>
            </Card>

            <Card className="grid grid-cols-1 gap-3 p-4 md:grid-cols-2 lg:grid-cols-5">
                <label className="flex flex-col gap-1 text-[11px] font-semibold text-text-secondary">
                    <span>Dari</span>
                    <Input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} />
                </label>
                <label className="flex flex-col gap-1 text-[11px] font-semibold text-text-secondary">
                    <span>Sampai</span>
                    <Input type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} />
                </label>
                {units.length > 0 && (
                    <label className="flex flex-col gap-1 text-[11px] font-semibold text-text-secondary">
                        <span>Unit</span>
                        <Select value={unitId} onChange={(event) => setUnitId(event.target.value)}>
                            <option value="">Semua unit</option>
                            {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                        </Select>
                    </label>
                )}
                <label className="flex flex-col gap-1 text-[11px] font-semibold text-text-secondary lg:col-span-1">
                    <span>Pegawai</span>
                    <span className="relative">
                        <Search className="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-text-muted" />
                        <Input className="pl-9" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama" />
                    </span>
                </label>
                <div className="flex items-end gap-2">
                    <Button type="submit" className="min-h-11 flex-1">Terapkan</Button>
                    <Button type="button" variant="secondary" className="min-h-11" onClick={resetFilter}>Reset</Button>
                </div>
            </Card>

            <div className="space-y-3 md:hidden">
                {rows.length === 0 ? (
                    <div className="card p-6 text-center text-sm text-text-muted">Tidak ada JP terlewat pada rentang ini.</div>
                ) : rows.map((item) => (
                    <div key={`${item.jadwal_id}-${item.tanggal}`} className="card p-4">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-bold text-text-primary">{item.pegawai?.nama_lengkap || '-'}</p>
                                <p className="mt-0.5 text-xs text-text-muted">{fmtTanggal(item.tanggal)} - {item.unit || '-'}</p>
                            </div>
                            <SumberBadge sumber={item.sumber} label={item.sumber_label} />
                        </div>
                        <dl className="mt-2.5 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <dt className="text-text-muted">Mapel / kelas</dt>
                                <dd className="font-semibold text-text-primary">{item.mapel || 'JP'} {item.kelas ? `- ${item.kelas}` : ''}</dd>
                            </div>
                            <div>
                                <dt className="text-text-muted">Jam JP</dt>
                                <dd className="font-mono font-bold text-text-primary">{item.jam_mulai}-{item.jam_selesai}</dd>
                            </div>
                        </dl>
                        <div className="mt-3 flex items-center justify-between gap-2">
                            <span className="text-xs text-text-muted">Status: {statusLabel(item.status_lama)}</span>
                            <button type="button" className="btn-primary btn-sm" onClick={() => setSelected(item)}>Koreksi</button>
                        </div>
                    </div>
                ))}
            </div>

            {jpTerlewat?.links?.length > 0 && rows.length > 0 && (
                <Pagination
                    links={jpTerlewat.links}
                    pagination={jpTerlewat}
                    data={filterData}
                    variant="mobile"
                    className="md:hidden"
                />
            )}

            <div className="card hidden overflow-hidden md:block">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-border">
                        <thead className="bg-surface/50">
                            <tr>
                                <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Tanggal</th>
                                <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Pegawai</th>
                                <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-text-muted">JP</th>
                                <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Jam</th>
                                <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Sumber</th>
                                <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Status Lama</th>
                                <th className="px-5 py-4 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border bg-white">
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan="7" className="px-5 py-12 text-center text-sm text-text-muted">
                                        Tidak ada JP terlewat pada rentang ini.
                                    </td>
                                </tr>
                            ) : rows.map((item) => (
                                <tr key={`${item.jadwal_id}-${item.tanggal}`} className="transition-colors hover:bg-surface/50">
                                    <td className="whitespace-nowrap px-5 py-4 text-sm font-medium text-text-primary">{fmtTanggal(item.tanggal)}</td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-bold text-text-primary">{item.pegawai?.nama_lengkap || '-'}</p>
                                        <p className="text-xs text-text-muted">{item.unit || '-'}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-medium text-text-primary">{item.mapel || 'JP'}</p>
                                        <p className="text-xs text-text-muted">{item.kelas || '-'}</p>
                                    </td>
                                    <td className="whitespace-nowrap px-5 py-4 font-mono text-sm text-text-primary">{item.jam_mulai}-{item.jam_selesai}</td>
                                    <td className="whitespace-nowrap px-5 py-4"><SumberBadge sumber={item.sumber} label={item.sumber_label} /></td>
                                    <td className="whitespace-nowrap px-5 py-4 text-sm text-text-secondary">{statusLabel(item.status_lama)}</td>
                                    <td className="whitespace-nowrap px-5 py-4 text-right">
                                        <button type="button" className="btn-primary btn-sm" onClick={() => setSelected(item)}>Koreksi</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {jpTerlewat?.links?.length > 0 && rows.length > 0 && (
                    <div className="pagination border-t border-border bg-surface/50 px-6 py-4">
                        <div className="text-sm text-text-muted">
                            Menampilkan {jpTerlewat.from}-{jpTerlewat.to} dari {jpTerlewat.total} JP
                        </div>
                        <Pagination links={jpTerlewat.links} pagination={jpTerlewat} data={filterData} />
                    </div>
                )}
            </div>

            <KoreksiJpModal item={selected} onClose={() => setSelected(null)} />
        </div>
    );
}
