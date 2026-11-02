<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Contracts\Search\Result;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Search\Index;
use Statamic\Search\QueryBuilder;
use Statamic\Sites\Site as SiteInstance;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;
use UAMSWeb\Aether\Search\Support\SearchScopes;
use UAMSWeb\Aether\Search\Tags\AetherSearch;

/**
 * Type-ahead suggestions for the site-search input.
 *
 * Answers the question the search page's `{{ search:results }}` tag answers for the same index
 * (the page's active type tab, or the unified `default` index), the current site and published
 * items, but returns a short JSON list the input's flyout renders as the reader types. It does
 * not replace the page: selecting "See all results", or submitting the form, still lands on it.
 *
 * The index, site and status constraints mirror `Statamic\Search\Tags::results()`. What a
 * suggestion looks like, and which hits are dropped, is the bound {@see SuggestionPresenter}'s
 * decision, so an application can hide the entries a reader may not open.
 */
final class SuggestionsController
{
    /**
     * The fewest characters worth querying, so a single keystroke does not scan the whole index.
     */
    public const int MIN_QUERY_LENGTH = 2;

    /**
     * The longest query accepted. A longer one returns no suggestions rather than an error,
     * because the flyout has nothing useful to show for it anyway.
     */
    public const int MAX_QUERY_LENGTH = 100;

    /**
     * How many suggestions the flyout shows.
     */
    public const int LIMIT = 8;

    /**
     * @param  SuggestionPresenter  $presenter  Shapes a hit as a suggestion, or drops it
     */
    public function __construct(private readonly SuggestionPresenter $presenter) {}

    /**
     * Return up to {@see LIMIT} suggestions for `?q=`, from `?index=` when it is a configured tab.
     *
     * @param  Request  $request  The incoming request, carrying `q` and optionally `index`
     * @return JsonResponse `{ query, index, results: [{ title, url, type }] }`
     */
    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->query('q');
        // A query that is not valid UTF-8 is treated as empty, so it never reaches the index
        $query = \is_string($raw) && mb_check_encoding($raw, 'UTF-8') ? trim($raw) : '';
        $length = mb_strlen($query);

        $suggestions = [];

        // The page and its type-ahead resolve the site and scope the same way
        $site = $this->site($request->query('site'));
        $scope = AetherSearch::resolve($site, $request->query('scope'));
        $siteHandle = $site->handle();
        $index = app(SearchScopes::class)->index($request->query('index'), \is_string($siteHandle) ? $siteHandle : '', $scope);

        if ($length >= self::MIN_QUERY_LENGTH && $length <= self::MAX_QUERY_LENGTH) {
            $suggestions = $this->suggest($query, $index, $scope === AetherSearch::THIS_SITE ? $site : null);
        }

        return new JsonResponse([
            'query' => $query,
            'index' => $index,
            'results' => $suggestions,
        ]);
    }

    /**
     * The site the reader is searching from: the one `?site=` names, else the current site.
     *
     * @param  mixed  $requested  The raw `site` parameter.
     */
    private function site(mixed $requested): SiteInstance
    {
        $site = \is_string($requested) ? Site::get($requested) : null;

        if ($site instanceof SiteInstance) {
            return $site;
        }

        /** @var SiteInstance $current */
        $current = Site::current();

        return $current;
    }

    /**
     * Run the query against the index and shape each hit for the flyout.
     *
     * Asks for twice {@see LIMIT} and trims after shaping, so a dropped hit does not leave the
     * flyout short while more matches exist.
     *
     * @param  SiteInstance|null  $site  The one site to search, or null for every site.
     * @return list<array{title: string, url: string, type: string}>
     */
    private function suggest(string $query, string $handle, ?SiteInstance $site): array
    {
        /** @var Index $index */
        $index = Search::index($handle);
        $index->ensureExists();

        /** @var QueryBuilder $builder */
        $builder = $index->search($query);

        if ($site instanceof SiteInstance) {
            $builder->whereIn('site', [$site->handle()]);
        }

        $builder->where('status', 'published');
        $builder->limit(self::LIMIT * 2);

        $hits = $builder->get();

        if (! is_iterable($hits)) {
            return [];
        }

        $suggestions = [];

        foreach ($hits as $result) {
            if (! $result instanceof Result) {
                continue;
            }

            $suggestion = $this->presenter->present($result);

            if ($suggestion !== null) {
                $suggestions[] = $suggestion;
            }

            if (\count($suggestions) === self::LIMIT) {
                break;
            }
        }

        return $suggestions;
    }
}
