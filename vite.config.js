import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    base: process.env.GITHUB_PAGES === 'true' ? '/schoolsafe/' : undefined,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    build: {
        target: 'es2019',
        sourcemap: false,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('@radix-ui') || id.includes('@headlessui') || id.includes('@inertiajs')) {
                            return 'ui-vendor';
                        }

                        if (id.includes('@vladmandic/human')) {
                            return 'biometric-vendor';
                        }

                        return 'vendor';
                    }
                },
            },
        },
        chunkSizeWarningLimit: 1000,
    },
});