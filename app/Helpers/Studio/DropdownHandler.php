<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

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
    public static function delete(string $group, string $key): bool
    {
        return self::modifyFile(function (array &$data) use ($group, $key): void {
            if (isset($data[$group][$key])) {
                unset($data[$group][$key]);
            }
        });
    }

    /**
     * CREATE NEW GROUP
     */
    public static function createGroup(string $module, string $fieldname, $options): bool
    {
        $name = $module . '_' . $fieldname . '_dom';

        return self::modifyFile(function (array &$data) use ($name, $options): void {
            if (!isset($data[$name])) {
                $dropdown = [];
                foreach ($options as $option) {
                    $dropdown[$option['key']] = $option['value'];
                }
                $data[$name] = $dropdown;
            }
        });
    }

    /**
     * DELETE ENTIRE GROUP
     */
    public static function deleteGroup(string $group): bool
    {
        return self::modifyFile(function (array &$data) use ($group): void {
            if (isset($data[$group])) {
                unset($data[$group]);
            }
        });
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
        $file = base_path(self::$filePath);

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
        $file = base_path(self::$filePath);

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
        $file = base_path(self::$filePath);

        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, json_encode(new \stdClass(), JSON_PRETTY_PRINT));
    }
}