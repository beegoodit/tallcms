<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Tables;

use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use TallCms\Cms\Enums\ContentStatus;
use TallCms\Cms\Services\PublishingWorkflowService;

final class PublishingBulkActions
{
    /**
     * Bulk actions for any model using HasPublishingWorkflow.
     *
     * @param  (callable(Model): bool)|null  $canUpdate
     * @param  (callable(Model): bool)|null  $canApprove
     * @return array<int, BulkAction>
     */
    public static function make(?callable $canUpdate = null, ?callable $canApprove = null): array
    {
        return [
            static::publishSelected($canUpdate),
            static::approveSelected($canApprove),
            static::unpublishSelected($canUpdate),
        ];
    }

    /**
     * @param  (callable(Model): bool)|null  $canUpdate
     */
    public static function publishSelected(?callable $canUpdate = null): BulkAction
    {
        $action = BulkAction::make('publishSelected')
            ->label(__('tallcms::fields.publish_selected'))
            ->icon('heroicon-o-globe-alt')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('tallcms::fields.publish_selected'))
            ->modalDescription(__('tallcms::fields.bulk_publish_modal_description'))
            ->deselectRecordsAfterCompletion()
            ->visible(fn (): bool => ! tallcms_review_workflow_enabled())
            ->action(function (Collection $records) use ($canUpdate): void {
                $result = static::publishRecords($records, $canUpdate);
                static::notifyResult('published', $result);
            });

        return static::withUpdateAuthorization($action, $canUpdate);
    }

    /**
     * @param  (callable(Model): bool)|null  $canApprove
     */
    public static function approveSelected(?callable $canApprove = null): BulkAction
    {
        $action = BulkAction::make('approveSelected')
            ->label(__('tallcms::fields.approve_selected'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('tallcms::fields.approve_selected'))
            ->modalDescription(__('tallcms::fields.bulk_approve_modal_description'))
            ->deselectRecordsAfterCompletion()
            ->visible(fn (): bool => tallcms_review_workflow_enabled())
            ->action(function (Collection $records) use ($canApprove): void {
                $result = static::approveRecords($records, $canApprove);
                static::notifyResult('approved', $result);
            });

        return static::withApproveAuthorization($action, $canApprove);
    }

    /**
     * @param  (callable(Model): bool)|null  $canUpdate
     */
    public static function unpublishSelected(?callable $canUpdate = null): BulkAction
    {
        $action = BulkAction::make('unpublishSelected')
            ->label(__('tallcms::fields.unpublish_selected'))
            ->icon('heroicon-o-eye-slash')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('tallcms::fields.unpublish_selected'))
            ->modalDescription(__('tallcms::fields.bulk_unpublish_modal_description'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($canUpdate): void {
                $result = static::unpublishRecords($records, $canUpdate);
                static::notifyResult('unpublished', $result);
            });

        return static::withUpdateAuthorization($action, $canUpdate);
    }

    /**
     * Publish draft records (review workflow off path).
     *
     * @param  Collection<int, Model>  $records
     * @param  (callable(Model): bool)|null  $canUpdate
     * @return array{done: int, skipped: int}
     */
    public static function publishRecords(Collection $records, ?callable $canUpdate = null): array
    {
        $canUpdate ??= static::defaultCanUpdate();
        $done = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! $record->isDraft() || ! $canUpdate($record)) {
                $skipped++;

                continue;
            }

            $record->update([
                'status' => ContentStatus::Published->value,
                'published_at' => $record->published_at ?? now(),
            ]);
            $done++;
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    /**
     * Approve pending records via PublishingWorkflowService.
     *
     * @param  Collection<int, Model>  $records
     * @param  (callable(Model): bool)|null  $canApprove
     * @return array{done: int, skipped: int}
     */
    public static function approveRecords(Collection $records, ?callable $canApprove = null): array
    {
        $workflow = app(PublishingWorkflowService::class);
        $canApprove ??= static fn (Model $record): bool => $workflow->canApprove($record);
        $done = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! $record->isPending() || ! $canApprove($record)) {
                $skipped++;

                continue;
            }

            $workflow->approve($record);
            $done++;
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    /**
     * Unpublish records — match PostController::unpublish.
     *
     * @param  Collection<int, Model>  $records
     * @param  (callable(Model): bool)|null  $canUpdate
     * @return array{done: int, skipped: int}
     */
    public static function unpublishRecords(Collection $records, ?callable $canUpdate = null): array
    {
        $canUpdate ??= static::defaultCanUpdate();
        $done = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if ($record->status !== ContentStatus::Published->value || ! $canUpdate($record)) {
                $skipped++;

                continue;
            }

            $record->update([
                'status' => ContentStatus::Draft->value,
                'published_at' => null,
            ]);
            $done++;
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    /**
     * @return callable(Model): bool
     */
    private static function defaultCanUpdate(): callable
    {
        return static function (Model $record): bool {
            $user = auth()->user();

            if ($user === null) {
                return false;
            }

            return Gate::forUser($user)->check('update', $record);
        };
    }

    /**
     * @param  (callable(Model): bool)|null  $canUpdate
     */
    private static function withUpdateAuthorization(BulkAction $action, ?callable $canUpdate): BulkAction
    {
        if ($canUpdate === null) {
            return $action->authorizeIndividualRecords('update');
        }

        return $action->authorizeIndividualRecords(
            static fn (Model $record): bool => $canUpdate($record),
        );
    }

    /**
     * @param  (callable(Model): bool)|null  $canApprove
     */
    private static function withApproveAuthorization(BulkAction $action, ?callable $canApprove): BulkAction
    {
        $resolver = $canApprove
            ?? static fn (Model $record): bool => app(PublishingWorkflowService::class)->canApprove($record);

        return $action->authorizeIndividualRecords(
            static fn (Model $record): bool => $resolver($record),
        );
    }

    /**
     * @param  array{done: int, skipped: int}  $result
     */
    private static function notifyResult(string $verb, array $result): void
    {
        $title = match ($verb) {
            'published' => __('tallcms::fields.bulk_published_title', [
                'count' => $result['done'],
            ]),
            'approved' => __('tallcms::fields.bulk_approved_title', [
                'count' => $result['done'],
            ]),
            default => __('tallcms::fields.bulk_unpublished_title', [
                'count' => $result['done'],
            ]),
        };

        $notification = Notification::make()->title($title);

        if ($result['skipped'] > 0) {
            $notification->body(__('tallcms::fields.bulk_skipped_body', [
                'count' => $result['skipped'],
            ]));
        }

        if ($result['done'] > 0) {
            $notification->success();
        } else {
            $notification->warning();
        }

        $notification->send();
    }
}
