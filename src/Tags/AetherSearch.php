<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Tags;

use Illuminate\Support\Facades\View;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Sites\Site as SiteObject;
use Statamic\Support\Html;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * `{{ aether_search:* }}`: the search page's logic, kept out of its Antlers.
 *
 * It answers how widely the page looks (this site or every site), which index and type tabs are
 * active, the settings the shell reads, and which card and label a result gets. Values it returns
 * are fixed strings or come from configuration, and none echoes the request unescaped, so they
 * are safe in an href or an attribute.
 */
class AetherSearch extends Tags
{
    /**
     * The scope that searches only the current site.
     */
    public const string THIS_SITE = 'site';

    /**
     * The scope that searches every site.
     */
    public const string ALL_SITES = 'all';

    /**
     * Resolve the requested `?scope=`, falling back to the current site's default.
     *
     * A sub-site offers "This site" (the default) and "All of <root site's name>"; the root site
     * offers only the second, so its default searches every site. Anything other than a scope this
     * site offers (an unknown string, or an array from `?scope[]=x`) falls back, so a hand-edited
     * URL cannot fail.
     */
    public function active(): string
    {
        if ($this->onRootSite()) {
            return self::ALL_SITES;
        }

        return request()->query('scope') === self::ALL_SITES
            ? self::ALL_SITES
            : self::THIS_SITE;
    }

    /**
     * The search query, `?q=`, escaped as Statamic's `get` variable escapes it (so it is safe to
     * echo), or empty when it is absent or an array.
     *
     * An array (`?q[]=x`) is treated as absent: piping one through a modifier throws an uncaught
     * TypeError, a hard 500. Read from the live request, not the view cascade, which a long-lived
     * process (a queue worker, a test host) can hold stale.
     */
    public function query(): string
    {
        $value = request()->query('q');

        return is_string($value) ? Html::sanitize($value) : '';
    }

    /**
     * The `scope` query value for the `active` scope, empty when it is the default.
     */
    public function param(): string
    {
        return $this->paramFor($this->parameters()->get('active'));
    }

    /**
     * The current site's search page path, such as `/search` or `/health/search`.
     */
    public function path(): string
    {
        $url = $this->currentSite()->url();

        return rtrim(URL::makeRelative(is_string($url) ? $url : '/'), '/').'/search';
    }

    /**
     * The root site's name, as configured.
     */
    public function rootName(): string
    {
        $name = $this->rootSite()->name();

        return is_string($name) ? $name : '';
    }

    /**
     * The scopes the current site offers, each marked against the `active` scope.
     *
     * @return list<array{value: string, label: string, param: string, is_active: bool}>
     */
    public function options(): array
    {
        $active = $this->parameters()->get('active');

        $scopes = [self::ALL_SITES => 'All of '.$this->rootName()];

        if (! $this->onRootSite()) {
            $scopes = [self::THIS_SITE => 'This site'] + $scopes;
        }

        $options = [];
        foreach ($scopes as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
                'param' => $this->paramFor($value),
                'is_active' => $value === $active,
            ];
        }

