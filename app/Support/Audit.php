<?php

namespace App\Support;

use App\Models\AuditLog;

class Audit
{
    /** Best-effort audit log; never throws into the caller. */
    public static function log(string $action, array $opts = []): void
    {
        try {
            $actor = auth()->user();
            AuditLog::create([
                'actor_id'    => $opts['actor_id']    ?? $actor?->id,
                'actor_email' => $opts['actor_email'] ?? $actor?->email,
                'action'      => $action,
                'entity'      => $opts['entity']   ?? null,
                'entity_id'   => isset($opts['entity_id']) ? (string) $opts['entity_id'] : null,
                'detail'      => $opts['detail']   ?? null,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // swallow — auditing must never break the request
        }
    }
}
