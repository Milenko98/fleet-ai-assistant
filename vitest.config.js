import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Zasebna konfiguracija za testove: bez Laravel i Tailwind pluginova
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        restoreMocks: true,
    },
});
