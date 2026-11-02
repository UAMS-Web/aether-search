<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\External;

use UAMSWeb\Aether\Search\Contracts\ExternalResultsProvider;

/**
 * Google Programmable Search as the external results: its `cse.js` reads the query from the page's
 * own `q` parameter and renders the results in place.
 *
 * The one setting is `engine_id`, the search engine ID, read from `GOOGLE_SEARCH_ENGINE_ID` by the
 * package's config. Without it the scope is not offered.
 */
final class GoogleResultsProvider implements ExternalResultsProvider
{
    /**
     * {@inheritDoc}
     */
    public function isConfigured(array $settings): bool
    {
        return self::engineId($settings) !== '';
    }

    /**
     * {@inheritDoc}
     */
    public function view(): string
    {
        return 'aether-search::external/google';
    }

    /**
     * Google's `cse.js` is nonce-tagged by the view, then loads its own scripts and stylesheets,
     * which a nonce cannot cover, and its element calls `eval()` for each result. Its three inline
     * style elements are deliberately not asked for: results render without them.
     *
     * {@inheritDoc}
     */
    public function needs(): array
    {
        return [
            'script-src' => ["'unsafe-eval'", 'https://cse.google.com', 'https://www.google.com'],
            'style-src' => ['https://www.google.com'],
            'connect-src' => ['https://cse.google.com'],
            'frame-src' => ['https://cse.google.com'],
        ];
    }

    /**
     * The trimmed search engine ID from the provider's settings, empty when it is not set.
     *
     * @param  array<array-key, mixed>  $settings  The provider's settings.
     */
    public static function engineId(array $settings): string
    {
        $engine = $settings['engine_id'] ?? null;

        return is_string($engine) ? trim($engine) : '';
    }
}
