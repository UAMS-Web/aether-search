Written against v0.4.1.

On a multisite Statamic install the search page is registered at `/search` and again under each additional site's URL prefix, for example `/health/search`. The current site comes from the URL, and "This site" needs a URL to mean it.

## The scope choices

What a page offers depends on the site:

| On | Choices (`scope` value) | Default |
| --- | --- | --- |
| The root (default) site | "This site" (no `scope` value), which searches every site | "This site" |
| A sub-site | "This site" (`site`), "All of" a tier (`tier`) when the site has one, "All of" the root site's name (`all`) | "This site" |

Any other `scope` value, including `tier` on a site with no tier and an array such as `?scope[]=x`, falls back to the site's default, so a hand-edited URL cannot fail. "This site" on a sub-site searches only the sites the site tree says it covers, which is the site itself unless the host binds another [SiteTree](Extension-points.md).

The external results scope, when it is on, adds one more choice after these. See [Search beyond the site](Search-beyond-the-site.md).

## Steps

1. Define the sites in Statamic as usual. Each site's `url` prefix gives its search page.
2. List the tabs and the collections each covers, so a sub-site shows only the tabs it can fill. See [Tabs, scopes and labels](Tabs-scopes-and-labels.md).
3. To group sites into tiers, bind a `SiteTree` in a service provider's `register()`. `covered($siteHandle)` returns the handles "This site" searches from a site, itself included. `tierOf($siteHandle)` returns the handle of the site offered as "All of <name>", or `null` when there is none. The default treats every site as flat: each covers itself alone and none has a tier.

## What the type-ahead does

The suggestions route takes the same `site` and `scope` values as the page and resolves them the same way, so the flyout and the page cannot disagree. The type-ahead script reads `data-site` and `data-scope` from its element and sends them as `site` and `scope`.

## Reference

- [Routes and request parameters](Routes-and-request-parameters.md): `scope` and `site`.
- [Extension points](Extension-points.md): `SiteTree`.
- [Search tag](Search-tag.md): `options`, `active` and `sites`.
