Written against v0.4.1.

One entry per symptom, as you meet it: the message or behavior, its cause, and the fix.

- [The application fails to boot naming an unknown external results provider](#the-application-fails-to-boot-naming-an-unknown-external-results-provider)
- [A search for content that exists returns nothing](#a-search-for-content-that-exists-returns-nothing)
- [A tab throws when it is opened](#a-tab-throws-when-it-is-opened)
- [A sub-site does not show a tab](#a-sub-site-does-not-show-a-tab)
- [The external results choice does not appear](#the-external-results-choice-does-not-appear)
- [The suggestions flyout never opens, or lists nothing](#the-suggestions-flyout-never-opens-or-lists-nothing)
- [The suggestions route answers 429](#the-suggestions-route-answers-429)
- [The page is unstyled](#the-page-is-unstyled)
- [Fixed](#fixed)

## The application fails to boot naming an unknown external results provider

Booting stops with:

```text
Unknown aether-search external results provider "bing". Valid providers: google.
```

**Cause:** `external.provider` names a provider nobody registered. The check runs once every provider has booted, so a provider registered from a host's `boot()` counts.

**Fix:** set `external.provider` to one of the names the message lists, or register the provider. See [Extension points](Extension-points.md).

## A search for content that exists returns nothing

The page says "No results found." for a word an entry contains.

**Cause:** the add-on searches the host's indexes and adds none, so an index that was never built, or not rebuilt since the entry was saved, does not hold it. Statamic indexes only published entries.

**Fix:** run `php please search:update --all`, and confirm the entry is published and its collection is one the index covers in `config/statamic/search.php`.

## A tab throws when it is opened

Opening a tab raises an error from the search index instead of listing results.

**Cause:** the tab's handle in `tabs` is not an index in the host's `config/statamic/search.php`. Searching an index nobody defined throws.

**Fix:** define the index, or remove the tab. To find every such tab, assert in a host test that `app(\UAMSWeb\Aether\Search\Support\SearchScopes::class)->unconfiguredIndexes()` is `[]`.

## A sub-site does not show a tab

A tab the root site shows is missing under "This site" on a sub-site.

**Cause:** the tab has an entry in `tab_collections`, and the sub-site carries none of the collections listed. Under "This site" and a tier, a tab shows only when the site carries one of its collections.

**Fix:** add the collection to the sub-site in Statamic's site and collection configuration, or change `tab_collections`. Under "All sites" every tab shows.

## The external results choice does not appear

No external choice is listed beside "This site".

**Cause:** `external.enabled` is `false`, or the provider is not configured. For Google that is an unset `GOOGLE_SEARCH_ENGINE_ID`.

**Fix:** turn the scope on and set the engine ID. See [Search beyond the site](Search-beyond-the-site.md). The scope also never applies to the scoped-subtree mode.

## The suggestions flyout never opens, or lists nothing

Typing in the field shows no list.

**Cause:** one of these.

- The script is not registered with Alpine, so the field behaves as a plain input. Submitting the form still works.
- The query has fewer than 2 or more than 100 characters, or is not valid UTF-8. The route answers with an empty list.
- The matching entries are unpublished, have no title or public URL, or the bound `EntryVisibility` refuses them.
- The index has not been built.

**Fix:** register the script ([Type-ahead script](Type-ahead-script.md)), then request `/!/search/suggestions?q=<word>` directly and read the JSON it answers.

## The suggestions route answers 429

The route refuses requests with `429 Too Many Requests`.

**Cause:** the client IP address passed `suggestions.per_minute` requests in a minute. The default is 120.

**Fix:** raise `suggestions.per_minute` in `config/aether-search.php`. See [Configuration](Configuration.md).

## The page is unstyled

The page renders with no layout styling.

**Cause:** the markup uses Bootstrap 4 class names and the add-on ships no CSS for them.

**Fix:** use Bootstrap 4, style those classes in the site's own CSS, or override the views. See [Override a card or a frame](Override-a-card-or-a-frame.md).

## Fixed

Problems fixed in a release stay here, with the release that fixed them, instead of being deleted. None is recorded yet.
