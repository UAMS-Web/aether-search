<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Support;

use Statamic\Entries\Entry;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;

/**
 * The default {@see EntryVisibility}: every entry may be seen.
 */
final class ShowsEveryEntry implements EntryVisibility
{
    /**
     * {@inheritDoc}
     */
    public function canView(Entry $entry): bool
    {
        return true;
    }
}
