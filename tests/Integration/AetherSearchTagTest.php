<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Facades\Site;
use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;
use UAMSWeb\Aether\Search\Tags\AetherSearch;
use UAMSWeb\Aether\Search\Tests\Support\RecordingAllowance;

/**
 * The `aether_search` tag's methods, called directly with the parameters a template would pass, so
 * each assertion reads one method's answer rather than a rendered page.
 *
 * @command  vendor/bin/pest tests/Integration/AetherSearchTagTest.php
 */

/**
 * The tag, built the way Statamic builds it, with the parameters a template passes.
 *
 * @param  array<mixed, mixed>  $params  The tag's parameters.
 */
function aetherTag(array $params = []): AetherSearch
{
    $tag = new AetherSearch;
    $tag->setProperties(['parser' => null, 'content' => '', 'context' => [], 'params' => $params, 'tag' => 'aether_search']);

    return $tag;
}

/**
 * Define a root site and a `/health` sub-site, make one current, and bind a request.
 *
 * @param  string  $current  The handle of the current site.
 * @param  string  $uri  The request URI, query string included.
 * @param  array<mixed, mixed>|null  $query  Query parameters to bind instead of the URI's, when set.
 */
function tagOnSite(string $current, string $uri = '/search', ?array $query = null): void
{
    // Define the two sites
    Site::setSites([
        'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
    ]);

    // Make one current and bind a request
    Site::setCurrent($current);
    app()->instance('request', $query === null ? Request::create($uri) : Request::create('/search', 'GET', $query));
}

/**
 * Turn the external scope on with Google as the provider.
 */
function externalScopeOn(): void
{
    config([
        'aether-search.external.enabled' => true,
        'aether-search.external.providers.google.engine_id' => 'abc:123',
    ]);
}

/**
 * Set the `scoped` mode up: its index, its request parameter and nothing else.
 */
function scopedModeOn(): void
{
    config(['aether-search.scoped' => ['index' => 'kb', 'parameter' => 'section']]);
}

it('resolves the scope a page searches for each site and requested value', function (bool $external, mixed $requested, string $site, string $expected): void {
    // Set the external scope on or off, and stand on the site
    tagOnSite($site);
    if ($external) {
        externalScopeOn();
    }

    // Resolve the scope
    /** @var Statamic\Sites\Site $current */
    $current = Site::get($site);
    expect(AetherSearch::resolve($current, $requested))->toBe($expected);
})->with([
    'external requested on a sub-site' => [true, 'google', 'health', 'google'],
    'external requested on the root site' => [true, 'google', 'default', 'google'],
    'another value with external on, on a sub-site' => [true, 'other', 'health', 'site'],
    'another value with external on, on the root site' => [true, 'other', 'default', 'all'],
    'the external value with external off, on a sub-site' => [false, 'google', 'health', 'site'],
    'the external value with external off, on the root site' => [false, 'google', 'default', 'all'],
    'all on a sub-site' => [false, 'all', 'health', 'all'],
    'site on a sub-site' => [false, 'site', 'health', 'site'],
    'nothing on a sub-site' => [false, null, 'health', 'site'],
    'an array on a sub-site' => [false, ['all'], 'health', 'site'],
    'site on the root site' => [false, 'site', 'default', 'all'],
]);

it('shows the external results only for the external scope, and not for the scoped index', function (bool $external, array $params, array $scoped, bool $expected): void {
    // Configure the external scope and the scoped mode
    tagOnSite('health');
    if ($external) {
        externalScopeOn();
    }

    config(['aether-search.scoped' => $scoped]);

    // Ask whether the page renders the external results
    expect(aetherTag($params)->isExternal())->toBe($expected);
})->with([
    'the external scope' => [true, ['scope' => 'google'], [], true],
    'another scope' => [true, ['scope' => 'site'], [], false],
    'no scope' => [true, [], [], false],
    'the external scope while it is off' => [false, ['scope' => 'google'], [], false],
    'a blank index with no scoped mode' => [true, ['scope' => 'google', 'index' => ''], [], true],
    'the scoped index' => [true, ['scope' => 'google', 'index' => 'kb'], ['index' => 'kb'], false],
    'another index with a scoped mode' => [true, ['scope' => 'google', 'index' => 'news'], ['index' => 'kb'], true],
    'no index with a scoped mode' => [true, ['scope' => 'google'], ['index' => 'kb'], true],
]);

