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
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    plugins: [vue()],
    build: {
        outDir: '', // Set this to the appropriate path
        rollupOptions: {
            // specify the path to your main JS file here
            input: '/src/main.ts',
        },
    },
    base: './', // Set this to the appropriate public path
})
