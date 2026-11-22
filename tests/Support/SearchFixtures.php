<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\Store;

/**
 * Local search indexes over entries a test publishes, for the tests that need something to query.
 */
final class SearchFixtures
{
    /**
     * Turn multisite on and define a root site and a `/health` sub-site, for the entries that name the second one.
     *
     * A collection reports only the default site until multisite is on.
     */
    public static function defineTwoSites(): void
    {
        config(['statamic.system.multisite' => true, 'statamic.editions.pro' => true]);

        Site::setSites([
            'default' => ['name' => 'Main', 'url' => '/', 'locale' => 'en_US'],
            'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
        ]);
    }

    /**
     * Define local indexes over the `pages` collection, publish one page and build every index.
     *
     * Each call writes under a directory of its own, because the tests run in parallel workers
     * and must not share an index file.
     *
     * @param  list<string>  $handles  The index handles to define besides `default`.
     * @param  string  $title  The title of the one page.
     * @param  string  $slug  The slug of the one page.
     * @return string The handle of the collection holding the page. It is unique to the call: saved
     *                entries outlive a test within a worker, so a shared handle would collect every
     *                earlier test's page.
     */
    public static function indexOnePage(array $handles = [], string $title = 'Hello world', string $slug = 'hello-world'): string
    {
        return self::indexEntries([['title' => $title, 'slug' => $slug]], $handles);
    }

    /**
     * Define local indexes over one collection, save each entry given and build every index.
     *
     * A call writes under an index directory of its own, for the reason {@see indexOnePage()} gives.
     * An entry on a site other than `default` needs that site defined first, by
     * {@see defineTwoSites()} or `Site::setSites()`.
     *
     * @param  list<array{title: string, slug: string, site?: string, stale_draft?: bool}>  $entries  The entries to save, each published when the indexes are built. `site` defaults to `default`. `stale_draft` unpublishes the entry afterwards without updating the indexes, so they still hold a hit for a draft.
     * @param  list<string>  $handles  The index handles to define besides `default`.
     * @return string The handle of the collection holding the entries, unique to the call.
     */
    public static function indexEntries(array $entries, array $handles = []): string
    {
        // Name a collection for this call alone
        $collectionHandle = 'pages_'.str_replace('.', '', uniqid('', true));

        // Give this call its own index directory
        $path = storage_path('framework/testing/search-'.uniqid('', true));

        // Define each handle as a local index over the collection
        foreach (['default', ...$handles] as $handle) {
            config(['statamic.search.indexes.'.$handle => ['driver' => 'local', 'searchables' => ['collection:'.$collectionHandle], 'fields' => ['title'], 'path' => $path]]);
        }

        // Keep the collection and its entries out of the shared content directory: a multisite Stache
        // reads a directory per site, and the flat files earlier tests left there make it throw
        $content = storage_path('framework/testing/content-'.uniqid('', true));
        Stache::stores()->only(['collections', 'entries'])->each(static fn (Store $store): Store => $store->directory($content));

        // Let the collection hold, and route, an entry on every site the entries name
        $sites = array_values(array_unique(['default', ...array_map(static fn (array $entry): string => $entry['site'] ?? 'default', $entries)]));

        $collection = new Collection;
        $collection->handle($collectionHandle);
        $collection->sites($sites);
        $collection->routes(array_fill_keys($sites, '{slug}'));
        $collection->save();

        // Save each entry on its site, published
        $saved = [];
        foreach ($entries as $spec) {
            $entry = new Entry;
            $entry->collection($collectionHandle);
            $entry->locale($spec['site'] ?? 'default');
            $entry->slug($spec['slug']);
            $entry->published(true);
            $entry->data(['title' => $spec['title']]);
            $entry->save();

            $saved[] = [$entry, $spec['stale_draft'] ?? false];
        }

        // Build every index
        Artisan::call('statamic:search:update', ['--all' => true]);

        // Unpublish the stale-draft entries now the index holds them, saving without the event that
        // would update the index
        foreach ($saved as [$entry, $stale]) {
            if ($stale) {
                $entry->published(false);
                $entry->saveQuietly();
            }
        }

        return $collectionHandle;
    }
}
