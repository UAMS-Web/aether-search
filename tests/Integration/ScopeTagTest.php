<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;

/**
 * How widely the page looks: this site or every site, per `{{ aether_search }}`.
 *
 * @command  vendor/bin/pest tests/Integration/ScopeTagTest.php
 */

/**
 * Define a root site and a `/health` sub-site, make one of them current, and request `$uri`.
 *
 * @param  string  $current  The handle of the current site.
 * @param  string  $uri  The request URI, query string included.
 */
function onSite(string $current, string $uri = '/search'): void
{
    // Define the two sites
    Site::setSites([
        'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
    ]);

    // Make one current and bind a request carrying the query
    Site::setCurrent($current);
    app()->instance('request', Request::create($uri));
}

/**
 * Render a tag expression through Antlers, trusted as a view is.
 *
 * @param  string  $template  The Antlers to render.
 * @return string The rendered text.
 */
function renderTag(string $template): string
{
    return trim((string) Antlers::parse($template, [], true));
}

it('searches every site from the root site and offers only that choice', function (): void {
    // Stand on the root site
    onSite('default');

    // Expect the all-sites scope, no param, the root path and one option
    expect(renderTag('{{ aether_search:active }}'))->toBe('all')
        ->and(renderTag('{{ aether_search:param active="all" }}'))->toBe('')
        ->and(renderTag('{{ aether_search:path }}'))->toBe('/search')
        ->and(renderTag('{{ aether_search:options active="all" }}[{{ label }}]{{ /aether_search:options }}'))->toBe('[This site]');
});

it('searches only the sub-site by default and offers both choices', function (): void {
    // Stand on the sub-site
    onSite('health');

    // Expect this site by default, the sub-site's path, and both options
    expect(renderTag('{{ aether_search:active }}'))->toBe('site')
        ->and(renderTag('{{ aether_search:path }}'))->toBe('/health/search')
        ->and(renderTag('{{ aether_search:options active="site" }}[{{ label }}={{ param }}]{{ /aether_search:options }}'))->toBe('[This site=][All of Main=all]');
});

it('widens to every site only for the exact value, and ignores anything else', function (): void {
    // Stand on the sub-site and ask for all sites
    onSite('health', '/health/search?scope=all');
    expect(renderTag('{{ aether_search:active }}'))->toBe('all')
        ->and(renderTag('{{ aether_search:param active="all" }}'))->toBe('all');

    // Ask for an array, then an unknown string
    onSite('health', '/health/search?scope[]=all');
    expect(renderTag('{{ aether_search:active }}'))->toBe('site');
    onSite('health', '/health/search?scope=everything');
    expect(renderTag('{{ aether_search:active }}'))->toBe('site');
});

it('treats an array-valued query as absent and escapes a string one', function (): void {
    // Stand on the root site with an array-valued query
    onSite('default', '/search?q[]=x');
    expect(renderTag('{{ aether_search:query }}'))->toBe('');

    // Send a query that carries markup
    onSite('default', '/search?q='.urlencode('<b>"x"</b>'));
    expect(renderTag('{{ aether_search:query }}'))->toBe('&lt;b&gt;&quot;x&quot;&lt;/b&gt;');
});
