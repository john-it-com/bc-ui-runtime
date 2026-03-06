/**
 * File: inertia.d.ts
 * Purpose: Provide shared Inertia page prop typings for runtime usage.
 * Role: Allows module augmentation for shared page props in strict mode.
 */
import '@inertiajs/core';

declare module '@inertiajs/core' {
    interface PageProps {
        i18n?: {
            locale?: string;
            timezone?: string;
            messages?: Record<string, unknown>;
        };
        ziggy?: Record<string, unknown> & { location?: string | URL };
        flashNotifications?: unknown;
    }
}
