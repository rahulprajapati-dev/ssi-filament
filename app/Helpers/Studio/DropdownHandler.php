<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

class DropdownHandler
{
    /** Resolve the absolute path to the dropdown JSON file. */
    protected static function filePath(): string
    {
        return config('studio.dropdown_path') ?: base_path('app/Helpers/Studio/Doms/app_doms.json');
    }

    /**
     * GET ALL OPTIONS BY KEY
     *
     * User file (list.json) takes precedence; Studio config DOMs are the fallback
     * so callers never need to know which source a group lives in.
     */
    public static function getStudioDom(string $key): array
    {
        $user = self::readFile();

        if (isset($user[$key])) {
            return $user[$key];
        }

        return config('studio_doms.' . $key, []);
    }

    public static function get(string $modulename, string $fieldname, ?string $type = ''): array
    {
        $user = self::readFile();
        $baseKey = "{$modulename}_{$fieldname}_dom";
        $key = $type
            ? "{$modulename}_{$type}_{$fieldname}_dom"
            : $baseKey;
        if (isset($user[$key])) {
            return $user[$key];
        }
        
        if ($type && isset($user[$baseKey])) {
            return $user[$baseKey];
        }

        return config('studio_doms.' . $key, []);
    }

    /**
     * ADD / UPDATE SINGLE VALUE
     */
    public static function set(string $group, string $key, string $value, string $type = ''): bool
    {
        $group = $type ? "{$type}_{$group}" : $group;
        return self::modifyFile(function (array &$data) use ($group, $key, $value): void {
            if (!isset($data[$group])) {
                $data[$group] = [];
            }
            $data[$group][$key] = $value;
        });
    }

    /**
     * DELETE SINGLE KEY
     */
    public static function delete(string $group, string $key, string $type = ''): bool
    {
        $group = $type ? "{$type}_{$group}" : $group;
        return self::modifyFile(function (array &$data) use ($group, $key): void {
            if (isset($data[$group][$key])) {
                unset($data[$group][$key]);
            }
        });
    }

    /**
     * CREATE NEW GROUP
     */
    public static function createGroup(string $module, string $fieldname, array $options): bool
    {
        
        $baseName = $module . '_' . $fieldname . '_dom';

        return self::modifyFile(function (array &$data) use ($module, $fieldname, $baseName, $options): void {
            $base = [];
            $groups = [];

            foreach ($options as $option) {
                $key = $option['key'] ?? null;
                $value = $option['value'] ?? null;
                if ($key === null) {
                    continue;
                }
                // Common/base dropdown — every option goes here
                $base[$key] = $value;
                // Per-trigger groups — same option duplicated into each type it depends on
                $types = $option['dependent_value'] ?? [];
                if (is_string($types)) {
                    $types = [$types];
                }
                foreach ($types as $type) {
                    if ($type === '' || $type === null) {
                        continue;
                    }
                    $groups[$type][$key] = $value;
                }
            }
            $data[$baseName] = $base;
            foreach ($groups as $type => $dropdown) {
                $data["{$module}_{$type}_{$fieldname}_dom"] = $dropdown;
            }
        });
    }

    /**
     * DELETE ENTIRE GROUP
     */
    public static function deleteGroup(string $group): bool
    {
        $prefix = $group . '_';
        return self::modifyFile(function (array &$data) use ($prefix): void {
            foreach (array_keys($data) as $key) {
                if (str_starts_with($key, $prefix)) {
                    unset($data[$key]);
                }
            }
        });
    }

    /**
     * GET ALL DROPDOWNS
     *
     * Returns Studio config DOMs merged with user file DOMs.
     * User file entries win on key collision.
     */
    public static function all(): array
    {
        $studio = config('studio_doms', []);
        $user = self::readFile();

        return array_merge($studio, $user);
    }

    /**
     * Returns true when the given group key is a Studio-owned DOM
     * (lives in config/studio_doms.php, not in the user's list.json).
     */
    public static function isStudioDom(string $key): bool
    {
        return array_key_exists($key, config('studio_doms', []));
    }

    /**
     * READ FILE
     */
    protected static function readFile(): array
    {
        $file = self::filePath();

        if (!file_exists($file)) {
            self::createFile();
            return [];
        }

        $fp = fopen($file, 'r');
        if ($fp === false) {
            return [];
        }
        try {
            flock($fp, LOCK_SH);
            $json = stream_get_contents($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * ATOMIC READ-MODIFY-WRITE
     *
     * Opens the file once, acquires LOCK_EX before reading, applies $callback
     * (which receives $data by reference), then writes back — all under the
     * same exclusive lock.  This eliminates the TOCTOU race that existed when
     * readFile() (LOCK_SH) and writeFile() (LOCK_EX) were called as two
     * separate file-open/lock cycles.
     *
     * @param  callable(array &$data): void  $callback
     */
    protected static function modifyFile(callable $callback): bool
    {
        $file = self::filePath();

        if (!file_exists($file)) {
            self::createFile();
        }

        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return false;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }
            // Read under the exclusive lock
            rewind($fp);
            $json = stream_get_contents($fp);
            $data = json_decode($json ?: '{}', true);
            if (!is_array($data)) {
                $data = [];
            }
            // Apply the caller's modification
            $callback($data);
            // Write back under the same exclusive lock (no gap between read and write)
            ftruncate($fp, 0);
            rewind($fp);
            $written = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            return $written !== false;
        } finally {
            fclose($fp);
        }
    }

    /**
     * WRITE FILE SAFELY
     *
     * @deprecated  Use modifyFile() for any read-then-write operation so the
     *              read and write share a single LOCK_EX.  writeFile() is kept
     *              only for callers that build $data outside of the lock window
     *              (none currently in this class).
     */
    protected static function writeFile(array $data): bool
    {
        $file = self::filePath();

        $fp = fopen($file, 'c');
        if ($fp === false) {
            return false;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }
            ftruncate($fp, 0);
            rewind($fp);
            $written = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            return $written !== false;
        } finally {
            fclose($fp);
        }
    }

    /**
     * CREATE FILE IF NOT EXISTS
     */
    protected static function createFile(): void
    {
        $file = self::filePath();

        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
    }
}