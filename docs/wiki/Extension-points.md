Written against v0.4.1.

Four interfaces let a host decide what the package cannot, and a fifth lets it add an external search provider. Each has a default that needs no host code. The package binds a default only when the application has not bound its own, and the application binds while registering, before any provider boots, so bind in a service provider's `register()`.

## The four seams

| Interface | Method | Decides | Default |
| --- | --- | --- | --- |
| `UAMSWeb\Aether\Search\Contracts\EntryVisibility` | `canView(Entry $entry): bool` | Whether this reader may see an entry in the suggestions. | `ShowsEveryEntry`: every entry. |
| `UAMSWeb\Aether\Search\Contracts\SuggestionPresenter` | `present(Result $result): ?array` | How a hit becomes a suggestion `{ title, url, type }`, or `null` to drop it. | `EntrySuggestionPresenter`: handles entries. |
| `UAMSWeb\Aether\Search\Contracts\ResponseAllowance` | `raise(string $provider, array $needs): void` | Raises the host's own per-response allowance, such as a content security policy, for an external provider's results. | `NoAllowance`: does nothing. |
| `UAMSWeb\Aether\Search\Contracts\SiteTree` | `covered(string $siteHandle): array` and `tierOf(string $siteHandle): ?string` | Which sites "This site" covers from a site, and the tier a site is offered "All of <tier>" for. | `FlatSiteTree`: each site covers itself and none has a tier. |

- **`EntryVisibility`** pairs with `always_scope`, so a listing applies the same rule. See [Hide entries from suggestions](Hide-entries-from-suggestions.md).
- **`SuggestionPresenter`** is for hits that are not entries, such as a synthetic result document. Delegate the rest to `EntrySuggestionPresenter`. The default drops a hit that is not an entry, one the reader may not see, and one with no title or no public URL.
- **`ResponseAllowance`** is called once, for a request that renders a provider's results: the scope is on, the provider is configured and the request has a query. It is never called otherwise.
- **`SiteTree`** is described in [Search across sites](Search-across-sites.md).

## An external results provider

Implement `UAMSWeb\Aether\Search\Contracts\ExternalResultsProvider`:

| Method | Returns |
| --- | --- |
| `isConfigured(array $settings): bool` | Whether the provider's settings are complete. A scope whose provider is not configured is not offered, and `?scope=` naming it falls back to the site's own search. |
| `view(): string` | The partial that renders the results, given `settings`. It renders only on a request that carries a query. |
| `needs(): array` | What the response needs to load the results, as source expressions by content security policy directive. The package hands this to the bound `ResponseAllowance` and never applies it. |

Register it from a service provider's `boot()`:

```php
app(\UAMSWeb\Aether\Search\External\ExternalResultsProviders::class)->register('bing', BingResultsProvider::class);
```

Then set `external.provider` to `bing` and put its settings under `external.providers.bing`. The registry is a singleton created in the register phase, so a provider added from a host's `boot()` is the one the add-on checks. The add-on checks the configured provider once every provider has booted. An unknown name fails with `Unknown aether-search external results provider "<name>". Valid providers: <names>.`

## Check the scopes

`UAMSWeb\Aether\Search\Support\SearchScopes` is the scope registry the page, the suggestions route and the tag read. `unconfiguredIndexes()` lists the handles in `tabs` that the host's `config/statamic/search.php` does not define.

## What the add-on does not provide

The add-on registers no commands, permissions or fieldtypes, and no Control Panel screens. Its Control Panel build (`resources/js/cp.js`) is an empty entry point.

## Reference

- [Configuration](Configuration.md): `external` and `always_scope`.
