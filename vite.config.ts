import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const rootDir = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    resolve: {
        alias: {
            '@': path.resolve(rootDir, 'resources/js'),
            'react-helmet-async': path.resolve(rootDir, 'resources/js/lib/helmet-shim.tsx'),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['resources/js/tests/setup.ts'],
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}', 'resources/js/**/__tests__/**/*.{ts,tsx}'],
        pool: 'vmThreads',
        poolOptions: {
            vmThreads: {
                singleThread: true,
            },
        },
        deps: {
            inline: ['@reduxjs/toolkit', 'recharts'],
        },
    },
});
