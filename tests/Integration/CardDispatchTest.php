<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;

/**
 * Which card a search hit gets, and which label, with no application override in the host.
 *
 * @command  vendor/bin/pest tests/Integration/CardDispatchTest.php
 */

/**
 * Render each hit through the result row, as the listing does.
 *
 * @param  list<array<string, string>>  $hits  The hits: `title`, `url`, and a `collection` or `result_type`.
 * @return string The rendered rows.
 */
function renderResultRows(array $hits): string
{
    return (string) Antlers::parse('{{ hits }}{{ partial:aether-search::listing/result }}{{ /hits }}', ['hits' => $hits], true);
}

it('gives every type the generic card when the host overrides none', function (): void {
    // Render a person, a page and a type nobody has heard of
    $html = renderResultRows([
        ['title' => 'Ada', 'url' => '/ada', 'collection' => 'people'],
        ['title' => 'About', 'url' => '/about', 'collection' => 'pages'],
        ['title' => 'Odd', 'url' => '/odd', 'collection' => 'mystery'],
    ]);

    // Expect the generic compact card for each, and no host marker
    expect(substr_count($html, 'class="card search-card h-100"'))->toBe(3)
        ->and($html)->not->toContain('host-card-people');
});

it('labels a hit from its result type first, then its collection, and leaves an unlabelled type bare', function (): void {
    // Configure two labels
    config(['aether-search.labels' => ['people' => 'Person', 'section' => 'Section']]);

    // Render a hit whose result type and collection both have labels, one with a collection label, and one with none
    $html = renderResultRows([
        ['title' => 'A', 'url' => '/a', 'result_type' => 'section', 'collection' => 'people'],
        ['title' => 'B', 'url' => '/b', 'collection' => 'people'],
        ['title' => 'C', 'url' => '/c', 'collection' => 'pages'],
    ]);

    // Expect the result type to win, the collection to label the second, and the third to carry no label
    expect($html)->toContain('>Section</p>')
        ->and(substr_count($html, '>Person</p>'))->toBe(1)
        ->and(substr_count($html, 'search-result-type'))->toBe(2);
});

it('never builds a card name from a hostile type', function (): void {
    // Render a hit whose collection tries to climb out of the cards directory
    $html = renderResultRows([['title' => 'X', 'url' => '/x', 'collection' => '../frames/passthrough']]);

    // Expect the generic card
    expect($html)->toContain('class="card search-card h-100"');
});

it('labels and cards a hit whose collection is a Collection object, as a search hit supplies it', function (): void {
    // Name a collection whose handle is also a package card, and label it
    $collection = new Collection;
    $collection->handle('compact');
    config(['aether-search.labels' => ['compact' => 'Compact page']]);

    // Render a hit carrying the Collection object, not its handle
    $html = (string) Antlers::parse('{{ partial:aether-search::listing/result }}', ['title' => 'A', 'url' => '/a', 'collection' => $collection], true);

    // Expect the label from the handle
    expect($html)->toContain('Compact page');

    // Resolve the card the same way
    $card = (string) Antlers::parse('{{ aether_search:card :collection="collection" }}', ['collection' => $collection], true);

    // Expect the card named for the handle, not the generic fallback
    expect(trim($card))->toBe('aether-search::cards/compact');
});
