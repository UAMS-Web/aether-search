<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use UAMSWeb\Aether\Search\Http\Controllers\SuggestionsController;
use UAMSWeb\Aether\Search\ServiceProvider;

// The search page at `/search`, and under each additional site's URL prefix, because the current
// site comes from the URL and "This site" needs a URL to mean it. Being a routeless view (no
// entry), it has no breadcrumb chain, so `breadcrumb_leaf` supplies the trailing crumb.
$routeData = config('aether-search.route_data');
$routeData = \is_array($routeData) ? $routeData : [];
$layout = config('aether-search.layout');

if (\is_string($layout) && $layout !== '') {
    $routeData['layout'] = $layout;
}

Route::statamic('/search', 'aether-search::index', $routeData);

Site::all()
    ->map(fn ($site) => URL::makeRelative($site->url()))
    ->reject(fn ($prefix) => $prefix === '/')
    ->unique()
    ->each(fn ($prefix) => Route::statamic($prefix.'/search', 'aether-search::index', $routeData));

// Type-ahead suggestions for the search input: a short JSON list, same site and status rules as
// the page. The `/!/` prefix keeps it clear of entry URIs.
Route::get('/!/search/suggestions', SuggestionsController::class)
    ->middleware('throttle:'.ServiceProvider::SUGGESTIONS_LIMITER)
    ->name('site-search.suggestions');
