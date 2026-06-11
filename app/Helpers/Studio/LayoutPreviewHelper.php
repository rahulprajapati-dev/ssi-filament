<?php

namespace App\Helpers\Studio;

use Illuminate\Support\HtmlString;

class LayoutPreviewHelper
{
    public static function renderLayoutJson($record, $get): HtmlString
    {
        if (! $record) {
            return new HtmlString('<p style="font-size:13px; color:#9ca3af; font-style:italic;">No record available.</p>');
        }

        $layout = $record->layout_json;

        if (empty($layout)) {
            return new HtmlString(
                '<div style="display:flex;align-items:center;justify-content:center;padding:32px;border:2px dashed #e5e7eb;border-radius:10px;color:#9ca3af;font-size:13px;font-style:italic;">'
                . 'No layout configured yet. Use the edit form to build your layout.'
                . '</div>'
            );
        }

        // Normalize: flat legacy array → sections format
        $sections = $layout;
        if (! empty($layout[0]) && is_string($layout[0])) {
            $sections = [['title' => 'Default Section', 'columns' => 2, 'fields' => $layout]];
        }

        $sectionCount = count($sections);
        $totalFields  = array_sum(array_map(fn ($s) => count($s['fields'] ?? []), $sections));

        $html  = '<div style="display:flex;flex-direction:column;gap:12px;">';

        // Summary bar
        $html .= '<div style="display:flex;align-items:center;gap:12px;padding:8px 14px;background:#f1f5f9;border-radius:8px;font-size:12px;color:#475569;">';
        $html .= '<span><b style="color:#1e293b;font-size:14px;">' . $sectionCount . '</b>&nbsp;' . ($sectionCount === 1 ? 'section' : 'sections') . '</span>';
        $html .= '<span style="color:#cbd5e1;">|</span>';
        $html .= '<span><b style="color:#1e293b;font-size:14px;">' . $totalFields . '</b>&nbsp;total fields</span>';
        $html .= '</div>';

        foreach ($sections as $index => $section) {
            $title   = $section['title']   ?? ('Section ' . ($index + 1));
            $columns = max(1, min(4, (int) ($section['columns'] ?? 2)));
            $fields  = $section['fields']  ?? [];
            $count   = count($fields);

            $html .= '<div style="border:1px solid #e2e8f0;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 1px 3px 0 rgba(0,0,0,.04);">';

            // Section header
            $html .= '<div style="display:flex;align-items:center;justify-content:space-between;padding:9px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">';
            $html .= '<div style="display:flex;align-items:center;gap:8px;">';
            $html .= '<svg style="width:13px;height:13px;color:#94a3b8;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>';
            $html .= '<span style="font-size:12px;font-weight:700;color:#1e293b;">' . e($title) . '</span>';
            $html .= '</div>';
            $html .= '<div style="display:flex;align-items:center;gap:6px;">';
            $html .= '<span style="font-size:10px;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:9999px;border:1px solid #e2e8f0;">' . $columns . '&nbsp;col</span>';
            $html .= '<span style="font-size:10px;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:9999px;border:1px solid #e2e8f0;">' . $count . '&nbsp;field' . ($count !== 1 ? 's' : '') . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            // Fields mockup grid
            if (! empty($fields)) {
                $html .= '<div style="display:grid;grid-template-columns:repeat(' . $columns . ',minmax(0,1fr));gap:10px;padding:14px;">';
                foreach ($fields as $fieldName) {
                    $label = ucwords(str_replace('_', ' ', (string) $fieldName));
                    $html .= '<div style="display:flex;flex-direction:column;gap:4px;">';
                    $html .= '<span style="font-size:10px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">' . e($label) . '</span>';
                    $html .= '<div style="height:26px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;"></div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            } else {
                $html .= '<div style="padding:20px 14px;text-align:center;font-size:11px;color:#cbd5e1;font-style:italic;">No fields added to this section</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
