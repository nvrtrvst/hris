import { useState, useEffect } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { buildAdminMenu } from '@/Layouts/adminMenu';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import FlashToast from '@/Components/FlashToast';
import { Bell, Megaphone } from 'lucide-react';

const ACTIVE_ICON = 'text-accent';

export default function AuthenticatedLayout({ user: userProp, header, children }) {
    const { auth } = usePage().props;
    const user = userProp ?? auth.user;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [drawerGroups, setDrawerGroups] = useState({});
    const [isSidebarOpen, setIsSidebarOpen] = useState(true);
    const [menuQuery, setMenuQuery] = useState('');
    const { url } = usePage();
    const [expandedGroups, setExpandedGroups] = useState({
        'Layanan Pribadi': true,
        'Beranda': true,
        'Kepegawaian': true,
        'Piket': true,
        'Siswa': true,
        'Penggajian': true,
        'Laporan': true,
        'Pengaturan': false
    });

    const toggleGroup = (title) => {
        setExpandedGroups(prev => ({
            ...prev,
            [title]: !prev[title]
        }));
    };

    const role = auth.roles?.includes('superadmin')
        ? 'superadmin'
        : auth.roles?.includes('admin_unit')
            ? 'admin_unit'
            : auth.roles?.includes('tu_kependidikan')
                ? 'tu_kependidikan'
                : 'pegawai';

    const currentPath = (url.split('?')[0].split('#')[0] || '/').replace(/\/+$/, '') || '/';
    const pathOf = (href) => {
        try {
            return new URL(href, 'http://localhost').pathname.replace(/\/+$/, '') || '/';
        } catch {
            return '/';
        }
    };
    const isMenuActive = (menu) => {
        const target = pathOf(menu.href);

        return menu.exact
            ? currentPath === target
            : currentPath === target || currentPath.startsWith(`${target}/`);
    };

    const { displayGroups, bottomNavItems } = buildAdminMenu(auth);
    const showBottomNav = bottomNavItems.length >= 2;

    useEffect(() => {
        setShowingNavigationDropdown(false);
        setMenuQuery('');
    }, [url]);

    useEffect(() => {
        if (!showingNavigationDropdown) return undefined;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [showingNavigationDropdown]);

    const query = menuQuery.trim().toLowerCase();
    const visibleGroups = query === ''
        ? displayGroups
        : displayGroups
            .map((group) => ({
                ...group,
                items: group.items.filter((menu) => menu.name.toLowerCase().includes(query)),
            }))
            .filter((group) => group.items.length > 0);

    return (
        <div className="min-h-screen bg-surface flex flex-col md:flex-row font-sans print:bg-white">

            {/* Sidebar Desktop */}
            <aside className={`hidden md:flex flex-col bg-primary text-white fixed left-0 top-0 h-full z-30 transition-all duration-300 ease-in-out border-r border-primary-800 print:hidden flex-shrink-0 ${isSidebarOpen ? 'w-[220px]' : 'w-[72px]'}`}>
                {/* Logo */}
                <div className={`flex items-center h-16 border-b border-primary-800 flex-shrink-0 ${isSidebarOpen ? 'justify-start px-4' : 'justify-center px-0'}`}>
                    <Link href={route('dashboard')} className={`flex items-center group ${isSidebarOpen ? 'gap-3' : ''}`}>
                        <ApplicationLogo className={`text-accent transition-all duration-300 flex-shrink-0 ${isSidebarOpen ? 'w-12 h-12' : 'w-9 h-9'}`} />
                        {isSidebarOpen && (
                            <span className="text-xl font-semibold tracking-tight text-white">HRIS <span className="text-accent">Yayasan</span></span>
                        )}
                    </Link>
                </div>

                {/* Menu Groups */}
                <div className="flex-1 overflow-y-auto py-3 px-2 space-y-5 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                    {isSidebarOpen && (
                        <div className="relative px-1">
                            <svg className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                            </svg>
                            <input
                                type="search"
                                value={menuQuery}
                                onChange={(e) => setMenuQuery(e.target.value)}
                                placeholder="Cari menu…"
                                aria-label="Cari menu"
                                className="w-full rounded-md bg-white/5 py-2 pl-8 pr-3 text-xs text-white placeholder:text-gray-500 ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-accent/60"
                            />
                        </div>
                    )}
                    {visibleGroups.map((group, index) => {
                        const isExpanded = query !== '' ? true : expandedGroups[group.title];
                        const isActive = group.items.some(isMenuActive);
                        return (
                            <div key={index} className="space-y-0.5">
                                {isSidebarOpen && (
                                    <button
                                        onClick={() => toggleGroup(group.title)}
                                        aria-expanded={isExpanded}
                                        aria-controls={`sidebar-group-${index}`}
                                        className={`w-full flex items-center justify-between px-3 py-1.5 rounded-md transition-colors ${isActive ? 'text-accent' : 'text-gray-400 hover:text-gray-200'
                                            }`}
                                    >
                                        <span className="text-[10px] font-bold uppercase tracking-[0.12em]">{group.title}</span>
                                        <svg className={`w-3 h-3 transition-transform duration-200 ${isExpanded ? '' : '-rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                )}
                                <div id={`sidebar-group-${index}`} className={`space-y-0.5 overflow-hidden transition-all duration-300 ${isExpanded || !isSidebarOpen ? 'max-h-[600px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                    {group.items.map((menu, i) => {
                                        const isLinkActive = isMenuActive(menu);
                                        return (
                                            <Link key={i} href={menu.href} title={menu.name}
                                                className={`flex items-center rounded-lg transition-all duration-150 group ${isSidebarOpen ? 'px-3 py-2 gap-3' : 'justify-center py-2.5'
                                                    } ${isLinkActive
                                                        ? 'bg-white/15 text-white font-semibold shadow-sm'
                                                        : 'text-gray-300 hover:bg-white/5 hover:text-white'
                                                    }`}
                                            >
                                                <svg className={`w-[18px] h-[18px] flex-shrink-0 transition-colors ${isLinkActive ? 'text-accent' : 'text-gray-500 group-hover:text-white'
                                                    } ${!isSidebarOpen && ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d={menu.icon} />
                                                </svg>
                                                {isSidebarOpen && <span className="text-sm whitespace-nowrap">{menu.name}</span>}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        )
                    })}
                    {isSidebarOpen && query !== '' && visibleGroups.length === 0 && (
                        <p className="px-3 py-2 text-xs text-gray-400">Menu tidak ditemukan.</p>
                    )}
                </div>

                {/* User Profile Footer */}
                <div className="p-3 border-t border-primary-800">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className={`flex items-center gap-3 p-2 rounded-lg hover:bg-white/5 transition-colors w-full ${!isSidebarOpen && 'justify-center'}`}>
                                {user.pegawai?.foto_url ? (
                                    <img src={user.pegawai.foto_url} alt={user.name} className="w-8 h-8 rounded-full object-cover flex-shrink-0" />
                                ) : (
                                    <div className="w-8 h-8 rounded-full bg-accent/20 flex items-center justify-center font-semibold text-accent text-sm flex-shrink-0">
                                        {user.name.charAt(0)}
                                    </div>
                                )}
                                {isSidebarOpen && (
                                    <>
                                        <div className="text-left flex-1 min-w-0">
                                            <div className="font-medium text-white text-sm truncate">{user.name}</div>
                                            <div className={`text-[11px] mt-px truncate ${role === 'superadmin' ? 'text-accent' :
                                                role === 'admin_unit' ? 'text-success' :
                                                    role === 'tu_kependidikan' ? 'text-sky-300' :
                                                    'text-gray-400'
                                                }`}>
                                                {role === 'superadmin'
                                                    ? 'Superadmin'
                                                    : role === 'admin_unit'
                                                        ? 'Admin Unit'
                                                        : role === 'tu_kependidikan'
                                                            ? 'TU Kependidikan'
                                                            : ([...new Set((user.pegawai?.jabatans || []).map((j) => j.nama))].join(', ') || 'Pegawai')}
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
            <div className="md:hidden fixed inset-x-0 top-0 z-40 bg-primary pt-[env(safe-area-inset-top)] text-white shadow-card print:hidden">
                <div className="flex h-14 items-center justify-between px-3">
                    <Link href={route('dashboard')} className="flex min-w-0 items-center gap-2">
                        <div className="rounded-lg bg-white/10 p-1.5">
                            <ApplicationLogo className="h-5 w-5 text-accent" />
                        </div>
                        <span className="truncate text-sm font-semibold">HRIS <span className="text-accent">Yayasan</span></span>
                    </Link>
                    <div className="flex shrink-0 items-center gap-2">
                        <Link href={route('notifications.index')} className="relative flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-white/10 transition-colors" aria-label="Notifikasi" title="Notifikasi">
                            <Bell className="h-5 w-5" />
                            {auth?.unread_notifications > 0 && (
                                <span className="absolute right-1 top-1 flex min-h-[16px] min-w-[16px] items-center justify-center rounded-full bg-rose-500 px-1 text-[8px] font-bold leading-none text-white ring-2 ring-primary">
                                    {auth?.unread_notifications > 99 ? '99+' : auth?.unread_notifications}
                                </span>
                            )}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                            aria-expanded={showingNavigationDropdown}
                            aria-controls="mobile-menu"
                            className="flex min-h-11 items-center gap-1.5 rounded-lg px-2.5 text-xs font-bold hover:bg-white/10 transition-colors"
                        >
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                {showingNavigationDropdown ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                )}
                            </svg>
                            Menu
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile Drawer */}
            {showingNavigationDropdown && (
                <div className="md:hidden fixed inset-0 z-20 bg-black/40" onClick={() => setShowingNavigationDropdown(false)} role="presentation" />
            )}
            <div
                id="mobile-menu"
                className={`md:hidden fixed inset-y-0 left-0 z-30 w-[min(18rem,calc(100vw-3rem))] overflow-y-auto overscroll-contain bg-primary pb-[env(safe-area-inset-bottom)] pt-[calc(3.5rem+env(safe-area-inset-top))] transform transition-transform duration-300 ease-in-out print:hidden ${showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'}`}
            >
                <div className="p-3 space-y-1">
                    <div className="relative px-1 pb-2">
                        <svg className="pointer-events-none absolute left-3 top-[18px] h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                        </svg>
                        <input
                            type="search"
                            value={menuQuery}
                            onChange={(e) => setMenuQuery(e.target.value)}
                            placeholder="Cari menu…"
                            aria-label="Cari menu"
                            className="w-full rounded-lg bg-white/5 py-2.5 pl-9 pr-3 text-sm text-white placeholder:text-gray-500 ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-accent/60"
                        />
                    </div>
                    {visibleGroups.map((group, groupIndex) => {
                        const isExpanded = query !== '' ? true : (drawerGroups[group.title] ?? false);
                        return (
                            <div key={groupIndex} className="mb-3">
                                <button
                                    type="button"
                                    onClick={() => setDrawerGroups((prev) => ({ ...prev, [group.title]: !isExpanded }))}
                                    aria-expanded={isExpanded}
                                    aria-controls={`drawer-group-${groupIndex}`}
                                    className="flex min-h-11 w-full items-center justify-between rounded-lg px-3 hover:bg-white/5 transition-colors"
                                >
                                    <span className="text-[11px] font-bold uppercase tracking-[0.12em] text-gray-400">{group.title}</span>
                                    <svg className={`h-3.5 w-3.5 text-gray-400 transition-transform duration-200 ${isExpanded ? '' : '-rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div id={`drawer-group-${groupIndex}`} className={`space-y-0.5 overflow-hidden transition-all duration-300 ${isExpanded ? 'max-h-[800px] opacity-100' : 'max-h-0 opacity-0'}`}>
                                    {group.items.map((menu, i) => {
                                        const isActive = isMenuActive(menu);
                                        return (
                                            <Link key={i} href={menu.href}
                                                className={`flex min-h-11 items-center px-3 rounded-lg text-sm font-medium transition-all duration-150 ${isActive
                                                    ? 'bg-white/15 text-white font-semibold shadow-sm'
                                                    : 'text-gray-300 hover:bg-white/5 hover:text-white'
                                                    }`}
                                            >
                                                {menu.name}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        )
                    })}
                    {query !== '' && visibleGroups.length === 0 && (
                        <p className="px-3 py-2 text-sm text-gray-400">Menu tidak ditemukan.</p>
                    )}
                    <div className="pt-3 mt-4 border-t border-primary-800 space-y-1">
                        <div className="px-3 text-gray-400 text-sm font-medium">{user.name}</div>
                        <ResponsiveNavLink href={route('profile.edit')} className="text-white hover:text-accent rounded-lg">Profile</ResponsiveNavLink>
                        <ResponsiveNavLink method="post" href={route('logout')} as="button" className="text-white hover:text-accent rounded-lg">Log Out</ResponsiveNavLink>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className={`flex-1 transition-all duration-300 min-h-screen flex flex-col print:!ml-0 md:pt-0 pt-[calc(3.5rem+env(safe-area-inset-top))] ${showBottomNav ? 'pb-[calc(4rem+env(safe-area-inset-bottom))] md:pb-0' : ''} ${isSidebarOpen ? 'md:ml-[220px]' : 'md:ml-[72px]'}`}>
                {/* Top Header Bar (desktop) */}
                <header className="hidden md:flex bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 border-b border-border sticky top-0 z-20 items-center h-16 print:hidden">
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
                    <div className="flex-1 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            {header}
                        </div>
                        <div className="flex items-center gap-2">
                            <Link href={route('notifications.index')} className="relative p-2 text-text-muted hover:text-primary transition-colors rounded-lg hover:bg-gray-100" title="Notifikasi">
                                <Bell className="h-5 w-5" />
                                {auth?.unread_notifications > 0 && (
                                    <span className="absolute -right-0.5 -top-0.5 flex min-h-[18px] min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold leading-none text-white ring-2 ring-white">
                                        {auth?.unread_notifications > 99 ? '99+' : auth?.unread_notifications}
                                    </span>
                                )}
                            </Link>
                        </div>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-4 sm:p-6 lg:p-8">
                    {header && <div className="mb-4 md:hidden">{header}</div>}
                    {children}
                </main>
            </div>

            {showBottomNav && (
                <nav
                    aria-label="Navigasi utama"
                    className="md:hidden fixed inset-x-0 bottom-0 z-30 border-t border-border bg-white pb-[env(safe-area-inset-bottom)] print:hidden"
                >
                    <div className="flex items-stretch justify-around">
                        {bottomNavItems.map((item) => {
                            const active = item.active();
                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={`flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 py-1.5 text-[11px] font-semibold transition-colors ${active ? 'text-primary' : 'text-text-secondary'}`}
                                >
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.6" d={item.icon} />
                                    </svg>
                                    {item.name}
                                </Link>
                            );
                        })}
                    </div>
                </nav>
            )}

            <FlashToast />
        </div>
    );
}
