<?php

/**
 * ImportMapBuilder.
 *
 * Purpose: Build import maps, module lists, and CSS links for bc-ui-runtime bootstraps.
 * Role: Translates module registry entries (including multiple entries per context) and Vite
 *       manifests into browser-consumable assets.
 */

namespace JohnIt\Bc\Runtime\Runtime;

use JohnIt\Bc\Runtime\Runtime\Manifest\ViteManifestRepository;

class ImportMapBuilder
{
    /**
     * Manifest keys for shared runtime dependencies managed by bc-ui-runtime.
     *
     * @var array<string, string>
     */
    private const SHARED_DEPENDENCY_ENTRIES = [
        'vue' => 'src/Resources/js/vendor/vue.ts',
        'pinia' => 'src/Resources/js/vendor/pinia.ts',
        'vue-i18n' => 'src/Resources/js/vendor/vue-i18n.ts',
        '@inertiajs/vue3' => 'src/Resources/js/vendor/inertia-vue3.ts',
        'ziggy-js' => 'src/Resources/js/vendor/ziggy.ts',
        '@john-it.com/bc-ui-runtime' => 'src/Resources/js/runtime/index.ts',
        '@john-it.com/bc-ui-runtime/inertia' => 'src/Resources/js/runtime/inertia.ts',
    ];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ViteManifestRepository $manifestRepository,
    ) {}

    /**
     * Build the import map array for a runtime context.
     *
     * Why: Import maps must point to publicly accessible asset URLs, including
     *      Vapor's CloudFront asset domain when ASSET_URL is configured.
     *
     * @return array<string, array<string, string>>
     */
    public function buildImportMap(string $context): array
    {
        $imports = $this->buildSharedDependencyImports();
        $seenSpecifiers = array_fill_keys(array_keys($imports), true);

        foreach ($this->registry->entriesForContext($context) as $entry) {
            // Prevent accidental overrides of shared dependencies or earlier registrations.
            if (isset($seenSpecifiers[$entry->importSpecifier])) {
                continue;
            }
            $publicBasePath = $entry->publicBasePath ?? $this->defaultPublicPathForModule($entry->importSpecifier);
            $resolved = $this->resolveEntryFile($publicBasePath, $entry->manifestKey);

            if ($resolved !== null) {
                $imports[$entry->importSpecifier] = $resolved;
                $seenSpecifiers[$entry->importSpecifier] = true;
            }
        }

        return ['imports' => $imports];
    }

    /**
     * Build a list of module import specifiers for a runtime context.
     *
     * Why: Contexts can include multiple entries, so we de-duplicate specifiers
     *      while preserving registration order.
     *
     * @return string[]
     */
    public function buildModuleList(string $context): array
    {
        $modules = [];
        $seenSpecifiers = [];

        foreach ($this->registry->entriesForContext($context) as $entry) {
            if (isset($seenSpecifiers[$entry->importSpecifier])) {
                continue;
            }
            $seenSpecifiers[$entry->importSpecifier] = true;
            $modules[] = $entry->importSpecifier;
        }

        return $modules;
    }

    /**
     * Fingerprint the registered frontend and its manifests independently of the checkout location.
     *
     * Missing manifests remain deterministic during local development. Runtime bridges are
     * included even when no feature explicitly registers them as a module entry.
     */
    public function versionForContext(string $context): string
    {
        $manifests = [
            'vendor/john-it-com/bc-ui-runtime' => $this->manifestRepository->manifestFor('vendor/john-it-com/bc-ui-runtime'),
        ];
        $entries = [];

        foreach ($this->registry->entriesForContext($context) as $entry) {
            $base = trim($entry->publicBasePath ?? $this->defaultPublicPathForModule($entry->importSpecifier), '/');
            $entries[] = [$entry->importSpecifier, $entry->manifestKey, $base];
            $manifests[$base] = $this->manifestRepository->manifestFor($base);
        }

        return hash('sha256', json_encode(
            $this->canonicalize(['context' => $context, 'entries' => $entries, 'manifests' => $manifests]),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    /** Preserve list order while making manifest object-key order irrelevant to asset versions. */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonicalize(...), $value);
    }

    /**
     * Build a list of CSS URLs for a runtime context.
     *
     * Why: CSS links must resolve through the configured asset base URL (CloudFront on Vapor).
     *
     * @return string[]
     */
    public function buildStyles(string $context): array
    {
        $styles = [];

        foreach ($this->registry->entriesForContext($context) as $entry) {
            $publicBasePath = $entry->publicBasePath ?? $this->defaultPublicPathForModule($entry->importSpecifier);
            $styles = array_merge(
                $styles,
                array_map(
                    fn (string $path) => $this->toAssetUrl($path),
                    $this->manifestRepository->resolveCss($publicBasePath, $entry->manifestKey)
                )
            );
        }

        return array_values(array_unique($styles));
    }

    /**
     * Build import-map entries for shared runtime dependencies.
     *
     * @return array<string, string>
     */
    private function buildSharedDependencyImports(): array
    {
        $imports = [];
        $runtimeBase = 'vendor/john-it-com/bc-ui-runtime';

        foreach (self::SHARED_DEPENDENCY_ENTRIES as $specifier => $manifestKey) {
            $resolved = $this->resolveEntryFile($runtimeBase, $manifestKey);
            if ($resolved !== null) {
                $imports[$specifier] = $resolved;
            }
        }

        return $imports;
    }

    /**
     * Resolve a manifest entry to a public URL, falling back to a non-hashed path.
     *
     * Why: All module assets must be served from the configured asset base URL.
     */
    private function resolveEntryFile(string $publicBasePath, string $manifestKey): ?string
    {
        $resolved = $this->manifestRepository->resolveFile($publicBasePath, $manifestKey);

        if ($resolved !== null) {
            return $this->toAssetUrl($resolved);
        }

        // Fallback to a non-hashed filename for development or before manifests are built.
        $fallbackFile = $this->fallbackFileForKey($manifestKey);
        $base = trim($publicBasePath, '/');

        if ($fallbackFile === '') {
            return null;
        }

        $path = $base === '' ? $fallbackFile : $base.'/'.$fallbackFile;

        return asset($path);
    }

    /**
     * Determine a fallback filename from a manifest key.
     *
     * Why: Unbuilt development entries retain their source filename until a manifest is available.
     */
    private function fallbackFileForKey(string $manifestKey): string
    {
        if (str_contains($manifestKey, 'src/')) {
            return basename($manifestKey);
        }

        return ltrim($manifestKey, '/');
    }

    /**
     * Infer the default public base path from a module import specifier.
     */
    private function defaultPublicPathForModule(string $importSpecifier): string
    {
        $parts = explode('/', $importSpecifier);
        $moduleName = $parts[0];

        if (str_starts_with($moduleName, '@') && isset($parts[1])) {
            $moduleName = $parts[1];
        }

        return 'vendor/john-it-com/'.$moduleName;
    }

    /**
     * Convert a public path into a fully qualified asset URL.
     *
     * Why: Ensures asset references respect ASSET_URL (CloudFront on Vapor).
     */
    private function toAssetUrl(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            return $path;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '//')) {
            return $trimmed;
        }

        return asset(ltrim($trimmed, '/'));
    }
}
