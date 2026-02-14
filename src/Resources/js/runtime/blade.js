/**
 * Blade/Vue runtime bootstrap for bc-ui-runtime.
 *
 * Purpose: Create the classic Vue app for Blade-driven pages and register module components.
 * Role: Provides the single, stable runtime entrypoint for non-Inertia admin pages.
 */
import { createApp } from 'vue';
import { exposeGlobals, setupSharedPlugins } from './shared';
import { registerAllModules } from './registry';

// Ensure Vue/Pinia/I18n/Inertia are shared globals for compatibility with legacy integrations.
exposeGlobals();

let appInstance = null;

/**
 * Load runtime modules by import specifier.
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
 * Initialize and return the Blade-driven Vue application.
 *
 * @returns {import('vue').App}
 */
export function bootstrapBladeApp() {
    if (appInstance) {
        return appInstance;
    }

    const app = createApp({});
    setupSharedPlugins(app, {});
    registerAllModules(app);

    window.AppVue = app;
    appInstance = app;

    return appInstance;
}
