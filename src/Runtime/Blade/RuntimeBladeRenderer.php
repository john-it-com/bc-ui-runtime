<?php

/**
 * RuntimeBladeRenderer.
 *
 * Purpose: Render Blade-friendly scripts and links for bc-ui-runtime import maps.
 * Role: Provides the HTML needed to bootstrap ESM modules without hardcoding module lists.
 */

namespace JohnIt\Bc\Runtime\Runtime\Blade;

use JohnIt\Bc\Runtime\Runtime\ImportMapBuilder;

class RuntimeBladeRenderer
{
    public function __construct(private readonly ImportMapBuilder $importMapBuilder) {}

    /**
     * Render the import map <script> tag for a runtime context.
     */
    public function renderImportMap(string $context): string
    {
        $importMap = $this->importMapBuilder->buildImportMap($context);
        $encoded = json_encode($importMap, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $encoded === false
            ? ''
            : "<script type=\"importmap\">\n{$encoded}\n</script>";
    }

    /**
     * Render a module list script that bc-ui-runtime loaders can read.
     *
     * Why: Emit the module list and keep a legacy alias for older layouts.
     */
    public function renderModuleListScript(string $context): string
    {
        $modules = $this->importMapBuilder->buildModuleList($context);
        $encoded = json_encode($modules, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $encoded === false
            ? ''
            : "<script>window.BcUiRuntimeModules = {$encoded};window.BcRuntimeModules = window.BcUiRuntimeModules;</script>";
    }

    /**
     * Render CSS <link> tags for all module styles in a context.
     */
    public function renderStyleLinks(string $context): string
    {
        $styles = $this->importMapBuilder->buildStyles($context);

        if (empty($styles)) {
            return '';
        }

        $links = array_map(function (string $href) {
            $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

            return "<link rel=\"stylesheet\" href=\"{$safeHref}\"/>";
        }, $styles);

        return implode("\n", $links);
    }

    /**
     * Render legacy <script> tags for UMD modules in a context.
     */
    public function renderLegacyScripts(string $context): string
    {
        $scripts = $this->importMapBuilder->buildLegacyScripts($context);

        if (empty($scripts)) {
            return '';
        }

        $tags = array_map(function (string $src) {
            $safeSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');

            return "<script src=\"{$safeSrc}\"></script>";
        }, $scripts);

        return implode("\n", $tags);
    }
}
