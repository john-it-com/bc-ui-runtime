/**
 * bc-ui-runtime public API entrypoint.
 *
 * Purpose: Expose runtime registry helpers for module packages.
 * Role: Provides the ESM import that modules use to register pages and components.
 */
export { registerModule, resolvePage, registerAllModules, listModules } from './registry';
export { registerFilterComponent, resolveFilterComponent, listFilterComponents } from './filterRegistry';
export type { DatalistFilterComponent } from './filterRegistry';
