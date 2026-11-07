<?php

/**
 * Pest smoke test for the bc-ui-runtime package.
 *
 * Purpose: Prove the standalone Pest/Testbench bootstrap executes for this previously untested module.
 * Role: Establishes a minimal verification point so future feature tests inherit a working package harness.
 */

use JohnIt\Bc\Runtime\Runtime\Blade\RuntimeBladeRenderer;
use JohnIt\Bc\Runtime\Runtime\ImportMapBuilder;
use JohnIt\Bc\Runtime\Runtime\Manifest\ViteManifestRepository;
use JohnIt\Bc\Runtime\Runtime\ModuleRegistry;
use Illuminate\Filesystem\Filesystem;

test('the package testbench bootstrap initializes', function (): void {
    expect(app())->not->toBeNull();
});

/** Import maps and the root document share one module list without classic-script aliases. */
test('the inertia runtime exposes only esm bootstrap entries and its canonical module list', function (): void {
    $registry = new ModuleRegistry;
    $registry->registerEntry('example', 'admin-inertia', 'src/example.ts', 'example/admin');
    $registry->registerEntry('example', 'admin-inertia', 'src/example.ts', 'example/admin');
    $builder = new ImportMapBuilder(
        $registry,
        app(ViteManifestRepository::class),
    );
    $renderer = new RuntimeBladeRenderer($builder);

    $imports = $builder->buildImportMap('admin-inertia')['imports'];
    $script = $renderer->renderModuleListScript('admin-inertia');

    expect($imports)->toHaveKeys(['vue', '@inertiajs/vue3', '@john-it.com/bc-ui-runtime/inertia', 'example/admin'])
        ->not->toHaveKey('@john-it.com/bc-ui-runtime/blade')
        ->not->toHaveKey('bc-ui-runtime/blade')
        ->not->toHaveKey('bc-ui-runtime')
        ->not->toHaveKey('bc-ui-runtime/inertia')
        ->and($builder->buildModuleList('admin-inertia'))->toBe(['example/admin'])
        ->and($registry->entriesForContext('admin-blade'))->toBe([])
        ->and($script)->toContain('window.BcUiRuntimeModules = [')->not->toContain('BcRuntimeModules');
});

/**
 * Resolve real manifests from an isolated public directory without inheriting published assets.
 *
 * @param array<string, array<string, mixed>> $manifests
 * @param list<array{string, string, string, string}> $entries
 */
function runtimeVersionFromManifests(array $manifests, array $entries): string
{
    $files = new Filesystem;
    $directory = sys_get_temp_dir().'/bc-runtime-version-'.bin2hex(random_bytes(8));
    $previousPublicPath = public_path();
    app()->usePublicPath($directory);

    try {
        foreach ($manifests as $path => $manifest) {
            $files->ensureDirectoryExists(dirname($directory.'/'.$path));
            $files->put($directory.'/'.$path, json_encode($manifest, JSON_THROW_ON_ERROR));
        }
        $registry = new ModuleRegistry;
        foreach ($entries as [$name, $context, $specifier, $base]) {
            $registry->registerEntry($name, $context, 'src/app.ts', $specifier, publicBasePath: $base);
        }

        return (new ImportMapBuilder($registry, new ViteManifestRepository($files)))->versionForContext('admin-inertia');
    } finally {
        app()->usePublicPath($previousPublicPath);
        $files->deleteDirectory($directory);
    }
}

/** Every loaded package and the framework bridges participate, but inactive entries do not. */
test('asset versions follow runtime css and active module manifests', function (): void {
    $entries = [['example', 'admin-inertia', 'example/admin', 'vendor/custom/example']];
    $runtimePath = 'vendor/john-it-com/bc-ui-runtime/.vite/manifest.json';
    $modulePath = 'vendor/custom/example/manifest.json';
    $manifests = [
        $runtimePath => ['runtime.ts' => ['file' => 'assets/runtime-a.js']],
        $modulePath => ['src/app.ts' => ['file' => 'assets/app-a.js', 'css' => ['assets/app-a.css']]],
    ];
    $version = runtimeVersionFromManifests($manifests, $entries);
    expect(runtimeVersionFromManifests($manifests, $entries))->toBe($version);

    foreach ([[$runtimePath, 'runtime.ts', 'file'], [$modulePath, 'src/app.ts', 'file'], [$modulePath, 'src/app.ts', 'css']] as [$path, $key, $field]) {
        $changed = $manifests;
        $changed[$path][$key][$field] = $field === 'css' ? ['assets/app-b.css'] : 'assets/changed.js';
        expect(runtimeVersionFromManifests($changed, $entries))->not->toBe($version);
    }

    $manifests['vendor/inactive/manifest.json'] = ['src/app.ts' => ['file' => 'unused.js']];
    $entries[] = ['inactive', 'other-context', 'inactive/admin', 'vendor/inactive'];
    expect(runtimeVersionFromManifests($manifests, $entries))->toBe($version);
    $entries[] = ['extra', 'admin-inertia', 'extra/admin', 'vendor/extra'];
    expect(runtimeVersionFromManifests($manifests, $entries))->not->toBe($version);
});

/** JSON object ordering and checkout paths do not change versions; module execution order does. */
test('asset versions are canonical and tolerate unbuilt manifests', function (): void {
    $entries = [
        ['first', 'admin-inertia', 'first/admin', 'vendor/first'],
        ['second', 'admin-inertia', 'second/admin', 'vendor/second'],
    ];
    $path = 'vendor/first/.vite/manifest.json';
    $first = [$path => ['src/app.ts' => ['file' => 'app.js', 'css' => ['app.css']]]];
    $reordered = [$path => ['src/app.ts' => ['css' => ['app.css'], 'file' => 'app.js']]];
    expect(runtimeVersionFromManifests($first, $entries))->toBe(runtimeVersionFromManifests($reordered, $entries))
        ->and(runtimeVersionFromManifests([], $entries))->toBe(runtimeVersionFromManifests([], $entries))
        ->and(runtimeVersionFromManifests($first, array_reverse($entries)))->not->toBe(runtimeVersionFromManifests($first, $entries));
});
