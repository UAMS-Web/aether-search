<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Search\Result;
use UAMSWeb\Aether\Search\Tests\Support\FakeSearchIndex;

/**
 * Which sites the type-ahead suggestions search, per `?site=` and `?scope=`.
 *
 * @command  vendor/bin/pest tests/Integration/SuggestionScopeTest.php
 */

/**
 * Define a root site and a `/health` sub-site.
 */
function defineTwoSites(): void
{
    Site::setSites([
        'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
    ]);
}

/**
 * Define the two sites, and answer the suggestions route's index with a fake.
 *
 * @param  list<object>  $hits  What the fake index answers with.
 * @return FakeSearchIndex The fake, to inspect after the request.
 */
function fakeIndexOnTwoSites(array $hits = []): FakeSearchIndex
{
    // Define the two sites
    defineTwoSites();

    // Hand the route the fake
    $index = new FakeSearchIndex($hits);
    Search::shouldReceive('index')->andReturn($index);

    return $index;
}

it('searches the sites the request asks for', function (string $query, ?array $expected): void {
    // Ask for suggestions with the site and scope under test
    $index = fakeIndexOnTwoSites();
    $this->getJson('/!/search/suggestions?q=cardio'.$query)->assertOk();

    // Expect the one site named, or no site constraint when every site is searched
    expect($index->searched)->toBeTrue()
        ->and($index->sites)->toBe($expected);
})->with([
    // This site: a sub-site that asks for nothing wider
    'sub-site, no scope' => ['&site=health', ['health']],
    'sub-site, scope site' => ['&site=health&scope=site', ['health']],
    'sub-site, unknown scope' => ['&site=health&scope=bogus', ['health']],
    'sub-site, array scope' => ['&site=health&scope[]=all', ['health']],
    // Every site
    'sub-site, wide scope' => ['&site=health&scope=all', null],
    'root site, wide scope' => ['&site=default&scope=all', null],
    'root site, no scope' => ['&site=default', null],
    'root site, scope site' => ['&site=default&scope=site', null],
    'no site' => ['', null],
    'nonexistent site' => ['&site=nope', null],
    'site star' => ['&site=*', null],
    'array of sites' => ['&site[]=health', null],
]);

it('searches every site for the external scope only when it is offered', function (?string $engine, ?array $expected): void {
    // Turn the external scope on, with or without an engine
    config(['aether-search.external.enabled' => true, 'aether-search.external.providers.google.engine_id' => $engine]);

    // Ask from the sub-site for the external scope
    $index = fakeIndexOnTwoSites();
    $this->getJson('/!/search/suggestions?q=cardio&site=health&scope=google')->assertOk();

    // Expect every site with an engine, and this site without one
    expect($index->sites)->toBe($expected);
})->with([
    'engine configured' => ['abc123', null],
    'no engine' => [null, ['health']],
]);

it('treats a query that is not valid UTF-8 as empty', function (): void {
    // Ask with a byte that cannot start a UTF-8 sequence
    $index = fakeIndexOnTwoSites();
    $response = $this->getJson('/!/search/suggestions?q=%FF');

    // Expect the empty response, and no search
    $response->assertOk()->assertExactJson(['query' => '', 'index' => 'default', 'results' => []]);
    expect($index->searched)->toBeFalse();
});

it('links a hit from another site to its own site', function (): void {
    // Answer with an entry of the sub-site, whose URL carries that site's prefix
    $entry = new class extends Entry
    {
        public function url(): string
        {
            return '/health/cardiology';
        }
    };
    $collection = new Collection;
    $collection->handle('health_pages_'.str_replace('.', '', uniqid('', true)));
    $collection->save();
    $entry->collection($collection);
    $entry->data(['title' => 'Cardiology']);

    fakeIndexOnTwoSites([new Result($entry, 'entry')]);

    // Ask from the root site for every site
    $response = $this->getJson('/!/search/suggestions?q=cardio&site=default&scope=all');

    // Expect the suggestion to link where the entry lives, not under the site asked from
    expect($response->assertOk()->json('results.0.url'))->toBe('/health/cardiology');
});
