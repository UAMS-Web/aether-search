<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use UAMSWeb\Aether\Search\ServiceProvider;
use UAMSWeb\Aether\Search\Tags\AetherSearch;

/**
 * What the service provider declares for Statamic to read at boot: the tag, the route file, the view
 * namespace and the Vite build. Read from a constructed provider, so nothing boots.
 *
 * @command  vendor/bin/pest tests/Unit/ServiceProviderDefaultsTest.php
 */

/**
 * A declared property of a provider built on an application that is never booted.
 *
 * @param  string  $name  The property's name.
 */
function providerProperty(string $name): mixed
{
    $provider = new ServiceProvider(new Application(dirname(__DIR__, 2)));

    return new ReflectionProperty($provider, $name)->getValue($provider);
}

it('registers the aether_search tag', function (): void {
    // Expect exactly the add-on's one tag class
    expect(providerProperty('tags'))->toBe([AetherSearch::class]);
});

it('loads the web routes file that ships in the package', function (): void {
    // Read the routes the provider declares
    $routes = providerProperty('routes');
    $web = is_array($routes) && is_string($routes['web'] ?? null) ? $routes['web'] : '';

    // Expect one `web` entry that resolves to the package's routes file
    expect($routes)->toBeArray()->and(array_keys((array) $routes))->toBe(['web'])
        ->and(realpath($web))->toBe(realpath(__DIR__.'/../../routes/web.php'));
});

it('names the view namespace the README and the views use', function (): void {
    // Expect the `aether-search` namespace
    expect(providerProperty('viewNamespace'))->toBe('aether-search');
});

it('builds the two Control Panel entry files into resources/dist', function (): void {
    // Expect both inputs, in order, and the output directory
    expect(providerProperty('vite'))->toBe([
        'input' => ['resources/js/cp.js', 'resources/css/cp.css'],
        'publicDirectory' => 'resources/dist',
    ]);
});
