/**
 * Shared Vite config factory for bc-* modules.
 *
 * Purpose: Standardize ESM builds across modules and enforce shared externals.
 * Role: Keeps all module bundles compatible with bc-ui-runtime import maps.
 * Notes: Allows per-module Rollup overrides to keep runtime entry exports intact when needed.
 */
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

const DEFAULT_EXTERNALS = [
    'vue',
    'pinia',
    'vue-i18n',
    '@inertiajs/vue3',
    'ziggy-js',
    'bc-ui-runtime',
];

/**
 * Create a Vite config for a bc module bundle.
 *
 * @param {{
 *  root: string,
 *  input: Record<string, string> | string,
 *  outDir: string,
 *  externals?: string[],
 *  alias?: Record<string, string>,
 *  useDefaultExternals?: boolean,
 *  rollupOptions?: import('rollup').RollupOptions,
 * }} options
 * @returns {import('vite').UserConfig}
 */
export function createBcViteConfig(options) {
    const root = options.root;
    const outDir = path.resolve(root, options.outDir);
    const includeDefaults = options.useDefaultExternals !== false;
    const externals = includeDefaults
        ? [...DEFAULT_EXTERNALS, ...(options.externals || [])]
        : (options.externals || []);

    const rollupOptions = {
        input: options.input,
        external: externals,
        ...(options.rollupOptions || {}),
    };

    return defineConfig({
        root,
        plugins: [
            vue(),
        ],
        resolve: {
            alias: {
                vue: 'vue/dist/vue.esm-bundler.js',
                ...(options.alias || {}),
            },
        },
        build: {
            outDir,
            emptyOutDir: true,
            manifest: true,
            target: 'es2020',
            rollupOptions,
        },
    });
}
