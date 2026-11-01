<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;

/**
 * Local search indexes over one published page, for the tests that need something to query.
 */
final class SearchFixtures
{
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
        // Name a collection for this call alone
        $collectionHandle = 'pages_'.str_replace('.', '', uniqid('', true));

        // Give this call its own index directory
        $path = storage_path('framework/testing/search-'.uniqid('', true));

        // Define each handle as a local index over the pages collection
        foreach (['default', ...$handles] as $handle) {
            config(['statamic.search.indexes.'.$handle => ['driver' => 'local', 'searchables' => ['collection:'.$collectionHandle], 'fields' => ['title'], 'path' => $path]]);
        }

        // Publish one page for the indexes to hold
        $collection = new Collection;
        $collection->handle($collectionHandle);
        $collection->routes('{slug}');
        $collection->save();

        $entry = new Entry;
        $entry->collection($collectionHandle);
        $entry->slug($slug);
        $entry->data(['title' => $title]);
        $entry->save();

        // Build every index
        Artisan::call('statamic:search:update', ['--all' => true]);

        return $collectionHandle;
    }
}
