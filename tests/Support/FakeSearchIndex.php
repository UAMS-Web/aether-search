<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Search\Tests\Support;

/**
 * A search index that records what it is asked, and answers with fixed hits.
 *
 * It stands in for the index the suggestions route asks for, so a test sees which sites the route
 * searched, how many hits it asked for and which constraints it set, without building entries.
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
     * Every query the route searched, in order.
     *
     * @var list<string>
     */
    public array $queries = [];

    /**
     * How many times the route asked for the index to exist.
     */
    public int $ensured = 0;

    /**
     * The equality constraints the route set, as `[column, value]` pairs.
     *
     * @var list<array{0: mixed, 1: mixed}>
     */
    public array $wheres = [];

    /**
     * The limit the route set, or null when it set none.
     */
    public ?int $limit = null;

    /**
     * @param  mixed  $hits  What a search answers with: a list of hits, or anything else to stand in for a broken index.
     */
    public function __construct(private readonly mixed $hits = []) {}

    /**
     * Stand-in for creating the index when it is missing.
     */
    public function ensureExists(): self
    {
        $this->ensured++;

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
        $this->queries[] = $query;

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
     * Record an equality constraint.
     *
     * @param  mixed  $column  The field constrained.
     * @param  mixed  $value  The value required.
     */
    public function where(mixed $column = null, mixed $value = null): self
    {
        $this->wheres[] = [$column, $value];

        return $this;
    }

    /**
     * Record the limit.
     *
     * @param  int  $limit  How many hits to return at most.
     */
    public function limit(int $limit = 0): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * The hits.
     */
    public function get(): mixed
    {
        return $this->hits;
    }
}
