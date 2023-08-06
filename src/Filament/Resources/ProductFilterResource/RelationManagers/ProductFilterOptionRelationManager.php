<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource\RelationManagers;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Tables\Actions\LinkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ButtonAction;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Filament\Resources\RelationManagers\HasManyRelationManager;

class ProductFilterOptionRelationManager extends HasManyRelationManager
{
    protected static string $relationship = 'productFilterOptions';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ButtonAction::make('Aanmaken')
                    ->url(fn ($record) => route('filament.resources.product-filter-options.create') . '?productFilterId=' . $record),
            ]);
    }

    protected function getTableActions(): array
    {
        return array_merge(parent::getTableActions(), [
            LinkAction::make('edit')
                ->label('Bewerken')
                ->url(fn (ProductFilterOption $record) => route('filament.resources.product-filter-options.edit', [$record])),
        ]);
    }
}
