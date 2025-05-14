/*
 *
 *
 *
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
import { defineConfig } from 'vite';
import vue              from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { resolve, dirname } from 'node:path';

const rootDir = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    // ---------------------------------------------------------------------------
    // 1. Aliases
    // ---------------------------------------------------------------------------
    resolve: {
        alias: {
            '@': resolve(rootDir, 'src'),
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    plugins: [vue()],
    build: {
        outDir: '../views',
        assetsDir: '',
        rollupOptions: {
            input: resolve(rootDir, 'src/main.ts'),
            output: {
                entryFileNames: 'js/buckaroo.vue.js',
                assetFileNames: assetInfo => {
                    const ext = assetInfo.name.substring(assetInfo.name.lastIndexOf('.') + 1);

                    if (/\.(png|jpe?g|gif|svg|webp|webm|mp3)$/.test(assetInfo.name))
                        return `img/[name]-[hash].${ext}`;

                    if (ext === 'css')
                        return `css/buckaroo3.vue.${ext}`;

                    if (/\.(woff2?|eot|ttf|otf)$/.test(assetInfo.name))
                        return `fonts/[name]-[hash].${ext}`;

                    return `[name]-[hash].${ext}`;
                },

                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('vue-i18n')) return 'js/i18n';
                        return 'js/vendor';
                    }
                }
            }
        },

        chunkSizeWarningLimit: 750,
    },
    base: '/modules/buckaroo3/views/',
});
