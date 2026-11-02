<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Contracts;

/**
 * The hook an application implements to raise its own per-response allowance, such as a content
 * security policy, for a provider's results.
 *
 * The package calls it once for a request that renders an external provider's results: the scope
 * is on, the provider is configured and the request carries a query. It never calls it otherwise.
 * The default does nothing, so a host with no policy to raise needs no code.
 */
interface ResponseAllowance
{
    /**
     * Raise the allowance for this response.
     *
     * @param  string  $provider  The provider's name, such as `google`.
     * @param  array<string, list<string>>  $needs  What the provider declares it needs, by directive.
     */
    public function raise(string $provider, array $needs): void;
}
