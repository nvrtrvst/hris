import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import ErrorBoundary from './Components/ErrorBoundary';
import NewVersionToast from './Components/NewVersionToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Durasi toast sebelum reload otomatis — harus sinkron dengan delay interceptor
// di bawah (satu-satunya sumber kebenaran).
const NEW_VERSION_TOAST_DELAY = 2000;

/**
 * Deteksi deploy (asset version mismatch): server mengirim 409 +
 * `X-Inertia-Location` saat Inertia client membawa version usang. Inertia
 * langsung memicu reload tanpa hook, jadi kita intercept `window.fetch`
 * untuk menampilkan toast singkat sebelum reload terjadi.
 *
 * Pembedaan dengan external redirect (409 + lokasi beda): version mismatch
 * selalu menuju URL yang sama (tanpa hash), external redirect ke URL lain.
 */
function installVersionMismatchDetection() {
    if (window.__hrisVersionHookInstalled) return;
    window.__hrisVersionHookInstalled = true;

    const originalFetch = window.fetch.bind(window);

    window.fetch = async (...args) => {
        const response = await originalFetch(...args);

        try {
            const location = response.headers.get('x-inertia-location');

            if (response.status === 409 && location) {
                // Pembedaan: version mismatch → lokasi 409 == URL yang diminta
                // Inertia (bisa berbeda dari halaman sekarang, mis. navigasi
                // A→B). External redirect → lokasi berbeda dari URL diminta.
                const input = args[0];
                const requestedUrl = input instanceof Request
                    ? input.url
                    : new URL(String(input), window.location.href).href;
                const stripHash = (u) => u.split('#')[0];
                const isVersionMismatch = stripHash(new URL(location, window.location.href).href)
                    === stripHash(requestedUrl);

                if (isVersionMismatch) {
                    window.dispatchEvent(new CustomEvent('hris:new-version', { detail: location }));
                    // Beri waktu toast dirender sebelum Inertia memicu reload.
                    await new Promise((resolve) => setTimeout(resolve, NEW_VERSION_TOAST_DELAY));
                }
            }
        } catch {
            // Interceptor tidak boleh pernah merusak alur request apa pun.
        }

        return response;
    };
}

installVersionMismatchDetection();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ErrorBoundary>
                <App {...props} />
                <NewVersionToast delay={NEW_VERSION_TOAST_DELAY} />
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
