import { BadgeCheck, Building2, Briefcase, Mail } from 'lucide-react';

function initials(name) {
    if (!name) return '?';
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('');
}

function Meta({ icon: Icon, children }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <Icon className="h-3.5 w-3.5 shrink-0" aria-hidden />
            <span className="break-words">{children}</span>
        </span>
    );
}

export default function ProfileHero({ user }) {
    const verified = !!user?.email_verified_at;
    const pegawai = user?.pegawai;
    const jabatan = [...new Set((pegawai?.jabatans || []).map((j) => j.nama))].join(', ');
    const primaryUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0];
    const foto = pegawai?.foto_url;

    return (
        <section className="rounded-card border border-primary-800 bg-primary p-6 text-white sm:p-7">
            <div className="flex items-start gap-4">
                {foto ? (
                    <img
                        src={foto}
                        alt=""
                        className="h-16 w-16 shrink-0 rounded-full object-cover ring-2 ring-white/20"
                    />
                ) : (
                    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-white/10 text-xl font-bold tracking-tight">
                        {initials(user?.name)}
                    </div>
                )}

                <div className="min-w-0 flex-1">
                    <h2 className="text-xl font-bold leading-tight break-words">{user?.name ?? 'Pengguna'}</h2>
                    {verified && (
                        <p className="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-white/85">
                            <BadgeCheck className="h-3.5 w-3.5" aria-hidden /> Email terverifikasi
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-5 space-y-2 border-t border-white/15 pt-4 text-sm text-white/85">
                <Meta icon={Mail}>{user?.email ?? 'Email belum diisi'}</Meta>
                <Meta icon={Building2}>{primaryUnit?.nama || 'Unit belum ditetapkan'}</Meta>
                <Meta icon={Briefcase}>{jabatan || 'Jabatan belum ditetapkan'}</Meta>
            </div>
        </section>
    );
}
