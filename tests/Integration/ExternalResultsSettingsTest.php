<?php

declare(strict_types=1);

use UAMSWeb\Aether\Search\External\ExternalResults;

/**
 * The external scope's three string settings (`provider`, `value`, `label`): a usable string is
 * returned as written, and a missing, empty or non-string entry falls back to the default.
 *
 * Every default is checked against an empty string and a non-string, because the guard that
 * falls back is `is_string($x) && $x !== ''`: dropping either half changes the result for exactly
 * one of those two inputs.
 *
 * @command  vendor/bin/pest tests/Integration/ExternalResultsSettingsTest.php
 */

/**
 * The scope's settings object, resolved from the container.
 *
 * @return ExternalResults The object under test.
 */
function externalResults(): ExternalResults
{
    return app(ExternalResults::class);
}

dataset('external string settings', [
    'provider' => ['provider', 'providerName', 'google'],
    'value' => ['value', 'value', 'google'],
    'label' => ['label', 'label', 'External results'],
]);

it('returns a configured string setting as written', function (string $key, string $method): void {
    // Configure the setting with a name the defaults never use
    config(["aether-search.external.{$key}" => 'custom']);

    // Expect the configured string, not the default
    expect(externalResults()->{$method}())->toBe('custom');
})->with('external string settings');

it('falls back to the default when a string setting is unusable', function (string $key, string $method, string $default, mixed $unusable): void {
    // Configure the setting with a value that is not a usable string
    config(["aether-search.external.{$key}" => $unusable]);

    // Expect the default
    expect(externalResults()->{$method}())->toBe($default);
})->with(function (): Generator {
    foreach (['provider' => ['providerName', 'google'], 'value' => ['value', 'google'], 'label' => ['label', 'External results']] as $key => [$method, $default]) {
        foreach (['an empty string' => '', 'an integer' => 123, 'an array' => ['x'], 'null' => null] as $name => $unusable) {
            yield "{$key} as {$name}" => [$key, $method, $default, $unusable];
        }
    }
});

it('falls back to the defaults when the whole external setting is not an array', function (): void {
    // Replace the setting with a scalar
    config(['aether-search.external' => 'on']);

    // Expect every default and an off scope
    expect(externalResults()->providerName())->toBe('google')
        ->and(externalResults()->value())->toBe('google')
        ->and(externalResults()->label())->toBe('External results')
        ->and(externalResults()->providerSettings())->toBe([])
        ->and(externalResults()->isActive())->toBeFalse();
});

it('reads the settings of the provider the setting names', function (): void {
    // Configure two providers and select the second
    config([
        'aether-search.external.provider' => 'second',
        'aether-search.external.providers' => ['google' => ['engine_id' => 'g'], 'second' => ['key' => 'k']],
    ]);

    // Expect the selected provider's settings only
    expect(externalResults()->providerSettings())->toBe(['key' => 'k']);
});

it('reads no provider settings when the providers entry is not an array or lacks the provider', function (mixed $providers): void {
    // Configure an unusable providers entry
    config(['aether-search.external.providers' => $providers]);

    // Expect no settings
    expect(externalResults()->providerSettings())->toBe([]);
})->with([
    'a string' => ['x'],
    'no entry for the provider' => [['other' => ['a' => 1]]],
    'a non-array entry for the provider' => [['google' => 'x']],
]);
