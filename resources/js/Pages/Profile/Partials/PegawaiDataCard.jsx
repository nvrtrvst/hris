import { Button, Card, FormField, Input } from '@/Components/AdminUI';
import ComboSelect from '@/Components/ComboSelect';
import { useForm, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as idLocale } from 'date-fns/locale';
import { Briefcase, Home, IdCard, Landmark, Loader2, Pencil, Save, X } from 'lucide-react';
import { useState } from 'react';

const textareaClass =
    'block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-text-primary shadow-sm transition-colors placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10';

const jkLabel = (v) => (v === 'L' ? 'Laki-laki' : v === 'P' ? 'Perempuan' : v);

const pendidikanOptions = [
    'SD/Sederajat', 'SMP/Sederajat', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3',
].map((x) => ({ value: x, label: x }));

const bankOptions = [
    'Bank Mandiri', 'BRI', 'BNI', 'BTN', 'BSI', 'Bank BJB', 'Bank Jatim', 'Bank Muamalat', 'Lainnya',
].map((x) => ({ value: x, label: x }));

function fmtDate(value) {
    if (!value) return null;
    try {
        return format(parseISO(value), 'd MMMM yyyy', { locale: idLocale });
    } catch {
        return value;
    }
}

function filled(value) {
    return value !== null && value !== undefined && value !== '';
}

