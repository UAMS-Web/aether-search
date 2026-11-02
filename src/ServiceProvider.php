<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Tags\Tags;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;
use UAMSWeb\Aether\Search\Support\EntrySuggestionPresenter;
use UAMSWeb\Aether\Search\Support\ShowsEveryEntry;
use UAMSWeb\Aether\Search\Tags\AetherSearch;

/**
 * Service provider for the add-on.
 *
 * The host application discovers it through `extra.laravel.providers` in `composer.json`. It
 * registers the `aether_search` tag, the search page and the type-ahead suggestions route
 * (`routes/web.php`), the views under the `aether-search` namespace, and the defaults for the two
 * seams an application can rebind: {@see EntryVisibility} and {@see SuggestionPresenter}.
 */
class ServiceProvider extends AddonServiceProvider
{
    /**
     * The name of the type-ahead rate limiter.
     */
    public const string SUGGESTIONS_LIMITER = 'aether-search-suggestions';

    /**
     * The tags the add-on registers.
     *
     * @var list<class-string<Tags>>
     */
    protected $tags = [
        AetherSearch::class,
    ];

    /**
     * The routes the add-on registers, loaded by Statamic ahead of the application's own wildcard
     * routes and the catch-all.
     *
     * @var array<string, string>
     */
    protected $routes = [
        'web' => __DIR__.'/../routes/web.php',
    ];

    /**
     * The views' namespace: `aether-search::index` and so on. A host overrides one view by placing
     * it at `resources/views/vendor/aether-search/`.
     *
     * @var string
     */
    protected $viewNamespace = 'aether-search';

    /**
     * The Vite build of the Control Panel assets. `input` and `publicDirectory`
     * must match `vite.config.js`; the built files are committed under
     * `resources/dist`.
     *
     * @var array{input: list<string>, publicDirectory: string}
     */
    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    /**
     * Boot the add-on once Statamic has booted.
     *
     * The seams are bound only when the application has not bound its own, which it does while
     * registering, before any provider boots.
     */
    public function bootAddon(): void
    {
        $this->app->bindIf(EntryVisibility::class, ShowsEveryEntry::class);
        $this->app->bindIf(SuggestionPresenter::class, EntrySuggestionPresenter::class);

        RateLimiter::for(
            self::SUGGESTIONS_LIMITER,
            function (Request $request): Limit {
                $perMinute = config('aether-search.suggestions.per_minute');

                return Limit::perMinute(\is_int($perMinute) ? $perMinute : 120)->by($request->ip() ?? 'unknown');
            },
        );
    }
}