        return $options;
    }

    /**
     * The index the page searches: the requested `?index=` when it is a configured tab, the scoped
     * index when it is requested with a scope root, otherwise `default`.
     *
     * A blank or hand-tampered value falls back to `default` rather than reaching `search:results`
     * with an unconfigured index, which throws.
     */
    public function activeIndex(): string
    {
        $requested = request()->query('index');

        if (! is_string($requested)) {
            return 'default';
        }

        $scopedIndex = $this->scoped('index');

        if ($scopedIndex !== '' && $requested === $scopedIndex) {
            return $this->scopeRoot() !== '' ? $scopedIndex : 'default';
        }

        return array_key_exists($requested, $this->tabLabels()) ? $requested : 'default';
    }

    /**
     * The scope root the scoped mode is rooted at, taken from its configured request parameter and
     * escaped for use in an attribute. Empty when the mode is off, the parameter is absent, or the
     * value is an array.
     */
    public function scopeRoot(): string
    {
        $parameter = $this->scoped('parameter');

        if ($parameter === '') {
            return '';
        }

        $value = request()->query($parameter);

        return is_string($value) ? e($value) : '';
    }

    /**
     * Whether the `index` is the scoped index.
     */
    public function isScoped(): bool
    {
        $scopedIndex = $this->scoped('index');

        return $scopedIndex !== '' && $this->parameters()->get('index') === $scopedIndex;
    }

    /**
     * The type tabs, each marked against the `active` index.
     *
     * @return list<array{handle: string, label: string, is_active: bool}>
     */
    public function tabs(): array
    {
        $active = $this->parameters()->get('active');

        $tabs = [];
        foreach ($this->tabLabels() as $handle => $label) {
            $tabs[] = ['handle' => $handle, 'label' => $label, 'is_active' => $handle === $active];
        }

        return $tabs;
    }

    /**
     * The settings the shell reads, with every partial resolved to a view that exists.
     *
     * A hard `{config:...}` read does not work inside a view, so the shell reads its settings here.
     *
     * @return array<string, string>
     */
    public function settings(): array
    {
        $partials = config('aether-search.partials');
        $partials = is_array($partials) ? $partials : [];

        $resolved = [];
        foreach (['article', 'entry_content', 'results_block', 'results_row', 'results_header', 'pagination'] as $key) {
            $name = $partials[$key] ?? null;
            $resolved['partial_'.$key] = is_string($name) && $name !== '' ? $name : $this->defaultPartial($key);
        }

        $always = config('aether-search.always_scope');
        $text = config('aether-search.text');
        $text = is_array($text) ? $text : [];

        return $resolved + [
            'always_scope' => is_string($always) ? $always : '',
            'form_label' => $this->text($text, 'form_label', 'Search'),
            'placeholder' => $this->text($text, 'placeholder', 'Type to search…'),
            'prompt_hint' => $this->text($text, 'prompt_hint', ''),
            'scoped_index' => $this->scoped('index'),
            'scoped_parameter' => $this->scoped('parameter'),
            'scoped_query_scope' => $this->scoped('query_scope'),
            'scoped_form_label' => $this->scoped('form_label'),
            'scoped_note' => $this->scoped('note'),
            'scoped_broaden_label' => $this->scoped('broaden_label'),
            'scoped_broaden_url' => $this->scoped('broaden_url'),
        ];
    }

    /**
     * The label for a result: the configured label for its `result_type`, else for its
     * `collection`, else empty.
     */
    public function label(): string
    {
        $labels = config('aether-search.labels');
        $labels = is_array($labels) ? $labels : [];

        foreach ([$this->parameters()->get('result_type'), $this->parameters()->get('collection')] as $type) {
            if (is_string($type) && is_string($labels[$type] ?? null)) {
                return $labels[$type];
            }
        }

        return '';
    }

    /**
     * The card partial for a result.
     *
     * A card named for the result's `result_type` wins, then one named for its `collection`, then
     * the generic card. Each is looked up through the view finder, so a card at
     * `resources/views/vendor/aether-search/cards/` in the host overrides the package's for that
     * type alone, and a type with no card falls to the generic one in the same render.
     */
    public function card(): string
    {
        foreach ([$this->parameters()->get('result_type'), $this->parameters()->get('collection')] as $type) {
            if (is_string($type) && preg_match('/^[a-z0-9_]+$/', $type) === 1 && View::exists('aether-search::cards._'.$type)) {
                return 'aether-search::cards/'.$type;
            }
        }

        return 'aether-search::cards/default';
    }

    /**
     * The package's own partial standing in for a setting left unset.
     */
    private function defaultPartial(string $key): string
    {
        return match ($key) {
            'results_header' => 'aether-search::frames/header',
            'pagination' => 'aether-search::frames/pagination',
            default => 'aether-search::frames/passthrough',
        };
    }

    /**
     * A string from the `text` setting, falling back to the default.
     *
     * @param  array<array-key, mixed>  $text  The `text` setting.
     */
    private function text(array $text, string $key, string $default): string
    {
        return is_string($text[$key] ?? null) ? $text[$key] : $default;
    }

    /**
     * A string from the `scoped` setting, empty when it is not set.
     */
    private function scoped(string $key): string
    {
        $scoped = config('aether-search.scoped');
        $value = is_array($scoped) ? ($scoped[$key] ?? null) : null;

        return is_string($value) ? $value : '';
    }

    /**
     * The configured tabs as handle => label, with `default` always present and first.
     *
     * @return array<string, string>
     */
    private function tabLabels(): array
    {
        $tabs = config('aether-search.tabs');
        $labels = [];

        foreach (is_array($tabs) ? $tabs : [] as $handle => $label) {
            if (is_string($label)) {
                $labels[(string) $handle] = $label;
            }
        }

        return ['default' => $labels['default'] ?? 'All results'] + $labels;
    }

    /**
     * The `scope` query value that selects a scope on the current site.
     */
    private function paramFor(mixed $scope): string
    {
        return $scope === self::ALL_SITES && ! $this->onRootSite() ? self::ALL_SITES : '';
    }

    /**
     * Whether the reader is on the root site.
     */
    private function onRootSite(): bool
    {
        return $this->currentSite()->handle() === $this->rootSite()->handle();
    }

    /**
     * The site the reader is on.
     */
    private function currentSite(): SiteObject
    {
        /** @var SiteObject $site */
        $site = Site::current();

        return $site;
    }

    /**
     * The root site.
     */
    private function rootSite(): SiteObject
    {
        /** @var SiteObject $site */
        $site = Site::default();

        return $site;
    }

    /**
     * The tag's parameters.
     */
    private function parameters(): Parameters
    {
        /** @var Parameters $parameters */
        $parameters = $this->params;

        return $parameters;
    }
}
