<?php

declare(strict_types=1);

namespace TallCms\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use TallCms\Cms\Contracts\SiteContext;
use TallCms\Cms\Models\Site;

/**
 * Site-owned content: site_id is filled on create from the SiteContext.
 *
 * Child records (comments, revisions, preview tokens) override
 * siteIdForNewRecord() to take their parent's site instead.
 *
 * Models listed in config('tallcms.sites.unassigned_models') keep site_id
 * empty on create (the multisite plugin's user-owned content, until the
 * content migration assigns it).
 */
trait BelongsToSite
{
    public static function bootBelongsToSite(): void
    {
        static::creating(function (Model $model): void {
            $model->assignSiteOnCreate();
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Whether a record created outside the console must end up with a site.
     */
    public function requiresSite(): bool
    {
        return ! $this->isUnassignedOnCreate();
    }

    protected function siteIdForNewRecord(): ?int
    {
        if ($this->isUnassignedOnCreate()) {
            return null;
        }

        return app(SiteContext::class)->currentSiteId();
    }

    protected function isUnassignedOnCreate(): bool
    {
        foreach (config('tallcms.sites.unassigned_models', []) as $class) {
            if ($this instanceof $class) {
                return true;
            }
        }

        return false;
    }

    protected function assignSiteOnCreate(): void
    {
        if ($this->site_id !== null || ! Schema::hasColumn($this->getTable(), 'site_id')) {
            return;
        }

        $this->site_id = $this->siteIdForNewRecord();

        if ($this->site_id === null && $this->requiresSite() && ! app()->runningInConsole()) {
            throw new RuntimeException('Cannot create '.class_basename($this).' without a site context.');
        }
    }

    /**
     * The site of a parent record, read without global scopes: the parent
     * may belong to a site other than the one the request is scoped to.
     */
    protected function siteIdOfParent(?string $type, mixed $id): ?int
    {
        $class = $this->parentClass($type);

        if ($class === null || $id === null) {
            return null;
        }

        $siteId = $class::withoutGlobalScopes()->whereKey($id)->value('site_id');

        return $siteId !== null ? (int) $siteId : null;
    }

    /**
     * A child requires a site when its parent does (pages always, posts
     * unless they are unassigned).
     */
    protected function parentRequiresSite(?string $type): bool
    {
        $class = $this->parentClass($type);

        return $class !== null
            && in_array(BelongsToSite::class, class_uses_recursive($class), true)
            && (new $class)->requiresSite();
    }

    /**
     * @return class-string<Model>|null
     */
    private function parentClass(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }
}
