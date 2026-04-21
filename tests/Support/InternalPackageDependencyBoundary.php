<?php

/**
 * Internal package dependency boundary resolver for the current module's architecture tests.
 *
 * Purpose: Derive the module's allowed internal namespaces from Composer metadata and detect undeclared JohnIt usages.
 * Role: Keeps the architecture test self-contained without a committed package namespace catalog.
 */

namespace JohnIt\Bc\Runtime\Tests\Support;

use LogicException;
use Pest\Arch\Factories\ObjectDescriptionFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class InternalPackageDependencyBoundary
{
    /**
     * @var list<string>
     */
    private array $allowedJohnItNamespaceRoots;

    /**
     * @var list<array{dependency: string, file: string, object: string}>
     */
    private array $unauthorizedJohnItUsages;

    /**
     * @param  list<string>  $allowedJohnItNamespaceRoots
     * @param  list<array{dependency: string, file: string, object: string}>  $unauthorizedJohnItUsages
     */
    private function __construct(array $allowedJohnItNamespaceRoots, array $unauthorizedJohnItUsages)
    {
        $this->allowedJohnItNamespaceRoots = $allowedJohnItNamespaceRoots;
        $this->unauthorizedJohnItUsages = $unauthorizedJohnItUsages;
    }

    /**
     * Build the boundary configuration for the current module from its Composer definition.
     *
     * @param  non-empty-string  $composerJsonPath
     */
    public static function fromComposerJson(string $composerJsonPath): self
    {
        $composerConfiguration = self::composerConfigurationFromPath($composerJsonPath);
        $composerDirectory = dirname($composerJsonPath);
        $packageName = self::packageNameFromConfiguration($composerConfiguration);
        $productionNamespaceRoots = self::productionNamespaceRoots($composerConfiguration);
        $productionSourceDirectories = self::productionSourceDirectories($composerConfiguration, $composerDirectory);

        if ($productionNamespaceRoots === []) {
            throw new LogicException(sprintf(
                'No production PSR-4 namespaces were found for package [%s].',
                $packageName,
            ));
        }

        if ($productionSourceDirectories === []) {
            throw new LogicException(sprintf(
                'No production PSR-4 source directories were found for package [%s].',
                $packageName,
            ));
        }

        $installedInternalPackageDefinitions = self::installedInternalPackageDefinitions($composerDirectory);
        $allowedJohnItNamespaceRoots = self::resolvedAllowedJohnItNamespaceRoots(
            $productionNamespaceRoots,
            self::internalPackageRequirements($composerConfiguration),
            $installedInternalPackageDefinitions,
        );

        return new self(
            $allowedJohnItNamespaceRoots,
            self::detectedUnauthorizedJohnItUsages($productionSourceDirectories, $composerDirectory, $allowedJohnItNamespaceRoots),
        );
    }

    /**
     * Return the allowed JohnIt namespace roots for this package and its reachable installed internal dependencies.
     *
     * @return list<string>
     */
    public function allowedJohnItNamespaceRoots(): array
    {
        return $this->allowedJohnItNamespaceRoots;
    }

    /**
     * Return each unauthorized JohnIt usage discovered in the module's production code.
     *
     * @return list<array{dependency: string, file: string, object: string}>
     */
    public function unauthorizedJohnItUsages(): array
    {
        return $this->unauthorizedJohnItUsages;
    }

    /**
     * Render a PHPUnit failure message for the discovered boundary violations.
     */
    public function violationSummary(): string
    {
        if ($this->unauthorizedJohnItUsages === []) {
            return 'No undeclared JohnIt namespace usages were detected.';
        }

        $lines = [
            'Detected undeclared JohnIt namespace usages:',
            '',
        ];

        foreach ($this->unauthorizedJohnItUsages as $violation) {
            $lines[] = sprintf(
                '- [%s] uses [%s] in [%s]',
                $violation['object'],
                $violation['dependency'],
                $violation['file'],
            );
        }

        $lines[] = '';
        $lines[] = sprintf(
            'Allowed JohnIt namespace roots: %s',
            implode(', ', $this->allowedJohnItNamespaceRoots),
        );

        return implode(PHP_EOL, $lines);
    }

    /**
     * Decode the Composer configuration from disk.
     *
     * @param  non-empty-string  $composerJsonPath
     * @return array<string, mixed>
     */
    private static function composerConfigurationFromPath(string $composerJsonPath): array
    {
        $composerJson = @file_get_contents($composerJsonPath);
        if ($composerJson === false) {
            throw new LogicException(sprintf(
                'Unable to read composer metadata at [%s].',
                $composerJsonPath,
            ));
        }

        $composerConfiguration = json_decode($composerJson, true);
        if (! is_array($composerConfiguration)) {
            throw new LogicException(sprintf(
                'Unable to decode composer metadata at [%s].',
                $composerJsonPath,
            ));
        }

        return $composerConfiguration;
    }

    /**
     * Extract the package name from the Composer configuration.
     *
     * @param  array<string, mixed>  $composerConfiguration
     * @return non-empty-string
     */
    private static function packageNameFromConfiguration(array $composerConfiguration): string
    {
        $packageName = $composerConfiguration['name'] ?? null;
        if (! is_string($packageName) || $packageName === '') {
            throw new LogicException('The package composer metadata does not define a valid [name] field.');
        }

        return $packageName;
    }

    /**
     * Extract the direct internal package requirements from the Composer configuration.
     *
     * @param  array<string, mixed>  $composerConfiguration
     * @return list<string>
     */
    private static function internalPackageRequirements(array $composerConfiguration): array
    {
        $requirements = $composerConfiguration['require'] ?? [];
        if (! is_array($requirements)) {
            return [];
        }

        $internalPackages = [];
        foreach (array_keys($requirements) as $packageName) {
            if (is_string($packageName) && str_starts_with($packageName, 'john-it-com/')) {
                $internalPackages[] = $packageName;
            }
        }

        sort($internalPackages);

        return $internalPackages;
    }

    /**
     * Extract the production PSR-4 namespace roots from the Composer configuration.
     *
     * @param  array<string, mixed>  $composerConfiguration
     * @return list<string>
     */
    private static function productionNamespaceRoots(array $composerConfiguration): array
    {
        $autoload = $composerConfiguration['autoload'] ?? [];
        if (! is_array($autoload)) {
            return [];
        }

        $psr4Namespaces = $autoload['psr-4'] ?? [];
        if (! is_array($psr4Namespaces)) {
            return [];
        }

        return self::normalizedNamespaceRoots($psr4Namespaces);
    }

    /**
     * Extract the production source directories from the Composer configuration.
     *
     * @param  array<string, mixed>  $composerConfiguration
     * @return list<string>
     */
    private static function productionSourceDirectories(array $composerConfiguration, string $composerDirectory): array
    {
        $autoload = $composerConfiguration['autoload'] ?? [];
        if (! is_array($autoload)) {
            return [];
        }

        $psr4Namespaces = $autoload['psr-4'] ?? [];
        if (! is_array($psr4Namespaces)) {
            return [];
        }

        $productionSourceDirectories = [];
        foreach ($psr4Namespaces as $namespaceRoot => $autoloadPaths) {
            if (! is_string($namespaceRoot) || $namespaceRoot === '' || str_starts_with($namespaceRoot, 'Tests\\')) {
                continue;
            }

            foreach (self::normalizedAutoloadPaths($autoloadPaths, $composerDirectory) as $autoloadPath) {
                if (is_dir($autoloadPath)) {
                    $productionSourceDirectories[] = $autoloadPath;
                }
            }
        }

        sort($productionSourceDirectories);

        return array_values(array_unique($productionSourceDirectories));
    }

    /**
     * Resolve installed internal package definitions from Composer metadata.
     *
     * @return array<string, array{internalRequires: list<string>, namespaceRoots: list<string>}>
     */
    private static function installedInternalPackageDefinitions(string $composerDirectory): array
    {
        $installedJsonPath = $composerDirectory.'/vendor/composer/installed.json';
        if (is_file($installedJsonPath)) {
            return self::installedInternalPackageDefinitionsFromJson($installedJsonPath);
        }

        $installedPhpPath = $composerDirectory.'/vendor/composer/installed.php';
        if (is_file($installedPhpPath)) {
            return self::installedInternalPackageDefinitionsFromPhp($installedPhpPath);
        }

        throw new LogicException(sprintf(
            'Unable to locate Composer installed metadata for package directory [%s].',
            $composerDirectory,
        ));
    }

    /**
     * Resolve installed internal package definitions from Composer's installed.json metadata.
     *
     * @return array<string, array{internalRequires: list<string>, namespaceRoots: list<string>}>
     */
    private static function installedInternalPackageDefinitionsFromJson(string $installedJsonPath): array
    {
        $installedJson = @file_get_contents($installedJsonPath);
        if ($installedJson === false) {
            throw new LogicException(sprintf(
                'Unable to read Composer installed metadata at [%s].',
                $installedJsonPath,
            ));
        }

        $installedPackages = json_decode($installedJson, true);
        if (! is_array($installedPackages)) {
            throw new LogicException(sprintf(
                'Unable to decode Composer installed metadata at [%s].',
                $installedJsonPath,
            ));
        }

        $packageDefinitions = $installedPackages['packages'] ?? $installedPackages;
        if (! is_array($packageDefinitions)) {
            throw new LogicException(sprintf(
                'Composer installed metadata at [%s] does not contain package definitions.',
                $installedJsonPath,
            ));
        }

        $packageDefinitionsByName = [];
        foreach ($packageDefinitions as $packageDefinition) {
            if (! is_array($packageDefinition)) {
                continue;
            }

            $packageName = $packageDefinition['name'] ?? null;
            if (! is_string($packageName) || $packageName === '') {
                continue;
            }

            $packageDefinitionsByName[$packageName] = self::installedPackageDefinitionFromComposerConfiguration($packageDefinition);
        }

        ksort($packageDefinitionsByName);

        return $packageDefinitionsByName;
    }

    /**
     * Resolve installed internal package definitions from Composer's installed.php metadata by reading each installed package.
     *
     * @return array<string, array{internalRequires: list<string>, namespaceRoots: list<string>}>
     */
    private static function installedInternalPackageDefinitionsFromPhp(string $installedPhpPath): array
    {
        $installedPackages = require $installedPhpPath;
        if (! is_array($installedPackages)) {
            throw new LogicException(sprintf(
                'Composer installed metadata at [%s] is not a valid PHP array.',
                $installedPhpPath,
            ));
        }

        $packageVersions = $installedPackages['versions'] ?? null;
        if (! is_array($packageVersions)) {
            throw new LogicException(sprintf(
                'Composer installed metadata at [%s] does not contain package version data.',
                $installedPhpPath,
            ));
        }

        $packageDefinitionsByName = [];
        foreach ($packageVersions as $packageName => $packageVersion) {
            if (! is_string($packageName) || ! is_array($packageVersion)) {
                continue;
            }

            $installPath = $packageVersion['install_path'] ?? null;
            if (! is_string($installPath) || $installPath === '') {
                continue;
            }

            $packageComposerJsonPath = rtrim($installPath, DIRECTORY_SEPARATOR).'/composer.json';
            if (! is_file($packageComposerJsonPath)) {
                continue;
            }

            $packageDefinitionsByName[$packageName] = self::installedPackageDefinitionFromComposerConfiguration(
                self::composerConfigurationFromPath($packageComposerJsonPath),
            );
        }

        ksort($packageDefinitionsByName);

        return $packageDefinitionsByName;
    }

    /**
     * Build the installed package definition used for transitive internal dependency resolution.
     *
     * @param  array<string, mixed>  $composerConfiguration
     * @return array{internalRequires: list<string>, namespaceRoots: list<string>}
     */
    private static function installedPackageDefinitionFromComposerConfiguration(array $composerConfiguration): array
    {
        return [
            'internalRequires' => self::internalPackageRequirements($composerConfiguration),
            'namespaceRoots' => self::productionNamespaceRoots($composerConfiguration),
        ];
    }

    /**
     * Build the allowed JohnIt namespace roots for the current package and all reachable installed internal dependencies.
     *
     * @param  list<string>  $productionNamespaceRoots
     * @param  list<string>  $requiredInternalPackages
     * @param  array<string, array{internalRequires: list<string>, namespaceRoots: list<string>}>  $installedInternalPackageDefinitions
     * @return list<string>
     */
    private static function resolvedAllowedJohnItNamespaceRoots(
        array $productionNamespaceRoots,
        array $requiredInternalPackages,
        array $installedInternalPackageDefinitions,
    ): array {
        $allowedNamespaceRoots = self::johnItNamespaceRoots($productionNamespaceRoots);
        $reachableInternalPackages = self::reachableInternalPackages(
            $requiredInternalPackages,
            $installedInternalPackageDefinitions,
        );

        foreach ($reachableInternalPackages as $packageName) {
            if (! array_key_exists($packageName, $installedInternalPackageDefinitions)) {
                throw new LogicException(sprintf(
                    'The internal dependency [%s] is missing from the Composer installed metadata.',
                    $packageName,
                ));
            }

            $allowedNamespaceRoots = array_merge(
                $allowedNamespaceRoots,
                self::johnItNamespaceRoots($installedInternalPackageDefinitions[$packageName]['namespaceRoots']),
            );
        }

        sort($allowedNamespaceRoots);

        return array_values(array_unique($allowedNamespaceRoots));
    }

    /**
     * Resolve every reachable internal package starting from the root package's direct internal requirements.
     *
     * @param  list<string>  $requiredInternalPackages
     * @param  array<string, array{internalRequires: list<string>, namespaceRoots: list<string>}>  $installedInternalPackageDefinitions
     * @return list<string>
     */
    private static function reachableInternalPackages(
        array $requiredInternalPackages,
        array $installedInternalPackageDefinitions,
    ): array {
        $reachableInternalPackages = [];
        $packageQueue = $requiredInternalPackages;

        while ($packageQueue !== []) {
            $packageName = array_shift($packageQueue);
            if (isset($reachableInternalPackages[$packageName])) {
                continue;
            }

            if (! array_key_exists($packageName, $installedInternalPackageDefinitions)) {
                throw new LogicException(sprintf(
                    'The internal dependency [%s] is missing from the Composer installed metadata.',
                    $packageName,
                ));
            }

            $reachableInternalPackages[$packageName] = true;

            foreach ($installedInternalPackageDefinitions[$packageName]['internalRequires'] as $requiredPackageName) {
                if (! isset($reachableInternalPackages[$requiredPackageName])) {
                    $packageQueue[] = $requiredPackageName;
                }
            }
        }

        $reachablePackageNames = array_keys($reachableInternalPackages);
        sort($reachablePackageNames);

        return $reachablePackageNames;
    }

    /**
     * Return only the namespace roots that belong to the JohnIt package boundary.
     *
     * @param  list<string>  $namespaceRoots
     * @return list<string>
     */
    private static function johnItNamespaceRoots(array $namespaceRoots): array
    {
        return array_values(array_filter(
            $namespaceRoots,
            static fn (string $namespaceRoot): bool => str_starts_with($namespaceRoot, 'JohnIt\\'),
        ));
    }

    /**
     * Detect undeclared JohnIt namespace usages from the module's production PHP files.
     *
     * @param  list<string>  $productionSourceDirectories
     * @param  list<string>  $allowedJohnItNamespaceRoots
     * @return list<array{dependency: string, file: string, object: string}>
     */
    private static function detectedUnauthorizedJohnItUsages(
        array $productionSourceDirectories,
        string $composerDirectory,
        array $allowedJohnItNamespaceRoots,
    ): array {
        $violations = [];

        foreach (self::productionPhpFiles($productionSourceDirectories) as $phpFilePath) {
            $objectDescription = ObjectDescriptionFactory::make($phpFilePath, true);
            if ($objectDescription === null) {
                continue;
            }

            foreach ($objectDescription->uses as $usedNamespace) {
                $normalizedUsedNamespace = self::normalizedNamespace((string) $usedNamespace);
                if ($normalizedUsedNamespace === '' || ! str_starts_with($normalizedUsedNamespace, 'JohnIt\\')) {
                    continue;
                }

                if (self::matchesAnyNamespaceRoot($normalizedUsedNamespace, $allowedJohnItNamespaceRoots)) {
                    continue;
                }

                $violationKey = sprintf(
                    '%s|%s|%s',
                    $objectDescription->name,
                    $normalizedUsedNamespace,
                    $phpFilePath,
                );

                $violations[$violationKey] = [
                    'dependency' => $normalizedUsedNamespace,
                    'file' => self::relativePath($phpFilePath, $composerDirectory),
                    'object' => $objectDescription->name,
                ];
            }
        }

        ksort($violations);

        return array_values($violations);
    }

    /**
     * Return every production PHP file from the configured PSR-4 source directories.
     *
     * @param  list<string>  $productionSourceDirectories
     * @return list<string>
     */
    private static function productionPhpFiles(array $productionSourceDirectories): array
    {
        $phpFiles = [];

        foreach ($productionSourceDirectories as $productionSourceDirectory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($productionSourceDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $phpFiles[] = $file->getPathname();
            }
        }

        sort($phpFiles);

        return array_values(array_unique($phpFiles));
    }

    /**
     * Normalize PSR-4 namespace roots so boundary checks receive consistent prefixes.
     *
     * @param  array<string, mixed>  $psr4Namespaces
     * @return list<string>
     */
    private static function normalizedNamespaceRoots(array $psr4Namespaces): array
    {
        $namespaceRoots = [];
        foreach ($psr4Namespaces as $namespaceRoot => $unusedPath) {
            if ($namespaceRoot === '' || str_starts_with($namespaceRoot, 'Tests\\')) {
                continue;
            }

            $namespaceRoots[] = self::normalizedNamespace($namespaceRoot);
        }

        sort($namespaceRoots);

        return array_values(array_unique($namespaceRoots));
    }

    /**
     * Normalize configured autoload paths into absolute package-local directories.
     *
     * @return list<string>
     */
    private static function normalizedAutoloadPaths(mixed $autoloadPaths, string $composerDirectory): array
    {
        $paths = is_array($autoloadPaths) ? $autoloadPaths : [$autoloadPaths];

        $normalizedAutoloadPaths = [];
        foreach ($paths as $autoloadPath) {
            if (! is_string($autoloadPath) || $autoloadPath === '') {
                continue;
            }

            $resolvedPath = realpath($composerDirectory.'/'.$autoloadPath);
            $normalizedAutoloadPaths[] = $resolvedPath !== false
                ? $resolvedPath
                : rtrim($composerDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.trim($autoloadPath, DIRECTORY_SEPARATOR);
        }

        sort($normalizedAutoloadPaths);

        return array_values(array_unique($normalizedAutoloadPaths));
    }

    /**
     * Normalize a namespace so prefix comparisons behave consistently.
     */
    private static function normalizedNamespace(string $namespace): string
    {
        return trim($namespace, '\\');
    }

    /**
     * Determine whether the used namespace belongs to one of the allowed internal namespace roots.
     *
     * @param  list<string>  $allowedNamespaceRoots
     */
    private static function matchesAnyNamespaceRoot(string $usedNamespace, array $allowedNamespaceRoots): bool
    {
        foreach ($allowedNamespaceRoots as $allowedNamespaceRoot) {
            if ($usedNamespace === $allowedNamespaceRoot || str_starts_with($usedNamespace, $allowedNamespaceRoot.'\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render a package-relative path for clearer failure messages.
     */
    private static function relativePath(string $absolutePath, string $composerDirectory): string
    {
        $normalizedComposerDirectory = rtrim(str_replace('\\', '/', $composerDirectory), '/');
        $normalizedAbsolutePath = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalizedAbsolutePath, $normalizedComposerDirectory.'/')) {
            return substr($normalizedAbsolutePath, strlen($normalizedComposerDirectory) + 1);
        }

        return $normalizedAbsolutePath;
    }
}
