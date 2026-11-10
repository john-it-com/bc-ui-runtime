/**
 * Purpose: Exercise emitted lazy imports and stylesheet preloads after a module is published.
 * Role: Protect the shared build helper's portable default and explicit public-base overrides.
 * Uses Node's native test runner and Vite; all build artifacts stay in temporary directories.
 */
import assert from 'node:assert/strict';
import { cp, mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
import { build } from 'vite';
import { createBcViteConfig } from '../../build/vite.shared.mjs';

/** Minimal link element: Vite waits for stylesheet load/error events before importing the lazy module. */
class AssetLink extends EventTarget {
    href = '';
    rel = '';
    as = '';
    crossOrigin = '';
}

type Scenario = {
    name: string;
    mount: string;
    base?: string;
};

const scenarios: Scenario[] = [
    { name: 'default base under a module publishing directory', mount: 'vendor/john-it-com/bc-example' },
    { name: 'default base under a customer publishing directory', mount: 'vendor/john-it-com/customers/example' },
    { name: 'explicit empty relative base', mount: 'vendor/john-it-com/bc-example', base: '' },
    { name: 'explicit root base', mount: '', base: '/' },
    { name: 'explicit subdirectory base', mount: 'static/widgets', base: '/static/widgets/' },
    { name: 'explicit CDN base', mount: 'cdn/widgets', base: 'https://cdn.example.test/widgets/' },
];

for (const scenario of scenarios) {
    test(scenario.name, async (context) => {
        const temporaryRoot = await mkdtemp(path.join(tmpdir(), 'bc-module-assets-'));
        context.after(() => rm(temporaryRoot, { recursive: true, force: true }));
        const sourceDirectory = path.join(temporaryRoot, 'source');
        const publishedDirectory = path.join(temporaryRoot, 'public', scenario.mount);
        await mkdir(sourceDirectory, { recursive: true });
        await writeFile(path.join(temporaryRoot, 'package.json'), JSON.stringify({ type: 'module' }));
        await writeFile(path.join(sourceDirectory, 'entry.ts'), 'export const load = () => import("./card.ts");');
        await writeFile(path.join(sourceDirectory, 'card.ts'), 'import "./card.css"; export const label = "loaded";');
        await writeFile(path.join(sourceDirectory, 'card.css'), '.fixture-card { color: rgb(10, 20, 30); }');

        const configuration = createBcViteConfig({
            root: sourceDirectory,
            input: { app: path.join(sourceDirectory, 'entry.ts') },
            outDir: 'dist',
            publicDir: false,
            ...(scenario.base === undefined ? {} : { base: scenario.base }),
            rollupOptions: { preserveEntrySignatures: 'strict' },
        });
        await build({ ...configuration, configFile: false, logLevel: 'silent' });
        await cp(path.join(sourceDirectory, 'dist'), publishedDirectory, { recursive: true });

        const manifest = JSON.parse(await readFile(path.join(publishedDirectory, '.vite/manifest.json'), 'utf8'));
        const entryUrl = pathToFileURL(path.join(publishedDirectory, manifest['entry.ts'].file));
        const expectedPrefix = scenario.base || pathToFileURL(`${publishedDirectory}${path.sep}`).href;
        const links: AssetLink[] = [];
        const loads: Promise<void>[] = [];
        const failures: string[] = [];
        const previousDocument = Object.getOwnPropertyDescriptor(globalThis, 'document');
        const previousWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');

        /**
         * Serve preload requests from the relocated bundle, exposing only the DOM surface Vite needs.
         * Native module imports execute unchanged; a wrong root URL fails instead of being rewritten.
         */
        Object.defineProperty(globalThis, 'document', {
            configurable: true,
            value: {
                getElementsByTagName: () => [],
                querySelector: () => null,
                createElement: () => new AssetLink(),
                head: {
                    appendChild(link: AssetLink): void {
                        links.push(link);
                        assert.ok(link.href.startsWith(expectedPrefix), `Unexpected preload URL: ${link.href}`);
                        const assetPath = path.join(publishedDirectory, link.href.slice(expectedPrefix.length));
                        loads.push(readFile(assetPath).then(
                            () => { link.dispatchEvent(new Event('load')); },
                            () => {
                                failures.push(link.href);
                                link.dispatchEvent(new Event('error'));
                            },
                        ));
                    },
                },
            },
        });
        Object.defineProperty(globalThis, 'window', {
            configurable: true,
            value: { dispatchEvent: () => true },
        });

        try {
            const entry = await import(entryUrl.href);
            const card = await entry.load();
            await Promise.all(loads);
            assert.equal(card.label, 'loaded');
            assert.deepEqual(failures, []);
            assert.ok(links.some(link => link.rel === 'stylesheet'), 'The lazy stylesheet must actually load');
            assert.ok(links.some(link => link.rel === 'modulepreload'), 'The lazy script must actually preload');
        } finally {
            await Promise.all(loads);
            if (previousDocument) {
                Object.defineProperty(globalThis, 'document', previousDocument);
            } else {
                Reflect.deleteProperty(globalThis, 'document');
            }
            if (previousWindow) {
                Object.defineProperty(globalThis, 'window', previousWindow);
            } else {
                Reflect.deleteProperty(globalThis, 'window');
            }
        }
    });
}
