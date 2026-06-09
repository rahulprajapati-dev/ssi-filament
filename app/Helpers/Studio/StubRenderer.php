<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Renders a stub file by replacing {{PLACEHOLDER}} tokens with values.
 *
 * All generators must go through this class so stub resolution stays
 * in one place and can be swapped (e.g. for testing) without touching
 * individual generators.
 */
final class StubRenderer
{
    /**
     * Render a stub file and return the resulting string.
     *
     * Keys in $vars should match the token name WITHOUT braces.
     * e.g. ['MODEL' => 'CustomerOrder'] replaces {{MODEL}}.
     *
     * @param  array<string, string>  $vars
     * @throws RuntimeException  if the stub file is missing
     */
    public static function render(string $stub, array $vars): string
    {
        $path = self::stubPath($stub);

        if (! File::exists($path)) {
            throw new RuntimeException(
                "Studio stub [{$stub}] not found at [{$path}]."
            );
        }

        $content = File::get($path);

        foreach ($vars as $token => $value) {
            $content = str_replace('{{' . $token . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Resolve the absolute path to a stub file.
     */
    public static function stubPath(string $stub): string
    {
        return app_path("Helpers/Studio/Stubs/{$stub}");
    }
}
