<?php

declare(strict_types=1);

namespace TallCms\Cms\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use TallCms\Cms\Models\CmsPost;
use TallCms\Cms\Policies\Concerns\ChecksSiteOwnership;

class CmsPostPolicy
{
    use ChecksSiteOwnership;
    use HandlesAuthorization;

    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('ViewAny:CmsPost');
    }

    public function view(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('View:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('Create:CmsPost');
    }

    public function update(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('Update:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    public function delete(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('Delete:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    public function restore(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('Restore:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    public function forceDelete(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('ForceDelete:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return $user->can('ForceDeleteAny:CmsPost');
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return $user->can('RestoreAny:CmsPost');
    }

    public function replicate(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('Replicate:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    public function reorder(Authenticatable $user): bool
    {
        return $user->can('Reorder:CmsPost');
    }

    /**
     * Determine if the user can approve the post (publish pending content).
     *
     * The approver of a review is usually not the author: whoever holds the
     * permission and may work in the post's site approves.
     */
    public function approve(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('Approve:CmsPost') && $cmsPost->canBeApproved() && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    /**
     * Determine if the user can submit the post for review
     */
    public function submitForReview(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('SubmitForReview:CmsPost') && $cmsPost->canSubmitForReview() && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    /**
     * Determine if the user can view revisions
     */
    public function viewRevisions(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('ViewRevisions:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    /**
     * Determine if the user can restore a revision
     */
    public function restoreRevision(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('RestoreRevision:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }

    /**
     * Determine if the user can generate preview links
     */
    public function generatePreviewLink(Authenticatable $user, CmsPost $cmsPost): bool
    {
        return $user->can('GeneratePreviewLink:CmsPost') && $this->userMayAccessContentSite($user, $cmsPost->site_id);
    }
}
