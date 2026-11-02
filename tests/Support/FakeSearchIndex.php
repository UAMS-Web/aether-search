<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Tests\Support;

/**
 * A search index that records the site constraint it is given, and answers with fixed hits.
 *
 * It stands in for the index the suggestions route asks for, so a test sees which sites the route
 * searched without building entries in several sites.
 */
final class FakeSearchIndex
{
    /**
     * The handles the `site` constraint was given, or null when the route set none.
     *
     * @var list<string>|null
     */
    public ?array $sites = null;

    /**
     * Whether the route searched at all.
     */
    public bool $searched = false;

    /**
     * @param  list<object>  $hits  What a search answers with.
     */
    public function __construct(private readonly array $hits = []) {}

    /**
     * Stand-in for creating the index when it is missing.
     */
    public function ensureExists(): self
    {
        return $this;
    }

    /**
     * Start a search.
     *
     * @param  string  $query  The query.
     */
    public function search(string $query): self
    {
        $this->searched = $query !== '';

        return $this;
    }

    /**
     * Record a constraint to a list of values, keeping the site's.
     *
     * @param  string  $column  The field constrained.
     * @param  list<string>  $values  The values allowed.
     */
    public function whereIn(string $column, array $values): self
    {
        if ($column === 'site') {
            $this->sites = $values;
        }

        return $this;
    }

    /**
     * Accept an equality constraint.
     *
     * @param  string  $column  The field constrained.
     * @param  mixed  $value  The value required.
     */
    public function where(string $column, mixed $value): self
    {
        return $this;
    }

    /**
     * Accept a limit.
     *
     * @param  int  $limit  The most hits to return.
     */
    public function limit(int $limit): self
    {
        return $this;
    }

    /**
     * The hits.
     *
     * @return list<object>
     */
    public function get(): array
    {
        return $this->hits;
    }
}
