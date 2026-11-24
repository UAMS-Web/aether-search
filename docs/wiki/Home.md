Aether Search adds a search page, type-ahead suggestions and the `{{ aether_search }}` tag to a Statamic site. It is part of the Aether ecosystem of UAMS Statamic add-ons. The page is a plain GET form over the search indexes the host already defines, with type tabs, a "this site or all sites" choice, pagination and an optional scoped-subtree mode. It adds no search index of its own.

Written against v0.4.1.

This wiki holds the walkthrough, the guides, the reference and troubleshooting. The [README](https://github.com/UAMS-Web/aether-search#readme) keeps the overview, the requirements and installation.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/aether-search#installation): add the package to a Statamic install.
- [Walkthrough](Walkthrough.md): from a fresh install to a search page with a type tab and result labels.

## Guides

- [Tabs, scopes and labels](Tabs-scopes-and-labels.md): add a type tab, limit it to some collections and label its results.
- [Search across sites](Search-across-sites.md): how "This site", a tier and "All sites" behave on a multisite install.
- [Search one section](Search-one-section.md): the scoped-subtree mode.
- [Search beyond the site](Search-beyond-the-site.md): the external results scope, with Google built in.
- [Hide entries from suggestions](Hide-entries-from-suggestions.md): bind a visibility rule and pair it with `always_scope`.
- [Override a card or a frame](Override-a-card-or-a-frame.md): change one result type's card, or wrap the page in host views.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per symptom, with its cause and fix.

## Reference

- [Configuration](Configuration.md): every key of `config/aether-search.php`.
- [Search tag](Search-tag.md): the methods of `{{ aether_search }}`.
- [Routes and request parameters](Routes-and-request-parameters.md): the page, the suggestions route and the query strings they read.
- [Extension points](Extension-points.md): the interfaces a host binds and the external provider contract.
- [Type-ahead script](Type-ahead-script.md): registering the Alpine component.

## Project

- [Project](Project.md): where the add-on is developed and how to report a problem.
