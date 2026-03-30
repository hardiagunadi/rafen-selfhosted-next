<?php

namespace App\Traits;

use App\Models\ActivityLog;

trait LogsActivity
{
    protected function logActivity(
        string $action,
        string $subjectType,
        ?int $subjectId,
        string $label,
        int $ownerId = 0,
        array $properties = []
    ): void {
        ActivityLog::create([
            'user_id'       => auth()->id(),
            'owner_id'      => $ownerId ?: auth()->id(),
            'action'        => $action,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'subject_label' => $label,
            'properties'    => $properties ?: null,
            'ip_address'    => request()->ip(),
        ]);
    }
}
