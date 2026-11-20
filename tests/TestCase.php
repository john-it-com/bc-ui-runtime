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
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use JohnIt\Bc\Runtime\PackageServiceProvider;
use JohnIt\Bc\Runtime\Tests\Support\TestDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Finder\Finder;

abstract class TestCase extends OrchestraTestCase
{
    use DatabaseTruncation {
        truncateDatabaseTables as protected truncateDatabaseTablesWithoutSeedRestore;
    }

    /** @var array<string, array<string, list<array<string, mixed>>>> Migration-owned reference rows per worker database. */
    protected static array $migrationSeedData = [];

    /**
     * Restrict truncation to the guarded package connection, including its native worker database.
     *
     * @return list<string>
     */
    protected function connectionsToTruncate(): array
    {
        return ['testbench'];
    }

    /** Restore migration-seeded reference data after cleanup without enclosing tests in a transaction. */
    protected function truncateDatabaseTables(): void
    {
        if (config('database.default') !== 'testbench') {
            throw new \RuntimeException('bc-ui-runtime cleanup requires the testbench connection.');
        }

        TestDatabase::assertSafeConnection(config('database.connections.testbench'));
        TestDatabase::assertSafeConnection(DB::connection('testbench')->getConfig());
        if (class_exists(PermissionRegistrar::class)) {
            $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->truncateDatabaseTablesWithoutSeedRestore();

        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();

        if (! array_key_exists($databaseName, self::$migrationSeedData)) {
            self::$migrationSeedData[$databaseName] = [];
            $migrations = config('database.migrations');
            $migrationsTable = is_array($migrations) ? ($migrations['table'] ?? 'migrations') : $migrations;

            foreach ($connection->getSchemaBuilder()->getTableListing(schema: $connection->getDatabaseName(), schemaQualified: false) as $table) {
                if ($table === $connection->getTablePrefix().$migrationsTable) {
                    continue;
                }

                $rows = $connection->table($table)->get()->map(static fn (object $row): array => (array) $row)->all();
                if ($rows !== []) {
                    self::$migrationSeedData[$databaseName][$table] = $rows;
                }
            }

            return;
        }

        /** Migration-seed restoration belongs to cleanup; test fixtures and requests run with constraints enabled. */
        $connection->getSchemaBuilder()->withoutForeignKeyConstraints(function () use ($connection, $databaseName): void {
            foreach (self::$migrationSeedData[$databaseName] as $table => $rows) {
                foreach (array_chunk($rows, 250) as $chunk) {
                    $connection->table($table)->insert($chunk);
                }
            }
        });
    }

    /** Keep runtime files separate from other workers and concurrent test runs. */
    private static ?string $processStoragePath = null;

    /** Select process-owned storage before configuration and provider registration can resolve file paths. */
    protected function resolveApplication(): LaravelApplication
    {
        $app = parent::resolveApplication();
        $this->isolateTestStorage($app);

        return $app;
    }

    /**
     * Isolate generated facades and compiled views before dependency providers boot.
     *
     * Why: Laravel writes real-time facade files directly, so parallel workers must not share that cache.
     */
    protected function isolateTestStorage(LaravelApplication $app): void
    {
        $filesystem = new Filesystem;

        if (self::$processStoragePath === null) {
            self::$processStoragePath = sys_get_temp_dir().'/bc-ui-runtime-tests-'.getmypid().'-'.bin2hex(random_bytes(8));
            $storagePath = self::$processStoragePath;

            register_shutdown_function(static function () use ($storagePath): void {
                (new Filesystem)->deleteDirectory($storagePath);
            });
        }

        foreach (['app/public', 'app/private', 'framework/cache/data', 'framework/views', 'framework/sessions', 'logs'] as $directory) {
            $filesystem->ensureDirectoryExists(self::$processStoragePath.'/'.$directory, 0700);
        }

        $app->useStoragePath(self::$processStoragePath);
    }

    /**
     * Enable automatic Composer package discovery for the package-local Testbench app.
     *
     * Why: Installed dependencies must register their production services and migrations, matching other BC harnesses.
     *
     * @var bool
     */
    protected $enablesPackageDiscoveries = true;

    /**
     * Register the package service provider under Testbench.
     *
     * Why: Package-local tests must boot the module in isolation instead of relying on the host application.
     *
     * @param  LaravelApplication  $app
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
     * Why: Testbench needs a disposable MariaDB database and package configuration to boot the module consistently.
     *
     * @param  LaravelApplication  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('telescope.storage.database.connection', 'testbench');
        $app['config']->set('queue.batching.database', 'testbench');
        $app['config']->set('database.default', 'testbench');
        TestDatabase::assertSafeEnvironment();
        $connection = require __DIR__.'/Support/database.php';
        TestDatabase::assertSafeConnection($connection);
        $app['config']->set('database.connections.testbench', $connection);
        $app['config']->set('view.compiled', $app->storagePath('framework/views'));
        $app['config']->set('session.files', $app->storagePath('framework/sessions'));
        $app['config']->set('cache.stores.file.path', $app->storagePath('framework/cache/data'));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('permission.cache.store', 'array');
        $app['config']->set('session.driver', 'array');
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
