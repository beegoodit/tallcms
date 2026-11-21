<?php

declare(strict_types=1);

namespace TallCms\Cms\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Database-agnostic lookups in translatable JSON columns
 * ({"en": "about", "de": "ueber-uns"}).
 *
 * The language is always passed to the database as a bound value: it can
 * come from the browser (the editor's active locale), and SQLite and MySQL
 * address a JSON key through a path string that used to be pasted into the
 * statement.
 */
class TranslatableJson
{
    /**
     * Limit the query to rows whose value for the language equals $value.
     * Anything that is not shaped like a language code matches no row.
     *
     * @template TBuilder of EloquentBuilder|QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function whereLocale(EloquentBuilder|QueryBuilder $query, string $column, string $locale, mixed $value): EloquentBuilder|QueryBuilder
    {
        if (! static::isLocaleCode($locale)) {
            return $query->whereRaw('1 = 0');
        }

        $column = $query->getGrammar()->wrap($column);
        $path = '$."'.$locale.'"';

        return match ($query->getConnection()->getDriverName()) {
            'pgsql' => $query->whereRaw("{$column}::jsonb ->> ? = ?", [$locale, $value]),
            'sqlite' => $query->whereRaw("JSON_EXTRACT({$column}, ?) = ?", [$path, $value]),
            default => $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$column}, ?)) = ?", [$path, $value]),
        };
    }

    /**
     * Limit the query to rows whose value for the language contains $term
     * (case-insensitive where the database offers it).
     *
     * @template TBuilder of EloquentBuilder|QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function whereLocaleContains(EloquentBuilder|QueryBuilder $query, string $column, string $locale, string $term): EloquentBuilder|QueryBuilder
    {
        if (! static::isLocaleCode($locale)) {
            return $query->whereRaw('1 = 0');
        }

        $column = $query->getGrammar()->wrap($column);
        $path = '$."'.$locale.'"';
        $like = '%'.$term.'%';

        return match ($query->getConnection()->getDriverName()) {
            'pgsql' => $query->whereRaw("{$column}::jsonb ->> ? ILIKE ?", [$locale, $like]),
            'sqlite' => $query->whereRaw("JSON_EXTRACT({$column}, ?) LIKE ?", [$path, $like]),
            default => $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$column}, ?)) LIKE ?", [$path, $like]),
        };
    }

    /**
     * Two or three letters, optionally followed by region or script parts
     * (en, pt_BR, zh-Hans-CN). Nothing that could end a JSON path or a string.
     */
    public static function isLocaleCode(string $locale): bool
    {
        return preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale) === 1;
    }
}
