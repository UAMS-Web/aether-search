<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Tests\Support\SearchFixtures;

/**
 * The type-ahead suggestions route behaves here as it does in the application today.
 *
 * @command  vendor/bin/pest tests/Integration/SuggestionsTest.php
 */
it('answers a query with the matching title, url and configured type label', function (): void {
    // Build the index and label the collection
    $collection = SearchFixtures::indexOnePage([], 'Cardiology clinic', 'cardiology');
    config(['aether-search.labels' => [$collection => 'Page']]);

    // Ask for suggestions
    $response = $this->getJson('/!/search/suggestions?q=cardio');

    // Expect the one hit, shaped for the flyout
    $response->assertOk()->assertExactJson([
        'query' => 'cardio',
        'index' => 'default',
        'results' => [['title' => 'Cardiology clinic', 'url' => '/cardiology', 'type' => 'Page']],
    ]);
});

it('returns nothing for a query that is too short or too long, without error', function (): void {
    // Build the index
    SearchFixtures::indexOnePage([], 'Cardiology clinic', 'cardiology');

    // Ask with one character, then with a query past the limit
    $short = $this->getJson('/!/search/suggestions?q=c');
    $long = $this->getJson('/!/search/suggestions?q='.str_repeat('c', 101));

    // Expect an empty list for both
    expect($short->assertOk()->json('results'))->toBe([])
        ->and($long->assertOk()->json('results'))->toBe([]);
});

it('falls back to the default index for one that is not a configured tab', function (): void {
    // Build the index
    SearchFixtures::indexOnePage([], 'Cardiology clinic', 'cardiology');

    // Ask for an index nobody configured, and for an array-valued query
    $unknown = $this->getJson('/!/search/suggestions?q=cardio&index=secret');
    $array = $this->getJson('/!/search/suggestions?q[]=cardio');

    // Expect the default index, and an empty query for the array
    expect($unknown->assertOk()->json('index'))->toBe('default')
        ->and($array->assertOk()->json('query'))->toBe('');
});

it('drops a hit the bound visibility rule refuses', function (): void {
    // Build the index, and bind a rule that refuses every entry
    SearchFixtures::indexOnePage([], 'Cardiology clinic', 'cardiology');
    app()->bind(EntryVisibility::class, fn (): EntryVisibility => new class implements EntryVisibility
    {
        public function canView(EntryInstance $entry): bool
        {
            return false;
        }
    });

    // Ask for suggestions
    $response = $this->getJson('/!/search/suggestions?q=cardio');

    // Expect no suggestion
    expect($response->assertOk()->json('results'))->toBe([]);
});

it('is throttled by the limiter the add-on defines', function (): void {
    // Allow two requests a minute
    config(['aether-search.suggestions.per_minute' => 2]);

    // Ask three times
    $statuses = [];
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $statuses[] = $this->getJson('/!/search/suggestions?q=c')->getStatusCode();
    }

    // Expect the third to be refused
    expect($statuses)->toBe([200, 200, 429]);
});
