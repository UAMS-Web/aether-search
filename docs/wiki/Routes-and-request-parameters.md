Written against v0.4.1.

The add-on registers two kinds of route, in `routes/web.php`, loaded by Statamic ahead of the application's own wildcard routes and its catch-all.

## The search page

`GET /search`, and `GET <prefix>/search` for each additional site's URL prefix. Each renders the `aether-search::index` view in the configured `layout`, with the configured `route_data`.

| Parameter | Values | Effect |
| --- | --- | --- |
| `q` | Text | The query. Results render only when it is present. An array (`q[]=x`) is treated as absent. |
| `index` | A tab's handle | The index to search. A handle that is not a tab this page shows falls back to `default`. |
| `scope` | `site`, `tier`, `all`, or the external scope's value | How widely to search. Falls back to the site's default when the site does not offer it. |
| `page` | Positive integer | The results page. Ten results to a page. |
| The `scoped.parameter` name | Text | The scoped mode's scope root. Read only when `scoped.index` is set. |

The form is a plain GET that carries `q`, `index` and, off the default scope, `scope`.

## The suggestions route

`GET /!/search/suggestions`, named `site-search.suggestions`. The `/!/` prefix keeps it clear of entry URIs.

| Parameter | Values | Effect |
| --- | --- | --- |
| `q` | Text of 2 to 100 characters | The query. A shorter or longer one, or one that is not valid UTF-8, returns an empty list. |
| `index` | A tab's handle | The index to search, resolved as on the page. |
| `site` | A site handle | The site the reader is searching from. An unknown handle uses the current site. |
| `scope` | As on the page | Resolved as on the page. |

The response is JSON:

```json
{
  "query": "cardio",
  "index": "default",
  "results": [
    { "title": "Cardiology clinic", "url": "/cardiology", "type": "Page" }
  ]
}
```

It returns at most 8 suggestions, only published entries, drops a hit the bound `EntryVisibility` refuses, and answers `429` once a client IP address passes `suggestions.per_minute` requests in a minute. The limiter is named `aether-search-suggestions`.

## Reference

- [Configuration](Configuration.md): `suggestions` and `route_data`.
- [Extension points](Extension-points.md): `EntryVisibility` and `SuggestionPresenter`.
