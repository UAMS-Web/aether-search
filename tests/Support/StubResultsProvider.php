<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Tests\Support;

use UAMSWeb\Aether\Search\Contracts\ExternalResultsProvider;

/**
 * A second external results provider, to show the contract is not Google's alone.
 *
 * It is configured when its `token` setting is non-empty, renders the fixture view
 * `host/external`, and declares one `connect-src` host.
 */
final class StubResultsProvider implements ExternalResultsProvider
{
    /**
     * {@inheritDoc}
     */
    public function isConfigured(array $settings): bool
    {
        return ($settings['token'] ?? '') !== '';
    }

    /**
     * {@inheritDoc}
     */
    public function view(): string
    {
        return 'host.external';
    }

    /**
     * {@inheritDoc}
     */
    public function needs(): array
    {
        return ['connect-src' => ['https://stub.example']];
    }
}
