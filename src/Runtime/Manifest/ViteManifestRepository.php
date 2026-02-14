<?php

/**
 * ViteManifestRepository.
 *
 * Purpose: Resolve entry files and CSS assets from Vite manifest files in public builds.
 * Role: Allows bc-ui-runtime to build import maps and style links from published module assets.
 */

namespace JohnIt\Bc\Runtime\Runtime\Manifest;

use Illuminate\Filesystem\Filesystem;

class ViteManifestRepository
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];

    /**
     * @param Filesystem $files
     */
    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * Fetch the manifest array for a module public base path.
     *
     * Why: Each module publishes its own Vite manifest to public/vendor/.../manifest.json.
     *
     * @param string $publicBasePath
     * @return array<string, mixed>
     */
    public function manifestFor(string $publicBasePath): array
    {
        $normalizedBasePath = $this->normalizePublicBasePath($publicBasePath);

        if (array_key_exists($normalizedBasePath, $this->cache)) {
            return $this->cache[$normalizedBasePath];
        }

        $manifestPath = public_path(trim($normalizedBasePath, '/').'/manifest.json');

        if (!$this->files->exists($manifestPath)) {
            $this->cache[$normalizedBasePath] = [];
            return [];
        }

        $contents = $this->files->get($manifestPath);
        $decoded = json_decode($contents, true);

        $this->cache[$normalizedBasePath] = is_array($decoded) ? $decoded : [];

        return $this->cache[$normalizedBasePath];
    }

    /**
     * Resolve the JS file path for a manifest entry key.
     *
     * @param string $publicBasePath
     * @param string $manifestKey
     * @return string|null
     */
    public function resolveFile(string $publicBasePath, string $manifestKey): ?string
    {
        $manifest = $this->manifestFor($publicBasePath);
        $entry = $manifest[$manifestKey] ?? null;

        if (!is_array($entry) || !isset($entry['file'])) {
            return null;
        }

        $file = ltrim((string) $entry['file'], '/');
        $base = trim($this->normalizePublicBasePath($publicBasePath), '/');

        return '/'.$base.'/'.$file;
    }

    /**
     * Resolve any CSS files associated with a manifest entry.
     *
     * @param string $publicBasePath
     * @param string $manifestKey
     * @return string[]
     */
    public function resolveCss(string $publicBasePath, string $manifestKey): array
    {
        $manifest = $this->manifestFor($publicBasePath);
        $entry = $manifest[$manifestKey] ?? null;

        if (!is_array($entry) || empty($entry['css']) || !is_array($entry['css'])) {
            return [];
        }

        $base = trim($this->normalizePublicBasePath($publicBasePath), '/');

        return array_map(function ($file) use ($base) {
            $filePath = ltrim((string) $file, '/');
            return '/'.$base.'/'.$filePath;
        }, $entry['css']);
    }

    /**
     * Normalize a public base path value.
     *
     * Why: Ensures we consistently build paths with or without leading slashes.
     *
     * @param string $publicBasePath
     * @return string
     */
    private function normalizePublicBasePath(string $publicBasePath): string
    {
        $trimmed = trim($publicBasePath);

        return $trimmed === '' ? '' : '/'.trim($trimmed, '/');
    }
}
