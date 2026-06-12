<?php

namespace App\Helpers\Studio;

use Illuminate\Support\HtmlString;

class ModuleRelationshipHelper
{
    private static array $typeColors = [
        'hasOne'         => '#0ea5e9',
        'hasMany'        => '#8b5cf6',
        'belongsTo'      => '#f59e0b',
        'belongsToMany'  => '#ec4899',
        'hasOneThrough'  => '#14b8a6',
        'hasManyThrough' => '#6366f1',
    ];

    private static array $typeLabels = [
        'hasOne'         => 'Has One',
        'hasMany'        => 'Has Many',
        'belongsTo'      => 'Belongs To',
        'belongsToMany'  => 'Belongs To Many',
        'hasOneThrough'  => 'Has One Through',
        'hasManyThrough' => 'Has Many Through',
    ];

    public static function renderRelationships($record, $get): HtmlString
    {
        $relationships = $record?->relationships_json;

        if (empty($relationships)) {
            return new HtmlString(
                '<div style="display:flex;align-items:center;justify-content:center;padding:24px;border:2px dashed #e5e7eb;border-radius:10px;color:#9ca3af;font-size:13px;font-style:italic;">'
                . 'No relationships defined. Use the Edit form to add relationships to other modules.'
                . '</div>'
            );
        }

        $html  = '<div style="display:flex;flex-direction:column;gap:8px;">';

        foreach ($relationships as $index => $rel) {
            $type          = $rel['type']           ?? '';
            $relatedModule = $rel['related_module']  ?? '';
            $methodName    = $rel['name']            ?? self::guessMethodName($type, $relatedModule);
            $foreignKey    = $rel['foreign_key']     ?? '';

            $color = self::$typeColors[$type] ?? '#6b7280';
            $label = self::$typeLabels[$type] ?? $type;

            $html .= '<div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 1px 2px 0 rgba(0,0,0,.04);">';

            // Type badge
            $html .= '<span style="flex-shrink:0;display:inline-flex;align-items:center;padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:.03em;color:#fff;background:' . $color . ';">' . e($label) . '</span>';

            // Arrow
            $html .= '<span style="color:#cbd5e1;font-size:16px;">→</span>';

            // Related module
            $relLabel = ucwords(str_replace('_', ' ', $relatedModule));
            $html .= '<span style="font-size:13px;font-weight:700;color:#1e293b;">' . e($relLabel) . '</span>';

            // Method name
            if ($methodName) {
                $html .= '<code style="flex-shrink:0;margin-left:auto;font-size:11px;padding:2px 8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;color:#64748b;font-family:monospace;">' . e($methodName) . '()</code>';
            }

            // Foreign key if custom
            if ($foreignKey) {
                $html .= '<span style="flex-shrink:0;font-size:10px;color:#94a3b8;background:#f1f5f9;padding:2px 8px;border-radius:6px;border:1px solid #e2e8f0;">FK: ' . e($foreignKey) . '</span>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    private static function guessMethodName(string $type, string $relatedModule): string
    {
        if (! $relatedModule) {
            return '';
        }

        $base = \Illuminate\Support\Str::camel($relatedModule);

        return match ($type) {
            'hasMany', 'belongsToMany', 'hasManyThrough' => \Illuminate\Support\Str::plural($base),
            default => \Illuminate\Support\Str::singular($base),
        };
    }
}