function SectionCard({ icon: Icon, title, items }) {
    const visible = items.filter((item) => filled(item.value));

    return (
        <section className="rounded-card border border-border bg-white p-4 sm:p-5">
            <h4 className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary">
                <Icon className="h-4 w-4 text-primary/60" aria-hidden />
                {title}
            </h4>
            {visible.length > 0 ? (
                <dl className="mt-3 divide-y divide-border-light">
                    {visible.map((item) => (
                        <div key={item.label} className="py-2.5">
                            <dt className="text-[11px] font-semibold uppercase tracking-wide text-text-secondary">
                                {item.label}
                            </dt>
                            <dd className="mt-0.5 break-words text-sm font-semibold text-text-primary">
                                {item.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            ) : (
                <p className="mt-3 text-xs text-text-secondary">
                    Belum diisi. Gunakan Edit Data untuk melengkapi.
                </p>
            )}
        </section>
    );
}

function EditSection({ title, children, className = '' }) {
    return (
        <section className={className}>
            <h4 className="mb-3 text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary">
                {title}
            </h4>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

export default function PegawaiDataCard() {
    const { props } = usePage();
    const user = props.auth.user;
    const pegawai = user?.pegawai;
    const statusOptions = props.statusKepegawaian || [];
    const kepegOptions = statusOptions.map((s) => ({ value: s.kode, label: s.label }));
    const kepegLabel = (value) => statusOptions.find((o) => o.kode === value)?.label ?? value;

    const primaryUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0];
    const jabatanNama = [...new Set((pegawai?.jabatans || []).map((j) => j.nama))].join(', ');

    const [editing, setEditing] = useState(false);

    const form = useForm({
        tempat_lahir: pegawai?.tempat_lahir || '',
        tanggal_lahir: pegawai?.tanggal_lahir || '',
        jenis_kelamin: pegawai?.jenis_kelamin || '',
        agama: pegawai?.agama || '',
        status_pernikahan: pegawai?.status_pernikahan || '',
        jumlah_tanggungan: pegawai?.jumlah_tanggungan ?? '',
        alamat: pegawai?.alamat || '',
        no_hp: pegawai?.no_hp || '',
        no_hp_darurat: pegawai?.no_hp_darurat || '',
        status_kepegawaian: pegawai?.status_kepegawaian || '',
        tmt_mengajar: pegawai?.tmt_mengajar || '',
        tanggal_akhir_kontrak: pegawai?.tanggal_akhir_kontrak || '',
        pendidikan_terakhir: pegawai?.pendidikan_terakhir || '',
        pendidikan_jurusan: pegawai?.pendidikan_jurusan || '',
        pendidikan_tahun_lulus: pegawai?.pendidikan_tahun_lulus || '',
        pendidikan_asal_sekolah: pegawai?.pendidikan_asal_sekolah || '',
        sk_nomor: pegawai?.sk_nomor || '',
        sk_tanggal: pegawai?.sk_tanggal || '',
        nama_bank: pegawai?.nama_bank || '',
        no_rekening: pegawai?.no_rekening || '',
        npwp: pegawai?.npwp || '',
        no_bpjs_kesehatan: pegawai?.no_bpjs_kesehatan || '',
        no_bpjs_ketenagakerjaan: pegawai?.no_bpjs_ketenagakerjaan || '',
    });

    const fid = (key) => `peg-${key}`;
    const fieldProps = (key) => ({
        id: fid(key),
        'aria-invalid': form.errors[key] ? true : undefined,
        'aria-describedby': form.errors[key] ? `${fid(key)}-error` : undefined,
    });

    const submit = (e) => {
        e.preventDefault();
        form.patch(route('profile.data.update'), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    if (!pegawai) {
        return (
            <Card className="p-5 sm:p-7">
                <p className="text-sm text-text-secondary">
                    Akun ini tidak terhubung dengan data pegawai. Hubungi admin untuk menautkannya.
                </p>
            </Card>
        );
    }

    const kontrakAktif = !pegawai?.is_tetap && pegawai?.tanggal_akhir_kontrak;
    const sisaKontrak = kontrakAktif
        ? Math.max(0, Math.ceil((new Date(pegawai.tanggal_akhir_kontrak) - new Date()) / (1000 * 60 * 60 * 24)))
        : null;

    const pendidikan = [pegawai?.pendidikan_terakhir, pegawai?.pendidikan_jurusan].filter(Boolean).join(', ');

    return (
        <Card className="p-5 sm:p-7">
            <div className="mb-5 flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-lg font-bold text-text-primary">Data Kepegawaian</h3>
                    <p className="mt-0.5 text-sm text-text-secondary">
                        Data pribadi dan kepegawaian Anda, hanya Anda yang dapat melihatnya.
                    </p>
                </div>
                <Button
                    variant={editing ? 'secondary' : 'primary'}
                    onClick={() => setEditing((v) => !v)}
                    className="shrink-0"
                >
                    {editing ? <X className="h-4 w-4" /> : <Pencil className="h-4 w-4" />}
                    {editing ? 'Batal' : 'Edit Data'}
                </Button>
            </div>

            {editing ? (
                <form onSubmit={submit} className="space-y-6">
                    <p className="rounded-lg border border-warning/30 bg-warning/10 px-4 py-2.5 text-xs font-semibold text-warning">
                        Nama dan email akun diubah pada bagian Akun Login. NIK dikelola admin. Kosongkan field yang tidak ingin diubah.
                    </p>

                    <EditSection title="Identitas">
                        <FormField label="Tempat Lahir" htmlFor={fid('tempat_lahir')} error={form.errors.tempat_lahir}>
                            <Input {...fieldProps('tempat_lahir')} value={form.data.tempat_lahir} onChange={(e) => form.setData('tempat_lahir', e.target.value)} />
                        </FormField>
                        <FormField label="Tanggal Lahir" htmlFor={fid('tanggal_lahir')} error={form.errors.tanggal_lahir}>
                            <Input {...fieldProps('tanggal_lahir')} type="date" value={form.data.tanggal_lahir} onChange={(e) => form.setData('tanggal_lahir', e.target.value)} />
                        </FormField>
                        <FormField label="Jenis Kelamin" htmlFor={fid('jenis_kelamin')} error={form.errors.jenis_kelamin}>
                            <ComboSelect
                                id={fid('jenis_kelamin')}
                                value={form.data.jenis_kelamin}
                                onChange={(v) => form.setData('jenis_kelamin', v)}
                                options={[{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }]}
                            />
                        </FormField>
                        <FormField label="Agama" htmlFor={fid('agama')} error={form.errors.agama}>
                            <ComboSelect
                                id={fid('agama')}
                                value={form.data.agama}
                                onChange={(v) => form.setData('agama', v)}
                                options={['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'].map((x) => ({ value: x, label: x }))}
                            />
                        </FormField>
                        <FormField label="Status Pernikahan" htmlFor={fid('status_pernikahan')} error={form.errors.status_pernikahan}>
                            <ComboSelect
                                id={fid('status_pernikahan')}
                                value={form.data.status_pernikahan}
                                onChange={(v) => form.setData('status_pernikahan', v)}
                                options={['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'].map((x) => ({ value: x, label: x }))}
                            />
                        </FormField>
                        <FormField label="Jumlah Tanggungan" htmlFor={fid('jumlah_tanggungan')} error={form.errors.jumlah_tanggungan}>
                            <Input {...fieldProps('jumlah_tanggungan')} type="number" min="0" value={form.data.jumlah_tanggungan} onChange={(e) => form.setData('jumlah_tanggungan', e.target.value)} />
                        </FormField>
                    </EditSection>

                    <EditSection title="Alamat dan Kontak">
                        <FormField label="Alamat KTP" htmlFor={fid('alamat')} error={form.errors.alamat} className="sm:col-span-2">
                            <textarea {...fieldProps('alamat')} rows="2" value={form.data.alamat} onChange={(e) => form.setData('alamat', e.target.value)} className={textareaClass} />
                        </FormField>
                        <FormField label="No. HP" htmlFor={fid('no_hp')} error={form.errors.no_hp}>
                            <Input {...fieldProps('no_hp')} value={form.data.no_hp} onChange={(e) => form.setData('no_hp', e.target.value)} />
                        </FormField>
                        <FormField label="No. HP Darurat" htmlFor={fid('no_hp_darurat')} error={form.errors.no_hp_darurat}>
                            <Input {...fieldProps('no_hp_darurat')} value={form.data.no_hp_darurat} onChange={(e) => form.setData('no_hp_darurat', e.target.value)} />
                        </FormField>
                    </EditSection>

                    <EditSection title="Kepegawaian">
                        <FormField label="Status Kepegawaian" htmlFor={fid('status_kepegawaian')} error={form.errors.status_kepegawaian}>
                            <ComboSelect
                                id={fid('status_kepegawaian')}
                                value={form.data.status_kepegawaian}
                                onChange={(v) => form.setData('status_kepegawaian', v)}
                                options={kepegOptions}
                            />
                        </FormField>
                        <FormField label="Mulai Kerja" htmlFor={fid('tmt_mengajar')} error={form.errors.tmt_mengajar}>
                            <Input {...fieldProps('tmt_mengajar')} type="date" value={form.data.tmt_mengajar} onChange={(e) => form.setData('tmt_mengajar', e.target.value)} />
                        </FormField>
                        {['honorer', 'kontrak', 'gtt'].includes(form.data.status_kepegawaian) && (
                            <FormField label="Akhir Kontrak" htmlFor={fid('tanggal_akhir_kontrak')} error={form.errors.tanggal_akhir_kontrak}>
                                <Input {...fieldProps('tanggal_akhir_kontrak')} type="date" value={form.data.tanggal_akhir_kontrak} onChange={(e) => form.setData('tanggal_akhir_kontrak', e.target.value)} />
                            </FormField>
                        )}
                        <FormField label="Pendidikan Terakhir" htmlFor={fid('pendidikan_terakhir')} error={form.errors.pendidikan_terakhir}>
                            <ComboSelect
                                id={fid('pendidikan_terakhir')}
                                value={form.data.pendidikan_terakhir}
                                onChange={(v) => form.setData('pendidikan_terakhir', v)}
                                options={pendidikanOptions}
                            />
                        </FormField>
                        <FormField label="Jurusan" htmlFor={fid('pendidikan_jurusan')} error={form.errors.pendidikan_jurusan}>
                            <Input {...fieldProps('pendidikan_jurusan')} value={form.data.pendidikan_jurusan} onChange={(e) => form.setData('pendidikan_jurusan', e.target.value)} />
                        </FormField>
                        <FormField label="Tahun Lulus" htmlFor={fid('pendidikan_tahun_lulus')} error={form.errors.pendidikan_tahun_lulus}>
                            <Input {...fieldProps('pendidikan_tahun_lulus')} type="number" min="1950" max="2100" value={form.data.pendidikan_tahun_lulus} onChange={(e) => form.setData('pendidikan_tahun_lulus', e.target.value)} />
                        </FormField>
                        <FormField label="Asal Sekolah" htmlFor={fid('pendidikan_asal_sekolah')} error={form.errors.pendidikan_asal_sekolah}>
                            <Input {...fieldProps('pendidikan_asal_sekolah')} value={form.data.pendidikan_asal_sekolah} onChange={(e) => form.setData('pendidikan_asal_sekolah', e.target.value)} />
                        </FormField>
                        <FormField label="No. SK" htmlFor={fid('sk_nomor')} error={form.errors.sk_nomor}>
                            <Input {...fieldProps('sk_nomor')} value={form.data.sk_nomor} onChange={(e) => form.setData('sk_nomor', e.target.value)} />
                        </FormField>
                        <FormField label="Tanggal SK" htmlFor={fid('sk_tanggal')} error={form.errors.sk_tanggal}>
                            <Input {...fieldProps('sk_tanggal')} type="date" value={form.data.sk_tanggal} onChange={(e) => form.setData('sk_tanggal', e.target.value)} />
                        </FormField>
                    </EditSection>

                    <EditSection title="Keuangan">
                        <FormField label="Nama Bank" htmlFor={fid('nama_bank')} error={form.errors.nama_bank}>
                            <ComboSelect
                                id={fid('nama_bank')}
                                value={form.data.nama_bank}
                                onChange={(v) => form.setData('nama_bank', v)}
                                options={bankOptions}
                            />
                        </FormField>
                        <FormField label="No. Rekening" htmlFor={fid('no_rekening')} error={form.errors.no_rekening}>
                            <Input {...fieldProps('no_rekening')} value={form.data.no_rekening} onChange={(e) => form.setData('no_rekening', e.target.value)} />
                        </FormField>
                        <FormField label="NPWP" htmlFor={fid('npwp')} error={form.errors.npwp}>
                            <Input {...fieldProps('npwp')} value={form.data.npwp} onChange={(e) => form.setData('npwp', e.target.value)} />
                        </FormField>
                        <FormField label="BPJS Kesehatan" htmlFor={fid('no_bpjs_kesehatan')} error={form.errors.no_bpjs_kesehatan}>
                            <Input {...fieldProps('no_bpjs_kesehatan')} value={form.data.no_bpjs_kesehatan} onChange={(e) => form.setData('no_bpjs_kesehatan', e.target.value)} />
                        </FormField>
                        <FormField label="BPJS Ketenagakerjaan" htmlFor={fid('no_bpjs_ketenagakerjaan')} error={form.errors.no_bpjs_ketenagakerjaan}>
                            <Input {...fieldProps('no_bpjs_ketenagakerjaan')} value={form.data.no_bpjs_ketenagakerjaan} onChange={(e) => form.setData('no_bpjs_ketenagakerjaan', e.target.value)} />
                        </FormField>
                    </EditSection>

                    <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
                        <Button variant="secondary" onClick={() => setEditing(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                </form>
            ) : (
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <SectionCard
                        icon={IdCard}
                        title="Identitas"
                        items={[
                            { label: 'NIK', value: pegawai?.nik_masked },
                            { label: 'NIP', value: pegawai?.nuptk },
                            { label: 'Unit', value: primaryUnit?.nama || primaryUnit?.nama_unit },
                            { label: 'Jabatan', value: jabatanNama },
                            {
                                label: 'Tempat, Tanggal Lahir',
                                value: [pegawai?.tempat_lahir, fmtDate(pegawai?.tanggal_lahir)].filter(Boolean).join(', '),
                            },
                            { label: 'Jenis Kelamin', value: jkLabel(pegawai?.jenis_kelamin) },
                            { label: 'Agama', value: pegawai?.agama },
                            { label: 'Status Pernikahan', value: pegawai?.status_pernikahan },
                            {
                                label: 'Jumlah Tanggungan',
                                value: filled(pegawai?.jumlah_tanggungan) ? `${pegawai.jumlah_tanggungan} orang` : null,
                            },
                        ]}
                    />

                    <SectionCard
                        icon={Home}
                        title="Alamat dan Kontak"
                        items={[
                            { label: 'Alamat KTP', value: pegawai?.alamat },
                            { label: 'No. HP', value: pegawai?.no_hp },
                            { label: 'No. HP Darurat', value: pegawai?.no_hp_darurat },
                        ]}
                    />

                    <SectionCard
                        icon={Briefcase}
                        title="Kepegawaian"
                        items={[
                            { label: 'Status Kepegawaian', value: kepegLabel(pegawai?.status_kepegawaian) },
                            { label: 'Mulai Kerja', value: fmtDate(pegawai?.tmt_mengajar) },
                            {
                                label: 'Akhir Kontrak',
                                value: kontrakAktif
                                    ? `${fmtDate(pegawai.tanggal_akhir_kontrak)}, sisa ${sisaKontrak} hari`
                                    : null,
                            },
                            { label: 'Pendidikan', value: pendidikan },
                            { label: 'Asal Sekolah', value: pegawai?.pendidikan_asal_sekolah },
                            { label: 'Tahun Lulus', value: pegawai?.pendidikan_tahun_lulus },
                            { label: 'No. SK', value: pegawai?.sk_nomor },
                            { label: 'Tanggal SK', value: fmtDate(pegawai?.sk_tanggal) },
                        ]}
                    />

                    <SectionCard
                        icon={Landmark}
                        title="Keuangan"
                        items={[
                            { label: 'Bank', value: pegawai?.nama_bank },
                            { label: 'No. Rekening', value: pegawai?.no_rekening },
                            { label: 'NPWP', value: pegawai?.npwp },
                            { label: 'BPJS Kesehatan', value: pegawai?.no_bpjs_kesehatan },
                            { label: 'BPJS Ketenagakerjaan', value: pegawai?.no_bpjs_ketenagakerjaan },
                        ]}
                    />
                </div>
            )}
        </Card>
    );
}
