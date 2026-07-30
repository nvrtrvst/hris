import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import UnitForm from './UnitForm';

export default function Create({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        nama: '',
        singkatan: '',
        logo: null,
        latitude: '-6.200000',
        longitude: '106.800000',
        radius_meter: 50,
        durasi_jp: 45,
        toleransi_menit: 0,
        max_jam_minggu: 30,
        jam_masuk_kantor: '07:30',
        jam_pulang_kantor: '15:00',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('unit-sekolah.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Tambah Unit Sekolah</h2>}>
            <Head title="Tambah Unit Sekolah" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="card p-6">
                        <UnitForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={handleSubmit}
                            isEdit={false}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
