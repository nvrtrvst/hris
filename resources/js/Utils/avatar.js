export const AVATAR_TONES = [
    'bg-emerald-100 text-emerald-700',
    'bg-sky-100 text-sky-700',
    'bg-purple-100 text-purple-700',
    'bg-amber-100 text-amber-700',
    'bg-rose-100 text-rose-700',
];

export const initials = (name) =>
    (name || '?').split(' ').map((w) => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();

export const avatarTone = (name) =>
    AVATAR_TONES[[...String(name || '')].reduce((a, c) => a + c.charCodeAt(0), 0) % AVATAR_TONES.length];
