<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use UAMSWeb\Aether\Search\Contracts\SiteTree;
use UAMSWeb\Aether\Search\Support\FlatSiteTree;
use UAMSWeb\Aether\Search\Support\SearchScopes;
use UAMSWeb\Aether\Search\Tests\Support\FakeSearchIndex;

/**
 * Which sites each scope covers once a host binds a {@see SiteTree}, and that the default stays flat.
 *
 * The stub tree stands in for an application's: `health` is a tier covering itself and
 * `health_nicu`, `health_nicu` sits under it, and `cancer` has no tier between it and the root.
 *
 * @command  vendor/bin/pest tests/Integration/SiteTreeScopeTest.php
 */

/**
 * A site tree that answers from fixed maps.
 */
final class StubSiteTree implements SiteTree
{
    /**
     * @param  array<string, list<string>>  $covered  Site handle => the sites "This site" covers.
     * @param  array<string, string>  $tiers  Site handle => its tier.
     */
    public function __construct(private readonly array $covered = [], private readonly array $tiers = []) {}

    /**
     * {@inheritDoc}
     */
    public function covered(string $siteHandle): array
    {
        return $this->covered[$siteHandle] ?? [$siteHandle];
    }

    /**
     * {@inheritDoc}
     */
    public function tierOf(string $siteHandle): ?string
    {
        return $this->tiers[$siteHandle] ?? null;
    }
}

/**
 * Define the four sites, bind a site tree, make a site current, and request `$uri`.
 *
 * @param  string  $current  The handle of the current site.
 * @param  string  $uri  The request URI, query string included.
 * @param  SiteTree|null  $tree  The tree to bind, or null for the package default.
 */
function onTreeSite(string $current, string $uri = '/search', ?SiteTree $tree = null): void
{
    // Define the sites
    Site::setSites([
        'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
        'health_nicu' => ['name' => 'NICU', 'url' => '/health-nicu/', 'locale' => 'en_US'],
        'cancer' => ['name' => 'Cancer', 'url' => '/cancer/', 'locale' => 'en_US'],
    ]);

    // Bind the tree under test, then make one site current and bind a request carrying the query
    app()->instance(SiteTree::class, $tree ?? new StubSiteTree(
        ['health' => ['health', 'health_nicu']],
        ['health_nicu' => 'health'],
    ));
    Site::setCurrent($current);
    app()->instance('request', Request::create($uri));
}

/**
 * Render a tag expression through Antlers, trusted as a view is.
 *
 * @param  string  $template  The Antlers to render.
 * @return string The rendered text.
 */
function renderTreeTag(string $template): string
{
    return trim((string) Antlers::parse($template, [], true));
}

it('binds a flat tree by default', function (): void {
    // Read the default binding and what it answers
    $tree = app(SiteTree::class);

    // Expect each site to cover itself alone, with no tier
    expect($tree)->toBeInstanceOf(FlatSiteTree::class)
        ->and($tree->covered('health'))->toBe(['health'])
        ->and($tree->tierOf('health'))->toBeNull();
});

it('keeps the two scopes and one site each under the flat default', function (): void {
    // Stand on a sub-site with the package default bound
    onTreeSite('health_nicu', '/health-nicu/search?scope=tier', new FlatSiteTree);

    // Expect no tier option, the tier request falling back, and the one site
    expect(renderTreeTag('{{ aether_search:active }}'))->toBe('site')
        ->and(renderTreeTag('{{ aether_search:options active="site" }}[{{ label }}={{ param }}]{{ /aether_search:options }}'))->toBe('[This site=][All of Main=all]')
        ->and(renderTreeTag('{{ aether_search:sites active="site" }}'))->toBe('health_nicu')
        ->and(renderTreeTag('{{ aether_search:sites active="all" }}'))->toBe('*');
});

it('searches a tier and every site below it from "This site"', function (): void {
    // Stand on the tier
    onTreeSite('health', '/health/search');

    // Expect the tier's whole subtree, and no tier option of its own
    expect(renderTreeTag('{{ aether_search:sites active="site" }}'))->toBe('health|health_nicu')
        ->and(renderTreeTag('{{ aether_search:options active="site" }}[{{ label }}={{ param }}]{{ /aether_search:options }}'))->toBe('[This site=][All of Main=all]');
});

it('offers a site below a tier "This site", "All of <tier>" and "All of <root>", in that order', function (): void {
    // Stand below the tier
    onTreeSite('health_nicu', '/health-nicu/search');

    // Expect the three options, with "This site" covering the one site
    expect(renderTreeTag('{{ aether_search:options active="site" }}[{{ value }}:{{ label }}={{ param }}]{{ /aether_search:options }}'))
        ->toBe('[site:This site=][tier:All of Health=tier][all:All of Main=all]')
        ->and(renderTreeTag('{{ aether_search:sites active="site" }}'))->toBe('health_nicu');
});

it("resolves the tier scope and searches the tier's sites", function (): void {
    // Ask for the tier from below it
    onTreeSite('health_nicu', '/health-nicu/search?scope=tier');

    // Expect the tier scope, its param, and the tier's subtree
    expect(renderTreeTag('{{ aether_search:active }}'))->toBe('tier')
        ->and(renderTreeTag('{{ aether_search:param active="tier" }}'))->toBe('tier')
        ->and(renderTreeTag('{{ aether_search:sites active="tier" }}'))->toBe('health|health_nicu');
});

