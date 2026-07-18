<?php

namespace Goldnead\WebhookManager\Tests\Feature;

use Goldnead\WebhookManager\Tests\TestCase;

/**
 * Every CP page (Overview, Outbound, Inbound, Rules, Insights, …) is bundled
 * into the single Vite entry `resources/js/cp.js`. Statamic 6 does NOT ship a
 * `vue` importmap to the browser; instead `@statamic/cms/vite-plugin` rewrites
 * every `import … from 'vue'` to `window.Vue` at build time.
 *
 * If the shipped bundle ever contains a bare `vue` specifier (e.g. because it
 * was built without the Statamic externals plugin, or a `from 'vue'` slipped
 * past the rewrite), the browser throws:
 *
 *     Uncaught TypeError: Failed to resolve module specifier "vue"
 *
 * on the FIRST page that executes that code path. This test locks the build
 * contract structurally, so a mis-built bundle fails CI instead of only
 * surfacing in the browser on a specific screen (the reported Inbound crash).
 */
class CpBundleExternalizesVueTest extends TestCase
{
    public function test_shipped_cp_bundle_contains_no_bare_vue_module_specifier(): void
    {
        $manifestPath = __DIR__.'/../../resources/dist/build/manifest.json';
        $this->assertFileExists(
            $manifestPath,
            'Vite manifest is missing — run `npm run build` before shipping the addon.'
        );

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest, 'Vite manifest is not valid JSON.');
        $this->assertArrayHasKey('resources/js/cp.js', $manifest, 'cp.js entry is missing from the manifest.');

        $distDir = dirname($manifestPath);

        // Collect every JS chunk referenced by the manifest (the entry may
        // code-split into async chunks; each one must also be vue-free).
        $jsFiles = [];
        foreach ($manifest as $entry) {
            if (isset($entry['file']) && str_ends_with($entry['file'], '.js')) {
                $jsFiles[] = $distDir.'/'.$entry['file'];
            }
            foreach ((array) ($entry['dynamicImports'] ?? []) as $dep) {
                if (isset($manifest[$dep]['file'])) {
                    $jsFiles[] = $distDir.'/'.$manifest[$dep]['file'];
                }
            }
        }
        $jsFiles = array_values(array_unique($jsFiles));
        $this->assertNotEmpty($jsFiles, 'No JS chunks found in the build manifest.');

        // Matches any surviving ESM reference to the bare `vue` module:
        //   import x from 'vue' / import 'vue' / export … from "vue" / import('vue')
        $bareVue = '/(?:import|export)[^;\n]*?[\'"]vue[\'"]|import\(\s*[\'"]vue[\'"]\s*\)/';

        foreach ($jsFiles as $file) {
            $this->assertFileExists($file, "Manifest references missing chunk: {$file}");
            $code = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                $bareVue,
                $code,
                sprintf(
                    'Built chunk %s still contains a bare "vue" module specifier. '
                    .'The Statamic externals plugin must rewrite every `from \'vue\'` to '
                    .'`window.Vue`; a bare specifier crashes the CP with '
                    .'"Failed to resolve module specifier vue".',
                    basename($file)
                )
            );
        }
    }
}
