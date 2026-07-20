<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class StudioDevGuide extends Page
{
    protected static ?string $navigationLabel = 'Developer Guide';
    protected static ?int    $navigationSort  = 99;
    protected static ?string $title           = 'Developer Customization Guide';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-book-open';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Studio';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    public function getView(): string
    {
        return 'filament.pages.studio-dev-guide';
    }
}
