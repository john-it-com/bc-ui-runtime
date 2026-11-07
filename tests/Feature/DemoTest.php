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
