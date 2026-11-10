<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Facades\Site;
use UAMSWeb\Aether\Search\Support\SearchScopes;

/**
 * The scope registry's reading of `aether-search.tabs` and `tab_collections`: which tabs exist, which
 * a site's page shows under "This site", and which index a request lands on.
 *
 * @command  vendor/bin/pest tests/Integration/SearchScopesRegistryTest.php
 */

/**
 * Save a collection that is carried by the given sites.
 *
 * @param  string  $handle  The collection's handle.
 * @param  list<string>  $sites  The sites that carry it.
 */
function carriedCollection(string $handle, array $sites): void
{
    $collection = new Collection;
    $collection->handle($handle);
    $collection->sites($sites);
    $collection->save();
}

/**
 * Two sites, so a collection can be carried by one and not the other.
 */
function twoSitesForScopes(): void
{
    // Turn multisite on, or a collection reports only the default site
    config(['statamic.system.multisite' => true, 'statamic.editions.pro' => true]);

    Site::setSites([
        'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
    ]);
}

it('lists the configured tabs with default first, under its configured or its own label', function (array $tabs, array $expected): void {
    // Configure the registry
    config(['aether-search.tabs' => $tabs]);

    // Expect default first, then the other string-labelled tabs in order
    expect((new SearchScopes)->tabs())->toBe($expected);
})->with([
    'no default entry' => [['news' => 'News'], ['default' => 'All results', 'news' => 'News']],
    'a default label' => [['default' => 'Everything'], ['default' => 'Everything']],
    'a default listed last' => [['news' => 'News', 'default' => 'Everything'], ['default' => 'Everything', 'news' => 'News']],
    'a label that is not a string' => [['news' => 7, 'events' => 'Events'], ['default' => 'All results', 'events' => 'Events']],
]);

it('lists only the default tab when the registry is not an array', function (): void {
    // Replace the registry with a scalar
    config(['aether-search.tabs' => 'news']);

    // Expect the default tab alone
    expect((new SearchScopes)->tabs())->toBe(['default' => 'All results']);
});

it('lists index handles as strings, including a numeric one', function (): void {
    // Configure a tab whose handle PHP stores as an integer key
    config(['aether-search.tabs' => ['default' => 'All', '7' => 'Seven']]);

    // Expect every handle a string
    expect((new SearchScopes)->indexes())->toBe(['default', '7']);
});

it('shows every tab under a scope wider than this site', function (): void {
    // Configure a tab that no site carries
    twoSitesForScopes();
    config(['aether-search.tabs' => ['news' => 'News'], 'aether-search.tab_collections' => ['news' => ['nowhere']]]);

    // Expect it shown under "all sites", but not under "this site"
    expect(array_keys((new SearchScopes)->visibleTabs('default', 'all')))->toBe(['default', 'news'])
        ->and(array_keys((new SearchScopes)->visibleTabs('default', 'site')))->toBe(['default']);
});

it('shows a tab under this site only when the site carries one of its collections', function (): void {
    // Two sites; one collection carried by `health` only
    twoSitesForScopes();
    carriedCollection('doctors', ['health']);
    config(['aether-search.tabs' => ['people' => 'People'], 'aether-search.tab_collections' => ['people' => ['doctors']]]);

    // Expect the tab on `health` and not on `default`
    expect(array_keys((new SearchScopes)->visibleTabs('health', 'site')))->toBe(['default', 'people'])
        ->and(array_keys((new SearchScopes)->visibleTabs('default', 'site')))->toBe(['default']);
});

it('always shows a tab that names no collections', function (mixed $covers): void {
    // Configure a tab with no usable collection list
    twoSitesForScopes();
    config(['aether-search.tabs' => ['news' => 'News'], 'aether-search.tab_collections' => $covers]);

    // Expect it shown on a site that carries nothing
    expect(array_keys((new SearchScopes)->visibleTabs('default', 'site')))->toBe(['default', 'news']);
})->with([
    'tab_collections not an array' => ['x'],
    'no entry for the tab' => [['other' => ['a']]],
    'an entry that is not a list' => [['news' => 'a']],
]);

it('hides a tab whose collection list is empty under this site', function (): void {
    // Configure a tab that covers an empty list
    twoSitesForScopes();
    config(['aether-search.tabs' => ['news' => 'News'], 'aether-search.tab_collections' => ['news' => []]]);

    // Expect it hidden, since it can fill from no collection
    expect(array_keys((new SearchScopes)->visibleTabs('default', 'site')))->toBe(['default']);
});

it('matches a tab collection by exact handle, not by loose equality', function (): void {
    // A site carrying a collection whose handle is the string "1", and a tab naming the integer 1
    twoSitesForScopes();
    carriedCollection('1', ['default']);
    config(['aether-search.tabs' => ['news' => 'News'], 'aether-search.tab_collections' => ['news' => [1]]]);

    // Expect the tab hidden, because 1 is not "1" under a strict comparison
    expect(array_keys((new SearchScopes)->visibleTabs('default', 'site')))->toBe(['default']);
});

it('reports the collections a site carries', function (): void {
    // One collection per site, and one carried by both
    twoSitesForScopes();
    carriedCollection('only_default', ['default']);
    carriedCollection('only_health', ['health']);
    carriedCollection('both', ['default', 'health']);

    // Expect each site's own, and none for an unknown site
    $scopes = new SearchScopes;
    expect($scopes->carriedBy('health'))->toContain('only_health', 'both')->not->toContain('only_default')
        ->and($scopes->carriedBy('default'))->toContain('only_default', 'both')->not->toContain('only_health')
        ->and($scopes->carriedBy('nowhere'))->not->toContain('both');
});

it('lands a request on a visible tab, and on default for anything else', function (mixed $requested, string $expected): void {
    // Configure two tabs, one of them hidden under this site
    twoSitesForScopes();
    config(['aether-search.tabs' => ['news' => 'News', 'hidden' => 'Hidden'], 'aether-search.tab_collections' => ['hidden' => ['nowhere']]]);

    // Resolve the index under "this site"
    expect((new SearchScopes)->index($requested, 'default', 'site'))->toBe($expected);
})->with([
    'a visible tab' => ['news', 'news'],
    'the default tab' => ['default', 'default'],
    'a hidden tab' => ['hidden', 'default'],
    'an unknown handle' => ['secret', 'default'],
    'an array' => [['news'], 'default'],
    'nothing' => [null, 'default'],
]);
