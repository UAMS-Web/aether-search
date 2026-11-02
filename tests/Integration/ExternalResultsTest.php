<?php

declare(strict_types=1);

use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;
use UAMSWeb\Aether\Search\External\ExternalResults;
use UAMSWeb\Aether\Search\External\ExternalResultsProviders;
use UAMSWeb\Aether\Search\ServiceProvider;
use UAMSWeb\Aether\Search\Tests\Support\RecordingAllowance;
use UAMSWeb\Aether\Search\Tests\Support\SearchFixtures;
use UAMSWeb\Aether\Search\Tests\Support\StubResultsProvider;

/**
 * The external results scope: off, on without a provider setting, on with one, and the hook.
 *
 * @command  vendor/bin/pest tests/Integration/ExternalResultsTest.php
 */

/**
 * Turn the external scope on with Google as the provider.
 *
 * @param  string|null  $engine  The search engine ID, or null for none.
 */
function googleScope(?string $engine): void
{
    config([
        'aether-search.external.enabled' => true,
        'aether-search.external.label' => 'All UAMS websites',
        'aether-search.external.providers.google.engine_id' => $engine,
    ]);
}

/**
 * Bind a recording allowance hook.
 *
 * @return RecordingAllowance The hook, to inspect after the request.
 */
function recordHook(): RecordingAllowance
{
    $hook = new RecordingAllowance;
    app()->instance(ResponseAllowance::class, $hook);

    return $hook;
}

it('offers no external scope while it is disabled, even with an engine ID', function (): void {
    // Set an engine ID but leave the scope off
    SearchFixtures::indexOnePage();
    config(['aether-search.external.label' => 'All UAMS websites', 'aether-search.external.providers.google.engine_id' => 'abc123']);

    // Ask for the external scope with a query
    $html = $this->get('/search?q=x&scope=google')->assertOk()->getContent();

    // Expect no choice, no Google markup and the site's own tabs
    expect($html)->not->toContain('All UAMS websites')
        ->and($html)->not->toContain('cse.google.com')
        ->and($html)->not->toContain('gcse-searchresults-only')
        ->and($html)->toContain('aria-label="Filter results by type"');
});

it('offers no external scope while it is enabled with no engine ID', function (): void {
    // Turn the scope on without an engine
    SearchFixtures::indexOnePage();
    googleScope(null);

    // Ask for the external scope with a query
    $html = $this->get('/search?q=x&scope=google')->assertOk()->getContent();

    // Expect it absent, as when it is off
    expect($html)->not->toContain('All UAMS websites')
        ->and($html)->not->toContain('cse.google.com')
        ->and($html)->not->toContain('gcse-searchresults-only')
        ->and($html)->toContain('aria-label="Filter results by type"');
});

it('renders the Google results in place when it is enabled with an engine ID', function (): void {
    // Turn the scope on with an engine
    googleScope('abc:123');

    // Ask for the external scope with a query
    $html = $this->get('/search?q=x&scope=google')->assertOk()->getContent();

    // Expect the choice, the script with the encoded engine, Google's class alone on its element
    // inside the site's wrapper, and no type tabs
    expect($html)->toContain('All UAMS websites')
        ->and($html)->toContain('src="https://cse.google.com/cse.js?cx=abc%3A123"')
        ->and($html)->toContain('<div class="site-search-google">')
        ->and($html)->toContain('<div class="gcse-searchresults-only" data-queryParameterName="q"></div>')
        ->and($html)->not->toContain('aria-label="Filter results by type"');
});

it('puts the site and the scope on the type-ahead', function (): void {
    // Request the page
    $html = $this->get('/search')->assertOk()->getContent();

    // Expect both for the script to send
    expect($html)->toContain('data-site="default"')->and($html)->toContain('data-scope=');
});

it('renders a second provider in place of Google', function (): void {
    // Register a stub provider and select it
    app(ExternalResultsProviders::class)->register('stub', StubResultsProvider::class);
    config(['aether-search.external' => [
        'enabled' => true,
        'provider' => 'stub',
        'value' => 'stub',
        'label' => 'Stub results',
        'providers' => ['stub' => ['token' => 't1']],
    ]]);

    // Ask for its scope with a query
    $html = $this->get('/search?q=x&scope=stub')->assertOk()->getContent();

    // Expect its view with its settings, and nothing of Google's
    expect($html)->toContain('Stub results')
        ->and($html)->toContain('<div class="stub-results" data-token="t1">stub results</div>')
        ->and($html)->not->toContain('cse.google.com');
});

it('fails at boot for a provider name nobody registered, naming the valid ones', function (): void {
    // Select an unknown provider, then boot the add-on's provider again
    config(['aether-search.external.provider' => 'nope']);

    // Expect the failure to name it and the valid ones
    expect(fn () => new ServiceProvider(app())->bootAddon())
        ->toThrow(InvalidArgumentException::class, 'Unknown aether-search external results provider "nope". Valid providers: google.');
});

it('boots with no engine ID set, with the scope off or on', function (): void {
    // Expect the settings read as unset, and no failure from booting with the scope off or on
    expect(config('aether-search.external.providers.google.engine_id'))->toBeNull()
        ->and(app(ExternalResults::class)->isActive())->toBeFalse();

    new ServiceProvider(app())->bootAddon();
    googleScope(null);
    new ServiceProvider(app())->bootAddon();

    expect(app(ExternalResults::class)->isActive())->toBeFalse();
});

it('ignores the external scope in the knowledge-base search', function (): void {
    // Turn the scope on and configure the scoped mode
    SearchFixtures::indexOnePage(['subtree']);
    googleScope('abc123');
    config(['aether-search.scoped' => ['index' => 'subtree', 'parameter' => 'root', 'query_scope' => 'subtree_articles'], 'aether-search.tabs' => ['default' => 'All results', 'subtree' => 'Subtree']]);
    $hook = recordHook();

    // Ask for the external scope on the scoped index
    $html = $this->get('/search?q=x&index=subtree&root=abc&scope=google')->assertOk()->getContent();

    // Expect no Google markup and no call to the hook
    expect($html)->not->toContain('cse.google.com')
        ->and($hook->calls)->toBe([]);
});

it('calls the hook only for the external scope with a query and a configured engine', function (): void {
    // Turn the scope on with an engine, and record the hook
    SearchFixtures::indexOnePage();
    googleScope('abc123');
    $hook = recordHook();

    // Ask for other pages first: no query, and another scope
    $this->get('/search?scope=google')->assertOk();
    $this->get('/search?q=x&scope=all')->assertOk();
    expect($hook->calls)->toBe([]);

    // Ask for the external scope with a query
    $this->get('/search?q=x&scope=google')->assertOk();

    // Expect one call, naming Google and what it needs
    expect($hook->calls)->toHaveCount(1)
        ->and($hook->calls[0]['provider'])->toBe('google')
        ->and($hook->calls[0]['needs']['script-src'])->toContain('https://cse.google.com');
});

it('never calls the hook for a disabled scope or one with no engine', function (): void {
    // Record the hook, and ask while the scope is off, then on with no engine
    SearchFixtures::indexOnePage();
    $hook = recordHook();
    config(['aether-search.external.providers.google.engine_id' => 'abc123']);
    $this->get('/search?q=x&scope=google')->assertOk();
    googleScope(null);
    $this->get('/search?q=x&scope=google')->assertOk();

    // Expect no calls
    expect($hook->calls)->toBe([]);
});
