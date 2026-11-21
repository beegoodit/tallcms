<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Feature;

use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use TallCms\Cms\Rules\UniqueTranslatableSlug;
use TallCms\Cms\Support\TranslatableJson;
use TallCms\Cms\Tests\TestCase;

/**
 * Lookups of one language's value in a translatable JSON column. The language
 * can come from the browser (the editor's active locale), so it must never
 * become part of the SQL text.
 */
class TranslatableJsonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Closes the JSON path and makes the condition true for every row on
     * drivers that paste the locale into the statement.
     */
    private const CRAFTED_LOCALE = "en') IS NOT NULL OR ('1' = '1";

    protected function setUp(): void
    {
        parent::setUp();

        config(['tallcms.i18n.locales' => [
            'en' => ['label' => 'English', 'native' => 'English'],
            'pt_BR' => ['label' => 'Portuguese (Brazil)', 'native' => 'Português'],
        ]]);

        $this->insertPage(['en' => 'about', 'pt_br' => 'sobre']);
    }

    public function test_it_finds_the_value_of_a_language(): void
    {
        $this->assertSame(1, $this->pagesWhere('en', 'about')->count());
        $this->assertSame(1, $this->pagesWhere('pt_br', 'sobre')->count());
        $this->assertSame(0, $this->pagesWhere('en', 'sobre')->count());
    }

    public function test_it_finds_text_inside_the_value_of_a_language(): void
    {
        $contains = fn (string $locale, string $term): int => TranslatableJson::whereLocaleContains(DB::table('tallcms_pages'), 'slug', $locale, $term)->count();

        $this->assertSame(1, $contains('en', 'bou'));
        $this->assertSame(0, $contains('en', 'sobre'));
        $this->assertSame(0, $contains(self::CRAFTED_LOCALE, 'bou'));
    }

    public function test_the_language_is_bound_not_written_into_the_statement(): void
    {
        $sql = $this->pagesWhere('pt_br', 'sobre')->toSql();

        $this->assertStringNotContainsString('pt_br', $sql);
    }

    public function test_a_crafted_language_matches_nothing(): void
    {
        $query = $this->pagesWhere(self::CRAFTED_LOCALE, 'no-such-slug');

        $this->assertSame(0, $query->count());
        $this->assertStringNotContainsString('IS NOT NULL', $query->toSql());
    }

    public function test_the_slug_rule_reports_a_duplicate_in_a_configured_language(): void
    {
        $this->assertNotNull($this->ruleFailure('en', 'about'));
        $this->assertNull($this->ruleFailure('en', 'contact'));
        $this->assertNotNull($this->ruleFailure('pt_BR', 'sobre'));
    }

    public function test_the_slug_rule_refuses_a_language_that_is_not_configured_without_querying(): void
    {
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'tallcms_pages')) {
                $queries++;
            }
        });

        $failure = $this->ruleFailure(self::CRAFTED_LOCALE, 'no-such-slug');

        $this->assertNotNull($failure);
        $this->assertStringNotContainsString('already used', $failure);
        $this->assertSame(0, $queries);
        $this->assertNotNull($this->ruleFailure('fr', 'about'), 'A well-formed but unconfigured language is refused too.');
    }

    public function test_no_other_code_writes_a_language_into_a_json_path(): void
    {
        $offenders = [];
        $source = realpath(__DIR__.'/../../src');

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'TranslatableJson.php'
                && preg_match('/JSON_EXTRACT\(/i', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace($source.'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Use TranslatableJson::whereLocale() instead of a hand-written JSON path.');
    }

    private function pagesWhere(string $locale, string $slug): \Illuminate\Database\Query\Builder
    {
        return TranslatableJson::whereLocale(DB::table('tallcms_pages'), 'slug', $locale, $slug);
    }

    private function ruleFailure(string $locale, string $slug): ?string
    {
        $message = null;
        $fail = Closure::fromCallable(function (string $text) use (&$message): void {
            $message = $text;
        });

        (new UniqueTranslatableSlug('tallcms_pages', 'slug', $locale))->validate('slug', $slug, $fail);

        return $message;
    }

    /**
     * @param  array<string, string>  $slugs
     */
    private function insertPage(array $slugs): void
    {
        DB::table('tallcms_pages')->insert([
            'title' => json_encode($slugs),
            'slug' => json_encode($slugs),
            'status' => 'published',
            'is_homepage' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
