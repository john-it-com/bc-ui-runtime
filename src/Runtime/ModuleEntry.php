<?php

/**
 * ModuleEntry value object.
 *
 * Purpose: Describe one frontend entrypoint (per context) for a bc module.
 * Role: Provides the manifest key and import-map specifier used to load a module bundle.
 */

namespace JohnIt\Bc\Runtime\Runtime;

class ModuleEntry
{
    /**
     * @param string $context Runtime context name (e.g. admin-inertia, admin-blade, shop-inertia).
     * @param string $manifestKey Vite manifest key for this entry (source path string) or legacy output path.
     * @param string $importSpecifier Import-map specifier that resolves to this entry.
     * @param string|null $publicBasePath Optional override for the module's public base path.
     * @param bool $legacy Whether the entry should be loaded as a classic script (legacy UMD build).
     */
    public function __construct(
        public readonly string $context,
        public readonly string $manifestKey,
        public readonly string $importSpecifier,
        public readonly ?string $publicBasePath = null,
        public readonly bool $legacy = false,
    ) {
    }
}
