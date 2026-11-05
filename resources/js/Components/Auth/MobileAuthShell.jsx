import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head } from '@inertiajs/react';

export default function MobileAuthShell({ title, heading, description, children }) {
    return (
        <main className="min-h-[100dvh] bg-[#f4f7f5] font-sans text-slate-950">
            <Head title={title} />

            <section className="bg-primary px-5 pb-20 pt-[max(2rem,env(safe-area-inset-top))] text-white">
                <div className="mx-auto max-w-sm">
                    <div className="flex items-center gap-3">
                        <ApplicationLogo className="h-14 w-14 shrink-0 text-white" />
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-100">Yayasan Nuurul Muttaqiin</p>
                            <p className="mt-0.5 text-base font-bold">Portal Presensi Pegawai</p>
                        </div>
                    </div>

                    <div className="mt-8">
                        <p className="text-sm font-semibold text-emerald-100">{heading}</p>
                        {description && <p className="mt-3 max-w-sm text-sm leading-relaxed text-emerald-50/80">{description}</p>}
                    </div>
                </div>
            </section>

            <section className="relative z-10 mx-auto -mt-12 w-full max-w-sm px-4 pb-[max(2rem,env(safe-area-inset-bottom))]">
                {children}

                <p className="mt-5 text-center text-[11px] leading-relaxed text-slate-500">
                    &copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin
                </p>
            </section>
        </main>
    );
}
