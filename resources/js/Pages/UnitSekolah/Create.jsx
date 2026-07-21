import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import UnitForm from './UnitForm';

export default function Create({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        nama: '',
        singkatan: '',
        latitude: '-6.200000',
        longitude: '106.800000',
        radius_meter: 50,
        jam_masuk_kantor: '07:30',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('unit-sekolah.store'));
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800">Tambah Unit Sekolah</h2>}>
            <Head title="Tambah Unit Sekolah" />
            <div className="py-12">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl p-6">
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
