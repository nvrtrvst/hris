import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                primary: {
                    DEFAULT: '#0F3D3E',
                    50: '#F0F7F6',
                    100: '#D9EAE8',
                    200: '#B3D5D1',
                    300: '#80B8B1',
                    400: '#4D9B91',
                    500: '#2D7D72',
                    600: '#1A5A57',
                    700: '#0F3D3E',
                    800: '#0A2B2C',
                    900: '#051A1B',
                },
                accent: {
                    DEFAULT: '#C9A227',
                    50: '#FEF9E8',
                    100: '#FCF0C5',
                    200: '#F9E08A',
                    300: '#F0C94A',
                    400: '#D9B032',
                    500: '#C9A227',
                    600: '#A88320',
                    700: '#7E6118',
                    800: '#5E4712',
                    900: '#3E2F0C',
                },
                surface: '#F4F6F8',
                'surface-card': '#FFFFFF',
                border: {
                    DEFAULT: '#E2E5EA',
                    light: '#F0F2F5',
                    dark: '#C5CAD4',
                },
                'text-primary': '#1A1D23',
                'text-secondary': '#6B7280',
                'text-muted': '#9CA3AF',
                success: {
                    DEFAULT: '#2E7D5B',
                    light: '#E8F5EF',
                },
                warning: {
                    DEFAULT: '#B7791F',
                    light: '#FEF3E7',
                },
                danger: {
                    DEFAULT: '#DC2626',
                    light: '#FEE8E8',
                },
                info: {
                    DEFAULT: '#2563EB',
                    light: '#E8F0FE',
                },
            },
            boxShadow: {
                'card': '0 1px 3px 0 rgba(0,0,0,0.04), 0 1px 2px -1px rgba(0,0,0,0.06)',
                'card-hover': '0 4px 12px 0 rgba(0,0,0,0.06), 0 2px 4px -2px rgba(0,0,0,0.04)',
                'elevated': '0 8px 24px 0 rgba(0,0,0,0.08), 0 2px 8px 0 rgba(0,0,0,0.04)',
                'sidebar': '4px 0 16px rgba(0,0,0,0.08)',
                'toast': '0 16px 48px rgba(0,0,0,0.12)',
            },
            borderRadius: {
                'card': '0.75rem',
                'button': '0.5rem',
                'input': '0.5rem',
            },
            keyframes: {
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'slide-in-left': {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(0)' },
                },
                'slide-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px) scale(0.98)' },
                    '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                },
                'scale-in': {
                    '0%': { opacity: '0', transform: 'scale(0.95)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both',
                'fade-in': 'fade-in 0.5s ease-out both',
                'slide-in-left': 'slide-in-left 0.3s ease-out both',
                'slide-up': 'slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) both',
                'scale-in': 'scale-in 0.2s ease-out both',
                shimmer: 'shimmer 2s linear infinite',
            },
        },
    },

    plugins: [forms],
};
