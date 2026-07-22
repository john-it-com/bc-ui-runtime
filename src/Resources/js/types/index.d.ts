/**
 * File: index.d.ts
 * Purpose: Publish shared TypeScript declarations for the bc-ui-runtime registry API.
 * Role: Acts as the single source of truth for module registration types consumed by all frontend packages.
 */
import type { App, Component } from 'vue';

/**
 * Registration payload for bc-ui-runtime module bootstrapping.
 */
export interface RegisterModulePayload {
    name: string;
    context?: string;
    pages?: Record<string, unknown>;
    components?: Record<string, unknown>;
    register?: (app: App, context?: unknown) => void;
}

/**
 * Register a module with the bc-ui-runtime registry.
 */
export function registerModule(payload: RegisterModulePayload): void;

/**
 * Vue component type used by datalist filter component registration.
 */
export type DatalistFilterComponent = Component;

/**
 * Register a datalist filter component under a stable module-namespaced key.
 */
export function registerFilterComponent(key: string, component: DatalistFilterComponent): void;

/**
 * Resolve a registered datalist filter component by key.
 */
export function resolveFilterComponent(key: string): DatalistFilterComponent | null;

/**
 * Return all registered datalist filter component keys.
 */
export function listFilterComponents(): string[];
