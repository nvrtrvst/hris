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
            },
            colors: {
                primary: '#0F3D3E',
                'primary-light': '#1A5A57',
                'primary-dark': '#0A2B2C',
                accent: '#C9A227',
                'accent-light': '#E2BE4C',
                surface: '#F7F8FA',
                border: '#E5E7EB',
                'text-primary': '#1A1D23',
                'text-secondary': '#6B7280',
                success: '#2E7D5B',
                warning: '#B7791F',
                danger: '#B3261E',
            },
            keyframes: {
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0) translateX(0)' },
                    '50%': { transform: 'translateY(-24px) translateX(12px)' },
                },
                'float-slow': {
                    '0%, 100%': { transform: 'translateY(0) translateX(0)' },
                    '50%': { transform: 'translateY(28px) translateX(-16px)' },
                },
                blob: {
                    '0%, 100%': { transform: 'scale(1) translate(0, 0)' },
                    '33%': { transform: 'scale(1.15) translate(20px, -20px)' },
                    '66%': { transform: 'scale(0.9) translate(-20px, 20px)' },
                },
                shine: {
                    '0%': { transform: 'translateX(-120%) skewX(-15deg)' },
                    '100%': { transform: 'translateX(220%) skewX(-15deg)' },
                },
                'pulse-ring': {
                    '0%': { transform: 'scale(0.9)', opacity: '0.7' },
                    '80%, 100%': { transform: 'scale(1.6)', opacity: '0' },
                },
                ripple: {
                    '0%': { transform: 'scale(0)', opacity: '0.6' },
                    '100%': { transform: 'scale(4)', opacity: '0' },
                },
                shake: {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '10%, 30%, 50%, 70%, 90%': { transform: 'translateX(-5px)' },
                    '20%, 40%, 60%, 80%': { transform: 'translateX(5px)' },
                },
                'float-up': {
                    '0%': { transform: 'translateY(0) scale(1)', opacity: '1' },
                    '100%': { transform: 'translateY(-60px) scale(0)', opacity: '0' },
                },
                gradientShift: {
                    '0%, 100%': { backgroundPosition: '0% 0%' },
                    '50%': { backgroundPosition: '100% 100%' },
                },
                'slide-in-left': {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(0)' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.7s cubic-bezier(0.16, 1, 0.3, 1) both',
                'fade-in': 'fade-in 0.8s ease-out both',
                float: 'float 7s ease-in-out infinite',
                'float-slow': 'float-slow 11s ease-in-out infinite',
                blob: 'blob 14s ease-in-out infinite',
                shine: 'shine 2.5s ease-in-out infinite',
                'pulse-ring': 'pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                ripple: 'ripple 0.6s ease-out forwards',
                shake: 'shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both',
                'float-up': 'float-up 0.8s ease-out forwards',
                'gradientShift': 'gradientShift 8s ease-in-out infinite',
                'slide-in-left': 'slide-in-left 0.3s ease-out both',
            },
        },
    },

    plugins: [forms],
};
