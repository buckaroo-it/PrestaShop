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
import { readdir, unlink } from 'node:fs/promises';

const rootDir = dirname(fileURLToPath(import.meta.url));

/**
 * License header stamped on every generated JavaScript bundle. The PrestaShop
 * validator requires it on all shipped JS files, including the hashed vendor
 * and i18n chunks. It is written as a "legal" comment (leading "!") so that
 * minifiers keep it, and it is added in generateBundle, i.e. after
 * minification, so it always ends up as the first bytes of the file.
 */
const LICENSE_BANNER = `/*!
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
`;

const licenseBanner = () => {
    return {
        name: 'buckaroo-license-banner',
        generateBundle(_options, bundle) {
            for (const file of Object.values(bundle)) {
                if (file.type === 'chunk' && file.fileName.endsWith('.js') && !file.code.startsWith(LICENSE_BANNER)) {
                    file.code = LICENSE_BANNER + file.code;
                }
            }
        }
    }
};

// Custom plugin to clean old hashed files in the directory the build writes to
const cleanOldHashedFiles = () => {
    let jsDir = resolve(rootDir, '../views/js');

    return {
        name: 'clean-old-hashed-files',
        configResolved(config) {
            // Respect --outDir overrides so a verification build never touches the shipped assets.
            jsDir = resolve(config.root, config.build.outDir, 'js');
        },
        buildStart: async () => {
            try {
                const files = await readdir(jsDir);

                // Clean old i18n and vendor files with hashes
                const oldFiles = files.filter(file =>
                    /^(i18n|vendor)-[a-zA-Z0-9]+\.js$/.test(file)
                );

                for (const file of oldFiles) {
                    await unlink(resolve(jsDir, file));
                    console.log(`🗑️  Cleaned old file: ${file}`);
                }
            } catch (error) {
                // Ignore errors if directory doesn't exist yet
            }
        }
    }
};

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
    plugins: [vue(), cleanOldHashedFiles(), licenseBanner()],
    esbuild: {
        // Keep third-party "legal" comments (e.g. MIT notices) that minification would otherwise drop.
        legalComments: 'inline',
    },
    build: {
        outDir: '../views',
        assetsDir: '',
        emptyOutDir: false,
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
