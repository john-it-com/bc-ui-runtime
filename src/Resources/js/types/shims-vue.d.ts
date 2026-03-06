/**
 * File: shims-vue.d.ts
 * Purpose: Declare Vue SFC modules for TypeScript.
 * Role: Allows TypeScript to import `.vue` files in the runtime package.
 */
declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
    export default component;
}
