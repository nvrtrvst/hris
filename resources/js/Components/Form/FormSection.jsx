// Ponytail helper: Card-like section dengan title + optional description + grid body.
// Mirip visual yang dipakai di Breeze pages tapi dibikin reusable.
// children diletakkan di dalam <div className="grid grid-cols-1 md:grid-cols-2 gap-6">;
// kalau butuh full-width block, bungkus di FormSection dengan className="md:col-span-2"
// lewat wrapper <div> sendiri.
export default function FormSection({ title, description, children, contentClassName = '' }) {
    return (
        <section className="card p-6">
            {title && (
                <div className="mb-4">
                    <h3 className="text-base font-semibold text-text-primary">{title}</h3>
                    {description && <p className="text-sm text-text-muted mt-1">{description}</p>}
                </div>
            )}
            <div className={contentClassName ?? 'form-grid'}>
                {children}
            </div>
        </section>
    );
}
