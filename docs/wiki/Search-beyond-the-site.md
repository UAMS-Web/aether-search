Written against v0.4.1.

The external results scope is a "search elsewhere" choice beside "This site" and the root site's choice. It hands the query to another search service, whose provider renders the results in place. Google Programmable Search is built in. The scope is off until it is turned on.

## Steps

1. Set the search engine ID in the application's environment:

   ```text
   GOOGLE_SEARCH_ENGINE_ID=your-engine-id
   ```
2. Turn the scope on in `config/aether-search.php`:

   ```php
   'external' => [
       'enabled' => true,
       'provider' => 'google',
       'value' => 'google',
       'label' => 'All UAMS websites',
       'providers' => [
           'google' => ['engine_id' => env('GOOGLE_SEARCH_ENGINE_ID')],
       ],
   ],
   ```

   `value` is the `?scope=` value and `label` the choice's text. `provider` names a registered provider, and an unknown name fails when the application boots with a message that names the valid providers.
3. Allow what Google's script needs in the site's content security policy, if the site has one. The add-on does not know the policy. Bind a `ResponseAllowance` (see [Extension points](Extension-points.md)); the add-on calls it once, only for a request that renders the provider's results, with what the provider declares it needs.

## What Google's provider asks for

By content security policy directive:

| Directive | Sources |
| --- | --- |
| `script-src` | `'unsafe-eval'`, `https://cse.google.com`, `https://www.google.com` |
| `style-src` | `https://www.google.com` |
| `connect-src` | `https://cse.google.com` |
| `frame-src` | `https://cse.google.com` |

The results view tags Google's script with the request's CSP nonce.

## Behavior to expect

- A disabled scope is not offered, and a request that names it searches the site's own content.
- Without `GOOGLE_SEARCH_ENGINE_ID` the scope is not offered, and the add-on boots and works as it does with the scope off.
- The provider has no types to filter by, so the type tabs are hidden on the external scope.
- Without JavaScript the results area shows "Searching beyond this site needs JavaScript."
- Google's script replaces the classes of its own element, so a site's class goes on a wrapper. The package's view already wraps it in `site-search-google`.

## Add another provider

See [Extension points](Extension-points.md).

## Reference

- [Configuration](Configuration.md): `external`.
