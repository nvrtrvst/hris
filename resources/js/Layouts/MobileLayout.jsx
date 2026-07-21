import { Link, usePage } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashToast from '@/Components/FlashToast';

const navItems = [
    { route: 'presensi.dashboard', label: 'Beranda', match: () => route().current('presensi.dashboard'), icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" {...p} />) },
    { route: 'presensi.jadwal', label: 'Jadwal', match: () => route().current('presensi.jadwal*'), icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" {...p} />) },
    { route: 'presensi.absen', label: 'Presensi', match: () => route().current('presensi.absen'), icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" {...p} />) },
    { route: 'presensi.izin.index', label: 'Izin', match: () => route().current('presensi.izin.*'), icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" {...p} />) },
    { route: 'presensi.profile.edit', label: 'Profil', match: () => route().current('presensi.profile.*'), icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" {...p} />) },
];

export default function MobileLayout({ user, header, children }) {
    const { url } = usePage();

    return (
        <div className="mx-auto min-h-[100dvh] max-w-md bg-[#f4f7f5] pb-24 font-sans font-antialiased text-slate-900 shadow-[0_0_0_1px_rgba(15,23,42,0.04)]">
            <header className="sticky top-0 z-20 border-b border-emerald-950/10 bg-primary px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] text-white">
                <div className="flex min-h-11 items-center justify-between">
                    <div className="flex items-center space-x-3">
                        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <ApplicationLogo className="h-5 w-5 text-white" />
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-100">Portal Pegawai</p>
                            <p className="text-sm font-bold leading-tight">Presensi Yayasan</p>
                        </div>
                    </div>
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="flex min-h-11 min-w-11 items-center justify-center rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="Buka menu akun">
                                <div className="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl border border-white/20 bg-white/10 text-sm font-bold text-white">
                                    {user?.pegawai?.foto_url ? (
                                        <img src={user.pegawai.foto_url} alt="" width="36" height="36" className="h-full w-full object-cover" />
                                    ) : (
                                        user?.name ? user.name.charAt(0).toUpperCase() : 'P'
                                    )}
                                </div>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content align="right" width="48">
                            <Dropdown.Link href={route('presensi.logout')} method="post" as="button" className="text-red-600 font-bold flex items-center">
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                Keluar
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </header>

            {/* Page Header (Optional) */}
            {header && (
                <div className="px-4 pt-5">
                    <h2 className="text-xl font-bold tracking-tight text-slate-900">{header}</h2>
                </div>
            )}

            <main className="px-4 pb-8 pt-5">
                {children}
            </main>

            <nav className="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-md border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md" aria-label="Navigasi utama">
                <div className="flex h-16 items-stretch justify-between px-1">
                    {navItems.map((item) => {
                        const active = item.match(url);
                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={`relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 py-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 ${
                                    active ? 'text-primary' : 'text-slate-500'
                                }`}
                                aria-current={active ? 'page' : undefined}
                            >
                                {active && (
                                    <span className="absolute inset-x-4 top-0 h-0.5 rounded-full bg-primary" />
                                )}
                                <span>
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        {item.icon({ strokeLinecap: 'round', strokeLinejoin: 'round' })}
                                    </svg>
                                </span>
                                <span className="truncate text-[10px] font-semibold">{item.label}</span>
                            </Link>
                        );
                    })}
                </div>
            </nav>

            <FlashToast />
        </div>
    );
}
