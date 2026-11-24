Written against v0.4.1.

The scoped-subtree mode searches one part of the site, such as a guide or a knowledge base, instead of the whole index. It is off until the `scoped.index` setting is set.

## Steps

1. Define an index for the scoped search in the host's `config/statamic/search.php`.
2. Register a query scope in the application that limits a listing to the subtree. The mode forwards the request's scope value to that scope as `scope_root`.
3. Turn the mode on in `config/aether-search.php`:

   ```php
   'scoped' => [
       'index' => 'subtree',
       'parameter' => 'root',
       'query_scope' => 'subtree_articles',
       'form_label' => 'Search this guide',
       'note' => 'Searches here are limited to this guide.',
       'broaden_label' => 'Search everything',
       'broaden_url' => '/search',
   ],
   ```

   `index` is the scoped index's handle, `parameter` the request parameter that carries the scope root, and `query_scope` the scope from step 2. `form_label`, `note`, `broaden_label` and `broaden_url` are the wording and the link back to the full search; their defaults are "Search this section", "Searches here are limited to this section.", "Search the whole site" and `/search`.
4. Link to the page with the scoped index and a value for the parameter: `/search?index=subtree&root=<value>`.

## Behavior to expect

- The type tabs are replaced by hidden fields that carry the index and the scope root, so a refined query stays scoped.
- The field is a plain search input with no type-ahead, because the suggestions come from the unified index.
- The page shows the `note` and a link to `broaden_url` that keeps the query.
- The mode searches every site, with the query scope as the bound, and has no scope choice. The external results scope never applies to it.
- A request for the scoped index with no value for the parameter falls back to `default` and renders the ordinary page.
- The `always_scope` query scope, if set, runs alongside the scoped one.

## Reference

- [Configuration](Configuration.md): `scoped` and `always_scope`.
