import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { PageHeader } from '@/Components/AdminUI';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import UnitForm from './UnitForm';

export default function Create({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        nama: '',
        singkatan: '',
        latitude: '-6.200000',
        longitude: '106.800000',
        radius_meter: 50,
        durasi_jp: 45,
        toleransi_menit: 0,
        toleransi_mengajar_menit: 0,
        toleransi_slide_menit: 15,
        max_jeda_sesi_menit: 120,
        wajib_pulang_mengajar: 0,
        pulang_sebelum_menit: 20,
        max_lembur_jam: null,
        max_jam_minggu: 30,
        jam_masuk_kantor: '07:30',
        jam_pulang_kantor: '15:00',
        jam_kerja_sabtu_mulai: null,
        jam_kerja_sabtu_selesai: null,
        jam_kerja_minggu_mulai: null,
        jam_kerja_minggu_selesai: null,
        web: '',
        telepon: '',
        alamat: '',
        sakit_wajib_tugas: 0,
        bobot_sakit_surat: null,
        bobot_sakit_tanpa_surat: null,
        bobot_izin: null,
        bobot_izin_setengah: null,
        bobot_cuti: null,
        bobot_alpa: null,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('unit-sekolah.store'), { forceFormData: true });
    };

    return (
        <AdminShell user={auth.user} header={<h2 className="page-title">Tambah Unit Sekolah</h2>}>
            <Head title="Tambah Unit Sekolah" />
            <div className="mx-auto max-w-5xl space-y-5">
                <Link href={route('unit-sekolah.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                    <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Unit
                </Link>
                <PageHeader
                    eyebrow="Pengaturan"
                    title="Tambah Unit Sekolah"
                    description="Lengkapi identitas, lokasi geofence, dan pengaturan presensi unit."
                />
                <UnitForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    isEdit={false}
                />
            </div>
        </AdminShell>
    );
}
