<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Support;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Entries\Collection as EntriesCollection;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use UAMSWeb\Aether\Search\Contracts\SiteTree;

/**
 * The search scopes, defined once: the type tabs, their index handles, the collections each one
 * covers, and the labels a result carries.
 *
 * The search page, the `?index=` allow-list, the type-ahead suggestions and the result labels all
 * read from here, so adding or renaming a scope is one edit in `config/aether-search.php`:
 *
 * - `tabs` is the registry: index handle => label. `default` ("All results") is always one.
 * - `tab_collections` names the collections a tab covers, by index handle. A tab with no entry
 *   there covers everything and always shows.
 * - `labels` is the result-type label, by collection handle.
 *
 * Which sites a scope covers comes from the bound {@see SiteTree}: "This site" covers the sites it
 * names for the current site, and "All of <tier>" the sites it names for the tier. Under either, a
 * page shows only the tabs those sites can fill: "All results", and each tab with a collection one
 * of them carries. Under every site, every tab shows.
 */
final class SearchScopes
{
    /**
     * The scope that searches the sites the current site covers, as the tag names it.
     */
    private const string THIS_SITE = 'site';

    /**
     * The scope that searches the sites the current site's tier covers, as the tag names it.
     */
    private const string TIER = 'tier';

    /**
     * Every tab, in order, as index handle => label. `default` comes first.
     *
     * @return array<string, string>
     */
    public function tabs(): array
    {
        $tabs = config('aether-search.tabs');
        $labels = [];

        foreach (is_array($tabs) ? $tabs : [] as $handle => $label) {
            if (is_string($label)) {
                // PHP stores a numeric-string key as an integer either way, so the cast changes no key.
                // @pest-mutate-ignore: RemoveStringCast
                $labels[(string) $handle] = $label;
            }
        }

        return ['default' => $labels['default'] ?? 'All results'] + $labels;
    }

