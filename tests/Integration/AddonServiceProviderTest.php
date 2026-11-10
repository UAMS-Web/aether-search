<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Statamic\Contracts\Search\Result;
use Statamic\Entries\Entry;
use Statamic\Facades\Addon;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;
use UAMSWeb\Aether\Search\External\ExternalResultsProviders;
use UAMSWeb\Aether\Search\External\NoAllowance;
use UAMSWeb\Aether\Search\ServiceProvider;
use UAMSWeb\Aether\Search\Support\EntrySuggestionPresenter;
use UAMSWeb\Aether\Search\Support\ShowsEveryEntry;

/**
 * The add-on boots inside a minimal Statamic.
 *
 * @command  vendor/bin/pest tests/Integration/AddonServiceProviderTest.php
 */
it('is discovered by Statamic as an add-on', function (): void {
    // Expect the add-on among those Statamic booted
    expect(Addon::all()->map->package()->all())->toContain('uams-web/aether-search');
});

it('publishes its config file under the tag the README names', function (): void {
    // Read the paths published under the config tag
    $paths = ServiceProvider::pathsToPublish(null, 'aether-search-config');

    // Expect the package config to publish to the app's config directory
    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(__DIR__.'/../../config/aether-search.php')])
        ->and(array_values($paths))->toBe([config_path('aether-search.php')]);
});

it('binds the default visibility rule, presenter and response allowance', function (): void {
    // Expect each seam to resolve to the class the add-on ships
    expect(app(EntryVisibility::class))->toBeInstanceOf(ShowsEveryEntry::class)
        ->and(app(SuggestionPresenter::class))->toBeInstanceOf(EntrySuggestionPresenter::class)
        ->and(app(ResponseAllowance::class))->toBeInstanceOf(NoAllowance::class);
});

it('keeps a seam the application bound before the add-on booted', function (): void {
    // Bind the application's own rule, presenter and allowance, then boot the add-on again
    $visibility = new class implements EntryVisibility
    {
        public function canView(Entry $entry): bool
        {
            return false;
        }
    };
    $presenter = new class implements SuggestionPresenter
    {
        public function present(Result $result): ?array
        {
            return null;
        }
    };
    $allowance = new class implements ResponseAllowance
    {
        public function raise(string $provider, array $needs): void {}
    };
    app()->instance(EntryVisibility::class, $visibility);
    app()->instance(SuggestionPresenter::class, $presenter);
    app()->instance(ResponseAllowance::class, $allowance);
    new ServiceProvider(app())->bootAddon();

    // Expect the application's own, not the defaults
    expect(app(EntryVisibility::class))->toBe($visibility)
        ->and(app(SuggestionPresenter::class))->toBe($presenter)
        ->and(app(ResponseAllowance::class))->toBe($allowance);
});

it('fails the boot naming the valid providers when the configured one is unknown', function (): void {
    // Configure a provider nobody registered
    config(['aether-search.external.provider' => 'nope']);

    // Expect booting to refuse it
    expect(fn () => new ServiceProvider(app())->bootAddon())
        ->toThrow(InvalidArgumentException::class, 'Unknown aether-search external results provider "nope". Valid providers: google.');
});

it('boots cleanly with the shipped provider configured', function (): void {
    // Configure the shipped provider and boot again
    config(['aether-search.external.provider' => 'google']);
    new ServiceProvider(app())->bootAddon();

    // Expect the registry to still name it
    expect(app(ExternalResultsProviders::class)->has('google'))->toBeTrue();
});

it('shares one provider registry across the application', function (): void {
    // Expect the same instance on every resolution, so a host's boot() registers into the one the scope reads
    expect(app(ExternalResultsProviders::class))->toBe(app(ExternalResultsProviders::class));
});

/**
 * Resolve the suggestions limit for a request.
 *
 * @param  string|null  $ip  The client address, or null for a request that carries none.
 */
function suggestionsLimit(?string $ip): Limit
{
    $request = Request::create('/!/search/suggestions');
    if ($ip === null) {
        $request->server->remove('REMOTE_ADDR');
    } else {
        $request->server->set('REMOTE_ADDR', $ip);
    }

    $limiter = RateLimiter::limiter(ServiceProvider::SUGGESTIONS_LIMITER);
    if (! $limiter instanceof Closure) {
        throw new RuntimeException('The suggestions limiter is not registered.');
    }

    $limit = $limiter($request);
    if (! $limit instanceof Limit) {
        throw new RuntimeException('The suggestions limiter did not return a limit.');
    }

    return $limit;
}

it('limits suggestions per minute by the configured count, keyed by the client address', function (): void {
    // Configure five a minute
    config(['aether-search.suggestions.per_minute' => 5]);

    // Resolve the limit for a client
    $limit = suggestionsLimit('10.0.0.7');

    // Expect five attempts over one minute, keyed by the address
    expect($limit->maxAttempts)->toBe(5)
        ->and($limit->decaySeconds)->toBe(60)
        ->and($limit->key)->toBe('10.0.0.7');
});

it('falls back to 120 a minute when the configured count is not an integer', function (mixed $configured): void {
    // Configure a count that is not an integer
    config(['aether-search.suggestions.per_minute' => $configured]);

    // Expect the default count
    expect(suggestionsLimit('10.0.0.7')->maxAttempts)->toBe(120);
})->with([
    'a numeric string' => ['5'],
    'null' => [null],
    'an array' => [[5]],
]);

it('shares one bucket among clients with no address', function (): void {
    // Expect the key `unknown` for a request that carries no address
    expect(suggestionsLimit(null)->key)->toBe('unknown');
});
