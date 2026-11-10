<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Support;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Entries\Collection as EntriesCollection;
use Statamic\Facades\Collection;

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
 * Under "This site" a page shows only the tabs its site can fill: "All results", and each tab with
 * a collection that site carries. Under any wider scope every tab shows.
 */
final class SearchScopes
{
    /**
     * The scope that searches only the current site, as the tag names it.
     */
    private const string THIS_SITE = 'site';

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
     * Only "This site" narrows them: a tab is dropped when it names collections and the site
     * carries none of them. Any other scope searches more than the one site, so every tab shows.
     *
     * @param  string  $siteHandle  The handle of the site the search covers.
     * @param  string  $scope  The resolved search scope.
     * @return array<string, string> Index handle => label.
     */
    public function visibleTabs(string $siteHandle, string $scope): array
    {
        $tabs = $this->tabs();

        if ($scope !== self::THIS_SITE) {
            return $tabs;
        }

        $carried = $this->carriedBy($siteHandle);

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
        $handles = [];

        /** @var EntriesCollection $collection */
        foreach (Collection::all() as $collection) {
            // A collection's sites are always a collection of handles, the default site's when none are set
            /** @var SupportCollection<int, string> $sites */
            $sites = $collection->sites();

            if ($sites->contains($siteHandle)) {
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
