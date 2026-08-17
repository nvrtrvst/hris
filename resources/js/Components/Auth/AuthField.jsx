import InputError from '@/Components/InputError';

/**
 * AuthField — input untuk halaman auth (login/lupa sandi/reset).
 *
 * Gaya konsisten dengan portal mobile: ikon kiri, min-h-14, fokus ring.
 *
 * @param {string}  id        id input + suffix error id
 * @param {string}  label     Label di atas input
 * @param {ReactNode} icon    Ikon lucide di kiri input
 * @param {string}  [error]   Pesan error (Inertia errors)
 * @param {ReactNode} [suffix] Konten di kanan input (mis. toggle password)
 */
export default function AuthField({ id, label, icon: Icon, error, suffix, ...props }) {
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div>
            <label htmlFor={id} className="mb-2 block text-sm font-bold text-slate-800">
                {label}
            </label>
            <div className="relative">
                <Icon className={`pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 ${error ? 'text-rose-500' : 'text-slate-400'}`} />
                <input
                    id={id}
                    aria-invalid={Boolean(error)}
                    aria-describedby={errorId}
                    className={`min-h-14 w-full rounded-xl border bg-slate-50 py-3.5 pl-12 text-[15px] font-medium text-slate-950 outline-none transition-colors placeholder:text-slate-400 focus:bg-white focus:ring-4 ${
                        suffix ? 'pr-14' : 'pr-4'
                    } ${
                        props.readOnly
                            ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-500'
                            : error
                                ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-100'
                                : 'border-slate-200 focus:border-primary focus:ring-primary/10'
                    }`}
                    {...props}
                />
                {suffix}
            </div>
            {error && <InputError id={errorId} message={error} className="mt-2" />}
        </div>
    );
}
