import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { initPush, disablePush, sendTestPush } from '@/push';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashToast from '@/Components/FlashToast';
import { Bell, CalendarDays, Clock, CreditCard, FileText, Home, LogOut, Megaphone, User } from 'lucide-react';

const navItems = [
    { route: 'presensi.dashboard', label: 'Beranda', match: () => route().current('presensi.dashboard'), icon: Home },
    { route: 'presensi.jadwal', label: 'Jadwal', match: () => route().current('presensi.jadwal*'), icon: CalendarDays },
    { route: 'presensi.absen', label: 'Presensi', match: () => route().current('presensi.absen'), icon: Clock },
    { route: 'presensi.izin.index', label: 'Izin', match: () => route().current('presensi.izin.*'), icon: FileText },
    { route: 'presensi.profile.edit', label: 'Profil', match: () => route().current('presensi.profile.*'), icon: User },
];

export default function MobileLayout({ user, header, children }) {
    const { url, props } = usePage();
    const auth = props.auth;
    const primaryUnit = user?.pegawai?.units?.find((unit) => unit.pivot?.is_primary) ?? user?.pegawai?.units?.[0];
    const unitLabel = primaryUnit?.singkatan || primaryUnit?.nama || 'Yayasan';

    const [pushPermission, setPushPermission] = useState(
        typeof Notification !== 'undefined' ? Notification.permission : 'unsupported'
    );
    const [testMsg, setTestMsg] = useState(null);

    // Auto-subscribe: bila izin sudah 'granted' langsung subscribe; bila 'default'
    // minta izin saat buka pertama, lalu subscribe bila diberikan.
    useEffect(() => {
        if (typeof Notification === 'undefined') return;
        if (Notification.permission === 'granted') {
            initPush();
        } else if (Notification.permission === 'default') {
            Notification.requestPermission()
                .then((perm) => {
                    if (perm === 'granted') {
                        initPush();
                        setPushPermission('granted');
                    }
                })
                .catch(() => {});
        }
    }, []);

    const enablePushNotifications = async () => {
        const ok = await initPush();
        setPushPermission(
            ok ? 'granted' : (typeof Notification !== 'undefined' ? Notification.permission : 'unsupported')
        );
    };

    const disablePushNotifications = async () => {
        await disablePush();
        setPushPermission(typeof Notification !== 'undefined' ? Notification.permission : 'unsupported');
    };

    return (
        <div className="mobile-root mx-auto max-w-md bg-[#f4f7f5] font-sans font-antialiased text-slate-900 shadow-[0_0_0_1px_rgba(15,23,42,0.04)]">
            <header className="sticky top-0 z-20 border-b border-emerald-950/10 bg-primary px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] text-white">
                <div className="flex min-h-11 items-center justify-between">
                    <div className="flex min-w-0 flex-1 items-center space-x-3">
                        <ApplicationLogo src={primaryUnit?.logo_url || '/logo.png'} alt={`Logo ${primaryUnit?.nama || 'Yayasan'}`} width="44" height="44" className="h-11 w-11 shrink-0 text-white" />
                        <div className="min-w-0">
                            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-100">Portal Pegawai</p>
                            <p className="truncate text-sm font-bold leading-tight">Presensi {unitLabel}</p>
                        </div>
                    </div>
                    <div className="ml-3 flex shrink-0 items-center gap-2">
                        <Link href={route('presensi.pengumuman')} className="relative flex min-h-11 min-w-11 items-center justify-center rounded-xl text-white/80 hover:text-white" aria-label="Pengumuman">
                            <Megaphone className="h-5 w-5" />
                            {auth?.announcement_count > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 flex min-h-[18px] min-w-[18px] items-center justify-center rounded-full bg-amber-400 px-1 text-[10px] font-bold leading-none text-amber-950 ring-2 ring-primary">
                                    {auth?.announcement_count > 99 ? '99+' : auth?.announcement_count}
                                </span>
                            )}
                        </Link>
                        <Link href={route('presensi.notifikasi.index')} className="relative flex min-h-11 min-w-11 items-center justify-center rounded-xl text-white/80 hover:text-white" aria-label="Notifikasi">
                            <Bell className="h-5 w-5" />
                            {auth?.unread_notifications > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 flex min-h-[18px] min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-primary">
                                    {auth?.unread_notifications > 99 ? '99+' : auth?.unread_notifications}
                                </span>
                            )}
                        </Link>
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
                            <Dropdown.Link href={route('presensi.gaji.index')} className="flex items-center">
                                <CreditCard className="mr-2 h-4 w-4" />
                                Slip Gaji
                            </Dropdown.Link>
                            <Dropdown.Link href={route('presensi.pengumuman')} className="flex items-center">
                                <Megaphone className="w-4 h-4 mr-2" />
                                Pengumuman
                            </Dropdown.Link>
                            {typeof Notification !== 'undefined' && pushPermission !== 'unsupported' && (
                                <button
                                    type="button"
                                    onClick={pushPermission === 'granted' ? disablePushNotifications : enablePushNotifications}
                                    className="flex w-full items-center px-4 py-3 text-sm text-slate-700 hover:bg-slate-100"
                                >
                                    <Bell className="mr-2 h-4 w-4" />
                                    {pushPermission === 'granted' ? 'Nonaktifkan Notifikasi' : 'Aktifkan Notifikasi'}
                                </button>
                            )}
                            {import.meta.env.DEV && (
                                <button
                                    type="button"
                                    onClick={async () => {
                                        const r = await sendTestPush();
                                        setTestMsg(r?.message || (r?.success ? 'Terkirim' : 'Gagal'));
                                    }}
                                    className="flex w-full items-center px-4 py-3 text-sm text-slate-700 hover:bg-slate-100"
                                >
                                    <Bell className="mr-2 h-4 w-4" />
                                    Kirim Tes Notif
                                </button>
                            )}
                            <Dropdown.Link href={route('presensi.logout')} method="post" as="button" className="text-rose-700 font-bold flex items-center">
                                <LogOut className="mr-2 h-4 w-4" />
                                Keluar
                            </Dropdown.Link>
                        </Dropdown.Content>
                        </Dropdown>
                    </div>
                </div>
            </header>

            {/* Page Header (Optional) */}
            {header && (
                <div className="px-4 pt-5">
                    <h2 className="text-xl font-bold tracking-tight text-slate-900">{header}</h2>
                </div>
            )}

            {import.meta.env.DEV && testMsg && (
                <div className="px-4 pt-2">
                    <p className="rounded-lg bg-slate-100 px-3 py-2 text-xs leading-relaxed text-slate-600">{testMsg}</p>
                </div>
            )}

            <main className="px-4 pb-24 pt-5">
                {children}
            </main>

            <nav className="fixed bottom-0 inset-x-0 z-30 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]" aria-label="Navigasi utama">
                <div className="mx-auto flex h-16 max-w-md items-stretch justify-between px-1">
                    {navItems.map((item) => {
                        const active = item.match(url);
                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={`relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 py-1 transition-colors [-webkit-tap-highlight-color:transparent] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 ${
                                    active ? 'text-primary' : 'text-slate-500'
                                }`}
                                aria-current={active ? 'page' : undefined}
                            >
                                {active && (
                                    <span className="absolute inset-x-4 top-0 h-0.5 rounded-full bg-primary" />
                                )}
                                <span>
                                    <item.icon className="h-5 w-5" />
                                </span>
                                <span className="truncate text-xs font-semibold">{item.label}</span>
                            </Link>
                        );
                    })}
                </div>
            </nav>

            <FlashToast />
        </div>
    );
}
