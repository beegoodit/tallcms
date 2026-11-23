<?php

declare(strict_types=1);

namespace TallCms\Cms\Notifications;

use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use TallCms\Cms\Filament\Resources\CmsComments\CmsCommentResource;
use TallCms\Cms\Models\CmsComment;
use TallCms\Cms\Services\SitePanelUrls;

class NewCommentNotification extends Notification
{
    public function __construct(
        protected CmsComment $comment
    ) {}

    public function via(object $notifiable): array
    {
        return array_filter(config('tallcms.comments.notification_channels', ['mail', 'database']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $authorName = $this->comment->getAuthorName() ?? 'A visitor';
        $postTitle = $this->comment->post?->title ?? 'Unknown Post';

        return (new MailMessage)
            ->subject("New comment on: {$postTitle}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$authorName} has left a new comment on \"{$postTitle}\".")
            ->line('**Comment:**')
            ->line(\Illuminate\Support\Str::limit($this->comment->content, 200))
            ->action('Review Comment', $this->getViewUrl($notifiable))
            ->line('Please review and approve or reject this comment.');
    }

    public function toDatabase(object $notifiable): array
    {
        $authorName = $this->comment->getAuthorName() ?? 'A visitor';
        $postTitle = $this->comment->post?->title ?? 'Unknown Post';

        return FilamentNotification::make()
            ->warning()
            ->icon('heroicon-o-chat-bubble-left-right')
            ->title(__('tallcms::ui.n_new_comment_on', ['title' => $postTitle]))
            ->body(__('tallcms::ui.n_comment_author_excerpt', [
                'author' => $authorName,
                'excerpt' => \Illuminate\Support\Str::limit($this->comment->content, 80),
            ]))
            ->actions([
                FilamentAction::make('review')
                    ->label(__('tallcms::ui.t_review'))
                    ->url($this->getViewUrl($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    protected function getViewUrl(?object $notifiable = null): string
    {
        // The site panel of the comment's site, for a recipient who may open it
        $sitePanelUrl = app(SitePanelUrls::class)->resource(CmsCommentResource::class, 'view', $this->comment, $notifiable);
        if ($sitePanelUrl) {
            return $sitePanelUrl;
        }

        // The configured panel, where it holds content; else its start page.
        return tallcms_panel_route_if_exists('resources.cms-comments.view', $this->comment) ?? tallcms_panel_url();
    }
}
