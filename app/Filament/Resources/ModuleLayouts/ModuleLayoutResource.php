<?php

namespace App\Filament\Resources\ModuleLayouts;

use App\Filament\Resources\ModuleLayouts\Pages\CreateModuleLayout;
use App\Filament\Resources\ModuleLayouts\Pages\EditModuleLayout;
use App\Filament\Resources\ModuleLayouts\Pages\ListModuleLayouts;
use App\Filament\Resources\ModuleLayouts\Pages\ViewModuleLayout;
use App\Filament\Resources\ModuleLayouts\Schemas\ModuleLayoutForm;
use App\Filament\Resources\ModuleLayouts\Tables\ModuleLayoutsTable;
use App\Models\ModuleLayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ModuleLayoutResource extends Resource
{
    protected static ?string $model = ModuleLayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $modelLabel = 'Layout';

    protected static ?string $pluralModelLabel = 'Layouts';

    protected static ?string $recordTitleAttribute = 'layout_type';

    protected static ?string $navigationLabel = 'Layout Builder';

    protected static string|UnitEnum|null $navigationGroup = 'Studio';

    public static function form(Schema $schema): Schema
    {
        return ModuleLayoutForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModuleLayoutsTable::configure($table);
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
            'index' => ListModuleLayouts::route('/'),
            'create' => CreateModuleLayout::route('/create'),
            'view' => ViewModuleLayout::route('/{record}'),
            'edit' => EditModuleLayout::route('/{record}/edit'),
        ];
    }
}
