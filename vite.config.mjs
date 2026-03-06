/**
 * Vite build configuration for bc-ui-runtime.
 *
 * Purpose: Build runtime entrypoints and vendor bridges for import maps.
 * Role: Produces the ESM assets published to public/vendor/john-it-com/bc-ui-runtime.
 * Note: Preserves entry exports so runtime APIs remain available as named exports.
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createBcViteConfig } from './build/vite.shared.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default createBcViteConfig({
    root: __dirname,
    input: {
        'runtime': 'src/Resources/js/runtime/index.ts',
        'runtime-inertia': 'src/Resources/js/runtime/inertia.ts',
        'runtime-blade': 'src/Resources/js/runtime/blade.ts',
        'vendor-vue': 'src/Resources/js/vendor/vue.ts',
        'vendor-pinia': 'src/Resources/js/vendor/pinia.ts',
        'vendor-vue-i18n': 'src/Resources/js/vendor/vue-i18n.ts',
        'vendor-inertia': 'src/Resources/js/vendor/inertia-vue3.ts',
        'vendor-ziggy': 'src/Resources/js/vendor/ziggy.ts',
    },
    outDir: 'src/Resources/dist',
    useDefaultExternals: false,
    externals: [],
    rollupOptions: {
        // Keep named exports on runtime entries (e.g., bootstrapBladeApp).
        preserveEntrySignatures: 'exports-only',
        output: {
            exports: 'named',
        },
    },
});
