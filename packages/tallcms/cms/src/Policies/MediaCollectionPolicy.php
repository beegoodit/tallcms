<?php

declare(strict_types=1);

namespace TallCms\Cms\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use TallCms\Cms\Models\MediaCollection;
use TallCms\Cms\Policies\Concerns\ChecksSiteOwnership;

class MediaCollectionPolicy
{
    use ChecksSiteOwnership;
    use HandlesAuthorization;

    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('ViewAny:TallcmsMedia');
    }

    public function view(Authenticatable $user, MediaCollection $collection): bool
    {
        return $user->can('View:TallcmsMedia') && $this->userMayAccessContentSite($user, $collection->site_id);
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('Create:TallcmsMedia');
    }

    public function update(Authenticatable $user, MediaCollection $collection): bool
    {
        return $user->can('Update:TallcmsMedia') && $this->userMayAccessContentSite($user, $collection->site_id);
    }

    public function delete(Authenticatable $user, MediaCollection $collection): bool
    {
        return $user->can('Delete:TallcmsMedia') && $this->userMayAccessContentSite($user, $collection->site_id);
    }
}
