// Ponytail helper: Card-like section dengan title + optional description + grid body.
// Mirip visual yang dipakai di Breeze pages tapi dibikin reusable.
// children diletakkan di dalam <div className="grid grid-cols-1 md:grid-cols-2 gap-6">;
// kalau butuh full-width block, bungkus di FormSection dengan className="md:col-span-2"
// lewat wrapper <div> sendiri.
export default function FormSection({ title, description, children, contentClassName = '' }) {
    return (
        <section>
            {title && (
                <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8 first:mt-0">
                    {title}
                </h3>
            )}
            {description && <p className="text-xs text-gray-500 mb-3">{description}</p>}
            <div className={contentClassName ?? 'grid grid-cols-1 md:grid-cols-2 gap-6'}>
                {children}
            </div>
        </section>
    );
}
