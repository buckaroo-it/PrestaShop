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
