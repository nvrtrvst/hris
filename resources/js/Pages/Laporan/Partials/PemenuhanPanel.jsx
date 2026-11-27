import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Button, Card, Input, Label, Select } from '@/Components/AdminUI';

const statuses = { belum_ditindaklanjuti: 'Belum ditindaklanjuti', diproses: 'Diproses', selesai: 'Selesai' };
const results = { alpa_terkonfirmasi: 'Alpa terkonfirmasi', data_dikoreksi: 'Data sudah dikoreksi', ketidakhadiran_sah: 'Ketidakhadiran sah', klarifikasi_selesai: 'Klarifikasi selesai' };
const metrics = { hadir: 'Hadir tepat waktu', telat: 'Terlambat', actual_jp: 'JP tercatat' };

function Followup({ finding, canManage, onSaved }) {
    const record = finding.record;
    const [form, setForm] = useState({
        status: record?.status || 'belum_ditindaklanjuti',
        penanggung_jawab_id: record?.penanggung_jawab_id || '',
        catatan: record?.catatan || '', hasil: record?.hasil || '',
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const abort = useRef(null);
    useEffect(() => () => abort.current?.abort(), []);
    const field = (key) => ({ value: form[key], onChange: (event) => setForm({ ...form, [key]: event.target.value }) });
    const prefix = finding.key;
    async function save(event) {
        event.preventDefault();
        setSaving(true); setError('');
        abort.current = new AbortController();
        try {
            await axios.post(route('laporan.pengawasan.tindak-lanjut'), {
                ...form, penanggung_jawab_id: form.penanggung_jawab_id ? Number(form.penanggung_jawab_id) : null,
                hasil: form.status === 'selesai' ? form.hasil : null,
                finding_key: finding.key, tanggal: finding.tanggal,
                unit_sekolah_id: finding.unit_sekolah_id, version: record?.version || 0,
            }, { signal: abort.current.signal });
            await onSaved();
        } catch (err) {
            if (!axios.isCancel(err)) setError(Object.values(err.response?.data?.errors || {}).flat().join(' ') || err.response?.data?.message || 'Gagal menyimpan. Coba lagi.');
        } finally { setSaving(false); }
    }
    return <details className="rounded-lg border border-border bg-white p-3 sm:p-4">
        <summary className="min-h-11 cursor-pointer break-words text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
            <strong>{finding.pegawai}</strong> | {finding.label} | {finding.tanggal}
            <span className="mt-1 block text-text-secondary">{finding.unit} | {statuses[record?.status || 'belum_ditindaklanjuti']}{finding.source_changed ? ' | Kondisi sumber sudah berubah' : ''}</span>
        </summary>
        <p className="my-3 break-words text-sm text-text-secondary">{finding.evidence}</p>
        {record?.hasil && <p className="text-sm">Hasil: {results[record.hasil]}</p>}
        <p className="text-sm">Penanggung jawab: {finding.penanggung_jawab || 'Belum ditetapkan'}</p>
        {canManage && <form onSubmit={save} className="mt-4 space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
                <div><Label htmlFor={prefix + '-status'}>Status</Label><Select id={prefix + '-status'} {...field('status')}>{Object.entries(statuses).map(([key, text]) => <option key={key} value={key}>{text}</option>)}</Select></div>
                <div><Label htmlFor={prefix + '-owner'}>Penanggung jawab</Label><Select id={prefix + '-owner'} {...field('penanggung_jawab_id')} required={form.status !== 'belum_ditindaklanjuti'}><option value="">Pilih petugas</option>{finding.candidates.map(person => <option key={person.id} value={person.id}>{person.name}</option>)}</Select></div>
            </div>
            <div><Label htmlFor={prefix + '-note'}>Catatan klarifikasi / hasil</Label><textarea id={prefix + '-note'} className="min-h-24 w-full rounded-lg border border-border p-3 text-base focus:ring-2 focus:ring-primary" {...field('catatan')} maxLength={2000} required={form.status === 'selesai'} /></div>
            {form.status === 'selesai' && <div><Label htmlFor={prefix + '-result'}>Hasil penyelesaian</Label><Select id={prefix + '-result'} {...field('hasil')} required><option value="">Pilih hasil</option>{Object.entries(results).filter(([key]) => key !== 'alpa_terkonfirmasi' || finding.jenis === 'belum_tercatat').map(([key, text]) => <option key={key} value={key}>{text}</option>)}</Select></div>}
            <p className="text-sm text-text-secondary">Koreksi data presensi tetap diproses melalui menu Koreksi Presensi. Menyelesaikan catatan ini tidak mengubah perhitungan gaji.</p>
            {error && <p role="alert" className="text-sm text-danger">{error}</p>}
            <Button type="submit" disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan tindak lanjut'}</Button>
        </form>}
        {!canManage && record?.catatan && <p className="mt-2 whitespace-pre-wrap text-sm">{record.catatan}</p>}
        {!!record?.history?.length && <ol className="mt-4 space-y-2 border-t border-border pt-3 text-sm">
            {record.history.map((entry, i) => <li key={i}><strong>{entry.actor}</strong> | {new Date(entry.at).toLocaleString('id-ID')} | {statuses[entry.status]}<p className="whitespace-pre-wrap text-text-secondary">{entry.catatan}</p></li>)}
        </ol>}
    </details>;
}

export default function PemenuhanPanel({ data, onSaved }) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('open');
    const summary = data.decision_summary;
    if (!summary) return null;
    const findings = data.followups.filter(f => {
        const closed = f.record?.status === 'selesai' || f.source_changed;
        return (status === 'all' || (status === 'open' ? !closed : closed)) &&
            (f.pegawai + ' ' + f.label + ' ' + f.unit).toLowerCase().includes(search.toLowerCase());
    });
    return <div className="space-y-5">
        <Card className="p-4 sm:p-5">
            <h2 className="text-lg font-semibold">Yang perlu ditindaklanjuti</h2>
            <p className="mt-1 text-sm text-text-secondary">Periode {data.period.from} sampai {data.period.to}. Temuan hari berjalan bersifat sementara; jadwal yang belum selesai tidak dihitung sebagai kekurangan.</p>
            <dl className="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                {[['Pegawai perlu klarifikasi', summary.pegawai_klarifikasi], ['Kejadian kewajiban kurang', summary.kewajiban_kurang], ['Slot kelas perlu dicek', summary.kelas_terdampak], ['Pengajuan menunggu keputusan', summary.administrasi_pending]].map(([label, value]) => <div key={label}><dt className="text-sm text-text-secondary">{label}</dt><dd className="mt-1 text-2xl font-semibold tabular-nums">{value}</dd></div>)}
            </dl>
            <p className="mt-4 border-t border-border pt-3 text-sm">{summary.belum_tercatat} hari belum tercatat | {summary.alpa_terkonfirmasi} hari alpa terkonfirmasi | {summary.kejadian} temuan terbuka</p>
        </Card>
        <Card className="p-4 sm:p-5">
            <h2 className="text-lg font-semibold">Target dan realisasi pegawai</h2>
            <p className="mt-1 text-sm text-text-secondary">Hari sah mencakup izin, sakit, cuti, dan tugas luar yang disetujui. Kuota ditampilkan per minggu penuh. Target periode menambahkan kuota hanya untuk minggu yang tercakup penuh; minggu berjalan masih sementara.</p>
            <div className="mt-3 overflow-x-auto" role="region" aria-label="Target dan realisasi pegawai" tabIndex={0}>
                <table className="w-full min-w-[700px] text-left text-sm">
                    <thead><tr>{['Pegawai / jabatan', 'Hari hadir / wajib terhitung', 'Izin / sakit / cuti / tugas luar', 'Belum jelas / pending / alpa', 'JP tercatat / jadwal', 'Kuota mingguan'].map(label => <th key={label} scope="col" className="border-b border-border p-2">{label}</th>)}</tr></thead>
                    <tbody>{data.matrix.map(row => <tr key={row.pegawai_id + '-' + row.unit_id}>
                        <th scope="row" className="border-b border-border p-2 font-medium">{row.pegawai}<span className="block text-xs text-text-secondary">{row.jabatan} | {row.unit}</span></th>
                        <td className="border-b border-border p-2">{row.realisasi_hari} / {row.target_hari} (telat {row.telat}){row.sementara ? ' (sementara)' : ''}</td>
                        <td className="border-b border-border p-2">{row.izin} / {row.sakit} / {row.cuti} / {row.tugas}</td>
                        <td className="border-b border-border p-2">{row.belum_tercatat} / {row.menunggu_keputusan} / {row.alpa_terkonfirmasi}</td>
                        <td className="border-b border-border p-2">{row.is_guru ? row.actual_jp + ' / ' + row.scheduled_jp : 'Tidak berlaku'}</td>
                        <td className="border-b border-border p-2">{row.weeks.filter(w => w.target > 0).map(w => <p key={w.from}>{w.from}: {w.realized}/{w.target}{!w.final ? ' (sementara)' : ''}{w.partial ? ' | lintas periode' : ''}</p>)}{!row.weeks.some(w => w.target > 0) && 'Tidak berlaku'}</td>
                    </tr>)}</tbody>
                </table>
            </div>
            {!data.matrix.length && <p className="py-3 text-sm">Tidak ada pegawai dalam cakupan ini.</p>}
        </Card>
        {data.comparison && <Card className="p-4 sm:p-5"><h2 className="text-lg font-semibold">Dibanding periode sebelumnya</h2><p className="text-sm text-text-secondary">{data.comparison.period.from} sampai {data.comparison.period.to}. Selisih dalam poin persentase; perbedaan hari wajib terlihat pada penyebut.</p><dl className="mt-3 grid gap-3 sm:grid-cols-3">{Object.entries(data.comparison.metrics).map(([key, value]) => <div key={key}><dt className="font-medium">{metrics[key]}</dt><dd className="text-sm">{value.current}/{value.denominator} vs {value.previous}/{value.previous_denominator} | {value.delta_pp === null ? 'Tidak berlaku' : (value.delta_pp > 0 ? '+' : '') + value.delta_pp + ' pp'}</dd></div>)}</dl></Card>}
        <Card className="p-4 sm:p-5">
            <h2 className="text-lg font-semibold">Klarifikasi dan tindak lanjut</h2>
            <div className="my-3 grid gap-3 sm:grid-cols-2"><div><Label htmlFor="followup-search">Cari pegawai atau temuan</Label><Input id="followup-search" value={search} onChange={e => setSearch(e.target.value)} /></div><div><Label htmlFor="followup-status">Tampilkan</Label><Select id="followup-status" value={status} onChange={e => setStatus(e.target.value)}><option value="open">Perlu ditangani</option><option value="closed">Selesai / sumber berubah</option><option value="all">Semua</option></Select></div></div>
            <div className="space-y-3">{findings.map(finding => <Followup key={finding.key + '-' + (finding.record?.version || 0)} finding={finding} canManage={data.can_manage_followups} onSaved={onSaved} />)}</div>
            {!findings.length && <p role="status" className="py-4 text-sm text-text-secondary">Tidak ada temuan sesuai filter.</p>}
        </Card>
    </div>;
}
