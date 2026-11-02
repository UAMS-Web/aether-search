<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\External;

use InvalidArgumentException;
use UAMSWeb\Aether\Search\Contracts\ExternalResultsProvider;

/**
 * The external results providers the package knows, by name.
 *
 * `google` is registered here. A host adds another from a service provider's `boot()`:
 *
 *     app(ExternalResultsProviders::class)->register('bing', BingResultsProvider::class);
 *
 * and selects it with `aether-search.external.provider`.
 */
final class ExternalResultsProviders
{
    /**
     * The registered provider classes, by name.
     *
     * @var array<string, class-string<ExternalResultsProvider>>
     */
    private array $classes = ['google' => GoogleResultsProvider::class];

    /**
     * Register a provider under a name, replacing any earlier one of that name.
     *
     * @param  string  $name  The provider's name, as `aether-search.external.provider` names it.
     * @param  class-string<ExternalResultsProvider>  $class  The provider class.
     */
    public function register(string $name, string $class): void
    {
        $this->classes[$name] = $class;
    }

    /**
     * Whether a provider is registered under the name.
     *
     * @param  string  $name  The provider's name.
     */
    public function has(string $name): bool
    {
        return isset($this->classes[$name]);
    }

    /**
     * The registered names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->classes);
    }

    /**
     * Build the provider registered under the name.
     *
     * @param  string  $name  The provider's name.
     *
     * @throws InvalidArgumentException When no provider has the name.
     */
    public function make(string $name): ExternalResultsProvider
    {
        if (! isset($this->classes[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown aether-search external results provider "%s". Valid providers: %s.', $name, implode(', ', $this->names())));
        }

        /** @var ExternalResultsProvider $provider */
        $provider = app()->make($this->classes[$name]);

        return $provider;
    }
}
