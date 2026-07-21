export default function ApplicationLogo({ src, alt = 'Logo Yayasan', fallback, className = '', ...props }) {
    if (!src && fallback) {
        return <span {...props} className={`flex items-center justify-center font-bold ${className}`}>{fallback}</span>;
    }

    return <img {...props} src={src || '/logo.png'} alt={alt} className={`object-contain ${className}`} />;
}
