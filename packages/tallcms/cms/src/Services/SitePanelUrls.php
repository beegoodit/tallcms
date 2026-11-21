<?php

declare(strict_types=1);

namespace TallCms\Cms\Services;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use TallCms\Cms\Contracts\SiteAccess;
use TallCms\Cms\Filament\Resources\CmsPages\CmsPageResource;
use TallCms\Cms\Filament\Resources\CmsPosts\CmsPostResource;
use TallCms\Cms\Models\CmsPage;
use TallCms\Cms\Models\CmsPost;
use TallCms\Cms\Models\Site;
use TallCms\Cms\SitePanelPlugin;

/**
 * Links into the site panel for links built outside it (notifications,
 * frontend views): the site panel of the record's site, when there is one,
 * it has the resource and the user may open that site (SiteAccess).
 *
 * Each method returns null otherwise; the caller then keeps its link into
 * the configured panel. Inside a panel, Filament's getUrl() already uses the
 * current panel and tenant.
 */
class SitePanelUrls
{
    public function __construct(protected SiteAccess $siteAccess) {}

    /**
     * A resource page of the record (e.g. 'edit') in the site panel of the
     * record's site.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    public function resource(string $resource, string $page, Model $record, ?object $user): ?string
    {
        $panel = $this->sitePanel();

        if (! $panel || ! in_array($resource, $panel->getResources(), true)) {
            return null;
        }

        $site = $this->accessibleSite($record->getAttribute('site_id'), $user);

        if (! $site) {
            return null;
        }

        return $resource::getUrl($page, ['record' => $record], panel: $panel->getId(), tenant: $site);
    }

    /**
     * The edit page of a page or post in the site panel of its site.
     */
    public function editContent(Model $content, ?object $user): ?string
    {
        $resource = match (true) {
            $content instanceof CmsPage => CmsPageResource::class,
            $content instanceof CmsPost => CmsPostResource::class,
            default => null,
        };

        return $resource ? $this->resource($resource, 'edit', $content, $user) : null;
    }

    /**
     * The site panel's start page for the site.
     */
    public function home(?int $siteId, ?object $user): ?string
    {
        $panel = $this->sitePanel();
        $site = $panel ? $this->accessibleSite($siteId, $user) : null;

        return $site ? $panel->getUrl($site) : null;
    }

    /**
     * The site's public address, for "open website" links in the site panel:
     * the site's domain with the scheme and (non-default) port of the panel
     * request, so it also works on a development server.
     */
    public function frontend(Site $site): string
    {
        $port = request()->getPort();
        $port = in_array($port, [80, 443], true) ? '' : ':'.$port;

        return request()->getScheme().'://'.$site->domain.$port;
    }

    /**
     * The panel that registers SitePanelPlugin, if any.
     */
    public function sitePanel(): ?Panel
    {
        foreach (Filament::getPanels() as $panel) {
            if ($panel->hasPlugin(SitePanelPlugin::ID)) {
                return $panel;
            }
        }

        return null;
    }

    protected function accessibleSite(mixed $siteId, ?object $user): ?Site
    {
        if (! $siteId || ! $user instanceof Authenticatable) {
            return null;
        }

        $siteModel = config('tallcms.sites.model', Site::class);
        $site = $siteModel::query()->find($siteId);

        return $site instanceof Site && $this->siteAccess->canAccess($user, $site) ? $site : null;
    }
}
