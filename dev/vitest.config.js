/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0 Academic Free License (AFL 3.0)
 */

// Standalone on purpose: vite.config.js has a plugin that deletes the shipped
// vendor/i18n bundles from views/js when Vite starts, and Vitest starts Vite.
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { resolve, dirname } from 'node:path';

const rootDir = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    resolve: {
        alias: {
            '@': resolve(rootDir, 'src'),
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    plugins: [
        vue({
            template: {
                compilerOptions: { isCustomElement: (tag) => tag === 'apple-pay-button' },
            },
        }),
    ],
    test: {
        environment: 'happy-dom',
        include: ['tests/**/*.spec.{js,ts}'],
        setupFiles: ['tests/setup.js'],
        restoreMocks: true,
        coverage: {
            provider: 'v8',
            include: ['src/**/*.{js,ts,vue}'],
            exclude: ['src/main.ts', 'src/bootstrap.ts', 'src/**/index.php'],
            reporter: ['text', 'text-summary', 'html', 'json-summary', 'clover'],
            reportsDirectory: 'coverage',
            thresholds: {
                lines: 90,
                statements: 90,
                functions: 85,
                branches: 80,
            },
        },
    },
});
