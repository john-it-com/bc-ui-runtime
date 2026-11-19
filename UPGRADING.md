# PHP 8.5 / Laravel 13 minor release — 3.2.0

This release line requires PHP **^8.5** and Laravel **^13.40**, with Composer platform **8.5.0** and Testbench **^11.3** for development. PHP 8.4 is no longer supported or tested. It uses a minor release under the explicitly approved project policy despite the breaking runtime/dependency requirements. Existing PHP 8.4 consumers must retain the published N.1.x line with a minor-bounded constraint; PHP 8.5 consumers must select each dependency's new N.2.x line (or its recorded next available minor).

Package workflows verify the Laravel 13 floor/latest resolutions on PHP 8.5, isolated package autoloading and no-dev production boot. Coverage is disabled. Exact release evidence is recorded in the consuming application's M6 upgrade register; do not infer a passing check from this release-note text. No branch merges to develop or production deployments are part of M6.

# Laravel 13 minor release

3.1.0 requires PHP 8.4 and Laravel ^13.40. Development uses Testbench ^11.3. This minor intentionally drops Laravel 12 support under the approved project release policy; Laravel 12 consumers must retain ~3.0.0. Existing module registry and frontend APIs are preserved.

# Laravel 12 upgrade notes

This file records the compatibility break and release preparation for `john-it-com/bc-ui-runtime`. Candidate version: **3.0.0**, from `laravel-12-13`. It is not yet tagged, published or verified.

This new package major requires PHP 8.4 and Laravel 12 (`^12.72`). PHP 8.3 and Laravel 11 are no longer supported by this release. Composer resolves dependencies against platform PHP `8.4.0`; PHP 8.5 qualification is outside this upgrade.

Added runtime/class-path checks and a registry-sharing regression for Laravel 12 container behavior. The existing provider constructs the registry explicitly and its nullable dependency has an explicit fallback, so no production change is required. Aligned package.json and package-lock.json to 3.0.0 for the existing tag-driven npm publisher; frontend dependencies and runtime assets are unchanged.

The backend and analysis workflows use PHP 8.4 only, with the Laravel/Testbench floor `12.72.0` / `10.12.0` and latest compatible Laravel 12 / Testbench 10 resolutions. Tests assert that framework and harness classes load from this package's own vendor directory. Workflow artifacts retain exact dependency resolutions and backend coverage where applicable. Existing analysis levels are preserved; no suppression baseline is introduced.

Consumers must use `~3.0.0` for this Laravel 12 line after publication. The planned `3.1.0` minor will require Laravel 13 and drop Laravel 12 support under the project's explicit release-policy exception; a broad `^3.0` would admit that incompatible framework line. State the break again in that minor's release notes.

Tag 3.0.0 only after developer-owned target-stage checks pass. Tags originate from verified commits on `laravel-12-13`; do not merge this branch to `develop` before the global Laravel 13 acceptance gate. The accepted Laravel 11 baseline limitations do not waive these release checks.

References: [Laravel 12 upgrade guide](https://laravel.com/framework/docs/12.x/upgrade), [Testbench compatibility](https://packages.tools/getting-started), [Testbench 10.12.0 metadata](https://github.com/orchestral/testbench/blob/v10.12.0/composer.json).

Pushing a matching numeric tag triggers this repository's existing npm publication workflow. Prepare and verify Composer and npm version metadata together before publishing; no npm publication or frontend build was initiated as part of source preparation.

## 3.0.1 production-install correction

Development discovery is explicit (`composer discover`), so package-root Composer installs without development dependencies do not invoke an absent Testbench executable. Backend and production jobs qualify both Laravel 12 resolutions without collecting coverage. Prior 3.0.0 evidence remains historical; publish this correction only after its own checks pass.
