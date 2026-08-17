import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head } from '@inertiajs/react';

/**
 * AuthShell — kerangka halaman auth (login/lupa sandi/reset/verify).
 *
 * Satu identitas dengan portal mobile: hero teal + kartu putih floating.
 * - Mobile (< md): hero di atas + kartu melayang (-mt-16), trust card di bawah.
 * - Desktop (md+): panel kiri teal (branding + headline + heroContent) dan
 *   panel kanan kartu form terpusat.
 *
 * Props:
 * @param {string}  title         Judul halaman (untuk <Head>)
 * @param {string}  [portal]      Label portal, mis. 'Portal Admin' / 'Portal Pegawai'
 * @param {string}  [eyebrow]     Label kecil di atas headline
 * @param {ReactNode} heading     Headline (boleh berisi span gradient emerald)
 * @param {ReactNode} [description] Deskripsi di bawah headline
 * @param {ReactNode} [heroContent] Konten pengayaan panel kiri (desktop only)
 * @param {ReactNode} [heroFooter] Baris trust/copyright (desktop only)
 * @param {ReactNode} [cardBelow] Konten di bawah kartu form (mobile only)
 * @param {ReactNode} children    Isi kartu form (dibungkus kartu putih)
 */
export default function AuthShell({
    title,
    portal = 'Portal Pegawai',
    eyebrow,
    heading,
    description,
    heroContent = null,
    heroFooter = null,
    cardBelow = null,
    children,
}) {
    return (
        <main className="min-h-[100dvh] bg-[#f4f7f5] font-sans text-slate-950 md:flex">
            <Head title={title} />

            {/* Branding panel — teal */}
            <section className="relative overflow-hidden bg-primary px-5 pb-28 pt-[max(2rem,env(safe-area-inset-top))] text-white md:flex md:w-1/2 md:flex-col md:justify-between md:px-10 md:pb-10 md:pt-10 lg:px-14 lg:pb-14 lg:pt-14">
                {/* Dekorasi halus (glow) */}
                <div aria-hidden="true" className="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary-700/70 blur-3xl" />
                <div aria-hidden="true" className="pointer-events-none absolute -bottom-28 -left-20 h-80 w-80 rounded-full bg-primary-600/40 blur-3xl" />
                <div aria-hidden="true" className="pointer-events-none absolute right-10 top-1/3 hidden h-40 w-40 rounded-full bg-accent-500/10 blur-2xl md:block" />

                <div className="relative z-10 mx-auto w-full max-w-sm md:mx-0 md:max-w-none">
                    <div className="flex items-center gap-3">
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <ApplicationLogo className="h-7 w-7 text-white" />
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-100">Yayasan Nuurul Muttaqiin</p>
                            <p className="mt-0.5 text-base font-bold">{portal}</p>
                        </div>
                    </div>

                    <div className="mt-10 md:mt-14">
                        {eyebrow && <p className="text-sm font-semibold text-emerald-100">{eyebrow}</p>}
                        <h1 className="mt-1 max-w-sm text-3xl font-bold leading-tight tracking-tight md:max-w-md md:text-4xl lg:text-[2.6rem]">
                            {heading}
                        </h1>
                        {description && <p className="mt-3 max-w-sm text-sm leading-relaxed text-emerald-50/80">{description}</p>}
                    </div>

                    {heroContent && <div className="mt-10 hidden md:block">{heroContent}</div>}
                </div>

                {heroFooter && <div className="relative z-10 mt-10 hidden md:block">{heroFooter}</div>}
            </section>

            {/* Form panel — kartu melayang di mobile, terpusat di desktop */}
            <section className="relative z-10 mx-auto -mt-16 w-full max-w-sm px-4 pb-[max(2rem,env(safe-area-inset-bottom))] md:mt-0 md:flex md:w-1/2 md:max-w-none md:items-center md:justify-center md:px-10">
                <div className="w-full max-w-md">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_18px_44px_-28px_rgba(15,23,42,0.45)] sm:p-6">
                        {children}
                    </div>

                    {cardBelow}

                    <p className="mt-5 text-center text-[11px] leading-relaxed text-slate-500 md:hidden">
                        &copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin
                    </p>
                </div>
            </section>
        </main>
    );
}
