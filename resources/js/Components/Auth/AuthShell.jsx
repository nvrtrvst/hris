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
 * @param {Array<{nama: string, singkatan: string, logo: string|null}>} [lembaga] Daftar lembaga binaan yayasan
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
    lembaga = null,
    children,
}) {
    return (
        <main className="min-h-[100dvh] bg-[#f4f7f5] font-sans text-slate-950 md:flex">
            <Head title={title} />

            {/* Branding panel — teal */}
            <section className="relative overflow-hidden bg-primary px-5 pb-28 pt-[max(2rem,env(safe-area-inset-top))] text-white md:flex md:w-1/2 md:flex-col md:justify-between md:px-10 md:pb-10 md:pt-10 lg:px-14 lg:pb-14 lg:pt-14">
                <div className="relative z-10 mx-auto w-full max-w-sm md:mx-0 md:max-w-none">
                    <div className="flex items-center gap-3">
                        <ApplicationLogo className="h-20 w-auto shrink-0 md:h-24" />
                        <div>
                            <p className="text-lg font-bold">{portal}</p>
                        </div>
                    </div>

                    <div className="mt-10 md:mt-14">
                        {eyebrow && <p className="text-sm font-semibold text-emerald-100">{eyebrow}</p>}
                        <h1 className="mt-1 max-w-sm text-3xl font-bold leading-tight tracking-tight md:max-w-md md:text-4xl lg:text-[2.6rem]">
                            {heading}
                        </h1>
                        {description && <p className="mt-3 max-w-sm text-sm leading-relaxed text-emerald-50/80">{description}</p>}
                    </div>

                    {lembaga?.length > 0 && (
                        <div className="mt-8 border-t border-white/10 pt-5">
                            <p className="text-xs font-medium text-emerald-100/75">Lembaga</p>
                            <ul className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2.5">
                                {lembaga.map((unit) => (
                                    <li key={unit.nama} className="inline-flex items-center gap-2 text-xs font-medium text-emerald-50/90">
                                        {unit.logo ? (
                                            <img src={unit.logo} alt={`Logo ${unit.nama}`} className="h-5 w-5 shrink-0 object-contain" />
                                        ) : unit.singkatan && unit.singkatan !== unit.nama ? (
                                            <span className="text-[10px] font-bold tracking-wide text-emerald-100/80">{unit.singkatan}</span>
                                        ) : null}
                                        <span>{unit.nama}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {heroContent && <div className="mt-10 hidden md:block">{heroContent}</div>}
                </div>

                {heroFooter && <div className="relative z-10 mt-10 hidden md:block">{heroFooter}</div>}
            </section>

            {/* Form panel — kartu melayang di mobile, terpusat di desktop */}
            <section className="relative z-10 mx-auto -mt-16 w-full max-w-sm px-4 pb-[max(2rem,env(safe-area-inset-bottom))] md:mt-0 md:flex md:w-1/2 md:max-w-none md:items-center md:justify-center md:px-10">
                <div className="w-full max-w-md">
                    <div className="rounded-2xl bg-white p-5 shadow-[0_18px_44px_-28px_rgba(15,23,42,0.45)] sm:p-6">
                        {children}
                    </div>

                    {cardBelow}

                    <p className="mt-5 text-center text-[11px] leading-relaxed text-slate-500">
                        &copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin
                    </p>
                </div>
            </section>
        </main>
    );
}