it('returns nothing, and raises no allowance, while the external scope is off', function (): void {
    // Bind a recording allowance and leave the scope off
    tagOnSite('health');
    $hook = new RecordingAllowance;
    app()->instance(ResponseAllowance::class, $hook);

    // Expect an empty answer and no call
    expect(aetherTag()->external())->toBe([])
        ->and($hook->calls)->toBe([]);
});

it('returns the provider view and settings, and raises the allowance once, while the scope is on', function (): void {
    // Bind a recording allowance and turn the scope on
    tagOnSite('health');
    externalScopeOn();
    $hook = new RecordingAllowance;
    app()->instance(ResponseAllowance::class, $hook);

    // Expect the Google view with the provider's settings, and one call naming the provider and what it needs
    expect(aetherTag()->external())->toBe(['view' => 'aether-search::external/google', 'settings' => ['engine_id' => 'abc:123']])
        ->and($hook->calls)->toHaveCount(1)
        ->and($hook->calls[0]['provider'])->toBe('google')
        ->and($hook->calls[0]['needs'])->toHaveKey('script-src');
});

it('names the handle of the current site', function (string $site): void {
    // Stand on the site
    tagOnSite($site);

    // Expect its handle
    expect(aetherTag()->site())->toBe($site);
})->with(['default', 'health']);

it('reads the query escaped, and empty when it is absent or an array', function (string $uri, string $expected): void {
    // Request the page
    tagOnSite('default', $uri);

    // Expect the escaped string, or empty
    expect(aetherTag()->query())->toBe($expected);
})->with([
    'a plain query' => ['/search?q=cardiology', 'cardiology'],
    'an absent query' => ['/search', ''],
    'an array query' => ['/search?q[]=x', ''],
    'a query with markup' => ['/search?q='.'%3Cb%3E', '&lt;b&gt;'],
]);

it('builds the search path from the current site url', function (string $site, string $expected): void {
    // Stand on the site
    tagOnSite($site);

    // Expect the site's own path, without a doubled slash
    expect(aetherTag()->path())->toBe($expected);
})->with([
    'the root site' => ['default', '/search'],
    'a sub-site' => ['health', '/health/search'],
]);

it('names the root site, and nothing when its name is not a string', function (): void {
    // The root site is named
    tagOnSite('health');
    expect(aetherTag()->rootName())->toBe('Main');

    // Give the root site a name that is not a string
    Site::setSites(['default' => ['name' => 123, 'url' => '/', 'locale' => 'en_US'], 'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US']]);
    expect(aetherTag()->rootName())->toBe('');
});

it('marks the scope the reader is on, and builds each link', function (): void {
    // Stand on the sub-site with the "This site" scope active, and hide a tab from that scope
    tagOnSite('health');
    config(['aether-search.tabs' => ['news' => 'News'], 'aether-search.tab_collections' => ['news' => ['nowhere']]]);

    // Expect both choices, the active one marked, and each link landing on a tab its scope shows
    expect(aetherTag(['active' => 'site', 'index' => 'news'])->options())->toBe([
        ['value' => 'site', 'label' => 'This site', 'param' => '', 'target_index' => 'default', 'is_active' => true],
        ['value' => 'all', 'label' => 'All of Main', 'param' => 'all', 'target_index' => 'news', 'is_active' => false],
    ]);
});

it('offers the root site one scope, and the external scope after the others when it is on', function (): void {
    // The root site offers one choice
    tagOnSite('default');
    expect(aetherTag(['active' => 'all', 'index' => 'default'])->options())->toBe([
        ['value' => 'all', 'label' => 'This site', 'param' => '', 'target_index' => 'default', 'is_active' => true],
    ]);

    // A sub-site with the external scope on offers it last, and marks it when active
    tagOnSite('health');
    externalScopeOn();
    expect(aetherTag(['active' => 'google', 'index' => 'default'])->options())->toBe([
        ['value' => 'site', 'label' => 'This site', 'param' => '', 'target_index' => 'default', 'is_active' => false],
        ['value' => 'all', 'label' => 'All of Main', 'param' => 'all', 'target_index' => 'default', 'is_active' => false],
        ['value' => 'google', 'label' => 'External results', 'param' => 'google', 'target_index' => 'default', 'is_active' => true],
    ]);
});

