<?php

namespace App\Helpers;

class DropdownHandler
{
    protected static string $filePath = 'SSI/Dropdowns/list.json';

    /**
     * GET ALL OPTIONS BY KEY
     */
    public static function get(string $key): array
    {
        $data = self::readFile();

        return $data[$key] ?? [];
    }

    /**
     * ADD / UPDATE SINGLE VALUE
     */
    public static function set(string $group, string $key, string $value): bool
    {
        $data = self::readFile();

        if (!isset($data[$group])) {
            $data[$group] = [];
        }

        $data[$group][$key] = $value;

        return self::writeFile($data);
    }

    /**
     * DELETE SINGLE KEY
     */
    public static function delete(string $group, string $key): bool
    {
        $data = self::readFile();

        if (isset($data[$group][$key])) {
            unset($data[$group][$key]);
        }

        return self::writeFile($data);
    }

    /**
     * CREATE NEW GROUP
     */
    public static function createGroup(string $group): bool
    {
        $data = self::readFile();

        if (!isset($data[$group])) {
            $data[$group] = [];
        }

        return self::writeFile($data);
    }

    /**
     * DELETE ENTIRE GROUP
     */
    public static function deleteGroup(string $group): bool
    {
        $data = self::readFile();

        if (isset($data[$group])) {
            unset($data[$group]);
        }

        return self::writeFile($data);
    }

    /**
     * GET ALL DROPDOWNS
     */
    public static function all(): array
    {
        return self::readFile();
    }

    /**
     * READ FILE
     */
    protected static function readFile(): array
    {
        $file = base_path(self::$filePath);

        if (!file_exists($file)) {
            self::createFile();
            return [];
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * WRITE FILE SAFELY
     */
    protected static function writeFile(array $data): bool
    {
        $file = base_path(self::$filePath);

        return file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ) !== false;
    }

    /**
     * CREATE FILE IF NOT EXISTS
     */
    protected static function createFile(): void
    {
        $file = base_path(self::$filePath);

        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
    }
}