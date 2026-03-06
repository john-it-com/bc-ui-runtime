/**
 * Inertia/Vue runtime bootstrap for bc-ui-runtime.
 *
 * Purpose: Create the Inertia app, resolve pages from registered modules, and install shared plugins.
 * Role: Provides the single, stable runtime entrypoint for all admin Inertia pages and runtime hooks.
 */
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { exposeGlobals, setupSharedPlugins, updateI18nMessages } from './shared';
import type { I18nPayload } from './shared';
import { registerAllModules, resolvePage } from './registry';
import type { Page } from '@inertiajs/core';
import type { DefineComponent } from 'vue';

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
            const inertiaProps = props as InertiaAppPropsWithLegacy;
            const vueApp = createApp({ render: () => h(App, props) });

            const initialI18n = inertiaProps?.initialPage?.props?.i18n
                || inertiaProps?.page?.props?.i18n
                || inertiaProps?.props?.i18n;
            const { i18n } = setupSharedPlugins(vueApp, initialI18n || {});

            const ziggyFromProps = (inertiaProps?.initialPage?.props?.ziggy
                || inertiaProps?.page?.props?.ziggy
                || inertiaProps?.props?.ziggy) as unknown as ZiggyConfigInput | undefined;
            const ziggyOptions = (() => {
                if (ziggyFromProps) {
                    const location = normalizeZiggyLocation(ziggyFromProps.location);
                    return location
                        ? { ...ziggyFromProps, location }
                        : { ...ziggyFromProps };
                }

                if (typeof window !== 'undefined' && window.Ziggy) {
                    const location = normalizeZiggyLocation((window.Ziggy as unknown as ZiggyConfigInput).location);
                    return location
                        ? { ...(window.Ziggy as unknown as ZiggyConfigInput), location }
                        : (window.Ziggy as unknown as ZiggyConfigInput);
                }

                return null;
            })();

            if (ziggyOptions) {
                if (typeof window !== 'undefined') {
                    window.Ziggy = ziggyOptions as unknown as typeof window.Ziggy;
                }
                vueApp.use(ZiggyVue, ziggyOptions);
            }

            const inertiaPage = inertiaProps?.initialPage || inertiaProps?.page || null;
            const inertiaPageProps = (inertiaPage?.props || inertiaProps?.props || {}) as Record<string, unknown>;

            // Provide module registers access to Inertia props for runtime-specific initialization.
            registerAllModules(vueApp, {
                inertia: {
                    props: inertiaProps as unknown as Record<string, unknown>,
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

type InertiaSetupArgs = Parameters<Parameters<typeof createInertiaApp>[0]['setup']>[0];

/**
 * Extra Inertia props surfaced by runtime for legacy payloads and SSR variants.
 */
type InertiaAppPropsWithLegacy = InertiaSetupArgs['props'] & {
    page?: Page;
    props?: Record<string, unknown>;
};

/**
 * Shape for Ziggy config payloads delivered via Inertia props.
 */
type ZiggyOptions = NonNullable<Parameters<typeof ZiggyVue['install']>[1]>;

/**
 * Ziggy config input that may include string/URL location payloads.
 */
type ZiggyConfigInput = ZiggyOptions & {
    location?: ZiggyOptions['location'] | string | URL;
};

/**
 * Normalize Ziggy location values to the config shape expected by ZiggyVue.
 *
 * Why: Inertia/legacy payloads may deliver location as a URL string or URL instance.
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
