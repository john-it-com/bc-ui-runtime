<?php

/**
 * TailwindContentRegistry.
 *
 * Purpose: Collect Tailwind content globs per runtime context and module.
 * Role: Feeds centralized Tailwind builds while keeping module configuration decentralized.
 */

namespace JohnIt\Bc\Runtime\Runtime\Tailwind;

class TailwindContentRegistry
{
    /**
     * @var array<string, array<string, TailwindContentEntry>>
     */
    private array $entries = [];

    /**
     * Register Tailwind content entry for a context.
     *
     * Why: Modules can register their own templates/components without central hardcoding.
     *
     * @param string $context
     * @param TailwindContentEntry $entry
     * @return void
     */
    public function register(string $context, TailwindContentEntry $entry): void
    {
        if (!array_key_exists($context, $this->entries)) {
            $this->entries[$context] = [];
        }

        if (array_key_exists($entry->moduleName, $this->entries[$context])) {
            $existing = $this->entries[$context][$entry->moduleName];

            // Preserve the first base path and merge globs deterministically.
            $mergedGlobs = $this->mergeGlobs($existing->globs, $entry->globs);

            $this->entries[$context][$entry->moduleName] = new TailwindContentEntry(
                moduleName: $entry->moduleName,
                basePath: $existing->basePath,
                globs: $mergedGlobs,
            );

            return;
        }

        $this->entries[$context][$entry->moduleName] = $entry;
    }

    /**
     * Return all Tailwind content entries for a context.
     *
     * @param string $context
     * @return TailwindContentEntry[]
     */
    public function entriesForContext(string $context): array
    {
        return array_values($this->entries[$context] ?? []);
    }

    /**
     * Merge glob arrays while preserving order and removing duplicates.
     *
     * @param string[] $first
     * @param string[] $second
     * @return string[]
     */
    private function mergeGlobs(array $first, array $second): array
    {
        $merged = [];
        $seen = [];

        foreach (array_merge($first, $second) as $glob) {
            $normalized = trim((string) $glob);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $merged[] = $normalized;
        }

        return $merged;
    }
}
