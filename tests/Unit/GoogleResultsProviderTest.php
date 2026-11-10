<?php

declare(strict_types=1);

use UAMSWeb\Aether\Search\External\GoogleResultsProvider;

/**
 * The Google provider: the engine ID it needs, the view it renders and the content-security-policy
 * sources that view needs. Pure: it reads only the settings array it is given.
 *
 * @command  vendor/bin/pest tests/Unit/GoogleResultsProviderTest.php
 */
it('is configured only when the settings hold a non-blank engine ID', function (array $settings, bool $expected): void {
    // Ask whether these settings configure the provider
    $configured = (new GoogleResultsProvider)->isConfigured($settings);

    // Expect it configured exactly when the engine ID has content once trimmed
    expect($configured)->toBe($expected);
})->with([
    'an engine ID' => [['engine_id' => 'abc:123'], true],
    'an engine ID with surrounding whitespace' => [['engine_id' => '  abc  '], true],
    'a blank engine ID' => [['engine_id' => '   '], false],
    'an empty engine ID' => [['engine_id' => ''], false],
    'a non-string engine ID' => [['engine_id' => 123], false],
    'no engine ID' => [[], false],
]);

it('reads the engine ID trimmed, or empty when it is not usable', function (array $settings, string $expected): void {
    // Read the engine ID
    $engine = GoogleResultsProvider::engineId($settings);

    // Expect the trimmed string, or empty
    expect($engine)->toBe($expected);
})->with([
    'trimmed' => [['engine_id' => "\t abc:123 \n"], 'abc:123'],
    'blank' => [['engine_id' => "  \n"], ''],
    'a non-string' => [['engine_id' => ['abc']], ''],
    'absent' => [[], ''],
]);

it('renders the Google view', function (): void {
    // Expect the namespaced view
    expect((new GoogleResultsProvider)->view())->toBe('aether-search::external/google');
});

it('asks for exactly the sources the Google element loads', function (): void {
    // Read what the provider needs
    $needs = (new GoogleResultsProvider)->needs();

    // Expect each directive with its exact sources, in order
    expect($needs)->toBe([
        'script-src' => ["'unsafe-eval'", 'https://cse.google.com', 'https://www.google.com'],
        'style-src' => ['https://www.google.com'],
        'connect-src' => ['https://cse.google.com'],
        'frame-src' => ['https://cse.google.com'],
    ]);
});
