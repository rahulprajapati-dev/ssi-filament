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

    private const JSON_TYPES = ['json', 'array', 'repeater', 'checkbox_list', 'checkboxlist', 'tags'];

    private const TEXT_TYPES = ['textarea', 'longtext', 'richtext'];

    private const STRING_TYPES = [
        'text', 'string', 'email', 'url', 'phone', 'password',
        'select', 'dropdown', 'enum', 'radio',
        'file', 'image', 'fileupload', 'color',
    ];

    /**
     * Columns that are always added explicitly by the schema builders (SchemaSyncService,
     * Migration.stub). Module-field rows for these names must be skipped during column
     * generation to prevent "Duplicate column" errors.
     */
    public const SYSTEM_FIELD_NAMES = ['created_by', 'updated_by', 'created_at', 'updated_at'];

    /**
     * Address sub-field suffixes → DB column max length.
     * Each entry becomes one real module_fields row (type='text') and one DB column.
     *
     * @var array<string, int>
     */
    public const ADDRESS_SUB_FIELDS = [
        '_street1' => 150,
        '_street2' => 150,
        '_city'    => 50,
        '_state'   => 50,
        '_pincode' => 20,
    ];

    // ── Type predicates ───────────────────────────────────────────────────────

    /** Returns true for the parent 'address' field type (virtual — no own DB column). */
    public static function isAddressType(string $type): bool
    {
        return strtolower($type) === 'address';
    }

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
        return ! self::isBooleanType($type) && ! self::isJsonType($type) && ! self::isTextType($type);
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
            'decimal', 'float', 'money',
            'currency'                         => 'decimal',
            'boolean', 'toggle', 'checkbox'    => 'boolean',
            'date'                             => 'date',
            'datetime', 'timestamp'            => 'dateTime',
            'time'                             => 'time',
            'json', 'array', 'repeater',
            'checkbox_list', 'checkboxlist',
            'tags'                             => 'json',
            'relationship', 'relate'           => 'unsignedBigInteger',
            'address'                          => (static function () {
                \Log::warning('FieldTypeMap::dbType called on virtual address type');
                return 'string';
            })(),
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
            'time'                             => 'timePicker',
            'color'                            => 'colorPicker',
            'tags'                             => 'tagsInput',
            'address'                          => 'address',
            default                            => 'textInput',
        };
    }

    /** Field type → Filament infolist component name (detail / view). */
    public static function toDetailComponent(string $type): string
    {
        return match (strtolower($type)) {
            'boolean', 'toggle', 'checkbox'    => 'toggle',
            'file', 'image', 'fileupload'      => 'imageEntry',
            'address'                          => 'addressEntry',
            default                            => 'textEntry',
        };
    }

    /**
     * Field type → JsonTableBuilder column type (list view).
     * Boolean types use 'icon' (IconColumn); image/file use 'image' (ImageColumn); everything else 'text' (TextColumn).
     */
    public static function toColumnComponent(string $type): string
    {
        if (self::isBooleanType($type)) {
            return 'icon';
        }

        if (in_array(strtolower($type), ['image', 'file', 'fileupload'], true)) {
            return 'image';
        }

        return 'text';
    }
}