    /**
     * The tabs a page shows for a site under a scope.
     *
     * "This site" and a tier narrow them: a tab is dropped when it names collections and no site
     * the scope covers carries one of them. Every site is too wide to narrow, so every tab shows.
     *
     * @param  string  $siteHandle  The handle of the site the reader is on.
     * @param  string  $scope  The resolved search scope.
     * @return array<string, string> Index handle => label.
     */
    public function visibleTabs(string $siteHandle, string $scope): array
    {
        $tabs = $this->tabs();
        $sites = $this->sites($siteHandle, $scope);

        if ($sites === null) {
            return $tabs;
        }

        $carried = $this->carriedByAny($sites);

        return array_filter(
            $tabs,
            fn (string $label, string $handle): bool => $this->fillable($handle, $carried),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The index a request lands on: the requested one when this page shows it, else `default`.
     *
     * A hidden or unknown handle falls back, so a hand-edited `?index=` cannot reach an
     * unconfigured index, which throws, or a tab the page does not offer.
     *
     * @param  mixed  $requested  The raw `index` query value.
     * @param  string  $siteHandle  The handle of the site the search covers.
     * @param  string  $scope  The resolved search scope.
     */
    public function index(mixed $requested, string $siteHandle, string $scope): string
    {
        return \is_string($requested) && array_key_exists($requested, $this->visibleTabs($siteHandle, $scope)) ? $requested : 'default';
    }

    /**
     * Every index handle the registry names, `default` first.
     *
     * @return list<string>
     */
    public function indexes(): array
    {
        return array_map(strval(...), array_keys($this->tabs()));
    }

    /**
     * The registry's index handles the host's search configuration does not define.
     *
     * A scope naming an index nobody configured would throw when searched. A host's test asserts
     * this is empty.
     *
     * @return list<string>
     */
    public function unconfiguredIndexes(): array
    {
        $configured = config('statamic.search.indexes');
        $configured = \is_array($configured) ? $configured : [];

        return array_values(array_filter($this->indexes(), static fn (string $handle): bool => ! array_key_exists($handle, $configured)));
    }

    /**
     * The label for a result: the configured label for its `result_type`, else for its
     * `collection`, else empty.
     *
     * @param  list<string>  $types  The names to try, in order.
     */
    public function resultLabel(array $types): string
    {
        $labels = config('aether-search.labels');
        $labels = \is_array($labels) ? $labels : [];

        foreach ($types as $type) {
            if (\is_string($labels[$type] ?? null)) {
                return $labels[$type];
            }
        }

        return '';
    }

    /**
     * The collections a site carries, from Statamic's site and collection configuration. A site's
     * domain or path does not enter into it.
     *
     * @param  string  $siteHandle  The site's handle.
     * @return list<string> Collection handles.
     */
    public function carriedBy(string $siteHandle): array
    {
        return $this->carriedByAny([$siteHandle]);
    }

    /**
     * The handles of the sites a scope searches from a site, or null when it searches every site.
     *
     * "This site" covers what the bound {@see SiteTree} names for the site, and the tier scope what
     * it names for the site's tier. The site the reader is on is always among "This site"'s, and a
     * tier scope on a site with no tier falls back to "This site"'s.
     *
     * @param  string  $siteHandle  The handle of the site the reader is on.
     * @param  string  $scope  The resolved search scope.
     * @return list<string>|null Site handles, or null for every site.
     */
    public function sites(string $siteHandle, string $scope): ?array
    {
        if ($scope === self::THIS_SITE) {
            return $this->covered($siteHandle);
        }

        if ($scope === self::TIER) {
            $tier = $this->tierOf($siteHandle);

            return $tier === null ? $this->covered($siteHandle) : $this->covered($tier);
        }

        return null;
    }

    /**
     * The handle of the tier a site is offered "All of <name>" for, or null when it has none.
     *
     * The bound {@see SiteTree}'s answer is checked here, so a host cannot point the scope at the
     * site itself, at the root site (whose scope is every site) or at a site that is not configured.
     *
     * @param  string  $siteHandle  The handle of the site the reader is on.
     */
    public function tierOf(string $siteHandle): ?string
    {
        $tier = $this->tierSite($siteHandle);

        // A site is keyed by its handle, so the handle is always a string
        /** @var string|null $handle */
        $handle = $tier?->handle();

        return $handle;
    }

    /**
     * The tier a site is offered "All of <name>" for, as a configured site, or null when it has none.
     *
     * @param  string  $siteHandle  The handle of the site the reader is on.
     */
    public function tierSite(string $siteHandle): ?SiteObject
    {
        $tier = app(SiteTree::class)->tierOf($siteHandle);
        /** @var SiteObject $root */
        $root = Site::default();
        // Statamic answers a handle with its configured site, or null when none is configured
        /** @var SiteObject|null $site */
        $site = $tier === null ? null : Site::get($tier);

        return $tier === $siteHandle || $tier === $root->handle() ? null : $site;
    }

    /**
     * The sites the bound {@see SiteTree} says a site covers, with that site always first among them.
     *
     * A handle that names no configured site is dropped, so the page's `|`-joined `site` and the
     * type-ahead's list cannot disagree over one the host got wrong.
     *
     * @param  string  $siteHandle  The site's handle.
     * @return list<string> Site handles, without duplicates.
     */
    private function covered(string $siteHandle): array
    {
        $sites = [$siteHandle];

        foreach (app(SiteTree::class)->covered($siteHandle) as $handle) {
            if (Site::get($handle) instanceof SiteObject && ! \in_array($handle, $sites, true)) {
                $sites[] = $handle;
            }
        }

        return $sites;
    }

    /**
     * The collections any of these sites carries, from Statamic's site and collection
     * configuration, each once.
     *
     * @param  list<string>  $siteHandles  The sites' handles.
     * @return list<string> Collection handles.
     */
    private function carriedByAny(array $siteHandles): array
    {
        $handles = [];

        /** @var EntriesCollection $collection */
        foreach (Collection::all() as $collection) {
            // A collection's sites are always a collection of handles, the default site's when none are set
            /** @var SupportCollection<int, string> $sites */
            $sites = $collection->sites();

            if ($sites->intersect($siteHandles)->isNotEmpty()) {
                /** @var string $handle */
                $handle = $collection->handle();
                $handles[] = $handle;
            }
        }

        return $handles;
    }

    /**
     * Whether a tab can return a result for a site carrying these collections.
     *
     * @param  string  $handle  The tab's index handle.
     * @param  list<string>  $carried  The collection handles the site carries.
     */
    private function fillable(string $handle, array $carried): bool
    {
        $covers = config('aether-search.tab_collections');
        $covers = \is_array($covers) ? ($covers[$handle] ?? null) : null;

        if ($handle === 'default' || ! \is_array($covers)) {
            return true;
        }

        return array_any($covers, fn ($collection): bool => \in_array($collection, $carried, true));
    }
}
