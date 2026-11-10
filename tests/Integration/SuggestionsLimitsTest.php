<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Statamic\Contracts\Search\Result as ResultContract;
use Statamic\Contracts\Search\Searchable;
use Statamic\Facades\Search;
use Statamic\Facades\User;
use Statamic\Search\Result;
use Symfony\Component\HttpFoundation\Response;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;
use UAMSWeb\Aether\Search\Http\Controllers\SuggestionsController;
use UAMSWeb\Aether\Search\Tests\Support\FakeSearchIndex;

/**
 * What the suggestions route asks the index for and how it shapes the answer: the query it trims
 * and bounds, the constraints and limit it sets, the hits it skips and the count it stops at.
 * The index is a fake that records its calls, so each assertion reads the route's own behavior.
 *
 * @command  vendor/bin/pest tests/Integration/SuggestionsLimitsTest.php
 */

/**
 * Answer the route's index with a fake, and present each hit by the email of the user it wraps.
 *
 * A hit whose email starts with `drop` is refused by the presenter, as a visibility rule would.
 *
 * @param  mixed  $hits  What the fake index answers with.
 * @return FakeSearchIndex The fake, to inspect after the request.
 */
function fakeIndexWithPresenter(mixed $hits = []): FakeSearchIndex
{
    // Hand the route the fake
    $index = new FakeSearchIndex($hits);
    Search::shouldReceive('index')->andReturn($index);

    // Present each hit by its wrapped user's email, dropping the ones named `drop`
    app()->bind(SuggestionPresenter::class, fn (): SuggestionPresenter => new class implements SuggestionPresenter
    {
        public function present(ResultContract $result): ?array
        {
            $searchable = $result->getSearchable();
            $address = $searchable instanceof Statamic\Auth\User ? $searchable->email() : '';
            $email = is_string($address) ? $address : '';

            return str_starts_with($email, 'drop') ? null : ['title' => $email, 'url' => '/'.$email, 'type' => 'T'];
        }
    });

    return $index;
}

/**
 * The titles in a suggestions response, in order.
 *
 * @param  TestResponse<Response>  $response  The route's response.
 * @return list<string> The titles.
 */
function suggestionTitles(TestResponse $response): array
{
    /** @var list<array{title: string}> $results */
    $results = $response->assertOk()->json('results');

    return array_column($results, 'title');
}

/**
 * Wrap a user with an email as a search hit.
 *
 * @param  string  $email  The email, which the presenter turns into the title.
 */
function userHit(string $email): Result
{
    /** @var Searchable $user */
    $user = User::make()->email($email);

    return new Result($user, 'user');
}

/**
 * Build hits named `<prefix>1` to `<prefix>N`.
 *
 * @param  int  $count  How many.
 * @param  string  $prefix  The email prefix.
 * @return list<Result>
 */
function userHits(int $count, string $prefix = 'hit'): array
{
    return array_map(fn (int $n): Result => userHit($prefix.$n.'@example.test'), range(1, $count));
}

it('asks the index to exist, for published items only, up to twice the limit, with the trimmed query', function (): void {
    // Ask with a query padded by whitespace
    $index = fakeIndexWithPresenter();
    $response = $this->getJson('/!/search/suggestions?q='.rawurlencode('  ab  '));

    // Expect the trimmed query echoed and searched, the index ensured once, and the published constraint and doubled limit set
    $response->assertOk();
    expect($response->json('query'))->toBe('ab')
        ->and($index->queries)->toBe(['ab'])
        ->and($index->ensured)->toBe(1)
        ->and($index->wheres)->toBe([['status', 'published']])
        ->and($index->limit)->toBe(16);
});

it('searches only queries of two to a hundred characters once trimmed', function (string $query, bool $searched): void {
    // Ask with the query under test
    $index = fakeIndexWithPresenter(userHits(1));
    $response = $this->getJson('/!/search/suggestions?q='.rawurlencode($query));

    // Expect it searched, with a suggestion, exactly when it is inside the bounds
    $response->assertOk();
    expect($index->searched)->toBe($searched)
        ->and($response->json('results'))->toHaveCount($searched ? 1 : 0);
})->with([
    'one character' => ['a', false],
    'one character padded to three' => [' a ', false],
    'two characters' => ['ab', true],
    'a hundred characters' => [str_repeat('a', 100), true],
    'a hundred and one characters' => [str_repeat('a', 101), false],
    'a hundred characters padded to a hundred and two' => [' '.str_repeat('a', 100).' ', true],
]);

it('returns no more than eight suggestions however many hits match', function (): void {
    // Answer with twelve presentable hits
    fakeIndexWithPresenter(userHits(12));

    // Ask for suggestions
    $titles = suggestionTitles($this->getJson('/!/search/suggestions?q=hit'));

    // Expect the first eight, in order
    expect($titles)->toBe(array_map(fn (int $n): string => "hit{$n}@example.test", range(1, 8)));
});

it('returns every suggestion when fewer than eight match', function (): void {
    // Answer with five presentable hits
    fakeIndexWithPresenter(userHits(5));

    // Expect all five
    expect($this->getJson('/!/search/suggestions?q=hit')->assertOk()->json('results'))->toHaveCount(5);
});

it('does not count a dropped hit toward the limit', function (): void {
    // Answer with ten hits the presenter drops, then ten it presents
    fakeIndexWithPresenter([...userHits(10, 'drop'), ...userHits(10)]);

    // Expect eight presented suggestions, none of them dropped
    $titles = suggestionTitles($this->getJson('/!/search/suggestions?q=hit'));
    expect($titles)->toHaveCount(8)
        ->and(implode(',', $titles))->not->toContain('drop');
});

it('skips a hit that is not a search result and keeps the ones after it', function (): void {
    // Answer with a stray object before, between and after two results
    fakeIndexWithPresenter([new stdClass, userHit('a@example.test'), new stdClass, userHit('b@example.test'), new stdClass]);

    // Expect both results
    $titles = suggestionTitles($this->getJson('/!/search/suggestions?q=hit'));
    expect($titles)->toBe(['a@example.test', 'b@example.test']);
});

it('answers with no suggestions when the index does not return a list', function (): void {
    // Answer with something that cannot be iterated
    fakeIndexWithPresenter('not a list');

    // Expect an empty list, not an error
    expect($this->getJson('/!/search/suggestions?q=hit')->assertOk()->json('results'))->toBe([]);
});

it('trims the query itself rather than relying on the request middleware', function (): void {
    // Call the controller directly, so no middleware has trimmed the query first
    $index = fakeIndexWithPresenter();
    $response = app(SuggestionsController::class)(Request::create('/suggestions', 'GET', ['q' => '  ab  ']));

    // Expect the trimmed query echoed and searched
    expect($response->getContent())->toContain('"query":"ab"')
        ->and($index->queries)->toBe(['ab']);
});
