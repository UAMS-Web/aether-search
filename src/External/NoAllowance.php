<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\External;

use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;

/**
 * The default {@see ResponseAllowance}: the host raises nothing.
 */
final class NoAllowance implements ResponseAllowance
{
    /**
     * @inheritDoc
     */
    public function raise(string $provider, array $needs): void {}
}
