<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModuleFields\Hooks;

use App\Helpers\Studio\DropdownHandler;
use Filament\Notifications\Notification;

class ModuleFieldDomHooks
{
    /**
     * Pre-fill the DOM editor modal with the field's existing options.
     *
     * Called via fill_hook. Must return an array that Filament uses to
     * populate the modal form.
     *
     * @param  \App\Models\ModuleField|null  $record
     */
    public function fillDomForm($record, $data, $livewire, $action): array
    {
        if (! $record) {
            return ['options' => []];
        }

        // Prefer the structured options stored on the model (includes 'default' flag)
        $existing = $record->options;

        if (is_array($existing) && count($existing) > 0 && isset($existing[0]['key'])) {
            return ['options' => $existing];
        }

        // Fallback: reconstruct from the DOM file (no 'default' flag available)
        $module = $record->module;

        if (! $module) {
            return ['options' => []];
        }

        // Use the new DropdownHandler::get(modulename, fieldname) signature
        $domData = DropdownHandler::get($module->fullname, $record->field_name);

        $options = [];
        foreach ($domData as $key => $value) {
            $options[] = ['default' => false, 'key' => (string) $key, 'value' => (string) $value];
        }

        return ['options' => $options];
    }

    /**
     * Save the edited options back to module_fields and rebuild the DOM group.
     *
     * @param  \App\Models\ModuleField|null  $record
     */
    public function saveDomOptions($record, $data, $livewire, $action): void
    {
        if (! $record) {
            Notification::make()->title('Record not found.')->danger()->send();
            return;
        }

        $module = $record->module;

        if (! $module) {
            Notification::make()->title('Module not found.')->danger()->send();
            return;
        }

        $optionRows = array_values($data['options'] ?? []);

        // Persist the full structured options on the model (key, value, default)
        $record->options = $optionRows;
        $record->save();

        // Rebuild the DOM file entry. deleteGroup() now deletes by prefix so passing
        // "{module}_{field}" removes the base group and all type-specific sub-groups.
        // createGroup() then writes the fresh base group (and any dependent_value sub-groups).
        DropdownHandler::deleteGroup($module->fullname . '_' . $record->field_name);
        DropdownHandler::createGroup($module->fullname, $record->field_name, $optionRows);

        Notification::make()
            ->title('Dropdown options saved.')
            ->success()
            ->send();
    }
}
