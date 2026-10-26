import { configDefaults, defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        // Playwright specs run with `npm run e2e`, not here.
        exclude: [...configDefaults.exclude, 'e2e/**'],
        setupFiles: ['./resources/js/test/setup.ts'],
    },
    resolve: {
        alias: {
            '@': new URL('./resources/js', import.meta.url).pathname,
        },
    },
});
