<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Support;

use UAMSWeb\Aether\Search\Contracts\SiteTree;

/**
 * The default {@see SiteTree}: every site is flat, covering itself alone, with no tier.
 */
final class FlatSiteTree implements SiteTree
{
    /**
     * {@inheritDoc}
     */
    public function covered(string $siteHandle): array
    {
        return [$siteHandle];
    }

    /**
     * {@inheritDoc}
     */
    public function tierOf(string $siteHandle): ?string
    {
        return null;
    }
}
