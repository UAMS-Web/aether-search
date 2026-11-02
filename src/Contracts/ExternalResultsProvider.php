<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Contracts;

use UAMSWeb\Aether\Search\External\ExternalResultsProviders;

/**
 * An external search that supplies the results of the "external results" scope in place of the
 * site's own.
 *
 * A provider is registered by name with {@see ExternalResultsProviders}
 * and selected by `aether-search.external.provider`. Its settings live under
 * `aether-search.external.providers.<name>` and are handed to it as an array.
 */
interface ExternalResultsProvider
{
    /**
     * Whether the settings are enough for the provider to supply results. A scope whose provider is
     * not configured is not offered, and `?scope=` naming it falls back to the site's own search.
     *
     * @param  array<array-key, mixed>  $settings  The provider's settings.
     * @return bool True when results can be supplied.
     */
    public function isConfigured(array $settings): bool;

    /**
     * The partial that renders the provider's results. It is given `settings`, the provider's
     * settings. It renders only on a request that carries a query.
     *
     * @return string A partial name, such as `aether-search::external/google`.
     */
    public function view(): string;

    /**
     * What the response needs for the provider's results to load, by content security policy
     * directive. The package never applies this itself: it hands it to the bound
     * {@see ResponseAllowance} when the provider's results are about to render.
     *
     * @return array<string, list<string>> Source expressions by directive, such as `script-src`.
     */
    public function needs(): array;
}
