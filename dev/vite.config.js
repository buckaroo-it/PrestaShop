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
import vue from '@vitejs/plugin-vue';
import {defineConfig} from 'vite'

export default defineConfig({
    resolve: {
        alias: {
            '@': '/src',
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    plugins: [vue()],
    build: {
        outDir: '../views/',
        assetsDir: '',
        rollupOptions: {
            input: '/src/main.ts',
            output: {
                entryFileNames: `js/buckaroo.vue.js`,
                assetFileNames: assetInfo => {
                    const info = assetInfo.name.split('.');
                    const extType = info[info.length - 1];
                    if (/\.(png|jpe?g|gif|svg|webp|webm|mp3)$/.test(assetInfo.name)) {
                        return `img/[name]-[hash].${extType}`;
                    }
                    if (/\.(css)$/.test(assetInfo.name)) {
                        return `css/buckaroo3.vue.${extType}`;
                    }
                    if (/\.(woff|woff2|eot|ttf|otf)$/.test(assetInfo.name)) {
                        return `fonts/[name]-[hash].${extType}`;
                    }
                    return `[name]-[hash].${extType}`;
                },
            }
        },
    },
    base: '/modules/buckaroo3/views/',
})
