<?php

declare(strict_types=1);

namespace TallCms\Cms\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Models\Site;

/**
 * Site access by a plain list, standing in for a plugin's rule (multisite:
 * team membership): each user id names the site ids that user may work in.
 */
class SiteAccessByList implements SiteAccess
{
    /**
     * @param  array<int|string, list<int>>  $siteIdsByUserId
     */
    public function __construct(private array $siteIdsByUserId) {}

    public function accessibleSites(Authenticatable $user): Collection
    {
        return Site::query()->withoutGlobalScopes()->whereKey($this->siteIdsOf($user))->orderBy('name')->get();
    }

    public function canAccess(Authenticatable $user, Site $site): bool
    {
        return in_array((int) $site->getKey(), $this->siteIdsOf($user), true);
    }

    public function scopeUsers(Builder $users, Site $site): Builder
    {
        return $users;
    }

    public function contactUser(Site $site): ?Authenticatable
    {
        return null;
    }

    /**
     * @return list<int>
     */
    private function siteIdsOf(Authenticatable $user): array
    {
        return $this->siteIdsByUserId[$user->getAuthIdentifier()] ?? [];
    }
}
