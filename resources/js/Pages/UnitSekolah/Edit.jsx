import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import UnitForm from './UnitForm';

export default function Edit({ auth, unit }) {
    const { data, setData, post, processing, errors } = useForm({
        _method: 'put',
        nama: unit.nama,
        singkatan: unit.singkatan,
        logo: null,
        latitude: String(unit.latitude),
        longitude: String(unit.longitude),
        radius_meter: unit.radius_meter,
        durasi_jp: unit.durasi_jp || 45,
        toleransi_menit: unit.toleransi_menit ?? 0,
        max_jam_minggu: unit.max_jam_minggu ?? 30,
        jam_masuk_kantor: unit.jam_masuk_kantor?.slice(0, 5) || '07:30',
        jam_pulang_kantor: unit.jam_pulang_kantor?.slice(0, 5) || '15:00',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('unit-sekolah.update', unit.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Edit Unit: {unit.nama}</h2>}
        >
            <Head title={`Edit Unit - ${unit.nama}`} />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="card p-6">
                        <UnitForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={handleSubmit}
                            isEdit={true}
                            unitName={unit.nama}
                            unitLogoUrl={unit.logo_url}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
