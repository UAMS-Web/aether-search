<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Contracts;

use Statamic\Contracts\Search\Result;

/**
 * Shapes one search hit as a type-ahead suggestion.
 *
 * The application binds its own presenter for the hits it indexes that are not entries (a
 * synthetic result document, for example), delegating to the package default for the rest.
 */
interface SuggestionPresenter
{
    /**
     * One hit as `{ title, url, type }`, or null when it must not be offered.
     *
     * @param  Result  $result  The search hit.
     * @return array{title: string, url: string, type: string}|null The suggestion, or null to drop the hit.
     */
    public function present(Result $result): ?array;
}
