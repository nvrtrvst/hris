import { useState, useEffect } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import FlashToast from '@/Components/FlashToast';

export default function AuthenticatedLayout({ user, header, children }) {
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [isSidebarOpen, setIsSidebarOpen] = useState(true);
    const { url } = usePage();
    const [expandedGroups, setExpandedGroups] = useState({
        'Layanan Pribadi': true,
        'Modul Utama': true,
        'Modul Penggajian': true,
        'Modul Admin': true
    });

    const toggleGroup = (title) => {
        setExpandedGroups(prev => ({
            ...prev,
            [title]: !prev[title]
        }));
    };

    const { auth } = usePage().props;
    const permissions = auth.permissions || [];
    const role = user?.role || 'pegawai';

    // Groups of menus
    const menuGroups = [];

    // 1. Layanan Pribadi (Selalu Muncul untuk Semua User)
    const layananPribadi = [];
    if (!permissions.includes('view_dashboard')) layananPribadi.push({ name: 'Beranda Pribadi', href: route('dashboard'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' });
    if (!permissions.includes('view_jadwal')) layananPribadi.push({ name: 'Jadwal Pribadi', href: route('jadwal.index'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' });
    if (!permissions.includes('view_presensi')) layananPribadi.push({ name: 'Presensi Pribadi', href: route('presensi.index'), icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' });
    if (!permissions.includes('view_payroll')) layananPribadi.push({ name: 'Slip Gaji Pribadi', href: route('penggajian.index'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' });
    
    if (layananPribadi.length > 0) {
        menuGroups.push({ title: 'Layanan Pribadi', items: layananPribadi });
    }

    // 2. Modul Utama (HR)
    const modulUtama = [];
    if (permissions.includes('view_dashboard')) modulUtama.push({ name: 'Dashboard Admin', href: route('dashboard'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' });
    if (permissions.includes('view_pegawai')) modulUtama.push({ name: 'Pegawai', href: route('pegawai.index'), icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' });
    if (permissions.includes('view_jadwal')) modulUtama.push({ name: 'Jadwal', href: route('jadwal.index'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' });
    if (permissions.includes('view_presensi')) modulUtama.push({ name: 'Presensi', href: route('presensi.index'), icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' });
    if (permissions.includes('view_izin')) modulUtama.push({ name: 'Pengajuan Izin', href: route('pengajuan-izin.index'), icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' });

    if (modulUtama.length > 0) {
        menuGroups.push({ title: 'Modul Utama', items: modulUtama });
    }

    // 3. Modul Penggajian
    const modulPenggajian = [];
    if (permissions.includes('manage_master_data')) modulPenggajian.push({ name: 'Komponen Gaji', href: route('komponen-gaji.index'), icon: 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4' });
    if (permissions.includes('manage_master_data')) modulPenggajian.push({ name: 'Skala Masa Bakti', href: route('skala-masa-bakti.index'), icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' });
    if (permissions.includes('view_payroll')) modulPenggajian.push({ name: 'Run Payroll', href: route('penggajian.run'), icon: 'M13 10V3L4 14h7v7l9-11h-7z' });
    if (permissions.includes('view_payroll')) modulPenggajian.push({ name: 'Riwayat Payroll', href: route('penggajian.index'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' });
    if (permissions.includes('view_payroll')) modulPenggajian.push({ name: 'Laporan', href: route('laporan.index'), icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' });

    if (modulPenggajian.length > 0) {
        menuGroups.push({ title: 'Modul Penggajian', items: modulPenggajian });
    }

    // 4. Modul Admin
    const modulAdmin = [];
    if (permissions.includes('manage_users')) modulAdmin.push({ name: 'Manajemen User', href: route('users.index'), icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' });
    if (permissions.includes('manage_users')) modulAdmin.push({ name: 'Manajemen Role', href: route('roles.index'), icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' });
    if (permissions.includes('manage_master_data')) modulAdmin.push({ name: 'Unit Sekolah', href: route('unit-sekolah.index'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' });
    if (permissions.includes('manage_master_data')) modulAdmin.push({ name: 'Backup DB', href: route('backup.index'), icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4' });

    if (modulAdmin.length > 0) {
        menuGroups.push({ title: 'Modul Admin', items: modulAdmin });
    }

    return (
        <div className="min-h-screen bg-surface flex flex-col md:flex-row font-sans print:bg-white">
            
            {/* Sidebar Desktop */}
            <aside className={`hidden md:flex flex-col bg-primary text-white shadow-2xl fixed h-full z-30 transition-all duration-300 border-r border-primary print:hidden ${isSidebarOpen ? 'w-64' : 'w-20'}`}>
                <div className="flex items-center justify-center h-20 border-b border-white/10 bg-primary/95 px-4 flex-shrink-0">
                    <Link href={route('dashboard')} className={`flex items-center group w-full ${isSidebarOpen ? 'space-x-3' : 'justify-center'}`}>
                        <div className="bg-white p-1.5 rounded-lg shadow-sm group-hover:scale-105 transition-transform flex-shrink-0">
                            <ApplicationLogo className="w-7 h-7 text-primary" />
                        </div>
                        {isSidebarOpen && <span className="text-xl font-bold tracking-wide text-white truncate">HRIS <span className="text-accent">Yayasan</span></span>}
                    </Link>
                </div>
                
                <div className="flex-1 overflow-y-auto py-4 px-3 space-y-6">
                    {menuGroups.map((group, index) => {
                        const isExpanded = expandedGroups[group.title];
                        return (
                        <div key={index} className="space-y-1">
                            {isSidebarOpen && (
                                <button 
                                    onClick={() => toggleGroup(group.title)}
                                    className="w-full flex items-center justify-between px-3 py-1.5 mb-1 hover:bg-white/5 rounded-lg transition-colors focus:outline-none group"
                                >
                                    <span className="text-[11px] font-bold text-gray-400 group-hover:text-gray-300 uppercase tracking-wider">{group.title}</span>
                                    <svg className={`w-3.5 h-3.5 text-gray-400 group-hover:text-gray-300 transition-transform duration-300 ${isExpanded ? '' : '-rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            )}
                            <div className={`space-y-1 overflow-hidden transition-all duration-300 ${isExpanded || !isSidebarOpen ? 'max-h-[800px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                {group.items.map((menu, i) => {
                                    const isActive = url.startsWith(new URL(menu.href).pathname);
                                    return (
                                        <Link key={i} href={menu.href} title={menu.name} className={`flex items-center rounded-lg transition-all duration-200 group ${isSidebarOpen ? 'px-3 py-2.5 space-x-3' : 'justify-center py-3'} ${isActive ? 'bg-white/10 text-accent font-semibold shadow-inner' : 'text-gray-300 hover:bg-white/5 hover:text-white'}`}>
                                            <svg className={`w-5 h-5 flex-shrink-0 transition-colors ${isActive ? 'text-accent' : 'text-gray-400 group-hover:text-white'} ${!isSidebarOpen && 'mx-auto'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={menu.icon}></path></svg>
                                            {isSidebarOpen && <span className="whitespace-nowrap text-sm">{menu.name}</span>}
                                        </Link>
                                    )
                                })}
                            </div>
                        </div>
                    )})}
                </div>
                
                <div className="p-4 border-t border-white/10 bg-primary/95">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className={`flex items-center space-x-3 p-2 rounded-lg hover:bg-white/10 transition-colors ${isSidebarOpen ? 'w-full text-left' : 'w-full justify-center'}`}>
                                <div className="w-9 h-9 rounded-full bg-accent flex items-center justify-center font-bold text-primary shadow-sm flex-shrink-0">
                                    {user.name.charAt(0)}
                                </div>
                                {isSidebarOpen && (
                                    <>
                                        <div className="text-left flex-1 overflow-hidden">
                                            <div className="font-semibold text-white text-sm truncate">{user.name}</div>
                                            {role === 'superadmin' ? (
                                                <div className="text-xs text-accent mt-0.5 truncate">Superadmin</div>
                                            ) : role === 'admin_unit' ? (
                                                <div className="text-xs text-green-300 mt-0.5 truncate">Admin Unit</div>
                                            ) : (
                                                <div className="text-xs text-gray-400 mt-0.5 truncate">Pegawai</div>
                                            )}
                                        </div>
                                        <svg className="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></svg>
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
            <div className="md:hidden bg-primary text-white flex items-center justify-between h-16 px-4 fixed top-0 w-full z-40 shadow-md print:hidden">
                <Link href={route('dashboard')} className="flex items-center space-x-2">
                    <div className="bg-white p-1 rounded">
                        <ApplicationLogo className="w-5 h-5 text-primary" />
                    </div>
                    <span className="text-lg font-bold">HRIS <span className="text-accent">Yayasan</span></span>
                </Link>
                <button onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)} className="p-2 rounded-md hover:bg-white/10 focus:outline-none transition-colors">
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
            <div className={`md:hidden fixed inset-0 bg-primary z-30 pt-16 transform transition-transform duration-300 print:hidden ${showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="p-4 space-y-1 h-full overflow-y-auto">
                    {menuGroups.map((group, groupIndex) => {
                        const isExpanded = expandedGroups[group.title];
                        return (
                        <div key={groupIndex} className="mb-4">
                            <button 
                                onClick={() => toggleGroup(group.title)}
                                className="w-full flex items-center justify-between px-4 py-2 mb-1 rounded-lg hover:bg-white/5 focus:outline-none group"
                            >
                                <span className="text-[11px] font-bold text-gray-400 uppercase tracking-wider">{group.title}</span>
                                <svg className={`w-4 h-4 text-gray-400 transition-transform duration-300 ${isExpanded ? '' : '-rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div className={`space-y-1 overflow-hidden transition-all duration-300 ${isExpanded ? 'max-h-[800px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                {group.items.map((menu, i) => (
                                    <ResponsiveNavLink key={i} href={menu.href} active={url.startsWith(new URL(menu.href).pathname)} className="text-white hover:bg-white/10 rounded-lg">
                                        {menu.name}
                                    </ResponsiveNavLink>
                                ))}
                            </div>
                        </div>
                    )})}
                    <div className="pt-2 mt-4 border-t border-white/10">
                        <div className="px-4 text-gray-300 text-sm mb-2 font-medium">{user.name} ({user.email})</div>
                        <ResponsiveNavLink href={route('profile.edit')} className="text-white hover:text-accent rounded-lg">Profile</ResponsiveNavLink>
                        <ResponsiveNavLink method="post" href={route('logout')} as="button" className="text-white hover:text-accent rounded-lg">Log Out</ResponsiveNavLink>
                    </div>
                </div>
            </div>

            {/* Main Content Area */}
            <div className={`flex-1 transition-all duration-300 min-h-screen flex flex-col print:!pl-0 ${isSidebarOpen ? 'md:pl-64' : 'md:pl-20'} pt-16 md:pt-0`}>
                {/* Header (Top bar content area) */}
                <header className="bg-white shadow-sm border-b border-border sticky top-0 z-20 flex items-center h-16 md:h-20 print:hidden">
                    <button 
                        onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                        className="hidden md:flex ml-4 p-2 text-gray-500 hover:bg-gray-100 rounded-lg focus:outline-none transition-colors items-center justify-center"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div className="flex-1 px-4 sm:px-6 lg:px-8 flex items-center">
                        {header}
                    </div>
                </header>

                {/* Content */}
                <main className="flex-1 p-4 sm:p-6 lg:p-8 animate-fade-in-up overflow-hidden">
                    {children}
                </main>
            </div>
            <FlashToast />
        </div>
    );
}
