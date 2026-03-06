/**
 * File: window.d.ts
 * Purpose: Declare runtime-specific global window extensions used by bc-ui-runtime.
 * Role: Ensures strict TypeScript builds can reference runtime globals safely.
 */
import type { App } from 'vue';
import type * as Vue from 'vue';
import type * as Pinia from 'pinia';
import type * as VueI18n from 'vue-i18n';
import type * as InertiaVue3 from '@inertiajs/vue3';
import type { LocaleMessageValue } from 'vue-i18n';

type ZiggyLocation = {
    host?: string;
    pathname?: string;
    search?: string;
};

type ZiggyConfig = {
    url: string;
    port: number | null;
    defaults: Record<string, unknown>;
    routes: Record<string, unknown>;
    location?: ZiggyLocation | string | URL;
};

declare global {
    interface Window {
        Vue?: typeof Vue;
        Pinia?: typeof Pinia;
        VueI18n?: typeof VueI18n;
        InertiaVue3?: typeof InertiaVue3;
        BcUiTailwind?: {
            messages?: Record<string, LocaleMessageValue>;
            default?: { messages?: Record<string, LocaleMessageValue> };
        };
        Ziggy?: ZiggyConfig;
        AppVue?: App;
    }
}

export {};
