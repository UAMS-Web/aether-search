Written against v0.4.1.

The type-ahead is a progressive enhancement: without it the search field is an ordinary input and the form is a plain GET to the page. The add-on ships the script's source but builds no front-end asset for it, so the host imports and bundles it.

## Steps

1. Import the script from the package in the host's JavaScript, and register the component with Alpine. The script is written for the `@alpinejs/csp` build, so the markup it drives references properties and methods by name and never an inline expression:

   ```js
   import { registerSiteSearchTypeahead } from '../../vendor/uams-web/aether-search/resources/js/site-search-typeahead.js';

   registerSiteSearchTypeahead(Alpine);
   ```

   `registerSiteSearchTypeahead` is `Alpine.data('siteSearchTypeahead', () => createSiteSearchTypeahead())`. The package's `index` view already carries the `x-data="siteSearchTypeahead"` markup, so no further wiring is needed. Adjust the relative path to where the host keeps its JavaScript.
2. Bundle it with the host's own assets.

## Behavior

- It asks the suggestions route as the reader types, 200 milliseconds after the last keystroke, once the query has at least 2 characters, and lists the answers in a listbox under the field. The last option is always "See all results for" the query.
- It reads `data-index`, `data-search-path`, `data-site` and `data-scope` from its element, so the suggestions search the same index, site and scope the page does.
- The field follows the ARIA 1.2 combobox pattern: ArrowDown and ArrowUp move the active option and wrap at either end, Enter follows the active option or submits the form when none is active, and Escape closes the list, then clears the query on a second press. The list closes when focus leaves the widget. A polite live region announces how many suggestions there are.
- A suggestion's URL is followed only when it is site-relative or http(s). Anything else, such as a `javascript:` URL an editor set, is refused.
- The scoped-subtree mode renders a plain field and no type-ahead.

## Reference

- [Routes and request parameters](Routes-and-request-parameters.md): the suggestions route the script calls.
