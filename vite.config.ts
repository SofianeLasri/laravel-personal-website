import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import * as path from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        hmr: {
            host: 'localhost',
            port: 5173,
        },
        watch: {
            usePolling: true,
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/dashboard-app.ts', 'resources/js/public-app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: true,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});
