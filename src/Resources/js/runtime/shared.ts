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

type LocaleMessages = Record<string, LocaleMessageValue>;
type LocaleMessageMap = Record<string, LocaleMessages>;

/**
 * Determine whether a message object is keyed by locale codes instead of translation namespaces.
 *
 * Why: bc-ui-tailwind exposes fallback messages as `{ en: {...}, de: {...} }`,
 * while Inertia payloads provide the already-selected locale tree.
 *
 * @param {Record<string, LocaleMessageValue> | undefined} messages
 * @returns {boolean}
 */
function isLocaleMessageMap(messages: Record<string, LocaleMessageValue> | undefined): messages is LocaleMessageMap {
    if (!messages) {
        return false;
    }

    return Object.keys(messages).some((key) => /^[a-z]{2}(?:[-_][A-Z]{2})?$/.test(key));
}

/**
 * Resolve fallback messages for the requested locale from either supported message shape.
 *
 * @param {Record<string, LocaleMessageValue> | undefined} messages
 * @param {string} locale
 * @param {string} fallbackLocale
 * @returns {Record<string, LocaleMessageValue> | undefined}
 */
function resolveMessagesForLocale(
    messages: Record<string, LocaleMessageValue> | undefined,
    locale: string,
    fallbackLocale: string,
): Record<string, LocaleMessageValue> | undefined {
    if (!messages) {
        return undefined;
    }

    if (!isLocaleMessageMap(messages)) {
        return messages;
    }

    const normalizedLocale = locale.replace('_', '-');
    const normalizedFallbackLocale = fallbackLocale.replace('_', '-');

    return messages[locale]
        || messages[normalizedLocale]
        || messages[locale.split(/[-_]/)[0]]
        || messages[fallbackLocale]
        || messages[normalizedFallbackLocale]
        || messages[fallbackLocale.split(/[-_]/)[0]]
        || messages.en;
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

    const fallbackMessages = (window.BcUiTailwind && (window.BcUiTailwind.messages || window.BcUiTailwind.default?.messages)) as Record<string, LocaleMessageValue> | undefined;
    const rawMessages = i18nPayload.messages
        || resolveMessagesForLocale(fallbackMessages, locale, fallbackLocale);
    const messages: Record<string, Record<string, LocaleMessageValue>> = {};

    if (rawMessages && locale) {
        messages[locale] = rawMessages;
    }

    const resolvedFallbackMessages = resolveMessagesForLocale(fallbackMessages, fallbackLocale, 'en');

    if (fallbackLocale && !messages[fallbackLocale] && resolvedFallbackMessages && !i18nPayload.messages) {
        messages[fallbackLocale] = resolvedFallbackMessages;
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
