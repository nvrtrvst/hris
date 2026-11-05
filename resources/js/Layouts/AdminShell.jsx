import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Bell, ChevronDown, ChevronLeft, ChevronRight, LogOut, Megaphone, Menu, Search, User, X } from 'lucide-react';
import Avatar from '@/Components/AdminUI/ui/Avatar';
import Dropdown from '@/Components/Dropdown';
import FlashToast from '@/Components/FlashToast';
import { cn } from '@/Components/AdminUI/lib/cn';
import { buildAdminMenu } from '@/Layouts/adminMenu';

export default function AdminShell({ user: userProp, header, children }) {
    const page = usePage();
    const { auth } = page.props;
    const { url } = page;
    const user = userProp ?? auth.user;
    const { displayGroups, bottomNavItems } = React.useMemo(() => buildAdminMenu(auth), [auth]);
    const [collapsed, setCollapsed] = React.useState(false);
    const [mobileOpen, setMobileOpen] = React.useState(false);
    const [menuQuery, setMenuQuery] = React.useState('');
    const [expandedGroups, setExpandedGroups] = React.useState(() =>
        Object.fromEntries(displayGroups.map((group) => [group.title, true])),
    );

    const currentPath = (url.split('?')[0].split('#')[0] || '/').replace(/\/+$/, '') || '/';
    const pathOf = (href) => {
        try {
            return new URL(href, 'http://localhost').pathname.replace(/\/+$/, '') || '/';
        } catch {
            return '/';
        }
    };
    const isActive = (item) => {
        const target = pathOf(item.href);

        return item.exact
            ? currentPath === target
            : currentPath === target || currentPath.startsWith(`${target}/`);
    };

    const query = menuQuery.trim().toLowerCase();
    const visibleGroups = query === ''
        ? displayGroups
        : displayGroups
            .map((group) => ({
                ...group,
                items: group.items.filter((item) => item.name.toLowerCase().includes(query)),
            }))
            .filter((group) => group.items.length > 0);

    React.useEffect(() => {
        setMobileOpen(false);
        setMenuQuery('');
    }, [url]);

    React.useEffect(() => {
        if (!mobileOpen) return undefined;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [mobileOpen]);

    const role = auth.roles?.includes('superadmin')
        ? 'Superadmin'
        : auth.roles?.includes('admin_unit')
            ? 'Admin Unit'
            : auth.roles?.includes('pimpinan')
                ? 'Pimpinan'
                : 'Pegawai';
    const permissions = auth.permissions || [];
    const canSeeAnnouncements = permissions.includes('view_dashboard')
        || permissions.includes('manage_master_data')
        || permissions.includes('manage_reminders');

    const navGroups = (drawer = false) => visibleGroups.map((group, groupIndex) => {
        const expanded = query !== '' || expandedGroups[group.title];
        const groupActive = group.items.some(isActive);

        return (
            <div key={group.title} className={drawer ? 'mb-3' : 'space-y-0.5'}>
                {(!collapsed || drawer) && (
                    <button
                        type="button"
                        onClick={() => setExpandedGroups((previous) => ({ ...previous, [group.title]: !expanded }))}
                        aria-expanded={expanded}
                        aria-controls={`admin-group-${drawer ? 'drawer' : 'desktop'}-${groupIndex}`}
                        className={cn(
                            'flex min-h-11 w-full items-center justify-between rounded-lg px-3 text-left transition-colors',
                            drawer ? 'text-white/60 hover:bg-white/5' : 'text-white/45 hover:bg-white/5',
                        )}
                    >
                        <span className="text-[10px] font-bold uppercase tracking-[0.14em]">{group.title}</span>
                        {groupActive && <span className="mr-1 h-1.5 w-1.5 rounded-full bg-accent" />}
                        <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', expanded ? '' : '-rotate-90')} />
                    </button>
                )}
                <div
                    id={`admin-group-${drawer ? 'drawer' : 'desktop'}-${groupIndex}`}
                    className={cn('space-y-0.5 overflow-hidden transition-all', expanded || collapsed ? 'max-h-[800px] opacity-100' : 'max-h-0 opacity-0')}
                >
                    {group.items.map((item) => {
                        const active = isActive(item);

                        return (
                            <Link
                                key={`${item.name}-${item.href}`}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'group flex min-h-11 items-center gap-3 rounded-lg px-3 text-xs font-semibold transition-colors',
                                    drawer ? 'text-sm' : '',
                                    collapsed && !drawer ? 'justify-center px-2' : '',
                                    active
                                        ? 'bg-white/10 text-white'
                                        : 'text-white/65 hover:bg-white/5 hover:text-white',
                                )}
                            >
                                <svg
                                    className={cn('h-[18px] w-[18px] shrink-0 transition-colors', active ? 'text-accent' : 'text-white/45 group-hover:text-white')}
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d={item.icon} />
                                </svg>
                                {(!collapsed || drawer) && <span className="truncate">{item.name}</span>}
                            </Link>
                        );
                    })}
                </div>
            </div>
        );
    });

    const sidebarContent = (drawer = false) => (
        <>
            <div className={cn('flex h-16 shrink-0 items-center border-b border-white/10 px-4', collapsed && !drawer ? 'justify-center px-2' : '')}>
                <Link href={route('dashboard')} className="flex min-w-0 items-center gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-xs font-extrabold text-accent ring-1 ring-white/15">NM</span>
                    {(!collapsed || drawer) && (
                        <span className="min-w-0">
                            <span className="block truncate text-sm font-bold text-white">Nuurul Muttaqiin</span>
                            <span className="block text-[9px] font-bold uppercase tracking-[0.18em] text-white/45">HRIS Admin</span>
                        </span>
                    )}
                </Link>
                <button
                    type="button"
                    onClick={() => setMobileOpen(false)}
                    className="ml-auto flex h-9 w-9 items-center justify-center rounded-lg text-white/65 hover:bg-white/10 hover:text-white lg:hidden"
                    aria-label="Tutup menu"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            {(!collapsed || drawer) && (
                <div className="px-3 pt-3">
                    <label className="relative block">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/35" />
                        <input
                            value={menuQuery}
                            onChange={(event) => setMenuQuery(event.target.value)}
                            placeholder="Cari menu..."
                            aria-label="Cari menu"
                            className="h-9 w-full rounded-lg border border-white/10 bg-white/5 pl-9 pr-3 text-xs text-white placeholder:text-white/35 focus:border-accent/50 focus:outline-none focus:ring-2 focus:ring-accent/30"
                        />
                    </label>
                </div>
            )}

            <nav className="sidebar-scroll flex-1 space-y-4 overflow-y-auto px-3 py-3">
                {navGroups(drawer)}
                {query !== '' && visibleGroups.length === 0 && (
                    <p className="px-3 py-2 text-xs text-white/45">Menu tidak ditemukan.</p>
                )}
            </nav>

            <div className="border-t border-white/10 p-3">
                <Link href={route('profile.edit')} className={cn('flex items-center gap-3 rounded-xl p-2 hover:bg-white/5', collapsed && !drawer ? 'justify-center' : '')}>
                    <Avatar src={user.pegawai?.foto_url} name={user.name} className="h-8 w-8" textClassName="text-[10px]" />
                    {(!collapsed || drawer) && (
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-xs font-bold text-white">{user.name}</span>
                            <span className="block truncate text-[10px] text-white/45">{role}</span>
                        </span>
                    )}
                </Link>
            </div>
        </>
    );

    return (
        <div className="flex min-h-screen bg-surface font-sans">
            <div
                className={cn('fixed inset-0 z-40 bg-black/40 backdrop-blur-[2px] lg:hidden', mobileOpen ? '' : 'hidden')}
                onClick={() => setMobileOpen(false)}
                role="presentation"
            />

            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col bg-primary text-white transition-transform duration-200 lg:sticky lg:bottom-auto lg:left-auto lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0 lg:self-start',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                    collapsed ? 'lg:w-[76px]' : 'lg:w-[260px]',
                )}
            >
                {sidebarContent(false)}
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <button
                        type="button"
                        onClick={() => setMobileOpen(true)}
                        className="flex h-10 w-10 items-center justify-center rounded-lg text-text-secondary hover:bg-surface hover:text-primary lg:hidden"
                        aria-label="Buka menu"
                    >
                        <Menu className="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setCollapsed((value) => !value)}
                        className="hidden h-10 w-10 items-center justify-center rounded-lg text-text-secondary hover:bg-surface hover:text-primary lg:flex"
                        aria-label={collapsed ? 'Perluas sidebar' : 'Persempit sidebar'}
                    >
                        {collapsed ? <ChevronRight className="h-5 w-5" /> : <ChevronLeft className="h-5 w-5" />}
                    </button>

                    <div className="min-w-0 flex-1">{header ?? <span className="text-sm font-bold text-primary">Admin</span>}</div>

                    <Link
                        href={route('notifications.index')}
                        className="relative flex h-10 w-10 items-center justify-center rounded-lg text-text-secondary hover:bg-surface hover:text-primary"
                        aria-label="Notifikasi"
                    >
                        <Bell className="h-4 w-4" />
                        {auth.unread_notifications > 0 && (
                            <span className="absolute right-1 top-1 flex min-h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[8px] font-bold leading-none text-white ring-2 ring-white">
                                {auth.unread_notifications > 99 ? '99+' : auth.unread_notifications}
                            </span>
                        )}
                    </Link>
                    {canSeeAnnouncements && (
                        <Link
                            href={route('pengumuman.index')}
                            className="relative hidden h-10 w-10 items-center justify-center rounded-lg text-text-secondary hover:bg-surface hover:text-primary sm:flex"
                            aria-label="Pengumuman"
                        >
                            <Megaphone className="h-4 w-4" />
                            {auth.announcement_count > 0 && (
                                <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-accent ring-2 ring-white" />
                            )}
                        </Link>
                    )}

                    <Dropdown closeOnOutsideClick>
                        <Dropdown.Trigger>
                            <button type="button" className="flex items-center gap-2 rounded-full border border-border bg-white p-1 pr-2 hover:bg-surface">
                                <Avatar src={user.pegawai?.foto_url} name={user.name} className="h-8 w-8" textClassName="text-[10px]" />
                                <span className="hidden text-left sm:block">
                                    <span className="block text-[11px] font-bold leading-tight text-primary">{user.name}</span>
                                    <span className="block text-[9px] leading-tight text-text-muted">{role}</span>
                                </span>
                                <ChevronDown className="hidden h-3 w-3 text-text-muted sm:block" />
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content align="right" width="48">
                            <Dropdown.Link href={route('profile.edit')}>
                                <span className="flex items-center gap-2"><User className="h-3.5 w-3.5" /> Profil Saya</span>
                            </Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                <span className="flex items-center gap-2 text-rose-600"><LogOut className="h-3.5 w-3.5" /> Keluar</span>
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </header>

                <main className={cn('flex-1 p-4 sm:p-6 lg:p-8', bottomNavItems.length >= 2 && 'pb-24 lg:pb-8')}>
                    {children}
                </main>
            </div>

            {bottomNavItems.length >= 2 && (
                <nav aria-label="Navigasi utama" className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
                    <div className="flex items-stretch justify-around">
                        {bottomNavItems.map((item) => {
                            const active = item.active();

                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 py-1.5 text-[10px] font-bold transition-colors',
                                        active ? 'text-primary' : 'text-text-secondary',
                                    )}
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
