Written against v0.4.1.

By the end of this page you will have a search page at `/search` that lists results from your search index, with a second type tab and a label above each result of that type.

It assumes the add-on is installed (see [Installation (README)](https://github.com/UAMS-Web/aether-search#installation)) on a Statamic install whose `config/statamic/search.php` defines the `default` index over some collections, and that the index has been built.

## 1. Build the index

Run:

```bash
php please search:update --all
```

The add-on searches the host's indexes and adds none, so an index that was never built returns nothing.

## 2. Open the page

Visit `/search`. With no settings, the page renders in a plain frame: a search field and button, one "All results" tab over the `default` index, and the prompt "Enter a search term above to get started."

## 3. Search

Type a word that appears in an entry and submit. The form is a plain GET back to `/search?q=...&index=default`. The page lists the hits for that index, ten to a page, under the heading "Search results for" followed by the query, with previous and next links when there is more than one page.

## 4. Publish the configuration

```bash
php artisan vendor:publish --tag=aether-search-config
```

This copies `config/aether-search.php` into the application. Composer merges the file at the top level only, so a key the application sets replaces that whole key.

## 5. Add a type tab

Define a second index in `config/statamic/search.php`, for example `articles`, over the collections it should cover. Then list it, and the `default` tab, in `config/aether-search.php`:

```php
'tabs' => [
    'default' => 'All results',
    'articles' => 'Articles',
],
```

Reload `/search?q=...`. A second tab appears and each tab is a link to the same query on its own index (`?q=...&index=articles`).

## 6. Label the results

Add a label for each collection (or each synthetic result's `result_type`) to show above its results:

```php
'labels' => [
    'articles' => 'Article',
],
```

A type with no label renders its card with no label.

**Note:** the page uses Bootstrap 4 class names and the add-on ships no CSS for them. On a site without Bootstrap 4 the page works but is unstyled until the site styles those classes or overrides the views.

## Go further

- [Tabs, scopes and labels](Tabs-scopes-and-labels.md): limit a tab to some collections so a site only shows tabs it can fill.
- [Type-ahead script](Type-ahead-script.md): add the suggestions flyout.
- [Override a card or a frame](Override-a-card-or-a-frame.md): change how a result looks.
