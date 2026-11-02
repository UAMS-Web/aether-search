<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Tests\Support;

use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;

/**
 * A response allowance that records each call, to show when the hook runs.
 */
final class RecordingAllowance implements ResponseAllowance
{
    /**
     * The calls, in order: the provider's name and what it declared.
     *
     * @var list<array{provider: string, needs: array<string, list<string>>}>
     */
    public array $calls = [];

    /**
     * @inheritDoc
     */
    public function raise(string $provider, array $needs): void
    {
        $this->calls[] = ['provider' => $provider, 'needs' => $needs];
    }
}
