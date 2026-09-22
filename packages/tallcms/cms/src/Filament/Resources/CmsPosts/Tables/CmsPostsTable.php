<?php

namespace TallCms\Cms\Filament\Resources\CmsPosts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TallCms\Cms\Filament\Tables\PublishingBulkActions;
use TallCms\Cms\Filament\Tables\PublishingTable;
use TallCms\Cms\Models\CmsCategory;

class CmsPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('featured_image')
                    ->label(__('tallcms::fields.image'))
                    ->square()
                    ->imageSize(50)
                    ->disk(cms_media_disk()),

                TextColumn::make('title')->label(__('tallcms::fields.title'))
                    ->searchable()
                    ->limit(50),

                TextColumn::make('excerpt')->label(__('tallcms::fields.excerpt'))
                    ->searchable()
                    ->limit(60)
                    ->toggleable()
                    ->color('gray'),

                PublishingTable::statusColumn(),

                ToggleColumn::make('is_featured')
                    ->label(__('tallcms::fields.featured')),

                TagsColumn::make('categories.name')
                    ->label(tallcms_label('categories', 'plural'))
                    ->limit(3),

                TextColumn::make('author.name')
                    ->label(__('tallcms::fields.author'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('views')
                    ->label(__('tallcms::fields.views'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')->label(__('tallcms::fields.published_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')->label(__('tallcms::fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                PublishingTable::statusFilter(),

                SelectFilter::make('is_featured')
                    ->label(__('tallcms::fields.featured'))
                    ->options([
                        '1' => __('tallcms::fields.featured'),
                        '0' => __('tallcms::fields.not_featured'),
                    ]),

                SelectFilter::make('categories')
                    ->label(tallcms_label('categories', 'plural'))
                    ->multiple()
                    ->options(fn (): array => CmsCategory::query()
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn (CmsCategory $category): array => [
                            $category->getKey() => (string) $category->name,
                        ])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $values = array_values(array_filter(
                            (array) ($data['values'] ?? []),
                            fn (mixed $value): bool => filled($value),
                        ));

                        if ($values === []) {
                            return $query;
                        }

                        return $query->whereHas(
                            'categories',
                            fn (Builder $categories): Builder => $categories->whereKey($values),
                        );
                    }),

                SelectFilter::make('author')
                    ->relationship('author', 'name'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ...PublishingBulkActions::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
