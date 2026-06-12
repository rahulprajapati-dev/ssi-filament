<?php

namespace App\Filament\Resources\ModuleFields;

use App\Filament\Resources\ModuleFields\Pages\CreateModuleField;
use App\Filament\Resources\ModuleFields\Pages\EditModuleField;
use App\Filament\Resources\ModuleFields\Pages\ListModuleFields;
use App\Filament\Resources\ModuleFields\Pages\ViewModuleField;
use App\Filament\Resources\ModuleFields\Schemas\ModuleFieldForm;
use App\Filament\Resources\ModuleFields\Tables\ModuleFieldsTable;
use App\Models\ModuleField;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ModuleFieldResource extends Resource
{
    protected static ?string $model = ModuleField::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = 'Field';

    protected static ?string $pluralModelLabel = 'Fields';

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $navigationLabel = 'Field Builder';

    protected static string|UnitEnum|null $navigationGroup = 'Studio';

    public static function form(Schema $schema): Schema
    {
        return ModuleFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModuleFieldsTable::configure($table);
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
            'index' => ListModuleFields::route('/'),
            'create' => CreateModuleField::route('/create'),
            'view' => ViewModuleField::route('/{record}'),
            'edit' => EditModuleField::route('/{record}/edit'),
        ];
    }
}
