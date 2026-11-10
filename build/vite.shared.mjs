/**
 * Shared Vite config factory for bc-* modules.
 *
 * Purpose: Standardize ESM builds across modules and enforce shared externals.
 * Role: Keeps all module bundles compatible with bc-ui-runtime import maps.
 * Notes: Enables sourcemaps for Sentry so production stack traces map to source.
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
    '@john-it.com/bc-ui-runtime',
];

/**
 * Create a Vite config for a bc module bundle.
 * Relative asset URLs keep lazy chunks and CSS portable across module publishing destinations.
 * Consumers with a fixed root or CDN location can explicitly override the base.
 *
 * @param {{
 *  root: string,
 *  input: Record<string, string> | string,
 *  outDir: string,
 *  base?: string,
 *  externals?: string[],
 *  alias?: Record<string, string>,
 *  useDefaultExternals?: boolean,
 *  rollupOptions?: import('rollup').RollupOptions,
 *  publicDir?: false | string,
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
        base: options.base ?? './',
        publicDir: options.publicDir,
        plugins: [
            vue(),
        ],
        resolve: {
            alias: {
                vue: 'vue/dist/vue.runtime.esm-bundler.js',
                ...(options.alias || {}),
            },
        },
        build: {
            outDir,
            emptyOutDir: true,
            manifest: true,
            sourcemap: true,
            target: 'es2020',
            rollupOptions,
        },
    });
}
