<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DbSchemaExporter
{
    public static function export(string $table, string $connection): array
    {
        // Get the table prefix from the connection
        $prefix = DB::connection($connection)->getTablePrefix();
        $fullTableName = $prefix . $table;

        // Check if table exists using raw query (more reliable with prefixes)
        $databaseName = DB::connection($connection)->getDatabaseName();
        $tables = DB::connection($connection)->select('SHOW TABLES');
        $tableColumn = 'Tables_in_' . $databaseName;
        
        $tableExists = collect($tables)->contains(function ($t) use ($tableColumn, $fullTableName) {
            return $t->$tableColumn === $fullTableName;
        });

        if (!$tableExists) {
            throw new \Exception("Table '{$fullTableName}' does not exist on connection '{$connection}'.");
        }

        // Fetch all column information from MySQL using full table name
        $columns = DB::connection($connection)->select("SHOW FULL COLUMNS FROM `{$fullTableName}`");

        $fields = [];
        foreach ($columns as $col) {
            $fields[$col->Field] = [
                'type' => self::mapColumnType($col->Type),
                'label' => ucwords(str_replace('_', ' ', $col->Field)),
                'required' => ($col->Null === 'NO' && $col->Default === null),
                'default' => $col->Default,
            ];

            //extract enum values
            $enumValues = self::extractEnumValues($col->Type);
            if(!empty($enumValues)){
                //store allowed option for this field
                $fields[$col->Field]['options']=$enumValues;
            }
        }

        return ['fields' => $fields];
    }

    /**
     * Extract ENUM or SET values from MySQL column type definition.
     * 
     * Example input: "enum('Petrol','Diesel','Electric','Hybrid','CNG')"
     * Example output: ['Petrol', 'Diesel', 'Electric', 'Hybrid', 'CNG']
     * 
     * @param string $mysqlType The raw MySQL column type from SHOW COLUMNS
     * @return array Array of enum values, empty if not enum/set
     */
    private static function extractEnumValues(string $mysqlType):array
    {
        if(!str_starts_with(strtolower($mysqlType),'enum') && !str_starts_with(strtolower($mysqlType),'set')){
            return [];
        }

        //use regex to extract values between the parentheses from enum 
        // - \( matches opening parenthesis
        // - (.+?) captures everything inside (non-greedy)
        // - \) matches closing parenthesis
        if(preg_match('/\((.+?)\)/',$mysqlType,$matches)){
            //all vaues of enum in values string
            $valuesString = $matches[1];

            //remove quotes and split to get individual values
            return array_map(
                fn($value) => trim($value,"'\""),
                str_getcsv($valuesString)
            );
        }

        return [];
    }

    private static function mapColumnType(string $mysqlType): string
    {
        $type = strtolower($mysqlType);
        return match (true) {
            str_contains($type, 'int') => 'integer',
            str_contains($type, 'decimal'), str_contains($type, 'float'), str_contains($type, 'double') => 'decimal',
            str_contains($type, 'varchar'), str_contains($type, 'text'), str_contains($type, 'char') => 'string',
            str_contains($type, 'bool') => 'boolean',
            str_contains($type, 'date'), str_contains($type, 'timestamp') => 'datetime',
            //map enum and set to 'enum' type for form building
            str_contains($type,'enum'),str_contains($type,'set') => 'enum',
            default => 'string',
        };
    }
}