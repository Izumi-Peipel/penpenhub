<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class AuditLogger
{
    public static function log(
        string $action,
        ?Model $subject = null,
        array $meta = [],
        ?int $companyId = null,
        ?Authenticatable $actor = null,
    ): ActivityLog {
        $actor ??= Auth::user();

        return ActivityLog::create([
            'user_id' => $actor?->getAuthIdentifier(),
            'company_id' => $companyId,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip' => request()?->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
