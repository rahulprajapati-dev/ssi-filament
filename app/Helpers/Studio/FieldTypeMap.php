<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\ModuleField;

/**
 * Central registry for all field-type mappings used across Studio generators.
 *
 * Adding a new field type: update this file only.
 * LayoutGenerator, SchemaSyncService, and MigrationGenerator all read from here.
 */
final class FieldTypeMap
{
    // ── Type groups ───────────────────────────────────────────────────────────

    private const BOOLEAN_TYPES = ['boolean', 'toggle', 'checkbox'];

    private const JSON_TYPES = ['json', 'array', 'repeater'];

    private const TEXT_TYPES = ['textarea', 'longtext', 'richtext'];

    private const STRING_TYPES = [
        'text', 'string', 'email', 'url', 'phone', 'password',
        'select', 'dropdown', 'enum', 'radio',
        'checkboxlist', 'checkbox_list',
        'file', 'image', 'fileupload', 'color', 'tags',
    ];

    // ── Type predicates ───────────────────────────────────────────────────────

    public static function isBooleanType(string $type): bool
    {
        return in_array(strtolower($type), self::BOOLEAN_TYPES, true);
    }

    public static function isStringType(string $type): bool
    {
        return in_array(strtolower($type), self::STRING_TYPES, true);
    }

    public static function isJsonType(string $type): bool
    {
        return in_array(strtolower($type), self::JSON_TYPES, true);
    }

    public static function isTextType(string $type): bool
    {
        return in_array(strtolower($type), self::TEXT_TYPES, true);
    }

    /** Whether this type cannot have a UNIQUE constraint (blob/text/json/boolean). */
    public static function supportsUnique(string $type): bool
    {
        return ! self::isBooleanType($type)
            && ! self::isJsonType($type)
            && ! self::isTextType($type);
    }

    /** Whether this type should receive a default value from the field definition. */
    public static function supportsDefault(string $type): bool
    {
        return ! self::isBooleanType($type) && ! self::isJsonType($type);
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    public static function resolveLength(ModuleField $field): int
    {
        return ($field->length > 0) ? (int) $field->length : 255;
    }

    // ── Database column type ──────────────────────────────────────────────────

    /**
     * Canonical DB column type for a field type string.
     * SchemaSyncService and MigrationGenerator use this to drive their match statements.
     */
    public static function dbType(string $type): string
    {
        return match (strtolower($type)) {
            'textarea', 'longtext', 'richtext' => 'text',
            'integer', 'number', 'int'         => 'integer',
            'biginteger', 'bigint'             => 'bigInteger',
            'decimal', 'float', 'money'        => 'decimal',
            'boolean', 'toggle', 'checkbox'    => 'boolean',
            'date'                             => 'date',
            'datetime', 'timestamp'            => 'dateTime',
            'time'                             => 'time',
            'json', 'array', 'repeater'        => 'json',
            default                            => 'string',
        };
    }

    // ── Filament component mappers ─────────────────────────────────────────────

    /** Field type → Filament form component name (create / edit views). */
    public static function toFormComponent(string $type): string
    {
        return match (strtolower($type)) {
            'textarea', 'longtext', 'richtext' => 'textarea',
            'boolean', 'toggle'                => 'toggle',
            'checkbox'                         => 'checkbox',
            'date'                             => 'datePicker',
            'datetime', 'timestamp'            => 'dateTimePicker',
            'select', 'dropdown', 'enum',
            'relationship'                     => 'select',
            'radio'                            => 'radio',
            'checkboxlist', 'checkbox_list'    => 'checkboxList',
            'fileupload', 'file', 'image'      => 'fileUpload',
            'json', 'array', 'repeater'        => 'textarea',
            default                            => 'textInput',
        };
    }

    /** Field type → Filament infolist component name (detail / view). */
    public static function toDetailComponent(string $type): string
    {
        return match (strtolower($type)) {
            'boolean', 'toggle', 'checkbox'    => 'toggle',
            'file', 'image', 'fileupload'      => 'imageEntry',
            default                            => 'textEntry',
        };
    }

    /**
     * Field type → JsonTableBuilder column type (list view).
     * Boolean types use 'icon' (IconColumn); everything else 'text' (TextColumn).
     */
    public static function toColumnComponent(string $type): string
    {
        return self::isBooleanType($type) ? 'icon' : 'text';
    }
}
