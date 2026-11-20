<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu locations are unique per site (header, footer, ... once per site).
 *
 * Same index names as the multisite plugin's migration, so installs where it
 * already ran are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tallcms_menus') || ! Schema::hasColumn('tallcms_menus', 'site_id')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('tallcms_menus'))->pluck('name')->all();

        Schema::table('tallcms_menus', function (Blueprint $table) use ($indexes): void {
            if (in_array('tallcms_menus_location_unique', $indexes, true)) {
                $table->dropUnique('tallcms_menus_location_unique');
            }

            if (! in_array('tallcms_menus_site_location_unique', $indexes, true)) {
                $table->unique(['site_id', 'location'], 'tallcms_menus_site_location_unique');
            }
        });
    }

    /**
     * Never restores the global unique: with several sites it would fail.
     */
    public function down(): void {}
};
