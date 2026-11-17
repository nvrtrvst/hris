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
                    description="Kelola data kepegawaian, akun login, dan keamanan Anda."
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-24 lg:self-start">
                        <ProfileHero user={user} />
                    </aside>

                    <main className="space-y-6">
                        <PegawaiDataCard />
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                        />
                        <UpdatePasswordForm />
                        <DeleteUserForm />
                    </main>
                </div>
            </div>
        </AdminShell>
    );
}
