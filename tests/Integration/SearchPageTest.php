<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use UAMSWeb\Aether\Search\Tests\Support\SearchFixtures;

/**
 * The search page renders in the add-on's own test host, with no UAMS collection, theme partial or
 * application class present.
 *
 * @command  vendor/bin/pest tests/Integration/SearchPageTest.php
 */
it('renders the shell with no settings and no host partial', function (): void {
    // Request the page with no query
    $response = $this->get('/search');

    // Expect the form, the single default tab and the prompt, inside the layout alone
    $response->assertOk()
        ->assertSee('<form action="/search" method="get" role="search"', false)
        ->assertSee('All results')
        ->assertSee('Enter a search term above to get started.')
        ->assertDontSee('host-article', false);
});

it('submits as a plain GET with no script', function (): void {
    // Request the page
    $html = $this->get('/search')->assertOk()->getContent();

    // Expect a form that works as a plain GET: an action, a method, a named field and a button
    expect($html)->toContain('action="/search"')
        ->and($html)->toContain('method="get"')
        ->and($html)->toContain('name="q"')
        ->and($html)->toContain('<button type="submit"')
        // Expect the script to be an enhancement only: the field is an ordinary search input
        ->and($html)->toContain('type="search"')
        ->and($html)->not->toContain('<script');
});

it('renders a query in the results heading, escaped, with the empty state', function (): void {
    // Create the default index
    SearchFixtures::indexOnePage();

    // Request the page with a query that carries markup
    $response = $this->get('/search?q='.urlencode('<b>x</b>'));

    // Expect the query escaped in the heading and the empty state shown
    $response->assertOk()
        ->assertSee('Search results for &ldquo;&lt;b&gt;x&lt;/b&gt;&rdquo;', false)
        ->assertSee('No results found.')
        ->assertDontSee('<b>x</b>', false);
});

it('wraps the page in the host partial a setting names', function (): void {
    // Name a host partial for the outer frame
    config(['aether-search.partials.article' => 'host.article']);

    // Request the page
    $response = $this->get('/search');

    // Expect the host's frame around the form
    $response->assertOk()->assertSee('<div class="host-article">', false);
});

it('falls back to the default index when the requested one is not a configured tab', function (): void {
    // Create the default index
    SearchFixtures::indexOnePage();

    // Request the page for an index that is not a configured tab
    $response = $this->get('/search?q=x&index=not_a_tab');

    // Expect the default tab active and the tampered value never reflected
    $response->assertOk()
        ->assertSee('name="index" value="default"', false)
        ->assertDontSee('not_a_tab');
});

it('lists the configured tabs and marks the active one', function (): void {
    // Configure two tabs over two indexes
    SearchFixtures::indexOnePage(['people']);
    config(['aether-search.tabs' => ['default' => 'All results', 'people' => 'People']]);

    // Request the page on the second tab
    $response = $this->get('/search?q=x&index=people');

    // Expect both tabs, with the second current
    $response->assertOk()
        ->assertSee('index=default', false)
        ->assertSeeInOrder(['class="nav-link active"', 'aria-current="page"', 'People'], false);
});

it('has no scoped mode and no scope name until one is configured', function (): void {
    // Create the default index, then request the page asking for a scoped index nobody configured
    SearchFixtures::indexOnePage();
    $response = $this->get('/search?q=x&index=knowledge_bases&kb_scope=abc');

    // Expect the ordinary page: tabs shown, no hidden scope field, no scope note
    $response->assertOk()
        ->assertSee('aria-label="Filter results by type"', false)
        ->assertDontSee('kb_scope')
        ->assertDontSee('Searches here are limited');
});

it('runs the scoped mode from settings alone', function (): void {
    // Configure the scoped mode over its own index
    SearchFixtures::indexOnePage(['subtree']);
    config(['aether-search.scoped' => [
        'index' => 'subtree',
        'parameter' => 'root',
        'query_scope' => 'subtree_articles',
        'form_label' => 'Search this guide',
        'note' => 'Searches here are limited to this guide.',
        'broaden_label' => 'Search everything',
        'broaden_url' => '/search',
    ], 'aether-search.tabs' => ['default' => 'All results', 'subtree' => 'Subtree']]);

    // Request the scoped index with a scope root
    $response = $this->get('/search?q=x&index=subtree&root=abc');

    // Expect the tabs replaced by hidden scope fields, the configured wording and the broaden link
    $response->assertOk()
        ->assertSee('Search this guide')
        ->assertSee('name="index" value="subtree"', false)
        ->assertSee('name="root" value="abc"', false)
        ->assertSee('Searches here are limited to this guide.')
        ->assertSee('<a href="/search?q=x">Search everything</a>', false)
        ->assertDontSee('aria-label="Filter results by type"', false);
});

it('falls back to the default index when the scoped index has no scope root', function (): void {
    // Configure the scoped mode over its own index
    SearchFixtures::indexOnePage(['subtree']);
    config(['aether-search.scoped' => ['index' => 'subtree', 'parameter' => 'root'], 'aether-search.tabs' => ['default' => 'All results', 'subtree' => 'Subtree']]);

    // Request the scoped index without a root
    $response = $this->get('/search?q=x&index=subtree');

    // Expect the ordinary page on the default index
    $response->assertOk()->assertSee('name="index" value="default"', false)->assertDontSee('name="root"', false);
});

it('is reached ahead of a wildcard route the host registers', function (): void {
    // Register a wildcard route like the application's `{entry}/{section}` after the add-on booted
    Route::get('/{entry}', fn (string $entry): string => 'host wildcard '.$entry);

    // Request the search page
    $response = $this->get('/search');

    // Expect the add-on's page, not the host's wildcard
    $response->assertOk()->assertSee('<form action="/search"', false)->assertDontSee('host wildcard');
});
