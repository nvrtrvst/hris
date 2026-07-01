import { Link, usePage } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashToast from '@/Components/FlashToast';

export default function MobileLayout({ user, header, children }) {
    const { url } = usePage();

    return (
        <div className="bg-gray-50 min-h-screen pb-20 font-sans font-antialiased text-gray-900 max-w-md mx-auto shadow-2xl relative overflow-hidden">
            {/* Top Bar */}
            <div className="bg-indigo-600 text-white p-4 flex items-center justify-between rounded-b-2xl shadow-md z-10 relative">
                <div className="flex items-center space-x-3">
                    <div className="bg-white p-1.5 rounded-full shadow-inner">
                        <ApplicationLogo className="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 className="font-bold text-lg leading-tight">HRIS Mobile</h1>
                        <p className="text-indigo-200 text-xs">Portal Pegawai</p>
                    </div>
                </div>
                {/* Profile Dropdown */}
                <div className="flex items-center">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="flex items-center space-x-2 focus:outline-none">
                                <div className="w-9 h-9 rounded-full bg-indigo-800 border-2 border-indigo-400 flex items-center justify-center font-bold text-sm shadow-sm text-white hover:bg-indigo-700 transition-colors overflow-hidden">
                                    {user?.pegawai?.foto ? (
                                        <img src={user.pegawai.foto} alt="Profile" className="w-full h-full object-cover" />
                                    ) : (
                                        user?.name ? user.name.charAt(0).toUpperCase() : 'P'
                                    )}
                                </div>
                            </button>
                        </Dropdown.Trigger>

                        <Dropdown.Content align="right" width="48">
                            <Dropdown.Link href={route('logout')} method="post" as="button" className="text-red-600 font-bold flex items-center">
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
                    <h2 className="font-bold text-xl text-gray-800">{header}</h2>
                </div>
            )}

            {/* Main Content Area */}
            <main className="p-4 animate-fade-in-up">
                {children}
            </main>

            {/* Bottom Navigation Bar */}
            <div className="fixed bottom-0 w-full max-w-md bg-white border-t border-gray-200 flex items-center h-16 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-20 pb-safe">
                <Link href={route('mobile.dashboard')} className={`flex flex-col items-center justify-center flex-1 h-full space-y-1 ${url === '/mobile' || url === '/mobile/dashboard' ? 'text-indigo-600' : 'text-gray-400 hover:text-indigo-400'}`}>
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span className="text-[10px] font-semibold">Beranda</span>
                </Link>

                <Link href={route('mobile.jadwal')} className={`flex flex-col items-center justify-center flex-1 h-full space-y-1 ${url.startsWith('/mobile/jadwal') ? 'text-indigo-600' : 'text-gray-400 hover:text-indigo-400'}`}>
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span className="text-[10px] font-semibold">Jadwal</span>
                </Link>

                <Link href={route('mobile.absen')} className={`flex flex-col items-center justify-center flex-1 h-full space-y-1 ${url === '/mobile/absen' ? 'text-indigo-600' : 'text-gray-400 hover:text-indigo-400'}`}>
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span className="text-[10px] font-semibold">Presensi</span>
                </Link>

                <Link href={route('mobile.izin.index')} className={`flex flex-col items-center justify-center flex-1 h-full space-y-1 ${url.startsWith('/mobile/izin') ? 'text-indigo-600' : 'text-gray-400 hover:text-indigo-400'}`}>
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span className="text-[10px] font-semibold">Izin/Cuti</span>
                </Link>

                <Link href={route('mobile.profile.edit')} className={`flex flex-col items-center justify-center flex-1 h-full space-y-1 ${url.startsWith('/mobile/profile') ? 'text-indigo-600' : 'text-gray-400 hover:text-indigo-400'}`}>
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span className="text-[10px] font-semibold">Profil</span>
                </Link>
            </div>

            <style dangerouslySetInnerHTML={{__html: `
                @keyframes fadeInUp {
                    from { opacity: 0; transform: translateY(15px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-fade-in-up {
                    animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                .pb-safe {
                    padding-bottom: env(safe-area-inset-bottom);
                }
            `}} />
            <FlashToast />
        </div>
    );
}
