<?php

declare(strict_types=1);

namespace TallCms\Cms\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The setup command (tallcms:setup) has made a user super admin: a new user,
 * or the existing first user. For plugins that have something to prepare for
 * the installation's first operator.
 */
class SetupAssignedSuperAdmin
{
    use Dispatchable;

    public function __construct(
        public Authenticatable $user,
    ) {}
}
