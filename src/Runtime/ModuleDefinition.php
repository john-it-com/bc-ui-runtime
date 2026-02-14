<?php

/**
 * ModuleDefinition value object.
 *
 * Purpose: Collect module metadata and entries for a bc package.
 * Role: Acts as the registry record consumed when building import maps and module lists.
 */

namespace JohnIt\Bc\Runtime\Runtime;

class ModuleDefinition
{
    /**
     * @var array<string, ModuleEntry>
     */
    private array $entries = [];

    /**
     * @param string $name Module identifier (e.g. bc-contacts).
     * @param int $priority Registration priority for module-level Vue registration.
     * @param string|null $publicBasePath Optional override for public asset path.
     */
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0,
        public readonly ?string $publicBasePath = null,
    ) {
    }

    /**
     * Add or replace a module entry for a runtime context.
     *
     * Why: Modules can register multiple entrypoints (admin/shop) without overwriting each other.
     *
     * @param ModuleEntry $entry
     * @return void
     */
    public function addEntry(ModuleEntry $entry): void
    {
        $this->entries[$entry->context] = $entry;
    }

    /**
     * Return all registered entries for this module.
     *
     * @return array<string, ModuleEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Fetch a module entry by context name.
     *
     * @param string $context
     * @return ModuleEntry|null
     */
    public function entryForContext(string $context): ?ModuleEntry
    {
        return $this->entries[$context] ?? null;
    }
}
