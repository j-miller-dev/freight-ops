import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: 'auto',
            workbox: {
                // Cache Laravel-served HTML responses (Inertia pages)
                navigationPreload: false,
                runtimeCaching: [
                    {
                        // Inertia page requests — network first, fall back to cache
                        urlPattern: ({ request }) => request.mode === 'navigate',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'inertia-pages',
                            networkTimeoutSeconds: 5,
                        },
                    },
                    {
                        // JS/CSS assets — cache first (content-hashed filenames)
                        urlPattern: /\/build\/.+\.(js|css)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'assets',
                            expiration: {
                                maxAgeSeconds: 60 * 60 * 24 * 30,
                            },
                        },
                    },
                ],
            },
            manifest: {
                name: 'Freight Ops',
                short_name: 'FreightOps',
                description: 'Warehouse tablet operations',
                theme_color: '#111827',
                background_color: '#111827',
                display: 'standalone',
                orientation: 'landscape',
                icons: [
                    {
                        src: '/apple-touch-icon.png',
                        sizes: '180x180',
                        type: 'image/png',
                    },
                ],
            },
        }),
    ],
});
