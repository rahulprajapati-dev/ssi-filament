<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class StudioDevGuide extends Page
{
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-book-open';
    protected static ?string                $navigationLabel = 'Developer Guide';
    protected static string|UnitEnum|null   $navigationGroup = 'Studio';
    protected static ?int                   $navigationSort  = 99;
    protected static ?string                $title           = 'Developer Customization Guide';

    public function getView(): string
    {
        return 'filament.pages.studio-dev-guide';
    }
}
