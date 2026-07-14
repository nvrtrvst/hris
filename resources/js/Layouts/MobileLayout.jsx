import { Link, usePage } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashToast from '@/Components/FlashToast';

const navItems = [
    { route: 'mobile.dashboard', label: 'Beranda', match: (u) => u === '/mobile' || u === '/mobile/dashboard', icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" {...p} />) },
    { route: 'mobile.jadwal', label: 'Jadwal', match: (u) => u.startsWith('/mobile/jadwal'), icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" {...p} />) },
    { route: 'mobile.absen', label: 'Presensi', match: (u) => u === '/mobile/absen', icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" {...p} />) },
    { route: 'mobile.izin.index', label: 'Izin', match: (u) => u.startsWith('/mobile/izin'), icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" {...p} />) },
    { route: 'mobile.profile.edit', label: 'Profil', match: (u) => u.startsWith('/mobile/profile'), icon: (p) => (<path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" {...p} />) },
];

export default function MobileLayout({ user, header, children }) {
    const { url } = usePage();

    return (
        <div className="min-h-screen pb-24 font-sans font-antialiased text-gray-900 max-w-md mx-auto relative overflow-hidden bg-transparent">
            {/* Ambient 3D background */}
            <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-b from-emerald-50/90 via-slate-50 to-white" />
                <div className="absolute -left-16 -top-16 h-64 w-64 rounded-full bg-emerald-300/30 blur-3xl animate-float" />
                <div className="absolute -right-10 top-1/3 h-72 w-72 rounded-full bg-teal-300/25 blur-3xl animate-float-slow" />
                <div className="absolute bottom-10 left-1/4 h-56 w-56 rounded-full bg-accent/20 blur-3xl animate-float" />
            </div>

            {/* Top Bar — gradient, rounded, elevated */}
            <div className="relative z-10 bg-gradient-to-br from-emerald-500 to-primary text-white px-4 pt-4 pb-7 rounded-b-[2.2rem] shadow-[0_12px_30px_-10px_rgba(15,61,62,0.55)]">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                        <div className="bg-white/20 p-1.5 rounded-2xl shadow-inner backdrop-blur">
                            <ApplicationLogo className="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h1 className="font-extrabold text-lg leading-tight tracking-tight">HRIS Mobile</h1>
                            <p className="text-indigo-100/90 text-[11px] font-medium">Portal Pegawai</p>
                        </div>
                    </div>
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="flex items-center focus:outline-none">
                                <div className="w-10 h-10 rounded-2xl bg-white/25 border border-white/40 flex items-center justify-center font-extrabold text-sm shadow-lg text-white backdrop-blur hover:bg-white/30 transition-colors overflow-hidden">
                                    {user?.pegawai?.foto_url ? (
                                        <img src={user.pegawai.foto_url} alt="Profile" className="w-full h-full object-cover" />
                                    ) : (
                                        user?.name ? user.name.charAt(0).toUpperCase() : 'P'
                                    )}
                                </div>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content align="right" width="48">
                            <Dropdown.Link href={route('mobile.logout')} method="post" as="button" className="text-red-600 font-bold flex items-center">
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                Log Out
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </div>

            {/* Page Header (Optional) */}
            {header && (
                <div className="px-4 pt-4">
                    <h2 className="font-extrabold text-xl text-gray-800">{header}</h2>
                </div>
            )}

            {/* Main Content Area */}
            <main className="px-4 pb-6 pt-4 animate-fade-in-up">
                {children}
            </main>

            {/* Floating Glass Bottom Navigation */}
            <div className="fixed bottom-5 left-1/2 z-30 w-[calc(100%-2rem)] max-w-[22rem] -translate-x-1/2">
                <div className="flex items-center justify-between rounded-[1.8rem] bg-white/80 px-2 py-2 shadow-[0_12px_30px_-8px_rgba(15,61,62,0.35)] ring-1 ring-white/60 backdrop-blur-xl">
                    {navItems.map((item) => {
                        const active = item.match(url);
                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={`relative flex flex-1 flex-col items-center justify-center space-y-1 rounded-2xl py-2 transition-all ${
                                    active ? 'text-primary' : 'text-slate-400 hover:text-emerald-500'
                                }`}
                            >
                                {active && (
                                    <span className="absolute inset-x-1.5 inset-y-0.5 -z-0 rounded-2xl bg-emerald-100/80 shadow-[inset_0_2px_6px_-2px_rgba(15,61,62,0.4)]" />
                                )}
                                <span className={`relative z-10 transition-transform duration-300 ${active ? '-translate-y-0.5' : ''}`}>
                                    <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        {item.icon({ strokeLinecap: 'round', strokeLinejoin: 'round' })}
                                    </svg>
                                </span>
                                <span className={`relative z-10 text-[10px] font-bold transition-all ${active ? 'opacity-100' : 'opacity-80'}`}>{item.label}</span>
                            </Link>
                        );
                    })}
                </div>
            </div>

            <FlashToast />
        </div>
    );
}
