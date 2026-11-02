# Aether Search

Search for Statamic sites, part of the Aether ecosystem of UAMS Statamic add-ons.

This repository was split out of [`UAMS-Web/uams-statamic`](https://github.com/UAMS-Web/uams-statamic) and is not released yet.

## What it provides

- A search page at `/search`, and under each additional site's URL prefix, with a search field, type tabs over your search indexes, a "this site or all sites" choice, a results listing with pagination, and an optional scoped-subtree mode. The form is a plain GET and works without JavaScript.
- Type-ahead suggestions at `/!/search/suggestions`, with the script that drives them.
- The `{{ aether_search }}` tag the page is built on.
- Generic result cards, one per result row, that a host overrides one type at a time.

It adds no search index of its own: the indexes are the host's `config/statamic/search.php`, and each tab names one.

## Requirements

The add-on needs PHP 8.4 or later and Statamic 6.35 or later, matching `composer.json`. `tests/Unit/ReadmeTest.php` fails when this README drifts from those floors.

## Installation

```bash
composer require uams-web/aether-search
```

The add-on registers itself through Statamic's add-on discovery. Its settings live in `config/aether-search.php`; publish it to the host with:

```bash
php artisan vendor:publish --tag=aether-search-config
```

## Configuration

Publish `config/aether-search.php` and read the comment above each setting. With nothing set, the page renders in a plain frame with one "All results" tab over the `default` index, no type labels and no scoped mode. The settings are:

- `tabs`: the type tabs, as search index handle to label. Each index must exist in `config/statamic/search.php`.
- `labels`: the result-type label shown above each result, by collection handle (or a synthetic result's `result_type`).
- `text`: the form label, placeholder and the hint shown before a query.
- `layout` and `route_data`: the layout the page renders in and the data its routes hand the cascade.
- `partials`: the host views that wrap the page. `article` and `entry_content` wrap the form and the results; `results_block`, `results_row` and `results_header` wrap only the results; `pagination` replaces the plain previous and next links. Left unset, each is a package view that adds no markup.
- `scoped`: the scoped-subtree mode, off until `index` is set. Requesting that index with a value in the `parameter` request parameter searches one subtree through `query_scope`, a query scope the host registers, and replaces the tabs with hidden fields.
- `always_scope`: a query scope every listing applies, such as one that hides entries the reader may not open.

Composer merges the config at the top level, so a host that sets `tabs` lists every tab it wants.

## Overriding a card

A result's card is chosen by its `result_type`, then its `collection`, and the generic compact card renders when neither has one. To give one type its own card, put a view at `resources/views/vendor/aether-search/cards/_<type>.antlers.html` in the host. It wins for that type only; every other type keeps the package's card in the same render.

## Seams

Two interfaces let a host decide what the package cannot, with a default that needs no host code:

- `UAMSWeb\Aether\Search\Contracts\EntryVisibility`: whether this reader may see an entry in the suggestions. The default shows every entry. Pair it with `always_scope` so the listing applies the same rule.
- `UAMSWeb\Aether\Search\Contracts\SuggestionPresenter`: how a hit becomes a suggestion, for hits that are not entries. The default handles entries.

Bind your own in a service provider's `register()`; the package binds its defaults only when you have not.

## The type-ahead script

The script is `resources/js/site-search-typeahead.js` in this package, with its test file beside it, and this package is its only home. It is an [Alpine](https://alpinejs.dev) component written for the `@alpinejs/csp` build. The host imports it from `vendor/uams-web/aether-search/resources/js/site-search-typeahead.js`, registers it with `Alpine.data('siteSearchTypeahead', ...)` and bundles it with its own assets; the package builds no front-end asset for it. Without the script the form is the plain GET above.

## Markup and styling

The page and cards use Bootstrap 4 class names (`form-row`, `nav-pills`, `card`, `stretched-link` and the like), so a host on Bootstrap 4 renders them with no change. This package ships no CSS for them. A host on another framework styles those classes itself or overrides the views.

## Development and submission

Developing against a host application is covered in [the development guide](docs/DEVELOPMENT.md), and the Packagist and Marketplace steps in [the submission checklist](docs/SUBMISSION.md). Decisions are recorded in [`docs/DECISIONS.md`](docs/DECISIONS.md).

## Namespace

The add-on's namespace is `UAMSWeb\Aether\Search\`. Every add-on in the Aether ecosystem sits under `UAMSWeb\Aether\`, in the shape `UAMSWeb\Aether\<Product>`, or `UAMSWeb\Aether\<Family>\<Product>` where a family groups packages. The level stays `UAMSWeb\<Product>` only for an add-on outside that ecosystem, and the family level is optional there too. The `autoload.psr-4` key maps that whole prefix to `src/` ([PSR-4](https://www.php-fig.org/psr/psr-4/) allows a prefix of any depth), so the files under `src/` do not move when the prefix changes. A prefix has one owner or none, and two packages never map the same one. Neither `UAMSWeb\Aether\` nor a family level that no package owns is mapped by anyone: `UAMSWeb\Aether\Forms\Settings\` and `UAMSWeb\Aether\Forms\Multisite\` are two packages' keys, and no package maps `UAMSWeb\Aether\Forms\`. A package may instead own its prefix with a companion nested inside it: one package maps `UAMSWeb\Aether\People\` and another maps `UAMSWeb\Aether\People\ListingsManagement\`. Composer picks the longest matching prefix, so a nested prefix works only when the outer package has no directory under `src/` named after the inner one (here, no `src/ListingsManagement/` in the `UAMSWeb\Aether\People\` package).

## License

The add-on has no license yet. The choice waits on the general decision from UAMS legal in [`UAMS-Web/uams-statamic#261`](https://github.com/UAMS-Web/uams-statamic/issues/261).
