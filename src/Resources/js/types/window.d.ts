/** Runtime globals retained for module loading and imported Ziggy route helpers. */
import type { ZiggyVue } from 'ziggy-js';

declare global {
    interface Window {
        BcUiRuntimeModules?: string[];
        Ziggy?: NonNullable<Parameters<typeof ZiggyVue['install']>[1]>;
    }
}

export {};
