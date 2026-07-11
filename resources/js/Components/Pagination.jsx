import { Link, router } from '@inertiajs/react';

const decodeEntities = (value) =>
    String(value)
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&hellip;/g, '…')
        .replace(/&amp;/g, '&')
        .replace(/&#039;/g, "'")
        .replace(/&quot;/g, '"');

export default function Pagination({ links, data = null, preserveState = true, className = '' }) {
    if (!links || links.length === 0) {
        return null;
    }

    const baseClass = (link) =>
        `px-4 py-2 border rounded-md text-sm font-medium transition-colors ${
            link.active
                ? 'bg-indigo-600 text-white border-indigo-600'
                : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'
        } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`;

    const renderLink = (link, index) => {
        const content = decodeEntities(link.label);

        if (!link.url) {
            return (
                <span
                    key={index}
                    className={baseClass(link)}
                    aria-disabled="true"
                    aria-current={link.active ? 'page' : undefined}
                >
                    {content}
                </span>
            );
        }

        if (data) {
            return (
                <button
                    key={index}
                    type="button"
                    onClick={() => router.get(link.url, data, { preserveState })}
                    className={baseClass(link)}
                    aria-current={link.active ? 'page' : undefined}
                >
                    {content}
                </button>
            );
        }

        return (
            <Link
                key={index}
                href={link.url}
                preserveScroll
                className={baseClass(link)}
                aria-current={link.active ? 'page' : undefined}
            >
                {content}
            </Link>
        );
    };

    return (
        <nav className={`flex justify-end ${className}`} aria-label="Navigasi halaman">
            <ul className="flex space-x-1">
                {links.map((link, index) => (
                    <li key={index}>{renderLink(link, index)}</li>
                ))}
            </ul>
        </nav>
    );
}
