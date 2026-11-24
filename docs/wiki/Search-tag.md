Written against v0.4.1.

`{{ aether_search }}` is the search page's logic, kept out of its Antlers. The package's `index` view calls it; a host that builds its own page from the same pieces can too. Each method is called as `{{ aether_search:<method> }}`, and a method that takes parameters receives them as tag parameters (`:active="$search_scope"`).

Values the tag returns are fixed strings, come from configuration, or are configured site handles, and none echoes the request unescaped, so they are safe in an href or an attribute. The one value taken from the request, `query`, is escaped as Statamic's `get` variable escapes it.

## Methods

| Method | Parameters | Returns |
| --- | --- | --- |
| `active` | none | The resolved `?scope=`: `site`, `tier`, `all`, or the external scope's value. Falls back to the current site's default. |
| `sites` | `active` | The `site` parameter of `{{ search:results }}` for that scope: handles joined by `\|`, or `*` for every site. |
| `options` | `active`, `index` | The scope choices the current site offers, each with `value`, `label`, `param`, `target_index` and `is_active`. |
| `active_index` | none | The index the page searches: the requested `?index=` when it is a tab this page shows, the scoped index when it is requested with a scope root, otherwise `default`. |
| `tabs` | `active` (the active index) | The visible type tabs, each with `handle`, `label` and `is_active`. |
| `is_scoped` | `index` | Whether that index is the scoped index. |
| `scope_root` | none | The scoped mode's request parameter value, escaped, or empty. |
| `is_external` | `scope`, `index` | Whether the page shows the external provider's results. Never true for the scoped index. |
| `external` | none | The provider's `view` and `settings`, or an empty array. Calling it raises the response allowance. |
| `param` | `active` | The `scope` query value that selects that scope, empty at the default. |
| `path` | none | The current site's search page path, such as `/search` or `/health/search`. |
| `site` | none | The current site's handle, for the type-ahead to send back. |
| `root_name` | none | The root site's configured name. |
| `query` | none | The `?q=` value, escaped, or empty when absent or an array. |
| `settings` | none | The settings the shell reads, with each partial resolved to a view that exists. |
| `label` | `result_type`, `collection` | The configured label for the result, or empty. |
| `card` | `result_type`, `collection` | The card view for the result: its own, else the generic card. |

`settings` is a method rather than a `{config:...}` read because a hard config read does not work inside a view.

## Reference

- [Configuration](Configuration.md): the settings the tag reads.
- [Routes and request parameters](Routes-and-request-parameters.md): the query strings behind `query`, `active`, `active_index` and `scope_root`.
