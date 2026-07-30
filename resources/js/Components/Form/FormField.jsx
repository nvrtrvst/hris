// Ponytail helper: Label + control + error + hint. Tidak bawa kontrol sendiri;
export default function FormField({ label, htmlFor, required = false, error, hint, className = '', children }) {
    return (
        <div className={className}>
            {label && (
                <label htmlFor={htmlFor} className="form-label">
                    {label}
                    {required && <span className="text-danger"> *</span>}
                </label>
            )}
            <div>{children}</div>
            {hint && !error && <p className="form-hint">{hint}</p>}
            {error && <p className="form-error">{error}</p>}
        </div>
    );
}
