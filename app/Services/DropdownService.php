<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class DropdownService
{
    private static string $path = 'SSI/dropdowns/list.json';

    /**
     * GET dropdown by key
     */
    public static function get(string $domainName): array
    {
        $data = self::readJson();

        return $data[$domainName] ?? [];
    }

    /**
     * ADD new option into dropdown
     */
    public static function add(string $domainName, string $key, string $value): array
    {
        $data = self::readJson();

        if (!isset($data[$domainName])) {
            $data[$domainName] = [];
        }

        $data[$domainName][$key] = $value;

        self::writeJson($data);

        return $data[$domainName];
    }

    /**
     * EDIT existing dropdown option
     */
    public static function edit(string $domainName, string $key, string $value): array
    {
        $data = self::readJson();

        if (!isset($data[$domainName][$key])) {
            throw new \Exception("Key '{$key}' not found in {$domainName}");
        }

        $data[$domainName][$key] = $value;

        self::writeJson($data);

        return $data[$domainName];
    }

    /**
     * DELETE option
     */
    public static function delete(string $domainName, string $key): array
    {
        $data = self::readJson();

        if (isset($data[$domainName][$key])) {
            unset($data[$domainName][$key]);
        }

        self::writeJson($data);

        return $data[$domainName] ?? [];
    }

    /**
     * READ JSON
     */
    private static function readJson(): array
    {
        $path = base_path(self::$path);

        if (!File::exists($path)) {
            File::put($path, json_encode([], JSON_PRETTY_PRINT));
        }

        return json_decode(File::get($path), true) ?? [];
    }

    /**
     * WRITE JSON
     */
    private static function writeJson(array $data): void
    {
        $path = base_path(self::$path);

        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}