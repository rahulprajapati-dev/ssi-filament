<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

/**
 * Immutable value object returned by StudioManager::deploy().
 *
 * Using a typed object instead of a bare array makes IDEs, static
 * analysis, and call-sites all happier while remaining backward-
 * compatible via toArray().
 */
final class DeploymentResult
{
    /**
     * @param  list<string>  $generated  Names of steps that created new files.
     * @param  list<string>  $skipped    Names of steps that were skipped (files existed).
     */
    private function __construct(
        public readonly bool   $success,
        public readonly string $message,
        public readonly array  $generated = [],
        public readonly array  $skipped   = [],
    ) {}

    // ─── Factory methods ─────────────────────────────────────────────────────

    public static function ok(
        string $message,
        array  $generated = [],
        array  $skipped   = [],
    ): self {
        return new self(true, $message, $generated, $skipped);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Backward-compatible array form used by existing hook callers that
     * still check $res['status'] / $res['msg'].
     *
     * @return array{status: bool, msg: string, generated: list<string>, skipped: list<string>}
     */
    public function toArray(): array
    {
        return [
            'status'    => $this->success,
            'msg'       => $this->message,
            'generated' => $this->generated,
            'skipped'   => $this->skipped,
        ];
    }
}
