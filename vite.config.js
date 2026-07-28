import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import statamic from '@statamic/cms/vite-plugin';

/**
 * One config, two jobs.
 *
 * Building the CP bundle uses the Statamic plugin, which rewrites `vue` to
 * `window.Vue` and leaves `@statamic/cms/*` to the host app — correct for a
 * bundle that runs inside the Control Panel, fatal in a test process, where
 * there is no `window.Vue` and the imports have to resolve for real.
 *
 * Under Vitest we therefore compile the SFCs with the plain Vue plugin. The
 * `@statamic/cms/*` entry points then resolve through node_modules (they are
 * symlinked to `vendor/statamic/cms/resources/dist-package`) and read their
 * components off the `__STATAMIC__` global, which `tests/js/setup.js`
 * populates with stubs before any test module is imported.
 */
const isTest = !!process.env.VITEST;

export default defineConfig({
    plugins: isTest
        ? [vue()]
        : [
            statamic(),
            tailwindcss(),
            laravel({
                hotFile: 'resources/dist/hot',
                publicDirectory: 'resources/dist',
                input: [
                    'resources/js/cp.js',
                    'resources/css/cp.css',
                ],
            }),
        ],

    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
        setupFiles: ['tests/js/setup.js'],
    },
});
