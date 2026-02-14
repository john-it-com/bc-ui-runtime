/**
 * Vite build configuration for bc-ui-runtime.
 *
 * Purpose: Build runtime entrypoints and vendor bridges for import maps.
 * Role: Produces the ESM assets published to public/vendor/john-it-com/bc-ui-runtime.
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createBcViteConfig } from './build/vite.shared.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default createBcViteConfig({
    root: __dirname,
    input: {
        'runtime': 'src/Resources/js/runtime/index.js',
        'runtime-inertia': 'src/Resources/js/runtime/inertia.js',
        'runtime-blade': 'src/Resources/js/runtime/blade.js',
        'vendor-vue': 'src/Resources/js/vendor/vue.js',
        'vendor-pinia': 'src/Resources/js/vendor/pinia.js',
        'vendor-vue-i18n': 'src/Resources/js/vendor/vue-i18n.js',
        'vendor-inertia': 'src/Resources/js/vendor/inertia-vue3.js',
        'vendor-ziggy': 'src/Resources/js/vendor/ziggy.js',
    },
    outDir: 'src/Resources/dist',
    useDefaultExternals: false,
    externals: [],
});
