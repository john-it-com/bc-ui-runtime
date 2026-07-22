/**
 * Filter component registry.
 *
 * Purpose: Resolve backend-provided datalist filter component keys to Vue components.
 * Role: Lets modules provide Vue/Inertia-compatible filter UI without hardcoding all filters in one package.
 */
import type { Component } from 'vue';

export type DatalistFilterComponent = Component;

const filterComponentRegistry = new Map<string, DatalistFilterComponent>();

/**
 * Register a datalist filter component under a stable module-namespaced key.
 *
 * Why: Backend filter DTOs carry only serializable keys and props; the frontend
 *      runtime owns the actual component lookup.
 *
 * @param {string} key Module-namespaced filter component key.
 * @param {DatalistFilterComponent} component Vue component used to render the filter body.
 * @returns {void}
 */
export function registerFilterComponent(key: string, component: DatalistFilterComponent): void {
    if (typeof key !== 'string' || key.trim() === '') {
        throw new Error('bc-ui-runtime: registerFilterComponent requires a non-empty key.');
    }

    if (!component) {
        throw new Error(`bc-ui-runtime: filter component "${key}" is invalid.`);
    }

    if (filterComponentRegistry.has(key)) {
        console.warn(`bc-ui-runtime: filter component "${key}" is already registered; replacing it.`);
    }

    filterComponentRegistry.set(key, component);
}

/**
 * Resolve a registered datalist filter component by key.
 *
 * @param {string} key Module-namespaced filter component key.
 * @returns {DatalistFilterComponent|null} Registered Vue component or null.
 */
export function resolveFilterComponent(key: string): DatalistFilterComponent | null {
    return filterComponentRegistry.get(key) ?? null;
}

/**
 * Return a snapshot of registered filter component keys for diagnostics.
 *
 * @returns {string[]} Registered keys.
 */
export function listFilterComponents(): string[] {
    return Array.from(filterComponentRegistry.keys());
}
