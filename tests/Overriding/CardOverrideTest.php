<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;

/**
 * A host overrides one card at `resources/views/vendor/aether-search/cards/_people`. It wins for its
 * type, the package serves every other, and a type with no card gets the generic one in the same
 * render. `CardDispatchTest` renders the same hits with no override and sees none of the host's markup,
 * so removing the override turns this file red.
 *
 * @command  vendor/bin/pest tests/Integration/CardOverrideTest.php
 */
it('lets a host card win for its type while the package serves the rest', function (): void {
    // Render a person, a page and an unmapped type in one render
    $html = (string) Antlers::parse('{{ hits }}{{ partial:aether-search::listing/result }}{{ /hits }}', ['hits' => [
        ['title' => 'Ada', 'url' => '/ada', 'collection' => 'people'],
        ['title' => 'About', 'url' => '/about', 'collection' => 'pages'],
        ['title' => 'Odd', 'url' => '/odd', 'collection' => 'mystery'],
    ]], true);

    // Expect the host's card for the person, and the generic card for the other two
    expect($html)->toContain('<div class="host-card-people" data-title="Ada"></div>')
        ->and(substr_count($html, 'class="card search-card h-100"'))->toBe(2);
});
