import { Mail, BadgeCheck, Briefcase } from 'lucide-react';

function initials(name) {
    if (!name) return '?';
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('');
}

export default function ProfileHero({ user }) {
    const verified = !!user?.email_verified_at;

    const jabatan =
        user?.pegawai?.jabatans?.find((j) => j.pivot?.is_primary === 1)?.nama ||
        user?.pegawai?.jabatans?.[0]?.nama;

    const foto = user?.pegawai?.foto_url;

    return (
        <section className="relative overflow-hidden rounded-card bg-gradient-to-br from-primary via-primary-light to-primary-dark text-white shadow-elevated">
            <div
                aria-hidden
                className="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-accent/20 blur-3xl"
            />
            <div
                aria-hidden
                className="absolute -bottom-16 -left-10 h-56 w-56 rounded-full bg-white/10 blur-3xl"
            />

            <div className="relative px-6 py-8 sm:px-10 sm:py-10">
                <div className="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                    {foto ? (
                        <img
                            src={foto}
                            alt={user?.name}
                            className="h-20 w-20 shrink-0 rounded-full object-cover ring-2 ring-white/20"
                        />
                    ) : (
                        <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-card bg-white/10 ring-2 ring-white/20 backdrop-blur">
                            <span className="text-2xl font-bold tracking-tight">
                                {initials(user?.name)}
                            </span>
                        </div>
                    )}

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl break-words">
                                {user?.name ?? 'Pengguna'}
                            </h1>
                            {verified && (
                                <span className="badge inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-medium backdrop-blur">
                                    <BadgeCheck className="h-3.5 w-3.5" /> Terverifikasi
                                </span>
                            )}
                        </div>

                        <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/80">
                            <span className="inline-flex items-center gap-1.5">
                                <Mail className="h-3.5 w-3.5" />
                                {user?.email ?? '-'}
                            </span>
                            {jabatan && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Briefcase className="h-3.5 w-3.5" />
                                    {jabatan}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
