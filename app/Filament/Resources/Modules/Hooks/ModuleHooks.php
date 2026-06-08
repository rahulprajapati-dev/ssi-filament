<?php

namespace App\Filament\Resources\Modules\Hooks;

use App\Models\Module;
use App\Helpers\Studio\StudioHelper;
use Filament\Notifications\Notification;

class ModuleHooks
{
    public function toggleModule(Module $record, array $data = [])
    {
        $record->update([
            'is_enable' => ! $record->is_enable,
        ]);
        $record->save();
        $record->refresh();

        $status = $record->is_enable ? 'Enabled' : 'Disabled';
        Notification::make()->success()->title("Module {$status} Successfully")->send();

        return [
            'success' => true,
        ];
    }

    public function deployModule(Module $record, array $data = [])
    {
        $res = StudioHelper::deploy($module);
        
        if ($res['status']) {
            Notification::make()->success()->title($res['msg'])->send();
            return [ 'success' => true, ];
        }

        Notification::make()->success()->title($res['msg'])->send();
        return [ 'success' => false, ];
    }
}