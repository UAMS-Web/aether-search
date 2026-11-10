<?php

declare(strict_types=1);

use Statamic\Contracts\Search\Searchable;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\User;
use Statamic\Search\Result;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Support\EntrySuggestionPresenter;

/**
 * The default suggestion presenter: which hits become a suggestion, and what its three fields hold.
 *
 * @command  vendor/bin/pest tests/Integration/EntrySuggestionPresenterTest.php
 */

/**
 * Save an entry in a collection of its own and wrap it as a search hit.
 *
 * @param  array<mixed, mixed>  $data  The entry's data.
 * @param  string|null  $routes  The collection's route, or null for a collection with no public URLs.
 * @return array{0: Result, 1: string} The hit, and the collection's handle.
 */
function hitOnEntry(array $data, ?string $routes = '{slug}'): array
{
    // Name a collection for this call alone
    $handle = 'presented_'.str_replace('.', '', uniqid('', true));

    $collection = new Collection;
    $collection->handle($handle);
    if ($routes !== null) {
        $collection->routes($routes);
    }

    $collection->save();

    // Save the entry in it
    $entry = new Entry;
    $entry->collection($handle);
    $entry->slug('a-page');
    $entry->data($data);
    $entry->save();

    return [new Result($entry, 'entry'), $handle];
}

/**
 * A visibility rule with a fixed answer.
 *
 * @param  bool  $answer  What the rule answers for every entry.
 */
function visibility(bool $answer): EntryVisibility
{
    return new readonly class($answer) implements EntryVisibility
    {
        public function __construct(private bool $answer) {}

        public function canView(Entry $entry): bool
        {
            return $this->answer;
        }
    };
}

it('shapes a visible entry with its title, url and collection label', function (): void {
    // Save an entry and label its collection
    [$hit, $handle] = hitOnEntry(['title' => 'Cardiology clinic']);
    config(['aether-search.labels' => [$handle => 'Page']]);

    // Present it
    $suggestion = new EntrySuggestionPresenter(visibility(true))->present($hit);

    // Expect all three fields
    expect($suggestion)->toBe(['title' => 'Cardiology clinic', 'url' => '/a-page', 'type' => 'Page']);
});

it('drops an entry the visibility rule refuses', function (): void {
    // Save an entry that would otherwise be presented
    [$hit] = hitOnEntry(['title' => 'Cardiology clinic']);

    // Present it under a rule that refuses everything
    expect(new EntrySuggestionPresenter(visibility(false))->present($hit))->toBeNull();
});

it('drops a hit that is not an entry without asking the visibility rule', function (): void {
    // Wrap a user as a hit, and a rule that fails the test if it is asked
    /** @var Searchable $user */
    $user = User::make()->email('a@example.test');
    $hit = new Result($user, 'user');
    $rule = new class implements EntryVisibility
    {
        public function canView(Entry $entry): bool
        {
            throw new LogicException('The rule must only be asked about entries.');
        }
    };

    // Expect the hit dropped
    expect(new EntrySuggestionPresenter($rule)->present($hit))->toBeNull();
});

it('leaves the type empty when the collection has no usable label', function (string $case): void {
    // Save an entry and configure labels that do not give its collection a string
    [$hit, $handle] = hitOnEntry(['title' => 'Cardiology clinic']);
    config(['aether-search.labels' => match ($case) {
        'not an array' => 'not labels',
        'other collections only' => ['other' => 'Other'],
        default => [$handle => 42],
    }]);

    // Present it
    $suggestion = new EntrySuggestionPresenter(visibility(true))->present($hit);

    // Expect the suggestion with an empty type
    expect($suggestion)->toBe(['title' => 'Cardiology clinic', 'url' => '/a-page', 'type' => '']);
})->with([
    'labels that are not an array' => ['not an array'],
    'labels for other collections only' => ['other collections only'],
    'a label that is not a string' => ['not a string'],
]);

it('drops an entry with no title or no public url', function (array $data, ?string $routes): void {
    // Save an entry missing one of the two
    [$hit] = hitOnEntry($data, $routes);

    // Expect it dropped
    expect(new EntrySuggestionPresenter(visibility(true))->present($hit))->toBeNull();
})->with([
    'no title' => [[], '{slug}'],
    'a title that is not a string' => [['title' => ['x']], '{slug}'],
    'a collection with no route' => [['title' => 'Cardiology clinic'], null],
]);

it('builds a suggestion only when both the title and the url have content', function (string $title, string $url, ?array $expected): void {
    // Build one
    $suggestion = EntrySuggestionPresenter::suggestion($title, $url, 'Page');

    // Expect the array, or null when either is empty
    expect($suggestion)->toBe($expected);
})->with([
    'both' => ['T', '/u', ['title' => 'T', 'url' => '/u', 'type' => 'Page']],
    'an empty title' => ['', '/u', null],
    'an empty url' => ['T', '', null],
    'both empty' => ['', '', null],
]);