it('builds the scope link param from the scope and the site', function (bool $external, string $site, mixed $active, string $expected): void {
    // Stand on the site
    tagOnSite($site);
    if ($external) {
        externalScopeOn();
    }

    // Expect the param the link carries
    expect(aetherTag(['active' => $active])->param())->toBe($expected);
})->with([
    'the external scope' => [true, 'health', 'google', 'google'],
    'the external value while it is off' => [false, 'health', 'google', ''],
    'all on a sub-site' => [false, 'health', 'all', 'all'],
    'all on the root site' => [false, 'default', 'all', ''],
    'site on a sub-site' => [false, 'health', 'site', ''],
    'nothing' => [false, 'health', null, ''],
]);

it('lands on the index a request names when the page shows it, else on default', function (string $uri, array $scoped, string $expected): void {
    // Configure a tab, and optionally the scoped mode
    tagOnSite('health', $uri);
    config(['aether-search.tabs' => ['news' => 'News'], 'aether-search.scoped' => $scoped]);

    // Expect the index the page searches
    expect(aetherTag()->activeIndex())->toBe($expected);
})->with([
    'no index' => ['/search', [], 'default'],
    'an array index' => ['/search?index[]=news', [], 'default'],
    'a configured tab' => ['/search?index=news', [], 'news'],
    'an unknown index' => ['/search?index=secret', [], 'default'],
    'the scoped index with a scope root' => ['/search?index=kb&section=abc', ['index' => 'kb', 'parameter' => 'section'], 'kb'],
    'the scoped index with no scope root' => ['/search?index=kb', ['index' => 'kb', 'parameter' => 'section'], 'default'],
    'a tab while the scoped mode is on' => ['/search?index=news&section=abc', ['index' => 'kb', 'parameter' => 'section'], 'news'],
    'a blank index with a scope root but no scoped index' => ['/search?index=&section=abc', ['parameter' => 'section'], 'default'],
]);

it('reads the scope root from the configured parameter, escaped, and empty otherwise', function (array $scoped, ?array $query, string $expected): void {
    // Configure the scoped mode and request with the query
    tagOnSite('default', '/search', $query ?? []);
    config(['aether-search.scoped' => $scoped]);

    // Expect the escaped value, or empty
    expect(aetherTag()->scopeRoot())->toBe($expected);
})->with([
    'a value' => [['parameter' => 'section'], ['section' => 'abc'], 'abc'],
    'a value with markup' => [['parameter' => 'section'], ['section' => '<b>'], '&lt;b&gt;'],
    'an array value' => [['parameter' => 'section'], ['section' => ['a']], ''],
    'an absent value' => [['parameter' => 'section'], [], ''],
    'no parameter configured' => [[], ['section' => 'abc'], ''],
    'no parameter configured, with an empty-named query key' => [[], ['' => 'x'], ''],
]);

it('knows when the index is the scoped one', function (array $scoped, array $params, bool $expected): void {
    // Configure the scoped mode
    tagOnSite('default');
    config(['aether-search.scoped' => $scoped]);

    // Ask whether the index is the scoped one
    expect(aetherTag($params)->isScoped())->toBe($expected);
})->with([
    'the scoped index' => [['index' => 'kb'], ['index' => 'kb'], true],
    'another index' => [['index' => 'kb'], ['index' => 'news'], false],
    'no index' => [['index' => 'kb'], [], false],
    'no scoped mode, no index' => [[], [], false],
    'no scoped mode, a blank index' => [[], ['index' => ''], false],
]);

