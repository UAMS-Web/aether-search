Written against v0.4.1.

A host changes how a result looks one type at a time, and wraps the page in its own markup with named partials. Both leave the rest of the package's views in place.

## Give one result type its own card

A result's card is chosen by its `result_type`, then its `collection`, and the generic card renders when neither has one. To give one type its own card, add a view to the host at:

```text
resources/views/vendor/aether-search/cards/_<type>.antlers.html
```

`<type>` is the `result_type` or collection handle, using lowercase letters, digits and underscores only. The view wins for that type alone; every other type keeps the package's card in the same render. Each result is also given its label (see [Tabs, scopes and labels](Tabs-scopes-and-labels.md)).

## Wrap the page in host views

Name host partials in the `partials` setting. Each unset key uses a package view that adds no markup:

```php
'partials' => [
    'article' => 'partials/search/article',
    'results_block' => 'partials/search/results-block',
],
```

| Key | Wraps | Receives |
| --- | --- | --- |
| `article` | The form and the results | |
| `entry_content` | The form and the results | |
| `results_block` | The results | `block_class`, `background_color` |
| `results_row` | The results | |
| `results_header` | The results heading | `heading`, already escaped |
| `pagination` | Replaces the previous and next links | `paginate`, `aria_label` |

## Change the layout and route data

`layout` is the layout the search page renders in, and `route_data` is the data its routes hand the cascade (`title`, `breadcrumb_leaf`, `published`, `title_options`). The page is a routeless view with no entry, so `breadcrumb_leaf` supplies the trailing crumb.

## Override any other view

The views live under the `aether-search` namespace (`aether-search::index` and so on), so a host overrides any of them by placing a file at the same path under `resources/views/vendor/aether-search/`.

## Reference

- [Configuration](Configuration.md): `layout`, `route_data` and `partials`.