it('falls back to "This site" when a site with no tier asks for one', function (): void {
    // Ask for a tier from a site that has none, and from the root
    onTreeSite('cancer', '/cancer/search?scope=tier');
    expect(renderTreeTag('{{ aether_search:active }}'))->toBe('site')
        ->and(renderTreeTag('{{ aether_search:options active="site" }}[{{ label }}]{{ /aether_search:options }}'))->toBe('[This site][All of Main]');

    onTreeSite('default', '/search?scope=tier');
    expect(renderTreeTag('{{ aether_search:active }}'))->toBe('all')
        ->and(renderTreeTag('{{ aether_search:param active="tier" }}'))->toBe('');
});

it('refuses a tier that is the site itself, the root site or not a configured site', function (string $tier): void {
    // Bind a tree whose tier answer is not one the page can offer
    onTreeSite('cancer', '/cancer/search?scope=tier', new StubSiteTree([], ['cancer' => $tier]));

    // Expect no tier, so the request falls back and the page offers two options
    expect(app(SearchScopes::class)->tierOf('cancer'))->toBeNull()
        ->and(renderTreeTag('{{ aether_search:active }}'))->toBe('site')
        ->and(renderTreeTag('{{ aether_search:options active="site" }}[{{ label }}]{{ /aether_search:options }}'))->toBe('[This site][All of Main]');
})->with(['the site itself' => 'cancer', 'the root site' => 'default', 'an unknown site' => 'nowhere']);

it('always puts the site the reader is on among "This site"\'s, once', function (): void {
    // Bind a tree that leaves the site out and repeats another
    onTreeSite('health', '/health/search', new StubSiteTree(['health' => ['health_nicu', 'health_nicu']]));

    // Expect the site first and the other once
    expect(app(SearchScopes::class)->sites('health', 'site'))->toBe(['health', 'health_nicu'])
        ->and(app(SearchScopes::class)->sites('health', 'all'))->toBeNull()
        ->and(app(SearchScopes::class)->sites('cancer', 'tier'))->toBe(['cancer']);
});

it('shows a tab for a collection any site the scope covers carries', function (): void {
    // Turn multisite on, or a collection reports only the default site, and carry `news` below the tier only
    config(['statamic.system.multisite' => true, 'statamic.editions.pro' => true]);
    onTreeSite('health', '/health/search');
    config(['aether-search.tabs' => ['default' => 'All results', 'news' => 'News'], 'aether-search.tab_collections' => ['news' => ['news']]]);
    $news = new Collection;
    $news->handle('news');
    $news->sites(['health_nicu']);
    $news->save();

    $scopes = app(SearchScopes::class);

    // Expect the tab on the tier, on the tier scope below it, and not on a site that carries nothing
    expect(array_keys($scopes->visibleTabs('health', 'site')))->toBe(['default', 'news'])
        ->and(array_keys($scopes->visibleTabs('health_nicu', 'tier')))->toBe(['default', 'news'])
        ->and(array_keys($scopes->visibleTabs('cancer', 'site')))->toBe(['default']);
});

it('suggests from the same sites the page searches for each scope', function (string $query, ?array $expected): void {
    // Answer the suggestions route's index with a fake, under the stub tree
    onTreeSite('default');
    $index = new FakeSearchIndex;
    Search::shouldReceive('index')->andReturn($index);

    $this->getJson('/!/search/suggestions?q=cardio'.$query)->assertOk();

    // Expect the scope's sites, or no constraint for every site
    expect($index->sites)->toBe($expected);
})->with([
    'tier, this site' => ['&site=health', ['health', 'health_nicu']],
    'below the tier, this site' => ['&site=health_nicu', ['health_nicu']],
    'below the tier, tier scope' => ['&site=health_nicu&scope=tier', ['health', 'health_nicu']],
    'no tier, tier scope' => ['&site=cancer&scope=tier', ['cancer']],
    'below the tier, every site' => ['&site=health_nicu&scope=all', null],
]);

it('searches every site when the tag is given no scope', function (): void {
    // Stand on a sub-site and ask for the sites with no `active` scope
    onTreeSite('health', '/health/search');

    expect(renderTreeTag('{{ aether_search:sites }}'))->toBe('*');
});

it('keeps two covered handles that only compare equal as numbers', function (): void {
    // `10.0` and `1e1` are the same number, and two configured sites
    onTreeSite('health', '/health/search', new StubSiteTree(['health' => ['10.0', '1e1']]));
    Site::setSites([
        'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
        '10.0' => ['name' => 'Ten', 'url' => '/ten/', 'locale' => 'en_US'],
        '1e1' => ['name' => 'Also ten', 'url' => '/also-ten/', 'locale' => 'en_US'],
    ]);

    expect(app(SearchScopes::class)->sites('health', 'site'))->toBe(['health', '10.0', '1e1']);
});

it('drops a covered handle that names no configured site', function (): void {
    // A host tree naming an unknown site, and one with the page's separator in it
    onTreeSite('health', '/health/search', new StubSiteTree(['health' => ['nowhere', 'health_nicu|cancer', 'health_nicu']]));

    // Expect the page and the type-ahead to get the same two configured sites
    expect(app(SearchScopes::class)->sites('health', 'site'))->toBe(['health', 'health_nicu'])
        ->and(renderTreeTag('{{ aether_search:sites active="site" }}'))->toBe('health|health_nicu');
});

it('labels a tier by its handle when its configured name is not a string', function (): void {
    // Name the tier with a number
    onTreeSite('health_nicu', '/health-nicu/search');
    Site::setSites([
        'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 7, 'url' => '/health/', 'locale' => 'en_US'],
        'health_nicu' => ['name' => 'NICU', 'url' => '/health-nicu/', 'locale' => 'en_US'],
    ]);
    Site::setCurrent('health_nicu');

    expect(renderTreeTag('{{ aether_search:options active="site" }}[{{ label }}]{{ /aether_search:options }}'))->toBe('[This site][All of health][All of Main]');
});
