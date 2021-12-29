<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Tables\Actions\LinkAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ButtonAction;
use Qubiqx\QcommerceCore\Models\MenuItem;
use Filament\Resources\RelationManagers\HasManyRelationManager;
use Qubiqx\QcommerceEcommerceCore\Models\Product;

class ChildProductsRelationManager extends HasManyRelationManager
{
    protected static string $relationship = 'childProducts';

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
                    ->searchable([
                        'name',
                        'short_description',
                        'description',
                        'search_terms',
                        'content',
                        'meta_title',
                        'meta_description',
                    ])
                    ->sortable(),
                TextColumn::make('total_purchases')
                    ->label('Aantal verkopen'),
                BooleanColumn::make('status')
                    ->label('Status'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ButtonAction::make('Aanmaken')
                    ->url(fn ($record) => route('filament.resources.products.create')),
            ]);
    }

    protected function getTableActions(): array
    {
        return array_merge(parent::getTableActions(), [
            LinkAction::make('edit')
                ->label('Bewerken')
                ->url(fn (Product $record) => route('filament.resources.products.edit', [$record])),
        ]);
    }
}
