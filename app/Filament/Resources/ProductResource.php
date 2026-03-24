<?php

namespace App\Filament\Resources;

use Dom\Text;
use Filament\Forms;
use Filament\Tables;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\SpatieTagsInput;
use App\Filament\Resources\ProductResource\Pages;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make()->schema([
                    SpatieMediaLibraryFileUpload::make('cover')
                        ->collection('cover'),
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->collection('gallery')
                        ->multiple(),
                    TextInput::make('name')
                        ->label('Nama Produk'),
                    TextInput::make('sku')
                        ->label('SKU')
                        ->unique(ignoreRecord: true),
                    TextInput::make('slug')
                        ->unique(ignoreRecord: true),
                    SpatieTagsInput::make('tags')
                        ->type('categories')
                        ->label('category'),
                    MarkdownEditor::make('description')
                        ->label('Deskripsi Produk'),
                    TextInput::make('stock')
                        ->numeric()
                        ->default(0),
                    TextInput::make('price')
                        ->numeric()
                        ->prefix('Rp'),
                    TextInput::make('weight')
                        ->numeric()
                        ->suffix('gram')
                ])
                
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('sku'),
                TextColumn::make('stock'),
                TextColumn::make('slug'),
                TextColumn::make('price')
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
