<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Aether Search configuration
|--------------------------------------------------------------------------
|
| Statamic merges this file into the application's config under the add-on's
| handle (`aether-search`), so `config('aether-search.tabs')` reads it. The host
| publishes it to its own `config/` directory with:
|
|     php artisan vendor:publish --tag=aether-search-config
|
| Composer merges the top level only: a key the host sets replaces the whole key,
| so a host that sets `tabs` lists every tab it wants.
|
| Every setting has a default that works with no application code. The search
| page then renders in a plain frame, with one "All results" tab over the
| `default` index, no scoped mode and no type labels. The markup keeps Bootstrap 4
| class names and the package ships no CSS for them; see the README.
|
*/

return [

    /*
    | The Control Panel-free front end: the layout the search page renders in
    | (Statamic's route `layout`), and the data its routes hand the cascade. The
    | page is registered at `/search` and under each additional site's URL prefix.
    */
    'layout' => 'layout',

    'route_data' => [
        'title' => 'Search',
        'breadcrumb_leaf' => 'Search',
        'published' => true,
        'title_options' => 'normal',
    ],

    /*
    | The type tabs: search index handle => label. `default` is the unified index
    | and the fallback for any handle not listed here, so a tampered `?index=`
    | never reaches an unconfigured index. Each handle other than `default` must
    | be an index in the host's `config/statamic/search.php`.
    */
    'tabs' => [
        'default' => 'All results',
    ],

    /*
    | The collections each type tab covers: search index handle => list of collection
    | handles. Under "This site" a page shows a tab only when its site carries at least one
    | of the tab's collections, so a tab that cannot return a result there is not offered;
    | under "All sites" every tab shows. A tab not listed here, and `default`, always show.
    | A site's collections come from Statamic's site and collection configuration. The tabs,
    | this map and `labels` are the one definition of the search scopes: the page, the
    | `?index=` allow-list, the type-ahead and the result labels all read them.
    */
    'tab_collections' => [],

    /*
    | Result-type labels shown above each result: a collection handle (or the
    | `result_type` of a synthetic result) => label. A type not listed here renders
    | its card with no label rather than a guessed one. Labels are fixed strings,
    | never editor text, so they are not escaped.
    */
    'labels' => [],

    /*
    | The heading above the search field, and the sentence shown before a query is
    | entered. `prompt_hint` is appended to that sentence when set.
    */
    'text' => [
        'form_label' => 'Search',
        'placeholder' => 'Type to search…',
        'prompt_hint' => '',
    ],

    /*
    | The host's own views. A name is a view the host provides, wrapped around the
    | page; `null` uses the package's plain default, which adds no markup, so the
    | page renders with no host partial.
    |
    | The page has two frames, `article` and `entry_content`, that wrap the search
    | form and the results. Three more wrap only the results: `results_block`
    | (given `block_class` and `background_color`), `results_row` and
    | `results_header` (given `heading`, already escaped). `pagination` is given
    | `paginate` and `aria_label`.
    */
    'partials' => [
        'article' => null,
        'entry_content' => null,
        'results_block' => null,
        'results_row' => null,
        'results_header' => null,
        'pagination' => null,
    ],

    /*
    | The scoped-subtree mode, off until `index` is set. A request for the scoped
    | index with a value in the `parameter` request parameter searches only that
    | subtree: the type tabs are replaced by hidden fields carrying the scope, and
    | the listing runs through `query_scope`, a query scope the host registers. The
    | page then offers a link back to `broaden_url`. Without a value for the
    | parameter, the scoped index falls back to `default`.
    */
    'scoped' => [
        'index' => null,
        'parameter' => null,
        'query_scope' => null,
        'form_label' => 'Search this section',
        'note' => 'Searches here are limited to this section.',
        'broaden_label' => 'Search the whole site',
        'broaden_url' => '/search',
    ],

    /*
    | A query scope every listing applies, named as the host registered it, such as
    | one that hides entries the reader may not open. A scoped listing's own scope
    | runs alongside it. `null` applies none.
    */
    'always_scope' => null,

    /*
    | The external results scope: a "search elsewhere" choice beside "This site" and the
    | root site, answered by a provider that renders the results in place. Off until
    | `enabled` is true. `provider` names a registered provider (`google` is built in);
    | an unknown name fails at boot, naming the valid ones. `value` is the `?scope=` value
    | and `label` the choice's label. `providers` holds each provider's own settings under
    | its name. The scope is offered only when the provider is configured: for Google, when
    | `GOOGLE_SEARCH_ENGINE_ID` is set. Without it the choice does not appear.
    */
    'external' => [
        'enabled' => false,
        'provider' => 'google',
        'value' => 'google',
        'label' => 'External results',
        'providers' => [
            'google' => [
                'engine_id' => env('GOOGLE_SEARCH_ENGINE_ID'),
            ],
        ],
    ],

    /*
    | Type-ahead suggestions: the route's rate limit per client IP per minute.
    */
    'suggestions' => [
        'per_minute' => 120,
    ],

];
