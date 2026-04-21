<?php

/**
 * TailwindContentEntry value object.
 *
 * Purpose: Describe a module's Tailwind content globs for a specific runtime context.
 * Role: Provides module-scoped content paths used by the centralized Tailwind manifest builder.
 */

namespace JohnIt\Bc\Runtime\Runtime\Tailwind;

class TailwindContentEntry
{
    /**
     * @param  string  $moduleName  Module identifier (e.g. bc-contacts).
     * @param  string  $basePath  Absolute module root path used to resolve globs.
     * @param  string[]  $globs  Content globs relative to the module root.
     */
    public function __construct(
        public readonly string $moduleName,
        public readonly string $basePath,
        public readonly array $globs,
    ) {}
}
