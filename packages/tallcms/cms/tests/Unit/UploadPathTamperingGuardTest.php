<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Filament passes a string in a FileUpload's state through as an already
 * stored path unless preventFilePathTampering() is set, and the browser
 * controls that state. Where a stored path leads further than to a reference
 * (a media record whose deletion or replacement deletes the file, a package
 * that gets installed), the field must refuse paths it did not store itself.
 *
 * The package's tests have no panel to drive these forms through Livewire,
 * so this guards the declaration; hosts test the behaviour.
 */
class UploadPathTamperingGuardTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedFields(): array
    {
        return [
            'media library upload' => ['Filament/Resources/TallcmsMedia/Schemas/TallcmsMediaForm.php', 'upload'],
            'media library replacement' => ['Filament/Resources/TallcmsMedia/Schemas/TallcmsMediaForm.php', 'new_file'],
            'page featured image' => ['Filament/Resources/CmsPages/Schemas/CmsPageForm.php', 'featured_image'],
            'post featured image' => ['Filament/Resources/CmsPosts/Schemas/CmsPostForm.php', 'featured_image'],
            'theme package' => ['Filament/Pages/ThemeManager.php', 'theme_zip'],
            'plugin package' => ['Filament/Pages/PluginManager.php', 'plugin_zip'],
        ];
    }

    #[DataProvider('protectedFields')]
    public function test_the_field_refuses_stored_paths_it_did_not_store(string $file, string $field): void
    {
        $source = file_get_contents(__DIR__.'/../../src/'.$file);
        $start = strpos($source, "FileUpload::make('{$field}')");

        $this->assertNotFalse($start, "FileUpload '{$field}' not found in {$file}.");

        // The field's own fluent chain: up to the next component.
        $rest = substr($source, $start + 1);
        $end = preg_match('/\b[A-Z][A-Za-z]+::make\(/', $rest, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : strlen($rest);

        $this->assertStringContainsString('->preventFilePathTampering()', substr($rest, 0, $end), "FileUpload '{$field}' in {$file} accepts stored paths from the client.");
    }
}
