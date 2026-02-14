/**
 * Runtime module registry.
 *
 * Purpose: Collect module registrations (pages/components) in a single place.
 * Role: Enables bc-ui-runtime to resolve Inertia pages and register global components without hardcoding modules.
 */

/**
 * @typedef {Object} ModuleRegistration
 * @property {string} name
 * @property {Record<string, any>} pages
 * @property {Array<(app: import('vue').App) => void>} registers
 * @property {number} priority
 */

const moduleRegistry = new Map();
const pageIndex = new Map();

/**
 * Register a module with the runtime registry.
 *
 * Why: Keeps module registration decentralized while allowing the runtime to resolve pages and components.
 *
 * @param {{
 *  name: string,
 *  pages?: Record<string, any>,
 *  register?: ((app: import('vue').App) => void) | Array<(app: import('vue').App) => void>,
 *  priority?: number,
 * }} definition
 */
export function registerModule(definition) {
    if (!definition || typeof definition.name !== 'string') {
        throw new Error('bc-ui-runtime: registerModule requires a module name.');
    }

    const name = definition.name;
    const existing = moduleRegistry.get(name);
    const pages = { ...(existing?.pages || {}) };

    if (definition.pages && typeof definition.pages === 'object') {
        Object.entries(definition.pages).forEach(([pageName, component]) => {
            if (pageIndex.has(pageName) && pageIndex.get(pageName) !== name) {
                console.warn(`bc-ui-runtime: page "${pageName}" already registered by ${pageIndex.get(pageName)}; skipping ${name}.`);
                return;
            }

            pageIndex.set(pageName, name);
            pages[pageName] = component;
        });
    }

    const registers = Array.isArray(definition.register)
        ? definition.register
        : definition.register
            ? [definition.register]
            : [];

    const combinedRegisters = [...(existing?.registers || []), ...registers];
    const priority = typeof definition.priority === 'number'
        ? definition.priority
        : (existing?.priority || 0);

    moduleRegistry.set(name, {
        name,
        pages,
        registers: combinedRegisters,
        priority,
    });
}

/**
 * Resolve an Inertia page component by name from all registered modules.
 *
 * @param {string} name
 * @returns {any | null}
 */
export function resolvePage(name) {
    for (const module of moduleRegistry.values()) {
        if (module.pages && module.pages[name]) {
            return module.pages[name];
        }
    }

    // Legacy fallback: scan global namespaces for UMD page maps during the migration window.
    if (typeof window !== 'undefined') {
        const windowKeys = Object.keys(window);
        for (const key of windowKeys) {
            const candidate = window[key];
            if (!candidate) {
                continue;
            }
            if (candidate.pages && candidate.pages[name]) {
                return candidate.pages[name];
            }
            if (key.endsWith('Pages') && candidate[name]) {
                return candidate[name];
            }
        }
    }

    return null;
}

/**
 * Register all module-provided global components and plugins on a Vue app.
 *
 * @param {import('vue').App} app
 */
export function registerAllModules(app) {
    const orderedModules = Array.from(moduleRegistry.values()).sort((a, b) => a.priority - b.priority);

    orderedModules.forEach((module) => {
        module.registers.forEach((register) => {
            if (typeof register === 'function') {
                register(app);
            }
        });
    });
}

/**
 * Return a snapshot of the module registry for diagnostics.
 *
 * @returns {ModuleRegistration[]}
 */
export function listModules() {
    return Array.from(moduleRegistry.values());
}
