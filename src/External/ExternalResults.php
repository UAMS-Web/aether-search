<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\External;

use UAMSWeb\Aether\Search\Contracts\ExternalResultsProvider;

/**
 * The external results scope as configured: whether it is offered, under what `?scope=` value and
 * label, and which provider supplies it.
 *
 * The scope is offered only when it is enabled and its provider is configured. Otherwise it does
 * not exist: it is not an option, and a request naming it searches the site's own content.
 */
final class ExternalResults
{
    /**
     * @param  ExternalResultsProviders  $providers  The registered providers.
     */
    public function __construct(private readonly ExternalResultsProviders $providers) {}

    /**
     * Fail when the configured provider name is not registered, naming the valid ones.
     *
     * @throws \InvalidArgumentException When no provider has the configured name.
     */
    public function validate(): void
    {
        $this->providers->make($this->providerName());
    }

    /**
     * Whether the scope is offered: enabled, and the provider has the settings it needs.
     */
    public function isActive(): bool
    {
        return $this->provider() instanceof ExternalResultsProvider;
    }

    /**
     * The provider that supplies the scope, or null when it is off or not configured.
     */
    public function provider(): ?ExternalResultsProvider
    {
        if ($this->setting('enabled') !== true || ! $this->providers->has($this->providerName())) {
            return null;
        }

        $provider = $this->providers->make($this->providerName());

        return $provider->isConfigured($this->providerSettings()) ? $provider : null;
    }

    /**
     * The configured provider name.
     */
    public function providerName(): string
    {
        $name = $this->setting('provider');

        return is_string($name) && $name !== '' ? $name : 'google';
    }

    /**
     * The provider's settings, `aether-search.external.providers.<name>`.
     *
     * @return array<array-key, mixed>
     */
    public function providerSettings(): array
    {
        $providers = $this->setting('providers');
        $settings = is_array($providers) ? ($providers[$this->providerName()] ?? null) : null;

        return is_array($settings) ? $settings : [];
    }

    /**
     * The `?scope=` value that selects the scope.
     */
    public function value(): string
    {
        $value = $this->setting('value');

        return is_string($value) && $value !== '' ? $value : 'google';
    }

    /**
     * The scope's label.
     */
    public function label(): string
    {
        $label = $this->setting('label');

        return is_string($label) && $label !== '' ? $label : 'External results';
    }

    /**
     * One entry of the `external` setting.
     *
     * @param  string  $key  The entry's key.
     */
    private function setting(string $key): mixed
    {
        $external = config('aether-search.external');

        return is_array($external) ? ($external[$key] ?? null) : null;
    }
}
