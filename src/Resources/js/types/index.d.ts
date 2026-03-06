/**
 * File: index.d.ts
 * Purpose: Publish shared TypeScript declarations for the bc-ui-runtime registry API.
 * Role: Acts as the single source of truth for module registration types consumed by all frontend packages.
 */
import type { App } from 'vue';

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
