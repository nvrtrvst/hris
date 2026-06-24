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
                accent: '#C9A227',
                surface: '#F7F8FA',
                border: '#E5E7EB',
                'text-primary': '#1A1D23',
                'text-secondary': '#6B7280',
                success: '#2E7D5B',
                warning: '#B7791F',
                danger: '#B3261E',
            }
        },
    },

    plugins: [forms],
};
