import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: {
        rollupOptions: {
            input: ['resources/css/app.css', 'resources/css/micrositio.css', 'resources/css/filament/management/theme.css', 'resources/js/app.js', 'resources/js/micrositio.js'],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/micrositio.css', 'resources/css/filament/management/theme.css', 'resources/js/app.js', 'resources/js/micrositio.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
