import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import ProfileHero from './Partials/ProfileHero';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Head, usePage } from '@inertiajs/react';

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const Layout = AuthenticatedLayout;

    return (
        <Layout
            user={user}
            header={
                <div>
                    <h2 className="text-2xl font-bold leading-tight text-text-primary">
                        Profil Saya
                    </h2>
                    <p className="mt-1 text-sm text-text-secondary">
                        Kelola informasi akun, kata sandi, dan preferensi Anda.
                    </p>
                </div>
            }
        >
            <Head title="Profile" />

            <div className="py-10">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8 animate-fade-in-up">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <aside className="lg:sticky lg:top-24 lg:self-start">
                            <ProfileHero user={user} />
                        </aside>

                        <main className="space-y-6 lg:col-span-2">
                            <div className="rounded-2xl border border-border bg-white p-5 shadow-sm sm:p-7 transition hover:shadow-md">
                                <UpdateProfileInformationForm
                                    mustVerifyEmail={mustVerifyEmail}
                                    status={status}
                                />
                            </div>

                            <div className="rounded-2xl border border-border bg-white p-5 shadow-sm sm:p-7 transition hover:shadow-md">
                                <UpdatePasswordForm />
                            </div>

                            <div className="rounded-2xl border border-danger/40 bg-white p-5 shadow-sm sm:p-7 transition hover:shadow-md">
                                <DeleteUserForm />
                            </div>
                        </main>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
