import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
    root: 'mobile',
    envDir: process.cwd(),
    base: './',

    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],

    resolve: {
        alias: {
            '@': path.resolve(process.cwd(), 'resources/js'),
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },

    build: {
        outDir: '../dist',
        emptyOutDir: true,
    },
});
