<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use UAMSWeb\Aether\Search\Support\SearchScopes;
use UAMSWeb\Aether\Search\Tests\Support\FakeSearchIndex;

/**
 * The search scopes, defined once: which tabs a site shows, which index a request lands on, and
 * where the scope links point.
 *
 * Three sites are defined: the root, `health` (which carries the clinical collection) and `other`
 * (which does not). Each test names its own collections, because saved collections outlive a test
 * within a worker.
 *
 * @command  vendor/bin/pest tests/Integration/SearchScopesTest.php
 */

/**
 * Define the sites and the collections each carries, and configure three tabs over them.
 *
 * @return array{people: string, clinical: string} The collection handles the tabs cover.
 */
function scopesWorld(): array
{
    // Turn multisite on and define the sites
    config(['statamic.system.multisite' => true, 'statamic.editions.pro' => true]);
    Site::setSites([
        'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
        'other' => ['name' => 'Other', 'url' => '/other/', 'locale' => 'en_US'],
    ]);

    // Save a collection every site carries, and one only `health` carries
    $suffix = str_replace('.', '', uniqid('', true));
    $people = 'people_'.$suffix;
    $clinical = 'clinical_'.$suffix;

    foreach ([$people => ['default', 'health', 'other'], $clinical => ['health']] as $handle => $sites) {
        $collection = new Collection;
        $collection->handle($handle);
        $collection->sites($sites);
        $collection->save();
    }

    // Offer a tab for each, beside "All results"
    config([
        'aether-search.tabs' => ['default' => 'All results', 'people' => 'People', 'clinical' => 'Clinical'],
        'aether-search.tab_collections' => ['people' => [$people], 'clinical' => [$clinical]],
        'statamic.search.indexes.people' => ['driver' => 'local', 'searchables' => ['collection:'.$people]],
        'statamic.search.indexes.clinical' => ['driver' => 'local', 'searchables' => ['collection:'.$clinical]],
    ]);

    return ['people' => $people, 'clinical' => $clinical];
}

/**
 * Stand on a site, with a request for `$uri`.
 *
 * @param  string  $site  The handle of the current site.
 * @param  string  $uri  The request URI, query string included.
 */
function scopesStandOn(string $site, string $uri = '/search'): void
{
    Site::setCurrent($site);
    app()->instance('request', Request::create($uri));
}

/**
 * Render a tag expression through Antlers, trusted as a view is.
 *
 * @param  string  $template  The Antlers to render.
 */
function scopesRender(string $template): string
{
    return trim((string) Antlers::parse($template, [], true));
}

const SCOPES_TABS = '{{ aether_search:tabs :active="\'default\'" }}[{{ handle }}]{{ /aether_search:tabs }}';

it('shows under "This site" only the tabs the site can fill', function (): void {
    // Stand on a site that carries people but not the clinical collection
    scopesWorld();
    scopesStandOn('other');

    // Expect "All results" and People, and no Clinical
    expect(scopesRender(SCOPES_TABS))->toBe('[default][people]');
});

it('shows every tab on a site that carries every collection', function (): void {
    // Stand on the site that carries both
    scopesWorld();
    scopesStandOn('health');

    // Expect all three
    expect(scopesRender(SCOPES_TABS))->toBe('[default][people][clinical]');
});

it('shows every tab under "All sites", on any site', function (): void {
    // Stand on the site without the clinical collection, asking for every site
    scopesWorld();
    scopesStandOn('other', '/other/search?scope=all');

    // Expect all three
    expect(scopesRender(SCOPES_TABS))->toBe('[default][people][clinical]');

    // Expect the same on the root site, whose one scope is every site
    scopesStandOn('default');
    expect(scopesRender(SCOPES_TABS))->toBe('[default][people][clinical]');
});

it('drops a tab whose collection the site does not carry, the same rule for any collection', function (): void {
    // Carry people on the root and health only
    $world = scopesWorld();
    $people = CollectionFacade::findByHandle($world['people']);
    $people?->sites(['default', 'health']);
    $people?->save();
    scopesStandOn('other');

    // Expect People to go, as Clinical does
    expect(scopesRender(SCOPES_TABS))->toBe('[default]');
});

it('lands a link to "This site" on "All results" when the site does not show the tab', function (): void {
    // Stand on a site without the clinical collection, on the Clinical tab under "All sites"
    scopesWorld();
    scopesStandOn('other', '/other/search?scope=all&index=clinical');

    // Expect the "This site" link to go to the default index and the "All sites" link to keep the tab
    expect(scopesRender('{{ aether_search:options active="all" index="clinical" }}[{{ value }}:{{ target_index }}]{{ /aether_search:options }}'))
        ->toBe('[site:default][all:clinical]');
});

