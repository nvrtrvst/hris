/**
 * PWA Push Notification — HRIS Yayasan
 * Register service worker + subscribe Web Push (hanya portal mobile).
 *
 * Flow:
 *  1. Cek 'serviceWorker' tersedia + sedang di portal mobile (path /mobile atau subdomain presensi).
 *  2. Register /sw.js.
 *  3. Minta izin Notification (dari interaksi user — tombol di UI memanggil initPush()).
 *  4. pushManager.subscribe → kirim subscription ke POST /mobile/push/subscribe.
 */

const isMobilePortal = () => {
    const path = window.location.pathname;
    const host = window.location.host;
    return path.startsWith('/mobile') || host.startsWith('presensi.');
};

let subscribed = false;

export async function initPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return false;
    }
    if (!isMobilePortal()) {
        return false;
    }

    try {
        const reg = await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;

        if (subscribed) {
            return true;
        }

        if (Notification.permission === 'denied') {
            return false;
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            return false;
        }

        const vapidKey = document.querySelector('meta[name="vapid-public-key"]')?.getAttribute('content');
        if (!vapidKey) {
            console.warn('[Push] VAPID public key tidak ditemukan di meta.');

            return false;
        }

        const existing = await reg.pushManager.getSubscription();
        const sub = existing || await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey),
        });

        await fetch('/mobile/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                endpoint: sub.endpoint,
                public_key: arrayBufferToBase64(sub.getKey('p256dh')),
                auth_token: arrayBufferToBase64(sub.getKey('auth')),
                content_encoding: 'aes128gcm',
            }),
        });

        subscribed = true;

        return true;
    } catch (err) {
        // Gagal (permission denied / network) — jangan bikin app crash.
        console.warn('[Push] Gagal subscribe:', err);

        return false;
    }
}

export async function disablePush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }
    try {
        const reg = await navigator.serviceWorker.getRegistration('/sw.js');
        const sub = reg ? await reg.pushManager.getSubscription() : null;
        if (sub) {
            await fetch('/mobile/push/unsubscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ endpoint: sub.endpoint }),
            });
            await sub.unsubscribe();
        }
        subscribed = false;
    } catch (err) {
        console.warn('[Push] Gagal unsubscribe:', err);
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

function arrayBufferToBase64(buffer) {
    if (!buffer) return null;
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }

    return window.btoa(binary);
}

/**
 * Kirim notifikasi tes ke device sendiri (diagnostic).
 * Mengembalikan JSON { success, message } dari server.
 */
export async function sendTestPush() {
    try {
        const res = await fetch('/mobile/push/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        return await res.json();
    } catch (err) {
        return { success: false, message: 'Network error: ' + err.message };
    }
}
