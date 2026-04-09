/**
 * Shared Tailwind preset for bc-* modules.
 *
 * Purpose: Centralize the shared design tokens and safelist rules used across modules.
 * Role: Lets each module keep local content scanning while sharing the same Tailwind theme.
 * Constraint: Enforce class-based dark mode so browser/OS color-scheme does not auto-switch the UI.
 */
// Resolve Tailwind from the consuming module so bc-ui-runtime does not need Tailwind installed.
const defaultTheme = require(
    require.resolve('tailwindcss/defaultTheme', { paths: [process.cwd()] })
);

module.exports = {
    // Class strategy disables automatic media-query dark mode unless app code explicitly sets `.dark`.
    darkMode: 'class',
    safelist: [
        {
            pattern: /^(text|bg|ring|fill)-(primary|secondary|light|success|warning|danger|info)(-\d+)?(\/\d+)?$/,
        }
    ],
    theme: {
        extend: {
            screens: {
                '3xl': '112rem',
            },
            fontFamily: {
                sans: ['InterVariable', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    '50': '#eef2ff',
                    '100': '#e0e7ff',
                    '200': '#c7d2fe',
                    '300': '#a5b4fc',
                    '400': '#818cf8',
                    '500': '#6366f1',
                    '600': '#4f46e5',
                    '700': '#4338ca',
                    '800': '#3730a3',
                    '900': '#312e81',
                    '950': '#1e1b4b',
                }, // Indigo
                secondary: {
                    '50': '#f9fafb',
                    '100': '#f3f4f6',
                    '200': '#e5e7eb',
                    '300': '#d1d5db',
                    '400': '#9ca3af',
                    '500': '#6b7280',
                    '600': '#4b5563',
                    '700': '#374151',
                    '800': '#1f2937',
                    '900': '#111827',
                    '950': '#030712',
                }, // Gray
                light: {
                    '50': '#f8fafc',
                    '100': '#f1f5f9',
                    '200': '#e2e8f0',
                    '300': '#cbd5e1',
                    '400': '#94a3b8',
                    '500': '#64748b',
                    '600': '#475569',
                    '700': '#334155',
                    '800': '#1e293b',
                    '900': '#0f172a',
                    '950': '#020617',
                }, // Slate
                success: {
                    '50': '#f0fdf4',
                    '100': '#dcfce7',
                    '200': '#bbf7d0',
                    '300': '#86efac',
                    '400': '#4ade80',
                    '500': '#22c55e',
                    '600': '#16a34a',
                    '700': '#15803d',
                    '800': '#166534',
                    '900': '#14532d',
                    '950': '#052e16',
                }, // Green
                danger: {
                    '50': '#fef2f2',
                    '100': '#fee2e2',
                    '200': '#fecaca',
                    '300': '#fca5a5',
                    '400': '#f87171',
                    '500': '#ef4444',
                    '600': '#dc2626',
                    '700': '#b91c1c',
                    '800': '#991b1b',
                    '900': '#7f1d1d',
                    '950': '#450a0a',
                }, // Red
                warning: {
                    '50': '#fefce8',
                    '100': '#fef9c3',
                    '200': '#fef08a',
                    '300': '#fde047',
                    '400': '#facc15',
                    '500': '#eab308',
                    '600': '#ca8a04',
                    '700': '#a16207',
                    '800': '#854d0e',
                    '900': '#713f12',
                    '950': '#422006',
                }, // Yellow
                info: {
                    '50': '#ecfeff',
                    '100': '#cffafe',
                    '200': '#a5f3fc',
                    '300': '#67e8f9',
                    '400': '#22d3ee',
                    '500': '#06b6d4',
                    '600': '#0891b2',
                    '700': '#0e7490',
                    '800': '#155e75',
                    '900': '#164e63',
                    '950': '#083344',
                }, // Cyan
            },
        },
    },
    plugins: [],
};
