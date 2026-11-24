Written against v0.4.1.

A search scope is one search index, a label and the collections it covers. It is defined once, in the `tabs`, `tab_collections` and `labels` settings, and everything else reads those: the tabs, the `?index=` allow-list on the page and on the suggestions route, and the result-type labels. Adding or renaming a scope is one edit.

## Steps

1. Define the index in the host's `config/statamic/search.php`, over the collections it should return. Each tab must name an index that exists there, because searching an index nobody defined throws.
2. List the tab in `config/aether-search.php`, as index handle to label. Keep `default`, the unified index, which is also the fallback for any handle not listed:

   ```php
   'tabs' => [
       'default' => 'All results',
       'people' => 'People',
       'news' => 'News',
   ],
   ```

   `default` is always present, even when the setting leaves it out, and it is labeled "All results" unless the setting gives another label.
3. Say which collections the tab covers, so a site that carries none of them does not offer a tab that cannot return a result:

   ```php
   'tab_collections' => [
       'people' => ['people', 'departments'],
       'news' => ['news'],
   ],
   ```

   Under "This site" (and under a tier) a page shows a tab only when its site carries at least one of the tab's collections. Under "All sites" every tab shows. A tab with no entry in `tab_collections`, and `default`, always show. A site's collections come from Statamic's site and collection configuration, so the address the site answers on does not change them.
4. Label the results. Each key is a collection handle, or the `result_type` of a synthetic result:

   ```php
   'labels' => [
       'people' => 'Person',
       'news' => 'News story',
   ],
   ```

   The label appears above each result, and as the `type` of a type-ahead suggestion for an entry in that collection. A type with no label renders no label. Labels are fixed strings, never editor text, so they are not escaped.
5. Check that every scope names a configured index. In a host test:

   ```php
   expect(app(\UAMSWeb\Aether\Search\Support\SearchScopes::class)->unconfiguredIndexes())->toBe([]);
   ```

   `unconfiguredIndexes()` returns the handles in `tabs` that `config/statamic/search.php` does not define.

## Behavior to expect

- A link between scopes keeps the current tab only when the target scope shows it, and otherwise lands on "All results".
- A hand-edited `?index=` that names a hidden or unknown tab falls back to `default` and answers normally, on the page and on the suggestions route.
- Switching tabs drops `page`, so a new tab starts at page 1.

## Reference

- [Configuration](Configuration.md): `tabs`, `tab_collections` and `labels`.
- [Routes and request parameters](Routes-and-request-parameters.md): `index` and `page`.
