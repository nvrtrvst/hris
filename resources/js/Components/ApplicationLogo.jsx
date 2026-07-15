export default function ApplicationLogo(props) {
    return (
        <img {...props} src="/logo.png" alt="Logo Perusahaan" className={`object-contain ${props.className || ''}`} />
    );
}
