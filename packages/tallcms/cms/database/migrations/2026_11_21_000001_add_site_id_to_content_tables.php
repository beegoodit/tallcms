<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every site-owned table carries site_id, with or without multisite.
 *
 * Every installation has at least one site (core tallcms_sites table), so a
 * single-site install simply has one value everywhere. The multisite plugin
 * used to add these columns itself; on installs where it already did, each
 * table is skipped, so both paths end up with the same schema.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'tallcms_pages',
        'tallcms_menus',
        'tallcms_posts',
        'tallcms_categories',
        'tallcms_media',
        'tallcms_media_collections',
        'tallcms_comments',
        'tallcms_revisions',
        'tallcms_preview_tokens',
        'tallcms_contact_submissions',
        'personal_access_tokens',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'site_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('site_id')->nullable()->after('id')
                    ->constrained('tallcms_sites')->nullOnDelete();
                $table->index('site_id');
            });
        }
    }

    /**
     * Never drops: on multisite installs the columns predate this migration,
     * and it cannot tell afterwards which ones it added.
     */
    public function down(): void {}
};
