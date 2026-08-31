<?php

declare(strict_types=1);

namespace TallCms\Cms\Filament\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use TallCms\Cms\Enums\ContentStatus;

final class PublishingTable
{
    public static function statusColumn(string $name = 'status'): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('tallcms::fields.status'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => ContentStatus::from($state)->getLabel())
            ->color(fn (string $state): string => ContentStatus::from($state)->getColor())
            ->icon(fn (string $state): string => ContentStatus::from($state)->getIcon());
    }

    public static function statusFilter(string $name = 'status'): SelectFilter
    {
        return SelectFilter::make($name)
            ->label(__('tallcms::fields.status'))
            ->options([
                ContentStatus::Draft->value => ContentStatus::Draft->getLabel(),
                ContentStatus::Pending->value => ContentStatus::Pending->getLabel(),
                ContentStatus::Published->value => ContentStatus::Published->getLabel(),
            ]);
    }
}
