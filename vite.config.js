import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/filament/association/theme.css',
                'resources/js/app.js',
                'resources/js/qr-scanner.js',
                'resources/js/snapshot.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});