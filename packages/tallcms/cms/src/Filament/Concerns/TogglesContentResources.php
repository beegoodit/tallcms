<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Concerns;

/**
 * Content resource switches shared by every panel plugin that registers
 * TallCMS content (TallCmsPlugin, SitePanelPlugin), so a host turns a
 * resource off the same way in each panel.
 */
trait TogglesContentResources
{
    protected bool $hasCategories = true;

    protected bool $hasPages = true;

    protected bool $hasPosts = true;

    protected bool $hasComments = true;

    protected bool $hasContactSubmissions = true;

    protected bool $hasMedia = true;

    protected bool $hasMenus = true;

    /**
     * Disable categories resource.
     */
    public function withoutCategories(): static
    {
        $this->hasCategories = false;

        return $this;
    }

    /**
     * Disable pages resource.
     */
    public function withoutPages(): static
    {
        $this->hasPages = false;

        return $this;
    }

    /**
     * Disable posts resource.
     */
    public function withoutPosts(): static
    {
        $this->hasPosts = false;

        return $this;
    }

    /**
     * Disable comments resource.
     */
    public function withoutComments(): static
    {
        $this->hasComments = false;

        return $this;
    }

    /**
     * Disable contact submissions resource.
     */
    public function withoutContactSubmissions(): static
    {
        $this->hasContactSubmissions = false;

        return $this;
    }

    /**
     * Disable media resource.
     */
    public function withoutMedia(): static
    {
        $this->hasMedia = false;

        return $this;
    }

    /**
     * Disable menus resource.
     */
    public function withoutMenus(): static
    {
        $this->hasMenus = false;

        return $this;
    }
}
