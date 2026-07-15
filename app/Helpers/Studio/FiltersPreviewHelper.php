<?php

namespace App\Helpers\Studio;

use Illuminate\Support\HtmlString;

class FiltersPreviewHelper
{
    public static function render($record, $get): HtmlString
    {
        if (! $record) {
            return new HtmlString('');
        }

        $filters = $record->filters_json;

        if (empty($filters) || ! is_array($filters)) {
            return new HtmlString(
                '<div style="display:flex;align-items:center;justify-content:center;padding:24px;border:2px dashed #e5e7eb;border-radius:8px;color:#9ca3af;font-size:13px;font-style:italic;">'
                . 'No filters configured for this list layout.'
                . '</div>'
            );
        }

        $rows = '';
        foreach ($filters as $i => $filter) {
            $field = htmlspecialchars($filter['field_name'] ?? '—');
            $label = htmlspecialchars($filter['label'] ?? '');
            $num   = $i + 1;

            $rows .= "
                <tr style=\"border-bottom:1px solid #f3f4f6;\">
                    <td style=\"padding:8px 12px;font-size:13px;color:#374151;\">{$num}</td>
                    <td style=\"padding:8px 12px;font-size:13px;color:#111827;font-weight:500;font-family:monospace;\">{$field}</td>
                    <td style=\"padding:8px 12px;font-size:13px;color:#6b7280;\">" . ($label ?: '<em>auto</em>') . "</td>
                </tr>";
        }

        return new HtmlString("
            <table style=\"width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;\">
                <thead>
                    <tr style=\"background:#f9fafb;\">
                        <th style=\"padding:8px 12px;font-size:12px;font-weight:600;color:#6b7280;text-align:left;\">#</th>
                        <th style=\"padding:8px 12px;font-size:12px;font-weight:600;color:#6b7280;text-align:left;\">Field</th>
                        <th style=\"padding:8px 12px;font-size:12px;font-weight:600;color:#6b7280;text-align:left;\">Label</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        ");
    }
}
