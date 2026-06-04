<?php

namespace App\Models\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\Models\Activity;

trait LogsActivityFromSchema
{
    use LogsActivity;

    /**
     * Define the activity log options based on the JSON schema.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logOptions = LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();

        $schemaFile = $this->getActivityLogSchemaFile();

        if ($schemaFile && file_exists($schemaFile)) {
            $config = json_decode(file_get_contents($schemaFile), true);
            $auditedFields = $this->getAuditedFieldsFromConfig($config);

            if (!empty($auditedFields)) {
                $logOptions->logOnly($auditedFields);
            } else {
                // If no fields marked for auditing, maybe we shouldn't log anything or log a default set?
                // The user specifically asked for "some field edit activity that i have marked audit true"
                // So if none marked, we can log nothing by using an empty array or just not calling logOnly.
                // However, spatie logOnly([]) might still log everything or nothing.
                // Let's use logOnly($auditedFields) which will be empty.
                $logOptions->logOnly($auditedFields);
            }
        }

        return $logOptions;
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        if (request()?->json('api_source') === 'crm' && !empty($this->updated_by)) {
            $activity->causer_id = $this->updated_by;
        }
    }

    /**
     * Get the path to the schema file.
     * Can be overridden in the model or via a property.
     */
    protected function getActivityLogSchemaFile(): ?string
    {
        $path = null;
        if (method_exists($this, 'getActivitySchemaFile')) {
            $path = $this->getActivitySchemaFile();
        } elseif (property_exists($this, 'activitySchemaFile')) {
            $path = $this->activitySchemaFile;
        }

        if ($path && !str_starts_with($path, DIRECTORY_SEPARATOR) && !str_contains($path, ':')) {
            $path = base_path($path);
        }

        return $path;
    }

    /**
     * Extract audited fields from the JSON config.
     */
    protected function getAuditedFieldsFromConfig(array $config): array
    {
        $fields = [];
        $components = $config['components'] ?? [];

        $this->traverseComponentsForAudit($components, $fields);

        return array_unique($fields);
    }

    /**
     * Recursively traverse components to find audited fields.
     */
    protected function traverseComponentsForAudit(array $components, array &$fields): void
    {
        foreach ($components as $component) {
            // Check for 'audit' key
            if (!empty($component['audit']) && !empty($component['name'])) {
                $fields[] = $component['name'];
            }

            // Recursive cases for layout components (grid, section, etc.)
            if (!empty($component['schema']) && is_array($component['schema'])) {
                $this->traverseComponentsForAudit($component['schema'], $fields);
            }

            // Wizard steps
            if (!empty($component['steps']) && is_array($component['steps'])) {
                foreach ($component['steps'] as $step) {
                    if (!empty($step['schema']) && is_array($step['schema'])) {
                        $this->traverseComponentsForAudit($step['schema'], $fields);
                    }
                }
            }

            // Tabs
            if (!empty($component['tabs']) && is_array($component['tabs'])) {
                foreach ($component['tabs'] as $tab) {
                    if (!empty($tab['schema']) && is_array($tab['schema'])) {
                        $this->traverseComponentsForAudit($tab['schema'], $fields);
                    }
                }
            }
        }
    }
}
