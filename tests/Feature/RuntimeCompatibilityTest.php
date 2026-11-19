<?php

/** Reject host/sibling autoload leakage and unsupported runtimes when certifying this release line. */

use Composer\InstalledVersions;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

test('the package boots its own PHP 8.5 Laravel 13 and Testbench 11 dependencies', function (): void {
    expect(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION)->toBe('8.5')
        ->and(Application::VERSION)->toStartWith('13.')
        ->and(InstalledVersions::getVersion('orchestra/testbench'))->toStartWith('11.')
        ->and(app())->toBeInstanceOf(Application::class);

    $packagePath = dirname(__DIR__, 2);

    foreach ([Application::class => 'laravel/framework', OrchestraTestCase::class => 'orchestra/testbench-core'] as $class => $dependency) {
        $installPath = InstalledVersions::getInstallPath($dependency);
        expect($installPath)->not->toBeNull();

        $dependencyPath = realpath($installPath);
        expect($dependencyPath)->not->toBeFalse()->toStartWith($packagePath.'/vendor/');

        $classFile = (new ReflectionClass($class))->getFileName();
        expect($classFile)->not->toBeFalse()->toStartWith($dependencyPath.'/');
    }
});
