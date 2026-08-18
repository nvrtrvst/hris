import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    build: {
        // Hapus chunk lama setiap build agar browser tidak pernah bisa memuat
        // bundle usang yang mereferensikan hook/API yang sudah tidak ada
        // (mis. Index-*.js lama yang menimbulkan ReferenceError).
        emptyOutDir: true,
    },
});
