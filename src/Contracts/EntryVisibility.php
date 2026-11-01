<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Contracts;

use Statamic\Entries\Entry;

/**
 * Decides whether this reader may see an entry in the type-ahead suggestions.
 *
 * The application binds its own rule for the entries it restricts; the package default shows
 * every entry. A listing applies the same rule through the query scope named by
 * `aether-search.always_scope`, so the two surfaces cannot disagree.
 */
interface EntryVisibility
{
    /**
     * Whether the reader may see the entry.
     *
     * @param  Entry  $entry  The entry a search hit refers to.
     * @return bool True when the suggestion may be offered.
     */
    public function canView(Entry $entry): bool;
}
