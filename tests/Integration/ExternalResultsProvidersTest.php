<?php

declare(strict_types=1);

use UAMSWeb\Aether\Search\Contracts\ExternalResultsProvider;
use UAMSWeb\Aether\Search\External\ExternalResultsProviders;
use UAMSWeb\Aether\Search\External\GoogleResultsProvider;
use UAMSWeb\Aether\Search\Tests\Support\StubResultsProvider;

/**
 * The provider registry: `google` is registered out of the box, a host adds others by name, and
 * an unknown name is refused with the valid ones listed.
 *
 * @command  vendor/bin/pest tests/Integration/ExternalResultsProvidersTest.php
 */
it('registers only google out of the box', function (): void {
    // Build a fresh registry
    $providers = new ExternalResultsProviders;

    // Expect google, and nothing else, registered
    expect($providers->names())->toBe(['google'])
        ->and($providers->has('google'))->toBeTrue()
        ->and($providers->has('stub'))->toBeFalse();
});

it('builds the google provider from its name', function (): void {
    // Build the registered default
    $provider = (new ExternalResultsProviders)->make('google');

    // Expect the shipped class
    expect($provider)->toBeInstanceOf(GoogleResultsProvider::class);
});

it('lists a registered provider after the shipped one and builds it', function (): void {
    // Register a second provider
    $providers = new ExternalResultsProviders;
    $providers->register('stub', StubResultsProvider::class);

    // Expect both names in registration order, and the new one built from its class
    expect($providers->names())->toBe(['google', 'stub'])
        ->and($providers->has('stub'))->toBeTrue()
        ->and($providers->make('stub'))->toBeInstanceOf(StubResultsProvider::class);
});

it('replaces an earlier provider of the same name', function (): void {
    // Register another class under the shipped name
    $providers = new ExternalResultsProviders;
    $providers->register('google', StubResultsProvider::class);

    // Expect the replacement, with the name listed once
    expect($providers->make('google'))->toBeInstanceOf(StubResultsProvider::class)
        ->and($providers->names())->toBe(['google']);
});

it('refuses an unknown provider name and lists the valid ones', function (): void {
    // Register a second provider so the list has two names
    $providers = new ExternalResultsProviders;
    $providers->register('stub', StubResultsProvider::class);

    // Expect the exact message, with the name and the valid names comma-separated
    expect(fn (): ExternalResultsProvider => $providers->make('nope'))->toThrow(
        InvalidArgumentException::class,
        'Unknown aether-search external results provider "nope". Valid providers: google, stub.',
    );
});
