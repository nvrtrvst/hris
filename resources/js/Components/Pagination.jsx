import { Link, router } from '@inertiajs/react';

const decodeEntities = (value) =>
    String(value)
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&hellip;/g, '…')
        .replace(/&amp;/g, '&')
        .replace(/&#039;/g, "'")
        .replace(/&quot;/g, '"');

const lastNumericPage = (links) => {
    const pages = links
        .map((l) => decodeEntities(String(l.label)))
        .filter((label) => /^\d+$/.test(label))
        .map(Number);

    return pages.length ? Math.max(...pages) : 1;
};

/**
 * Komponen pagination bersama (admin desktop + mobile).
 *
 * @param {Array} links   Array link paginator Laravel (`paginator.links`).
 * @param {Object|null} pagination  Objek paginator utuh (current_page/last_page) — opsional utk variant mobile.
 * @param {Object|null} data  Data filter yang dipertahankan saat pindah halaman (mis. `filters`).
 * @param {string} variant  'desktop' = link nomor halaman; 'mobile' = prev/next + "Halaman X / Y".
 */
export default function Pagination({ links, pagination = null, data = null, preserveState = true, className = '', variant = 'desktop' }) {
    const currentPage = pagination?.current_page ?? Number(links.find((l) => l.active)?.label || 1);
    const lastPage = pagination?.last_page ?? lastNumericPage(links);

    if (!links || links.length === 0 || lastPage <= 1) {
        return null;
    }

    const navigate = (page) => {
        if (page < 1 || page > lastPage || page === currentPage) {
            return;
        }
        const link = links.find((l) => decodeEntities(String(l.label)) === String(page));
        if (link?.url) {
            router.get(link.url, data || {}, { preserveState });
        } else if (data) {
            router.get(window.location.pathname, { ...data, page }, { preserveState });
        }
    };

    if (variant === 'mobile') {
        return (
            <nav className={`mt-4 flex items-center justify-center gap-3 ${className}`} aria-label="Navigasi halaman">
                <button
                    type="button"
                    onClick={() => navigate(currentPage - 1)}
                    disabled={currentPage <= 1}
                    className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                >
                    « Sebelumnya
                </button>
                <span className="text-xs font-bold text-slate-500">Halaman {currentPage} / {lastPage}</span>
                <button
                    type="button"
                    onClick={() => navigate(currentPage + 1)}
                    disabled={currentPage >= lastPage}
                    className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Berikutnya »
                </button>
            </nav>
        );
    }

    const baseClass = (link) =>
        `px-3.5 py-2 text-sm font-medium transition-all duration-150 rounded-button ${
            link.active
                ? 'bg-primary text-white shadow-card'
                : 'bg-white text-text-secondary hover:bg-primary-50 hover:text-primary border border-border hover:border-primary/20'
        } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`;

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