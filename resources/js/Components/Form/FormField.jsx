// Ponytail helper: Label + control + error + hint. Tidak bawa kontrol sendiri;
export default function FormField({ label, htmlFor, required = false, error, hint, className = '', children }) {
    return (
        <div className={className}>
            {label && (
                <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-700">
                    {label}
                    {required && <span className="text-red-500"> *</span>}
                </label>
            )}
            <div className="mt-1">{children}</div>
            {hint && !error && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
