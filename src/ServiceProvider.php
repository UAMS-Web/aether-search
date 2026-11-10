<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Tags\Tags;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;
use UAMSWeb\Aether\Search\External\ExternalResults;
use UAMSWeb\Aether\Search\External\ExternalResultsProviders;
use UAMSWeb\Aether\Search\External\NoAllowance;
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
    protected $tags;

    /**
     * The routes the add-on registers, loaded by Statamic ahead of the application's own wildcard
     * routes and the catch-all.
     *
     * @var array<string, string>
     */
    protected $routes;

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
    protected $vite;

    /**
     * Declare the tag, the routes and the Control Panel build.
     *
     * They are set here rather than as property defaults because a default is not an executable
     * line, so a test cannot be attributed to a mutation of it. Statamic reads them at boot.
     *
     * @param  Application  $app  The application the provider belongs to.
     */
    public function __construct($app)
    {
        parent::__construct($app);

        $this->tags = [
            AetherSearch::class,
        ];

        $this->routes = [
            'web' => __DIR__.'/../routes/web.php',
        ];

        $this->vite = [
            'input' => [
                'resources/js/cp.js',
                'resources/css/cp.css',
            ],
            'publicDirectory' => 'resources/dist',
        ];
    }

    /**
     * Register the providers registry as a singleton in the register phase, so a host can add a
     * provider from its own provider's `boot()` to the same instance.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(ExternalResultsProviders::class);
    }

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
        $this->app->bindIf(ResponseAllowance::class, NoAllowance::class);

        // Check the configured external results provider once every provider has booted, so a host
        // that registers its own in a provider's boot() is counted, and fail with the valid names
        $this->app->booted(fn () => $this->app->make(ExternalResults::class)->validate());

        RateLimiter::for(
            self::SUGGESTIONS_LIMITER,
            function (Request $request): Limit {
                $perMinute = config('aether-search.suggestions.per_minute');

                return Limit::perMinute(\is_int($perMinute) ? $perMinute : 120)->by($request->ip() ?? 'unknown');
            },
        );
    }
}
