<?php

/**
 * ModuleRegistry.
 *
 * Purpose: Maintain an in-memory registry of bc module frontend entries.
 * Role: Provides the authoritative module list for building import maps and runtime bootstraps.
 */

namespace JohnIt\Bc\Runtime\Runtime;

class ModuleRegistry
{
    /**
     * @var array<string, ModuleDefinition>
     */
    private array $modules = [];

    /**
     * Register a module definition.
     *
     * Why: Each module's service provider should contribute its entrypoints here, and the registry
     *      must allow multiple entries per context without overwriting prior registrations.
     *
     * @param ModuleDefinition $definition
     * @return void
     */
    public function registerModule(ModuleDefinition $definition): void
    {
        $this->modules[$definition->name] = $definition;
    }

    /**
     * Convenience helper to register a module entry without instantiating objects.
     *
     * @param string $name
     * @param string $context
     * @param string $manifestKey
     * @param string $importSpecifier
     * @param int $priority
     * @param string|null $publicBasePath
     * @param bool $legacy
     * @return void
     */
    public function registerEntry(
        string $name,
        string $context,
        string $manifestKey,
        string $importSpecifier,
        int $priority = 0,
        ?string $publicBasePath = null,
        bool $legacy = false,
    ): void {
        $definition = $this->modules[$name] ?? new ModuleDefinition($name, $priority, $publicBasePath);

        $definition->addEntry(new ModuleEntry(
            context: $context,
            manifestKey: $manifestKey,
            importSpecifier: $importSpecifier,
            publicBasePath: $publicBasePath,
            legacy: $legacy,
        ));

        $this->modules[$name] = $definition;
    }

    /**
     * Return all registered modules.
     *
     * @return array<string, ModuleDefinition>
     */
    public function modules(): array
    {
        return $this->modules;
    }

    /**
     * Get all module entries matching a runtime context.
     *
     * Why: Multiple entries can exist per module/context, so we flatten while preserving order.
     *
     * @param string $context
     * @return ModuleEntry[]
     */
    public function entriesForContext(string $context): array
    {
        $entries = [];

        foreach ($this->modules as $definition) {
            $contextEntries = $definition->entriesForContext($context);
            if (!empty($contextEntries)) {
                $entries = array_merge($entries, $contextEntries);
            }
        }

        return $entries;
    }

    /**
     * Get legacy (UMD) module entries for a runtime context.
     *
     * @param string $context
     * @return ModuleEntry[]
     */
    public function legacyEntriesForContext(string $context): array
    {
        return array_values(array_filter(
            $this->entriesForContext($context),
            fn (ModuleEntry $entry) => $entry->legacy === true
        ));
    }
}
