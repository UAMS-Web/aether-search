<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Contracts;

/**
 * Which sites a search scope covers, for a host whose sites form a tree.
 *
 * "This site" searches the sites {@see covered()} names for the current site, and a site with a
 * tier between it and the root site is also offered "All of <tier>", which searches the sites
 * covered for that tier. The package default treats every site as flat: each covers itself alone
 * and none has a tier, so the page offers "This site" and "All of <root site's name>". The
 * application decides what makes a site a tier; nothing here names one.
 */
interface SiteTree
{
    /**
     * The handles of the sites "This site" searches from a site, that site included.
     *
     * @param  string  $siteHandle  The site the reader is on.
     * @return list<string> Site handles.
     */
    public function covered(string $siteHandle): array;

    /**
     * The handle of the site offered as "All of <name>" from a site, or null when there is none.
     *
     * Null when the nearest tier above the site is the root site, whose own scope is every site.
     *
     * @param  string  $siteHandle  The site the reader is on.
     */
    public function tierOf(string $siteHandle): ?string;
}
