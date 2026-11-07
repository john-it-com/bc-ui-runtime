/**
 * Inertia/Vue runtime bootstrap for bc-ui-runtime.
 *
 * Purpose: Create the Inertia app, resolve pages from registered modules, and install shared plugins.
 * Role: Provides the single, stable runtime entrypoint for all admin Inertia pages and runtime hooks.
 */
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { setupSharedPlugins, updateI18nMessages } from './shared';
import type { I18nPayload } from './shared';
import { registerAllModules, resolvePage } from './registry';
import type { Page } from '@inertiajs/core';
import type { DefineComponent } from 'vue';

let inertiaStarted = false;

/**
 * Load runtime modules by import specifier.
 *
 * Why: Modules self-register on import, so loading them prepares the registry.
 *
 * @param {string[]} moduleList
 * @returns {Promise<void>}
 */
export async function loadModules(moduleList: string[] = []): Promise<void> {
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
export function bootstrapInertiaApp(): void {
    if (inertiaStarted) {
        return;
    }

    inertiaStarted = true;

    const resolvePageComponent = (name: string): DefineComponent => {
        const page = resolvePage(name);
        if (!page) {
            throw new Error(`Inertia page component "${name}" could not be resolved.`);
        }
        return page as DefineComponent;
    };

    createInertiaApp({
        resolve: resolvePageComponent,
        setup({ el, App, props, plugin }) {
            const inertiaPage = props.initialPage;
            const inertiaPageProps = inertiaPage.props;
            const vueApp = createApp({ render: () => h(App, props) });
            const { i18n } = setupSharedPlugins(vueApp, inertiaPageProps.i18n as I18nPayload);
            const ziggyFromProps = inertiaPageProps.ziggy as unknown as ZiggyConfigInput | undefined;

            if (ziggyFromProps) {
                const ziggyOptions = {
                    ...ziggyFromProps,
                    location: normalizeZiggyLocation(ziggyFromProps.location),
                };
                // Imported route() calls resolve the same configuration as the Vue plugin.
                window.Ziggy = ziggyOptions;
                vueApp.use(ZiggyVue, ziggyOptions);
            }

            // Provide module registers access to Inertia props for runtime-specific initialization.
            registerAllModules(vueApp, {
                inertia: {
                    props: props as unknown as Record<string, unknown>,
                    page: inertiaPage,
                    pageProps: inertiaPageProps,
                },
            });
            vueApp.use(plugin);

            router.on('navigate', (event: InertiaNavigateEvent) => {
                const nextPage = event?.detail?.page;
                updateI18nMessages(i18n, nextPage?.props?.i18n as I18nPayload | undefined);
            });

            vueApp.mount(el);
        },
    });
}

/**
 * Shape for Ziggy config payloads delivered via Inertia props.
 */
type ZiggyOptions = NonNullable<Parameters<typeof ZiggyVue['install']>[1]>;

/**
 * Ziggy config input that may include string/URL location payloads.
 */
type ZiggyConfigInput = Omit<ZiggyOptions, 'location'> & {
    location?: ZiggyOptions['location'] | string | URL;
};

/**
 * Normalize Ziggy location values to the config shape expected by ZiggyVue.
 *
 * Why: Inertia props may deliver location as a URL string or URL instance.
 *
 * @param {ZiggyConfigInput['location']} location
 * @returns {ZiggyOptions['location'] | undefined}
 */
function normalizeZiggyLocation(location: ZiggyConfigInput['location']): ZiggyOptions['location'] | undefined {
    if (!location) {
        return undefined;
    }

    if (location instanceof URL) {
        return {
            host: location.host,
            pathname: location.pathname,
            search: location.search,
        };
    }

    if (typeof location === 'string') {
        const parsed = new URL(location);
        return {
            host: parsed.host,
            pathname: parsed.pathname,
            search: parsed.search,
        };
    }

    return location;
}

/**
 * Navigate event shape used by the Inertia router.
 */
interface InertiaNavigateEvent {
    detail?: {
        page?: Page;
    };
}
