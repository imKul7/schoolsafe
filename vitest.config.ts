import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],

    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },

    test: {
        environment: 'jsdom',

        setupFiles: ['./resources/js/tests/setup.ts'],

        include: ['resources/js/**/*.test.{ts,tsx}'],

        clearMocks: true,
        restoreMocks: true,
    },
});
