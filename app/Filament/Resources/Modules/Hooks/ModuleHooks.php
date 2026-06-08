<?php

namespace App\Filament\Resources\Modules\Hooks;

use App\Models\Module;
use Filament\Notifications\Notification;

class ModuleHooks
{
    public function toggleModule(Module $record, array $data = [])
    {
        // $record->update([
        //     'is_enabled' => ! $record->is_enabled,
        // ]);

        // $record->refresh();

        Notification::make()->success()->title(
            $record->is_enabled ? 'Module Enabled Successfully' : 'Module Disabled Successfully'
        )->send();

        return [
            'success' => true,
        ];
    }

    public function deployModule(Module $record, array $data = [])
    {
        $record->update([
            'is_deploy' => true,
        ]);
        $record->save();


        Notification::make()->success()->title('Module Deployed Successfully')->send();

        return [
            'success' => true,
        ];
    }
}