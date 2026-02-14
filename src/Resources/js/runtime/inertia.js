/**
 * Inertia/Vue runtime bootstrap for bc-ui-runtime.
 *
 * Purpose: Create the Inertia app, resolve pages from registered modules, and install shared plugins.
 * Role: Provides the single, stable runtime entrypoint for all admin Inertia pages.
 */
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { exposeGlobals, setupSharedPlugins, updateI18nMessages } from './shared';
import { registerAllModules, resolvePage } from './registry';

// Ensure Vue/Pinia/I18n/Inertia are shared globals for compatibility with legacy integrations.
exposeGlobals();

let inertiaStarted = false;

/**
 * Load runtime modules by import specifier.
 *
 * Why: Modules self-register on import, so loading them prepares the registry.
 *
 * @param {string[]} moduleList
 * @returns {Promise<void>}
 */
export async function loadModules(moduleList = []) {
    if (!Array.isArray(moduleList) || moduleList.length === 0) {
        return;
    }

    await Promise.all(moduleList.map((specifier) => import(specifier)));
}

/**
 * Bootstrap the Inertia admin application.
 *
 * @returns {void}
 */
export function bootstrapInertiaApp() {
    if (inertiaStarted) {
        return;
    }

    inertiaStarted = true;

    createInertiaApp({
        resolve: (name) => {
            const page = resolvePage(name);
            if (!page) {
                throw new Error(`Inertia page component "${name}" could not be resolved.`);
            }
            return page;
        },
        setup({ el, App, props, plugin }) {
            const vueApp = createApp({ render: () => h(App, props) });

            const initialI18n = props?.initialPage?.props?.i18n || props?.page?.props?.i18n || props?.props?.i18n;
            const { i18n } = setupSharedPlugins(vueApp, initialI18n || {});

            const ziggyFromProps = props?.initialPage?.props?.ziggy || props?.page?.props?.ziggy || props?.props?.ziggy;
            const ziggyOptions = (() => {
                if (ziggyFromProps) {
                    const location = ziggyFromProps.location || (typeof window !== 'undefined' ? window.location.href : '');
                    return { ...ziggyFromProps, location: new URL(location) };
                }

                if (typeof window !== 'undefined' && window.Ziggy) {
                    const location = window.location?.href || window.Ziggy.location || '';
                    return { ...window.Ziggy, location: new URL(location) };
                }

                return null;
            })();

            if (ziggyOptions) {
                if (typeof window !== 'undefined') {
                    window.Ziggy = ziggyOptions;
                }
                vueApp.use(ZiggyVue, ziggyOptions);
            }

            registerAllModules(vueApp);
            vueApp.use(plugin);

            router.on('navigate', (event) => {
                const nextPage = event?.detail?.page;
                updateI18nMessages(i18n, nextPage?.props?.i18n);
            });

            vueApp.mount(el);
        },
    });
}
