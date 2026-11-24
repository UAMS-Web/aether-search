Written against v0.4.1.

Every setting lives in `config/aether-search.php`. Publish it to the application with:

```bash
php artisan vendor:publish --tag=aether-search-config
```

Statamic merges the file into the application's config under the add-on's handle, so `config('aether-search.tabs')` reads it. Composer merges the top level only: a key the application sets replaces the whole key, so an application that sets `tabs` lists every tab it wants. Every setting has a default that works with no application code.

## Page and routes

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `layout` | A layout name, or an empty string | `'layout'` | The layout the search page renders in. An empty string leaves the route without a `layout`. |
| `route_data` | Array | `title` `'Search'`, `breadcrumb_leaf` `'Search'`, `published` `true`, `title_options` `'normal'` | The data the search routes hand the cascade. `layout` is added to it when set. |
| `text` | Array of `form_label`, `placeholder`, `prompt_hint` | `'Search'`, `'Type to search…'`, `''` | The heading above the field, the field's placeholder, and a hint appended to the sentence shown before a query is entered. |

## Scopes and results

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `tabs` | Index handle to label | `['default' => 'All results']` | The type tabs. `default` is the unified index and the fallback for any handle not listed. Each other handle must be an index in the host's `config/statamic/search.php`. |
| `tab_collections` | Index handle to a list of collection handles | `[]` | The collections each tab covers. Under "This site" a page shows a tab only when its site carries one of them. |
| `labels` | Collection handle or `result_type` to label | `[]` | The label above each result, and the `type` of a suggestion. |
| `always_scope` | A query scope's name, or `null` | `null` | A query scope every listing applies. |
| `partials` | Array, keys below | each `null` | Host views that wrap the page. `null` uses a package view that adds no markup. |

`partials` keys: `article`, `entry_content`, `results_block`, `results_row`, `results_header`, `pagination`. See [Override a card or a frame](Override-a-card-or-a-frame.md).

## Scoped-subtree mode

Off until `scoped.index` is set. See [Search one section](Search-one-section.md).

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `scoped.index` | An index handle, or `null` | `null` | The scoped index. |
| `scoped.parameter` | A request parameter name, or `null` | `null` | The parameter that carries the scope root. |
| `scoped.query_scope` | A query scope's name, or `null` | `null` | The scope the listing runs through. |
| `scoped.form_label` | String | `'Search this section'` | The heading above the field in this mode. |
| `scoped.note` | String | `'Searches here are limited to this section.'` | The note shown above the results. |
| `scoped.broaden_label` | String | `'Search the whole site'` | The text of the link back to the full search. |
| `scoped.broaden_url` | A path or URL | `'/search'` | Where that link goes. |

## External results

Off until `external.enabled` is true. See [Search beyond the site](Search-beyond-the-site.md).

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `external.enabled` | `true`, `false` | `false` | Whether the scope exists. A disabled scope is not offered. |
| `external.provider` | A registered provider's name | `'google'` | The provider that renders the results. An unknown name fails at boot and names the valid ones. |
| `external.value` | String | `'google'` | The `?scope=` value. |
| `external.label` | String | `'External results'` | The choice's label. |
| `external.providers` | Provider name to its settings | `google` with `engine_id` read from `GOOGLE_SEARCH_ENGINE_ID` | Each provider's own settings. |

The scope is offered only when the provider is configured: for Google, when `GOOGLE_SEARCH_ENGINE_ID` is set.

## Suggestions

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `suggestions.per_minute` | Integer | `120` | The suggestions route's rate limit, per client IP address per minute. A value that is not an integer uses `120`. |

## Example

```php
// config/aether-search.php
return [
    'tabs' => [
        'default' => 'All results',
        'people' => 'People',
    ],
    'tab_collections' => [
        'people' => ['people'],
    ],
    'labels' => [
        'people' => 'Person',
    ],
    'suggestions' => [
        'per_minute' => 60,
    ],
];
```

Keys the example leaves out keep their defaults. A key it sets replaces that key's default whole, which is why `tabs` repeats `default`.
