<?php

namespace App\Filament\Resources\Modules\Hooks;

use App\Helpers\Studio\StudioManager;
use App\Models\Module;
use Filament\Notifications\Notification;

class ModuleHooks
{
    public function toggleModule(Module $record, array $_data = []): array
    {
        $record->update(['is_enable' => ! $record->is_enable]);
        $record->refresh();

        $status = $record->is_enable ? 'Enabled' : 'Disabled';

        Notification::make()
            ->success()
            ->title("Module {$status} Successfully")
            ->send();

        return ['success' => true];
    }

    public function deployModule(Module $record, array $_data = []): array
    {
        $result = StudioManager::deploy($record);

        if ($result->success) {
            Notification::make()->success()->title($result->message)->send();
        } else {
            Notification::make()->danger()->title('Deployment Failed')->body($result->message)->send();
        }

        return ['success' => $result->success];
    }

    public function repairRebuild(Module $record, array $_data = []): array
    {
        $result = StudioManager::rebuild($record);

        if ($result->success) {
            Notification::make()->success()->title($result->message)->send();
        } else {
            Notification::make()->danger()->title('Rebuild Failed')->body($result->message)->send();
        }

        return ['success' => $result->success];
    }

    public function uninstall(Module $record, array $_data = []): array
    {
        $result = StudioManager::uninstall($record, $_data);

        if ($result->success) {
            Notification::make()->success()->title($result->message)->send();
        } else {
            Notification::make()->danger()->title('Uninstall Failed')->body($result->message)->send();
        }

        return ['success' => $result->success];
    }
}
