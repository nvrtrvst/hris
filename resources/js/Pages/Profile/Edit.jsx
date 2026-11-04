import AdminShell from '@/Layouts/AdminShell';
import { PageHeader } from '@/Components/AdminUI';
import DeleteUserForm from './Partials/DeleteUserForm';
import PegawaiDataCard from './Partials/PegawaiDataCard';
import ProfileHero from './Partials/ProfileHero';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Head, usePage } from '@inertiajs/react';

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    return (
        <AdminShell
            user={user}
            header={<h2 className="page-title">Profil Saya</h2>}
        >
            <Head title="Profile" />

            <div className="mx-auto w-full max-w-6xl space-y-6">
                    <PageHeader
                        eyebrow="Akun"
                        title="Profil Saya"
                        description="Kelola informasi akun, kata sandi, dan preferensi Anda."
                    />
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <aside className="lg:sticky lg:top-24 lg:self-start">
                            <ProfileHero user={user} />
                        </aside>

                        <main className="space-y-6 lg:col-span-2">
                            <PegawaiDataCard />

                            <div className="card p-5 sm:p-7">
                                <UpdateProfileInformationForm
                                    mustVerifyEmail={mustVerifyEmail}
                                    status={status}
                                />
                            </div>

                            <div className="card p-5 sm:p-7">
                                <UpdatePasswordForm />
                            </div>

                            <div className="card p-5 sm:p-7 border-danger/40">
                                <DeleteUserForm />
                            </div>
                        </main>
                    </div>
            </div>
        </AdminShell>
    );
}
