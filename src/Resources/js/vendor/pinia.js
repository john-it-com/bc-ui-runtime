/**
 * Pinia ESM bridge entry.
 *
 * Purpose: Provide a stable bc-ui-runtime import-map target for Pinia.
 * Role: Ensures all modules resolve Pinia from a single public URL.
 */
export * from 'pinia';
