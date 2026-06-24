import { useState, useEffect } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';

export default function AuthenticatedLayout({ user, header, children }) {
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [isSidebarOpen, setIsSidebarOpen] = useState(true);
    const { url } = usePage();

    // Cek Role
    const role = user?.role || 'pegawai';

    const superadminMenus = [
        { name: 'Dashboard Pusat', href: route('dashboard'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
        { name: 'Unit Sekolah', href: route('unit-sekolah.index'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
        { name: 'Pegawai', href: route('pegawai.index'), icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
        { name: 'Jadwal', href: route('jadwal.index'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
        { name: 'Presensi', href: route('presensi.index'), icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'Payroll', href: route('penggajian.index'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'Laporan', href: route('laporan.index'), icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        { name: 'Backup DB', href: route('backup.index'), icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4' },
    ];

    const adminUnitMenus = [
        { name: 'Dashboard Unit', href: route('dashboard'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
        { name: 'Pegawai', href: route('pegawai.index'), icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
        { name: 'Jadwal', href: route('jadwal.index'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
        { name: 'Presensi', href: route('presensi.index'), icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'Payroll', href: route('penggajian.index'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'Laporan', href: route('laporan.index'), icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    ];

    const staffMenus = [
        { name: 'Dashboard Pribadi', href: route('dashboard'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
        { name: 'Jadwal Pribadi', href: route('jadwal.index'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
        { name: 'Riwayat Absen', href: route('presensi.index'), icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'Slip Gaji', href: route('penggajian.index'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    ];

    const menus = role === 'superadmin' ? superadminMenus : (role === 'admin_unit' ? adminUnitMenus : staffMenus);

    return (
        <div className="min-h-screen bg-surface flex flex-col md:flex-row font-sans">
            
            {/* Sidebar Desktop */}
            <aside className={`hidden md:flex flex-col bg-primary text-white shadow-xl fixed h-full z-30 transition-all duration-300 border-r border-secondary/20 ${isSidebarOpen ? 'w-64' : 'w-20'}`}>
                <div className="flex items-center justify-center h-20 border-b border-secondary/30 bg-primary/95">
                    <Link href={route('dashboard')} className={`flex items-center group ${isSidebarOpen ? 'space-x-3' : 'space-x-0'}`}>
                        <div className="bg-white p-1.5 rounded-lg shadow-lg group-hover:scale-110 transition-transform">
                            <ApplicationLogo className="w-8 h-8 text-primary" />
                        </div>
                        {isSidebarOpen && <span className="text-xl font-extrabold tracking-wider text-white">HRIS <span className="text-accent">Yayasan</span></span>}
                    </Link>
                </div>
                
                <div className="flex-1 overflow-y-auto py-6 px-4 space-y-2">
                    {isSidebarOpen && <p className="text-xs font-bold text-accent uppercase tracking-widest mb-4 px-2">Menu Utama</p>}
                    {menus.map((menu, i) => {
                        const isActive = url.startsWith(new URL(menu.href).pathname);
                        return (
                            <Link key={i} href={menu.href} title={menu.name} className={`flex items-center rounded-xl transition-all duration-200 ${isSidebarOpen ? 'px-4 py-3 space-x-3' : 'justify-center py-3'} ${isActive ? 'bg-secondary shadow-md text-white border-l-4 border-accent' : 'text-white/70 hover:bg-secondary/50 hover:text-white'}`}>
                                <svg className={`w-5 h-5 flex-shrink-0 ${!isSidebarOpen && 'mx-auto'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={menu.icon}></path></svg>
                                {isSidebarOpen && <span className="font-medium whitespace-nowrap">{menu.name}</span>}
                            </Link>
                        )
                    })}
                </div>
                
                <div className="p-4 border-t border-secondary/30 bg-primary/95">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className={`flex items-center space-x-3 p-2 rounded-xl hover:bg-secondary/50 transition-colors ${isSidebarOpen ? 'w-full text-left' : 'w-full justify-center'}`}>
                                <div className="w-10 h-10 rounded-full bg-accent flex items-center justify-center font-bold text-primary shadow-inner flex-shrink-0">
                                    {user.name.charAt(0)}
                                </div>
                                {isSidebarOpen && (
                                    <>
                                        <div className="text-left flex-1 overflow-hidden">
                                            <span className="font-medium text-white">{user.name}</span>
                                            {role === 'superadmin' ? (
                                                <span className="text-xs text-indigo-200 bg-white/10 border border-white/20 px-2 py-0.5 rounded ml-2">Yayasan</span>
                                            ) : role === 'admin_unit' ? (
                                                <span className="text-xs text-green-200 bg-white/10 border border-white/20 px-2 py-0.5 rounded ml-2">Admin Unit</span>
                                            ) : null}
                                        </div>
                                        <svg className="w-4 h-4 text-accent flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </>
                                )}
                            </button>
                        </Dropdown.Trigger>

                        <Dropdown.Content align="top" width="48">
                            <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </aside>

            {/* Mobile Header & Bottom Nav (Fallback) */}
            <div className="md:hidden bg-primary text-white flex items-center justify-between h-16 px-4 fixed top-0 w-full z-20 shadow-md">
                <Link href={route('dashboard')} className="flex items-center space-x-2">
                    <ApplicationLogo className="w-6 h-6 text-accent" />
                    <span className="text-lg font-bold">HRIS <span className="text-accent">Yayasan</span></span>
                </Link>
                <button onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)} className="p-2 rounded-md hover:bg-secondary focus:outline-none">
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {showingNavigationDropdown ? (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        ) : (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                        )}
                    </svg>
                </button>
            </div>

            {/* Mobile Dropdown Menu */}
            <div className={`md:hidden fixed inset-0 bg-primary z-10 pt-16 transform transition-transform duration-300 ${showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="p-4 space-y-2">
                    {menus.map((menu, i) => (
                        <ResponsiveNavLink key={i} href={menu.href} active={url.startsWith(new URL(menu.href).pathname)} className="text-white hover:bg-secondary">
                            {menu.name}
                        </ResponsiveNavLink>
                    ))}
                    <div className="pt-4 mt-4 border-t border-secondary/50">
                        <div className="px-4 text-white/70 text-sm mb-2">{user.name} ({user.email})</div>
                        <ResponsiveNavLink href={route('profile.edit')} className="text-white hover:text-accent">Profile</ResponsiveNavLink>
                        <ResponsiveNavLink method="post" href={route('logout')} as="button" className="text-white hover:text-accent">Log Out</ResponsiveNavLink>
                    </div>
                </div>
            </div>

            {/* Main Content Area */}
            <div className={`flex-1 transition-all duration-300 min-h-screen flex flex-col ${isSidebarOpen ? 'md:ml-64' : 'md:ml-20'} pt-16 md:pt-0`}>
                {/* Header (Top bar content area) */}
                {header && (
                    <header className="bg-white shadow-sm border-b border-border sticky top-0 md:top-0 z-20 flex items-center h-20">
                        <button 
                            onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                            className="hidden md:block ml-4 p-2 text-gray-500 hover:bg-gray-100 rounded-md focus:outline-none transition-colors"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div className="flex-1 px-4 sm:px-6 lg:px-8 flex items-center">
                            {header}
                        </div>
                    </header>
                )}

                {/* Content */}
                <main className="flex-1 p-4 sm:p-6 lg:p-8 animate-fade-in-up overflow-hidden">
                    {children}
                </main>
            </div>
        </div>
    );
}
