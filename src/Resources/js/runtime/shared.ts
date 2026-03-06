/**
 * Shared bootstrapping utilities for bc-ui-runtime.
 *
 * Purpose: Expose singleton dependencies, install shared plugins, and manage i18n updates.
 * Role: Ensures every module uses a single Vue/Pinia/I18n/Inertia instance.
 */
import * as Vue from 'vue';
import * as Pinia from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import * as VueI18n from 'vue-i18n';
import * as InertiaVue3 from '@inertiajs/vue3';
import type { App } from 'vue';
import type { Pinia as PiniaInstance } from 'pinia';
import type { I18n, LocaleMessageValue } from 'vue-i18n';

/**
 * Shared i18n payload shape used by bc-ui-runtime.
 */
export interface I18nPayload {
    locale?: string;
    messages?: Record<string, LocaleMessageValue>;
}

/**
 * Expose Vue ecosystem globals for compatibility with legacy integrations.
 */
export function exposeGlobals(): void {
    window.Vue = window.Vue || Vue;
    window.Pinia = window.Pinia || Pinia;
    window.VueI18n = window.VueI18n || VueI18n;
    window.InertiaVue3 = window.InertiaVue3 || InertiaVue3;
}

/**
 * Install shared plugins (Pinia with persistence, i18n) onto the provided Vue app instance.
 *
 * @param {App} app The Vue app to configure.
 * @param {I18nPayload} i18nPayload Optional i18n payload from the server.
 * @returns {{ pinia: PiniaInstance, i18n: I18n }}
 */
export function setupSharedPlugins(app: App, i18nPayload: I18nPayload = {}): { pinia: PiniaInstance; i18n: I18n } {
    const pinia = Pinia.createPinia();
    pinia.use(piniaPluginPersistedstate);
    app.use(pinia);

    const fallbackLocale = document.documentElement.getAttribute('lang') || 'en';
    const locale = i18nPayload.locale || fallbackLocale;

    const fallbackMessages = ((window.BcUiTailwind && (window.BcUiTailwind.messages || window.BcUiTailwind.default?.messages)) || {}) as Record<string, LocaleMessageValue>;
    const rawMessages = (i18nPayload.messages || fallbackMessages) as Record<string, LocaleMessageValue> | undefined;
    const messages: Record<string, Record<string, LocaleMessageValue>> = {};

    if (rawMessages && locale) {
        messages[locale] = rawMessages;
    }

    if (fallbackLocale && !messages[fallbackLocale] && fallbackMessages && !i18nPayload.messages) {
        messages[fallbackLocale] = fallbackMessages;
    }

    const i18n = VueI18n.createI18n({
        legacy: false,
        globalInjection: true,
        locale,
        messages,
    });

    app.use(i18n);

    return { pinia, i18n };
}

/**
 * Update an existing i18n instance with new locale/messages from Inertia responses.
 *
 * @param {I18n} i18n
 * @param {I18nPayload | undefined | null} payload
 */
export function updateI18nMessages(i18n: I18n, payload: I18nPayload | undefined | null): void {
    if (!payload || !i18n) {
        return;
    }

    const currentLocale = typeof i18n.global.locale === 'string'
        ? i18n.global.locale
        : i18n.global.locale?.value;
    const nextLocale = payload.locale || currentLocale || 'en';

    if (payload.messages) {
        i18n.global.setLocaleMessage(nextLocale, payload.messages as Record<string, LocaleMessageValue>);
    }

    if (typeof i18n.global.locale === 'string') {
        i18n.global.locale = nextLocale;
    } else if (i18n.global.locale && typeof i18n.global.locale === 'object' && 'value' in i18n.global.locale) {
        i18n.global.locale.value = nextLocale;
    }
}
