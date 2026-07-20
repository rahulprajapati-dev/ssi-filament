<?php

declare(strict_types=1);

namespace App\Helpers;

use Filament\Schemas\Schema;

/**
 * Studio-specific form builder.
 *
 * Entry point for all Studio-generated forms (RelationManagers: Fields, Layouts).
 * Pre-processes the JSON config before passing to JsonFormBuilder so Studio
 * forms always get sensible defaults without littering every JSON file with
 * repetitive config keys.
 *
 * Current Studio defaults applied automatically:
 *   - fileUpload components default to disk: "public" (avoids the S3 fallback).
 *
 * To add a new Studio-wide default: add a case in preprocessComponents().
 */
class JsonStudioFormBuilder extends JsonFormBuilder
{
    public static function buildSchema(Schema $schema, array $config): Schema
    {
        $config['components'] = self::preprocessComponents($config['components'] ?? []);

        return parent::buildSchema($schema, $config);
    }

    public static function buildActionSchema(array $config): array
    {
        $config['components'] = self::preprocessComponents($config['components'] ?? []);

        return parent::buildActionSchema($config);
    }

    // ── Pre-processing ────────────────────────────────────────────────────────

    /**
     * Recursively walk the component tree and apply Studio defaults.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    private static function preprocessComponents(array $components): array
    {
        foreach ($components as &$item) {
            $type = $item['component'] ?? null;

            // fileUpload: default to public disk in Studio
            if ($type === 'fileUpload' && ! isset($item['disk'])) {
                $item['disk'] = 'public';
            }

            // Recurse into nested schemas (section, grid, group, repeater, …)
            foreach (['schema', 'items'] as $childKey) {
                if (! empty($item[$childKey]) && is_array($item[$childKey])) {
                    $item[$childKey] = self::preprocessComponents($item[$childKey]);
                }
            }

            // Recurse into tabs
            if (! empty($item['tabs']) && is_array($item['tabs'])) {
                foreach ($item['tabs'] as &$tab) {
                    if (! empty($tab['schema']) && is_array($tab['schema'])) {
                        $tab['schema'] = self::preprocessComponents($tab['schema']);
                    }
                }
                unset($tab);
            }

            // [M11] Recurse into wizard steps — each step has its own schema array
            // that must receive the same Studio defaults (e.g. fileUpload disk).
            if (! empty($item['steps']) && is_array($item['steps'])) {
                foreach ($item['steps'] as &$step) {
                    if (! empty($step['schema']) && is_array($step['schema'])) {
                        $step['schema'] = self::preprocessComponents($step['schema']);
                    }
                }
                unset($step);
            }
        }
        unset($item);

        return $components;
    }
}
