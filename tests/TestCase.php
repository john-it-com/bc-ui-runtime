<?php

/**
 * Testbench base test case for the bc-ui-runtime package.
 *
 * Purpose: Provide the isolated Laravel application bootstrap used by package-local Pest tests.
 * Role: Keeps bc-ui-runtime tests independent from the host application while preserving package-level service registration.
 */

namespace JohnIt\Bc\Runtime\Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use JohnIt\Bc\Runtime\PackageServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Symfony\Component\Finder\Finder;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Register the package service provider under Testbench.
     *
     * Why: Package-local tests must boot the module in isolation instead of relying on the host application.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    /**
     * Define the environment for package-local tests.
     *
     * Why: Testbench needs an in-memory database and package configuration to boot the module consistently.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Load all package configuration files into the Testbench container.
     *
     * Why: Modules with publishable config must boot their defaults without the host application copying them first.
     */
    protected function loadConfigurationFiles(Application $app): void
    {
        $configRepository = $app->make(ConfigRepository::class);

        foreach ($this->getConfigurationFiles() as $key => $path) {
            $configRepository->set($key, require $path);
        }
    }

    /**
     * Discover package configuration files.
     *
     * Why: Testbench should mirror the package's standalone config layout when booting tests.
     *
     * @return array<string, string>
     */
    protected function getConfigurationFiles(): array
    {
        $configPath = realpath($this->configPath());
        if ($configPath === false) {
            return [];
        }

        $files = [];
        foreach (Finder::create()->files()->name('*.php')->in($configPath) as $file) {
            $directory = $this->getNestedDirectory($file, $configPath);
            $files[$directory.basename($file->getRealPath(), '.php')] = $file->getRealPath();
        }

        ksort($files, SORT_NATURAL);

        return $files;
    }

    /**
     * Resolve the configuration directory for this package.
     *
     * Why: Configuration loading is rooted at the module's local config directory.
     */
    protected function configPath(): string
    {
        return __DIR__.'/../config/';
    }

    /**
     * Compute a dotted key prefix for nested configuration directories.
     *
     * Why: Nested config files must retain their namespaced keys when loaded into the container.
     */
    protected function getNestedDirectory(\SplFileInfo $file, string $configPath): string
    {
        $directory = $file->getPath();
        $nested = trim(str_replace($configPath, '', $directory), DIRECTORY_SEPARATOR);

        if ($nested !== '') {
            $nested = str_replace(DIRECTORY_SEPARATOR, '.', $nested).'.';
        }

        return $nested;
    }
}
