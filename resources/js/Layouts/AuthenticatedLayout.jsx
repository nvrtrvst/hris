import { useState, useEffect } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import FlashToast from '@/Components/FlashToast';

export default function AuthenticatedLayout({ user: userProp, header, children }) {
    const { auth } = usePage().props;
    const user = userProp ?? auth.user;
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

    const permissions = auth.permissions || [];
    const role = auth.roles?.includes('superadmin')
        ? 'superadmin'
        : auth.roles?.includes('admin_unit')
            ? 'admin_unit'
            : 'pegawai';

    const menuGroups = [];

    const layananPribadi = [];
    if (!permissions.includes('view_dashboard')) layananPribadi.push({ name: 'Beranda Pribadi', href: route('dashboard'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' });
    if (!permissions.includes('view_jadwal')) layananPribadi.push({ name: 'Jadwal Pribadi', href: route('jadwal.index'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' });
    if (!permissions.includes('view_presensi')) layananPribadi.push({ name: 'Presensi Pribadi', href: route('presensi.index'), icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' });
    if (!permissions.includes('view_payroll')) layananPribadi.push({ name: 'Slip Gaji Pribadi', href: route('penggajian.index'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' });
    
    if (layananPribadi.length > 0) {
        menuGroups.push({ title: 'Layanan Pribadi', items: layananPribadi });
    }

    const modulUtama = [];
    if (permissions.includes('view_dashboard')) modulUtama.push({ name: 'Dashboard Admin', href: route('dashboard'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' });
    if (permissions.includes('view_all_units')) modulUtama.push({ name: 'Perbandingan Unit', href: route('dashboard.perbandingan-unit'), icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' });
    if (permissions.includes('view_pegawai')) modulUtama.push({ name: 'Pegawai', href: route('pegawai.index'), icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' });
    if (permissions.includes('view_jadwal')) modulUtama.push({ name: 'Jadwal', href: route('jadwal.index'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' });
    if (permissions.includes('view_presensi')) modulUtama.push({ name: 'Presensi', href: route('presensi.index'), icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' });
    if (permissions.includes('view_izin') || auth.is_approver) modulUtama.push({ name: 'Pengajuan Izin', href: route('pengajuan-izin.index'), icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.293.707l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' });

    if (modulUtama.length > 0) {
        menuGroups.push({ title: 'Modul Utama', items: modulUtama });
    }

    const modulPenggajian = [];
    if (permissions.includes('manage_master_data')) modulPenggajian.push({ name: 'Komponen Gaji', href: route('komponen-gaji.index'), icon: 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4' });
    if (permissions.includes('manage_master_data')) modulPenggajian.push({ name: 'Skala Masa Bakti', href: route('skala-masa-bakti.index'), icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' });
    if (permissions.includes('view_payroll')) modulPenggajian.push({ name: 'Run Payroll', href: route('penggajian.run'), icon: 'M13 10V3L4 14h7v7l9-11h-7z' });
    if (permissions.includes('view_payroll')) modulPenggajian.push({ name: 'Riwayat Payroll', href: route('penggajian.index'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' });
    if (permissions.includes('view_payroll')) modulPenggajian.push({ name: 'Laporan', href: route('laporan.index'), icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' });

    if (modulPenggajian.length > 0) {
        menuGroups.push({ title: 'Modul Penggajian', items: modulPenggajian });
    }

    const modulAdmin = [];
    if (permissions.includes('manage_users')) modulAdmin.push({ name: 'Manajemen User', href: route('users.index'), icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' });
    if (permissions.includes('manage_users')) modulAdmin.push({ name: 'Manajemen Role', href: route('roles.index'), icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' });
        if (permissions.includes('manage_master_data')) modulAdmin.push({ name: 'Unit Sekolah', href: route('unit-sekolah.index'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' });
        if (permissions.includes('manage_master_data')) modulAdmin.push({ name: 'Mata Pelajaran', href: route('mata-pelajaran.index'), icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' });
        if (permissions.includes('manage_master_data')) modulAdmin.push({ name: 'Unit Sekolah', href: route('unit-sekolah.index'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' });
        if (permissions.includes('manage_master_data')) modulAdmin.push({ name: 'Jabatan', href: route('jabatan.index'), icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' });
    if (permissions.includes('manage_master_data')) modulAdmin.push({ name: 'Backup DB', href: route('backup.index'), icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4' });

    if (modulAdmin.length > 0) {
        menuGroups.push({ title: 'Modul Admin', items: modulAdmin });
    }

    const pegawaiComplete = auth.pegawai_complete !== false;
    const displayGroups = pegawaiComplete ? menuGroups : [{
        title: 'Data Diri',
        items: [{ name: 'Lengkapi Data', href: route('lengkapi-data'), icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.293.707l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' }]
    }];

    return (
        <div className="min-h-screen bg-surface flex flex-col md:flex-row font-sans print:bg-white">
            
            {/* Sidebar Desktop */}
            <aside className={`hidden md:flex flex-col bg-primary text-white fixed left-0 top-0 h-full z-30 transition-all duration-300 ease-in-out border-r border-primary-800 print:hidden flex-shrink-0 ${isSidebarOpen ? 'w-64' : 'w-[72px]'}`}>
                {/* Logo */}
                <div className={`flex items-center h-16 border-b border-primary-800 px-4 flex-shrink-0 ${isSidebarOpen ? 'justify-start' : 'justify-center'}`}>
                    <Link href={route('dashboard')} className={`flex items-center group ${isSidebarOpen ? 'gap-3' : ''}`}>
                        <div className="bg-white/10 p-1.5 rounded-lg group-hover:bg-white/20 transition-colors flex-shrink-0">
                            <ApplicationLogo className="w-6 h-6 text-accent" />
                        </div>
                        {isSidebarOpen && (
                            <span className="text-base font-semibold tracking-tight text-white">HRIS <span className="text-accent">Yayasan</span></span>
                        )}
                    </Link>
                </div>

                {/* Menu Groups */}
                <div className="flex-1 overflow-y-auto py-3 px-2 space-y-5 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                    {displayGroups.map((group, index) => {
                        const isExpanded = expandedGroups[group.title];
                        const isActive = group.items.some(m => url.startsWith(new URL(m.href).pathname));
                        return (
                        <div key={index} className="space-y-0.5">
                            {isSidebarOpen && (
                                <button 
                                    onClick={() => toggleGroup(group.title)}
                                    className={`w-full flex items-center justify-between px-3 py-1.5 rounded-md transition-colors ${
                                        isActive ? 'text-accent' : 'text-gray-400 hover:text-gray-200'
                                    }`}
                                >
                                    <span className="text-[10px] font-bold uppercase tracking-[0.12em]">{group.title}</span>
                                    <svg className={`w-3 h-3 transition-transform duration-200 ${isExpanded ? '' : '-rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            )}
                            <div className={`space-y-0.5 overflow-hidden transition-all duration-300 ${isExpanded || !isSidebarOpen ? 'max-h-[600px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                {group.items.map((menu, i) => {
                                    const isLinkActive = url.startsWith(new URL(menu.href).pathname);
                                    return (
                                        <Link key={i} href={menu.href} title={menu.name}
                                            className={`flex items-center rounded-lg transition-all duration-150 group ${
                                                isSidebarOpen ? 'px-3 py-2 gap-3' : 'justify-center py-2.5'
                                            } ${
                                                isLinkActive
                                                    ? 'bg-accent/10 text-accent font-medium'
                                                    : 'text-gray-300 hover:bg-white/5 hover:text-white'
                                            }`}
                                        >
                                            <svg className={`w-[18px] h-[18px] flex-shrink-0 transition-colors ${
                                                isLinkActive ? 'text-accent' : 'text-gray-500 group-hover:text-white'
                                            } ${!isSidebarOpen && ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d={menu.icon} />
                                            </svg>
                                            {isSidebarOpen && <span className="text-sm whitespace-nowrap">{menu.name}</span>}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    )})}
                </div>

                {/* User Profile Footer */}
                <div className="p-3 border-t border-primary-800">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className={`flex items-center gap-3 p-2 rounded-lg hover:bg-white/5 transition-colors w-full ${!isSidebarOpen && 'justify-center'}`}>
                                <div className="w-8 h-8 rounded-full bg-accent/20 flex items-center justify-center font-semibold text-accent text-sm flex-shrink-0">
                                    {user.name.charAt(0)}
                                </div>
                                {isSidebarOpen && (
                                    <>
                                        <div className="text-left flex-1 min-w-0">
                                            <div className="font-medium text-white text-sm truncate">{user.name}</div>
                                            <div className={`text-[11px] mt-px ${
                                                role === 'superadmin' ? 'text-accent' :
                                                role === 'admin_unit' ? 'text-success' :
                                                'text-gray-400'
                                            }`}>
                                                {role === 'superadmin' ? 'Superadmin' :
                                                 role === 'admin_unit' ? 'Admin Unit' : 'Pegawai'}
                                            </div>
                                        </div>
                                        <svg className="w-3.5 h-3.5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                        </svg>
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

            {/* Mobile Header */}
            <div className="md:hidden bg-primary text-white flex items-center justify-between h-14 px-4 fixed top-0 w-full z-40 shadow-card print:hidden">
                <Link href={route('dashboard')} className="flex items-center gap-2">
                    <div className="bg-white/10 p-1.5 rounded-lg">
                        <ApplicationLogo className="w-5 h-5 text-accent" />
                    </div>
                    <span className="text-base font-semibold">HRIS <span className="text-accent">Yayasan</span></span>
                </Link>
                <button onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)} className="p-2 rounded-lg hover:bg-white/10 transition-colors">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {showingNavigationDropdown ? (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        ) : (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                        )}
                    </svg>
                </button>
            </div>

            {/* Mobile Drawer */}
            <div className={`md:hidden fixed inset-0 bg-primary z-30 pt-14 transform transition-transform duration-300 ease-in-out print:hidden overflow-y-auto ${showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="p-4 space-y-1">
                    {displayGroups.map((group, groupIndex) => {
                        const isExpanded = expandedGroups[group.title];
                        return (
                        <div key={groupIndex} className="mb-4">
                            <button 
                                onClick={() => toggleGroup(group.title)}
                                className="w-full flex items-center justify-between px-3 py-2 rounded-lg hover:bg-white/5 transition-colors"
                            >
                                <span className="text-[10px] font-bold text-gray-400 uppercase tracking-[0.12em]">{group.title}</span>
                                <svg className={`w-3 h-3 text-gray-400 transition-transform duration-200 ${isExpanded ? '' : '-rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div className={`space-y-0.5 overflow-hidden transition-all duration-300 ${isExpanded ? 'max-h-[800px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                {group.items.map((menu, i) => (
                                    <ResponsiveNavLink key={i} href={menu.href} active={url.startsWith(new URL(menu.href).pathname)} className="text-white hover:bg-white/10 rounded-lg">
                                        {menu.name}
                                    </ResponsiveNavLink>
                                ))}
                            </div>
                        </div>
                    )})}
                    <div className="pt-3 mt-4 border-t border-primary-800 space-y-1">
                        <div className="px-3 text-gray-400 text-sm font-medium">{user.name}</div>
                        <ResponsiveNavLink href={route('profile.edit')} className="text-white hover:text-accent rounded-lg">Profile</ResponsiveNavLink>
                        <ResponsiveNavLink method="post" href={route('logout')} as="button" className="text-white hover:text-accent rounded-lg">Log Out</ResponsiveNavLink>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className={`flex-1 transition-all duration-300 min-h-screen flex flex-col print:!ml-0 md:pt-0 pt-14 ${isSidebarOpen ? 'md:ml-64' : 'md:ml-[72px]'}`}>
                {/* Top Header Bar */}
                <header className="bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 border-b border-border sticky top-0 z-20 flex items-center h-16 print:hidden">
                    <button 
                        onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                        className="hidden md:flex ml-4 p-2 text-text-muted hover:bg-gray-100 rounded-lg transition-colors"
                        aria-label={isSidebarOpen ? 'Persempit sidebar' : 'Perluas sidebar'}
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {isSidebarOpen ? (
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                            ) : (
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            )}
                        </svg>
                    </button>
                    <div className="flex-1 px-4 sm:px-6 lg:px-8 flex items-center">
                        {header && (
                            <div className="flex items-center gap-3">
                                {header}
                            </div>
                        )}
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-4 sm:p-6 lg:p-8 animate-fade-in-up">
                    {children}
                </main>
            </div>
            <FlashToast />
        </div>
    );
}