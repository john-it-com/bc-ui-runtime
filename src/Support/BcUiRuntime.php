<?php

/**
 * BcUiRuntime facade.
 *
 * Purpose: Provide a simple static API for modules to register bc-ui-runtime entries.
 * Role: Allows module service providers to register assets without reaching into the container.
 */

namespace JohnIt\Bc\Runtime\Support;

use Illuminate\Support\Facades\Facade;
use JohnIt\Bc\Runtime\Runtime\ModuleRegistry;

class BcUiRuntime extends Facade
{
    /**
     * Resolve the underlying service key for the facade.
     */
    protected static function getFacadeAccessor(): string
    {
        return ModuleRegistry::class;
    }
}