it('keeps a tab on the "This site" link when the site shows it', function (): void {
    // Stand on a site that carries People, on the People tab
    scopesWorld();
    scopesStandOn('other', '/other/search?scope=all&index=people');

    // Expect both links to keep it
    expect(scopesRender('{{ aether_search:options active="all" index="people" }}[{{ value }}:{{ target_index }}]{{ /aether_search:options }}'))
        ->toBe('[site:people][all:people]');
});

it('falls back to the default index for a hidden or unknown index, on the page', function (): void {
    // Ask a site without the clinical collection for the Clinical tab, then for an unknown one
    scopesWorld();
    scopesStandOn('other', '/other/search?index=clinical');
    expect(scopesRender('{{ aether_search:active_index }}'))->toBe('default');

    scopesStandOn('other', '/other/search?index=nobody');
    expect(scopesRender('{{ aether_search:active_index }}'))->toBe('default');

    // Expect the tab when the search covers every site
    scopesStandOn('other', '/other/search?scope=all&index=clinical');
    expect(scopesRender('{{ aether_search:active_index }}'))->toBe('clinical');
});

it('falls back to the default index for a hidden or unknown index, on the suggestions endpoint', function (): void {
    // Answer the route's index with a fake
    scopesWorld();
    Search::shouldReceive('index')->andReturn(new FakeSearchIndex);

    // Ask from a site without the clinical collection for its tab, then for an unknown one
    $hidden = $this->getJson('/!/search/suggestions?q=cardio&site=other&index=clinical');
    $unknown = $this->getJson('/!/search/suggestions?q=cardio&site=other&index=nobody');
    $wide = $this->getJson('/!/search/suggestions?q=cardio&site=other&scope=all&index=clinical');

    // Expect 200 and the default index for the first two, and the tab itself under "All sites"
    expect($hidden->assertOk()->json('index'))->toBe('default')
        ->and($unknown->assertOk()->json('index'))->toBe('default')
        ->and($wide->assertOk()->json('index'))->toBe('clinical');
});

it('keeps the knowledge-base search as it is: its own index, whatever the site carries', function (): void {
    // Configure the scoped mode and stand on a site without the clinical collection
    scopesWorld();
    config([
        'aether-search.scoped' => ['index' => 'subtree', 'parameter' => 'root', 'query_scope' => 'subtree_articles'],
        'aether-search.tabs' => ['default' => 'All results', 'subtree' => 'Subtree'],
    ]);
    scopesStandOn('other', '/other/search?index=subtree&root=abc');

    // Expect the scoped index, as before
    expect(scopesRender('{{ aether_search:active_index }}'))->toBe('subtree');
});

it('names an index nobody configured, so a host can fail its own test', function (): void {
    // Expect none unconfigured with the host's indexes in place
    scopesWorld();
    expect(app(SearchScopes::class)->unconfiguredIndexes())->toBe([]);

    // Add a scope whose index the host never defined
    config(['aether-search.tabs' => ['default' => 'All results', 'ghost' => 'Ghost']]);

    // Expect it named
    expect(app(SearchScopes::class)->unconfiguredIndexes())->toBe(['ghost']);
});

it('adds a scope with one edit: the tab, the allow-list, the suggestions and the label follow', function (): void {
    // Define a fourth scope in the config alone
    $world = scopesWorld();
    config([
        'aether-search.tabs' => ['default' => 'All results', 'people' => 'People', 'news' => 'News'],
        'aether-search.tab_collections' => ['news' => [$world['people']]],
        'aether-search.labels' => [$world['people'] => 'Person'],
        'statamic.search.indexes.news' => ['driver' => 'local', 'searchables' => ['collection:'.$world['people']]],
    ]);
    scopesStandOn('other', '/other/search?index=news');
    Search::shouldReceive('index')->andReturn(new FakeSearchIndex);

    // Expect the tab, the page's allow-list, the suggestions' allow-list and the label
    expect(scopesRender(SCOPES_TABS))->toBe('[default][people][news]')
        ->and(scopesRender('{{ aether_search:active_index }}'))->toBe('news')
        ->and($this->getJson('/!/search/suggestions?q=cardio&site=other&index=news')->json('index'))->toBe('news')
        ->and(trim((string) Antlers::parse('{{ aether_search:label :collection="c" }}', ['c' => CollectionFacade::findByHandle($world['people'])], true)))->toBe('Person');
});
