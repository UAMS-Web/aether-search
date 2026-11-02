<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Tests;

/**
 * A test host whose application overrides one card, as a real application does: a view at
 * `<view path>/vendor/aether-search/cards/_people`, on a view path set before the provider boots.
 */
abstract class OverridingTestCase extends TestCase
{
    /**
     * The fixture layout, plus the path that holds the overriding card.
     *
     * @return list<string> Absolute view paths.
     */
    #[\Override]
    protected function viewPaths(): array
    {
        return [...parent::viewPaths(), __DIR__.'/Fixtures/overrides'];
    }
}
