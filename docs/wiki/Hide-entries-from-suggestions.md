Written against v0.4.1.

By default the type-ahead suggests every published entry the index returns. To hide the entries a reader may not open, bind a visibility rule, and pair it with a query scope so the results page applies the same rule.

## Steps

1. Implement `UAMSWeb\Aether\Search\Contracts\EntryVisibility`. `canView()` returns `true` when this reader may see the entry:

   ```php
   use Statamic\Entries\Entry;
   use UAMSWeb\Aether\Search\Contracts\EntryVisibility;

   final class MembersOnlyVisibility implements EntryVisibility
   {
       public function canView(Entry $entry): bool
       {
           return $entry->get('members_only') !== true || auth()->check();
       }
   }
   ```
2. Bind it in a service provider's `register()`. The add-on binds its default (`ShowsEveryEntry`) only when the application has not bound its own:

   ```php
   $this->app->bind(EntryVisibility::class, MembersOnlyVisibility::class);
   ```
3. Register a query scope in the application that applies the same rule to a listing, and name it in `config/aether-search.php`:

   ```php
   'always_scope' => 'hide_members_only',
   ```

   Every listing applies `always_scope`, and a scoped listing's own scope runs alongside it. Without it the results page can show an entry the suggestions hide.

## Behavior to expect

- A suggestion is a link, so a hit with no title or no public URL is dropped too, as is a hit that is not an entry.
- To suggest hits that are not entries, bind a `SuggestionPresenter` that handles them and delegates the rest to the default. See [Extension points](Extension-points.md).
- The route asks the index for twice the number of suggestions it shows and trims after the rule runs, so hidden entries do not leave the flyout short while more matches exist.

## Reference

- [Extension points](Extension-points.md): `EntryVisibility` and `SuggestionPresenter`.
- [Configuration](Configuration.md): `always_scope`.