it('lists the tabs the scope shows and marks the active one', function (): void {
    // Two tabs, one of them hidden from "This site"
    tagOnSite('health');
    config(['aether-search.tabs' => ['news' => 'News', 'events' => 'Events'], 'aether-search.tab_collections' => ['news' => ['nowhere']]]);

    // Expect the tabs the sub-site's default scope shows, the active one marked
    expect(aetherTag(['active' => 'events'])->tabs())->toBe([
        ['handle' => 'default', 'label' => 'All results', 'is_active' => false],
        ['handle' => 'events', 'label' => 'Events', 'is_active' => true],
    ]);
});

it('resolves every partial to a view and reads the text and scoped settings, defaulting what is unset', function (): void {
    // Configure nothing
    tagOnSite('default');
    config(['aether-search.partials' => [], 'aether-search.text' => [], 'aether-search.scoped' => [], 'aether-search.always_scope' => null]);

    // Expect the package's own partials and the default text
    expect(aetherTag()->settings())->toBe([
        'partial_article' => 'aether-search::frames/passthrough',
        'partial_entry_content' => 'aether-search::frames/passthrough',
        'partial_results_block' => 'aether-search::frames/passthrough',
        'partial_results_row' => 'aether-search::frames/passthrough',
        'partial_results_header' => 'aether-search::frames/header',
        'partial_pagination' => 'aether-search::frames/pagination',
        'always_scope' => '',
        'form_label' => 'Search',
        'placeholder' => 'Type to search…',
        'prompt_hint' => '',
        'scoped_index' => '',
        'scoped_parameter' => '',
        'scoped_query_scope' => '',
        'scoped_form_label' => '',
        'scoped_note' => '',
        'scoped_broaden_label' => '',
        'scoped_broaden_url' => '',
    ]);
});

it('reads every configured partial, text and scoped setting as written', function (): void {
    // Configure every setting
    tagOnSite('default');
    config([
        'aether-search.partials' => ['article' => 'p.article', 'entry_content' => 'p.entry', 'results_block' => 'p.block', 'results_row' => 'p.row', 'results_header' => 'p.header', 'pagination' => 'p.pagination'],
        'aether-search.text' => ['form_label' => 'Find', 'placeholder' => 'Look', 'prompt_hint' => 'Hint'],
        'aether-search.always_scope' => 'site',
        'aether-search.scoped' => ['index' => 'kb', 'parameter' => 'section', 'query_scope' => 'q', 'form_label' => 'F', 'note' => 'N', 'broaden_label' => 'B', 'broaden_url' => '/u'],
    ]);

    // Expect each as written
    expect(aetherTag()->settings())->toBe([
        'partial_article' => 'p.article',
        'partial_entry_content' => 'p.entry',
        'partial_results_block' => 'p.block',
        'partial_results_row' => 'p.row',
        'partial_results_header' => 'p.header',
        'partial_pagination' => 'p.pagination',
        'always_scope' => 'site',
        'form_label' => 'Find',
        'placeholder' => 'Look',
        'prompt_hint' => 'Hint',
        'scoped_index' => 'kb',
        'scoped_parameter' => 'section',
        'scoped_query_scope' => 'q',
        'scoped_form_label' => 'F',
        'scoped_note' => 'N',
        'scoped_broaden_label' => 'B',
        'scoped_broaden_url' => '/u',
    ]);
});

it('falls back to the default for a partial or text that is empty, not a string, or the whole setting not an array', function (): void {
    // An empty partial name, a non-string one, and a non-string text
    tagOnSite('default');
    config(['aether-search.partials' => ['article' => '', 'entry_content' => 42], 'aether-search.text' => ['form_label' => 7], 'aether-search.always_scope' => 5]);
    $settings = aetherTag()->settings();
    expect($settings['partial_article'])->toBe('aether-search::frames/passthrough')
        ->and($settings['partial_entry_content'])->toBe('aether-search::frames/passthrough')
        ->and($settings['form_label'])->toBe('Search')
        ->and($settings['always_scope'])->toBe('');

    // Settings that are not arrays at all
    config(['aether-search.partials' => 'x', 'aether-search.text' => 'x', 'aether-search.scoped' => 'x']);
    $settings = aetherTag()->settings();
    expect($settings['partial_results_header'])->toBe('aether-search::frames/header')
        ->and($settings['placeholder'])->toBe('Type to search…')
        ->and($settings['scoped_index'])->toBe('');
});
