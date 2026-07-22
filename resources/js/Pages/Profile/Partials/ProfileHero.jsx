import { ShieldCheck, Mail, BadgeCheck } from 'lucide-react';

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

    return (
        <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary-light to-primary-dark text-white shadow-lg">
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
                    <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-2 ring-white/20 backdrop-blur">
                        <span className="text-2xl font-bold tracking-tight">
                            {initials(user?.name)}
                        </span>
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="truncate text-2xl font-bold tracking-tight sm:text-3xl">
                                {user?.name ?? 'Pengguna'}
                            </h1>
                            {verified && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-medium backdrop-blur">
                                    <BadgeCheck className="h-3.5 w-3.5" /> Terverifikasi
                                </span>
                            )}
                        </div>

                        <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/80">
                            <span className="inline-flex items-center gap-1.5">
                                <Mail className="h-3.5 w-3.5" />
                                {user?.email ?? '-'}
                            </span>
                            {user?.roles?.length > 0 && (
                                <span className="inline-flex items-center gap-1.5">
                                    <ShieldCheck className="h-3.5 w-3.5" />
                                    {user.roles.join(', ')}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
