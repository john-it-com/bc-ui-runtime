<?php

/**
 * BuildTailwindContentManifest command.
 *
 * Purpose: Generate a Tailwind content manifest for a unified Tailwind context.
 * Role: Allows centralized Tailwind builds to consume module-provided content globs.
 */

namespace JohnIt\Bc\Runtime\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use JohnIt\Bc\Runtime\Runtime\ModuleRegistry;
use JohnIt\Bc\Runtime\Runtime\Tailwind\TailwindContentEntry;

class BuildTailwindContentManifest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bc-ui-runtime:tailwind-content
                            {context : Tailwind content context name (admin)}
                            {--output= : Path to JSON output file}';

    /**
     * @var string
     */
    protected $description = 'Build a Tailwind content manifest for a bc-ui-runtime context.';

    /**
     * @param Filesystem $files
     */
    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Execute the command.
     *
     * @param ModuleRegistry $registry
     * @return int
     */
    public function handle(ModuleRegistry $registry): int
    {
        $context = (string) $this->argument('context');
        $outputPath = $this->option('output');

        $entries = $registry->tailwindEntriesForContext($context);
        $manifest = $this->buildManifest($context, $entries);

        $encoded = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            $this->error('Failed to encode Tailwind content manifest.');
            return self::FAILURE;
        }

        if (is_string($outputPath) && $outputPath !== '') {
            $this->writeOutput($outputPath, $encoded);
            $this->info("Tailwind content manifest written to {$outputPath}.");
            return self::SUCCESS;
        }

        $this->line($encoded);

        return self::SUCCESS;
    }

    /**
     * Build the Tailwind content manifest array.
     *
     * @param string $context
     * @param TailwindContentEntry[] $entries
     * @return array<string, mixed>
     */
    private function buildManifest(string $context, array $entries): array
    {
        $content = [];
        $modules = [];
        $seen = [];

        foreach ($entries as $entry) {
            $modules[] = $entry->moduleName;

            foreach ($entry->globs as $glob) {
                $resolved = $this->resolveGlobPath($entry->basePath, $glob);
                if ($resolved === '' || isset($seen[$resolved])) {
                    continue;
                }

                $seen[$resolved] = true;
                $content[] = $resolved;
            }
        }

        return [
            'context' => $context,
            'generated_at' => now()->toISOString(),
            'modules' => array_values(array_unique($modules)),
            'content' => $content,
        ];
    }

    /**
     * Resolve a content glob to an absolute path.
     *
     * Why: Tailwind expects full paths when the build runs outside module directories.
     *
     * @param string $basePath
     * @param string $glob
     * @return string
     */
    private function resolveGlobPath(string $basePath, string $glob): string
    {
        $trimmedGlob = trim($glob);
        if ($trimmedGlob === '') {
            return '';
        }

        if ($this->isAbsolutePath($trimmedGlob)) {
            return $trimmedGlob;
        }

        $resolvedBase = realpath($basePath) ?: $basePath;
        $normalizedBase = rtrim($resolvedBase, '/');

        return $normalizedBase.'/'.ltrim($trimmedGlob, '/');
    }

    /**
     * Determine whether a path is absolute.
     *
     * @param string $path
     * @return bool
     */
    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
    }

    /**
     * Write the manifest output to disk.
     *
     * @param string $outputPath
     * @param string $contents
     * @return void
     */
    private function writeOutput(string $outputPath, string $contents): void
    {
        $directory = dirname($outputPath);

        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($outputPath, $contents);
    }
}
