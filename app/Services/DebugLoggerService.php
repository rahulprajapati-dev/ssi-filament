<?php

namespace App\Services;

use App\Models\DebugLog;
use Illuminate\Support\Facades\Auth;
use Throwable;

class DebugLoggerService
{
    protected string $channel;
    protected ?string $stage    = null;
    protected ?string $stageId  = null;

    public function __construct(string $channel = 'general')
    {
        $this->channel = $channel;
    }

    // ─── Fluent setters ──────────────────────────────────────────────────────

    public function channel(string $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    public function stage(string $stage, $stageId = null): static
    {
        $this->stage   = $stage;
        $this->stageId = $stageId !== null ? (string) $stageId : null;
        return $this;
    }

    // ─── Log level shortcuts ─────────────────────────────────────────────────

    public function info(string $message, array $payload = []): DebugLog
    {
        return $this->write('info', $message, $payload);
    }

    public function debug(string $message, array $payload = []): DebugLog
    {
        return $this->write('debug', $message, $payload);
    }

    public function warning(string $message, array $payload = []): DebugLog
    {
        return $this->write('warning', $message, $payload);
    }

    public function error(string $message, array $payload = []): DebugLog
    {
        return $this->write('error', $message, $payload);
    }

    /**
     * Log an exception with full trace inside payload.
     */
    public function exception(Throwable $e, array $extra = []): DebugLog
    {
        $payload = array_merge([
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => collect($e->getTrace())->take(10)->toArray(),
        ], $extra);

        // debug_logs.message is VARCHAR(255); SQL/HTTP exception messages
        // often embed the full query and overflow the column, causing the
        // log insert to silently fail. Clip the summary here — full text
        // is preserved in payload['message'] above.
        $summary = mb_substr($e->getMessage(), 0, 250);

        return $this->write('error', $summary, $payload);
    }

    // ─── Core writer ─────────────────────────────────────────────────────────

    protected function write(string $level, string $message, array $payload): DebugLog
    {
        $caller = $this->resolveCallerMethod();

        return DebugLog::create([
            'channel'      => $this->channel,
            'stage'        => $this->stage,
            'stage_id'     => $this->stageId,
            'level'        => $level,
            'message'      => $message,
            'payload'      => $payload,
            'triggered_by' => $caller,
            'user_id'      => Auth::id(),
            'ip_address'   => request()?->ip(),
        ]);
    }

    /**
     * Resolve the calling class::method outside this service.
     */
    protected function resolveCallerMethod(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            if ($class && !str_contains($class, 'DebugLoggerService')) {
                return ($frame['class'] ?? '') . '::' . ($frame['function'] ?? '');
            }
        }

        return 'unknown';
    }

    // ─── Static factory ──────────────────────────────────────────────────────

    /**
     * Quick static entry point.
     *
     * Usage:
     *   DebugLoggerService::for('warranty')->stage('pricing', $warrantyId)->info('Price calculated', $data);
     */
    public static function for(string $channel): static
    {
        return new static($channel);
    }

    // ─── Query helpers ───────────────────────────────────────────────────────

    public static function getByStageId($stageId, ?string $channel = null)
    {
        $query = DebugLog::stageId($stageId)->latest();

        if ($channel) {
            $query->channel($channel);
        }

        return $query->get();
    }

    public static function getByStage(string $stage, ?string $channel = null)
    {
        $query = DebugLog::stage($stage)->latest();

        if ($channel) {
            $query->channel($channel);
        }

        return $query->get();
    }

    public static function getErrors(?string $channel = null)
    {
        $query = DebugLog::errors()->latest();

        if ($channel) {
            $query->channel($channel);
        }

        return $query->get();
    }
}