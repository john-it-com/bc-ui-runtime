<?php

/**
 * Package service provider for the bc-ui-runtime module.
 *
 * Purpose: Register runtime registries, import-map builders, and Blade directives used to bootstrap ESM modules.
 * Role: Centralizes runtime asset publishing, Tailwind content aggregation, and frontend bootstrapping for all bc-* packages.
 */

namespace JohnIt\Bc\Runtime;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use JohnIt\Bc\Runtime\Console\Commands\BuildTailwindContentManifest;
use JohnIt\Bc\Runtime\Runtime\Blade\RuntimeBladeRenderer;
use JohnIt\Bc\Runtime\Runtime\ImportMapBuilder;
use JohnIt\Bc\Runtime\Runtime\Manifest\ViteManifestRepository;
use JohnIt\Bc\Runtime\Runtime\ModuleRegistry;

class PackageServiceProvider extends ServiceProvider
{
    /**
     * Register runtime services in the container.
     *
     * Why: Modules need a shared registry and import-map builder during boot.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            return new ModuleRegistry;
        });

        $this->app->singleton(ViteManifestRepository::class, function ($app) {
            return new ViteManifestRepository($app['files']);
        });

        $this->app->singleton(ImportMapBuilder::class, function ($app) {
            return new ImportMapBuilder(
                $app->make(ModuleRegistry::class),
                $app->make(ViteManifestRepository::class)
            );
        });

        $this->app->singleton(RuntimeBladeRenderer::class, function ($app) {
            return new RuntimeBladeRenderer(
                $app->make(ImportMapBuilder::class)
            );
        });
    }

    /**
     * Boot publishing rules and Blade directives for runtime assets.
     *
     * Why: The runtime must publish its compiled assets and expose helpers in Blade layouts.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/Resources/dist/' => public_path('vendor/john-it-com/bc-ui-runtime'),
        ], 'public');

        // Blade directive for emitting the import map used by bc-ui-runtime.
        Blade::directive('bcUiRuntimeImportMap', function ($expression) {
            $contextExpression = $expression ?: "'admin-inertia'";

            return '<?php echo app(\\'.RuntimeBladeRenderer::class."::class)->renderImportMap({$contextExpression}); ?>";
        });

        // Blade directive for emitting the module list used by the runtime loader.
        Blade::directive('bcUiRuntimeModuleList', function ($expression) {
            $contextExpression = $expression ?: "'admin-inertia'";

            return '<?php echo app(\\'.RuntimeBladeRenderer::class."::class)->renderModuleListScript({$contextExpression}); ?>";
        });

        // Blade directive for emitting any CSS assets linked to the runtime/module entries.
        Blade::directive('bcUiRuntimeStyles', function ($expression) {
            $contextExpression = $expression ?: "'admin-inertia'";

            return '<?php echo app(\\'.RuntimeBladeRenderer::class."::class)->renderStyleLinks({$contextExpression}); ?>";
        });

        // Blade directive for emitting legacy script tags (UMD bundles).
        Blade::directive('bcUiRuntimeLegacyScripts', function ($expression) {
            $contextExpression = $expression ?: "'admin-inertia'";

            return '<?php echo app(\\'.RuntimeBladeRenderer::class."::class)->renderLegacyScripts({$contextExpression}); ?>";
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildTailwindContentManifest::class,
            ]);
        }
    }
}
