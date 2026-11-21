<?php

declare(strict_types=1);

namespace TallCms\Cms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Adjusts the queries Filament builds for a many-to-many Select.
 *
 * For its option lookups Filament left-joins the pivot table without
 * constraints and adds DISTINCT to remove the duplicates that join produces
 * (RelationshipJoiner::prepareQueryForNoConstraints()). PostgreSQL cannot
 * compare `json` columns for DISTINCT, so a related model with translatable
 * JSON columns fails there ("could not identify an equality operator for
 * type json").
 */
class RelationshipSelectQuery
{
    /**
     * Take the unconstrained pivot join and the DISTINCT it needs out again:
     * without that join there are no duplicates. Only when both are there,
     * so the query that loads a record's own related rows (inner join on the
     * pivot, constrained to the record, no DISTINCT) is left alone, and so
     * are joins on other tables.
     */
    public static function withoutPivotDistinct(Builder $query, string $pivotTable): Builder
    {
        $base = $query->getQuery();

        if (! $base->distinct) {
            return $query;
        }

        $isUnconstrainedPivotJoin = fn (mixed $join): bool => $join instanceof JoinClause
            && $join->table === $pivotTable
            && $join->type === 'left';

        $joins = $base->joins ?? [];
        $remaining = array_values(array_filter($joins, fn (mixed $join): bool => ! $isUnconstrainedPivotJoin($join)));

        if (count($remaining) === count($joins)) {
            return $query;
        }

        $base->joins = $remaining === [] ? null : $remaining;
        $base->distinct = false;

        return $query;
    }
}
