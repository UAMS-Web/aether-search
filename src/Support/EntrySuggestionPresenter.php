<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Support;

use Statamic\Contracts\Search\Result;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;

/**
 * The default {@see SuggestionPresenter}: an entry hit becomes `{ title, url, type }`.
 *
 * A hit that is not an entry, an entry the reader may not see, and a hit with no title or no
 * public URL are dropped, since a suggestion is a link. The type is the configured label for
 * the entry's collection, empty when the collection has none.
 */
final class EntrySuggestionPresenter implements SuggestionPresenter
{
    /**
     * @param  EntryVisibility  $visibility  Decides whether this reader may see an entry
     */
    public function __construct(private readonly EntryVisibility $visibility) {}

    /**
     * {@inheritDoc}
     */
    public function present(Result $result): ?array
    {
        $searchable = $result->getSearchable();

        if (! $searchable instanceof Entry || ! $this->visibility->canView($searchable)) {
            return null;
        }

        $title = $searchable->value('title');
        $url = $searchable->url();
        $collection = $searchable->collectionHandle();
        $labels = config('aether-search.labels');

        $label = \is_string($collection) && \is_array($labels) ? ($labels[$collection] ?? null) : null;
        $type = \is_string($label) ? $label : '';

        return self::suggestion(\is_string($title) ? $title : '', \is_string($url) ? $url : '', $type);
    }

    /**
     * Build a suggestion, or null when the title or URL is empty.
     *
     * @param  string  $title  The suggestion's title.
     * @param  string  $url  The suggestion's URL.
     * @param  string  $type  The result-type label.
     * @return array{title: string, url: string, type: string}|null The suggestion, or null when it has no title or URL.
     */
    public static function suggestion(string $title, string $url, string $type): ?array
    {
        if ($title === '' || $url === '') {
            return null;
        }

        return ['title' => $title, 'url' => $url, 'type' => $type];
    }
}
