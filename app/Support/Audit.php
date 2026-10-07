<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(?User $actor, string $action, Model $subject, array $changes = [], ?string $reason = null, ?int $instructorId = null): void
    {
        // Deliberately never capture request payloads, credentials, or invitation tokens.
        AuditEvent::create([
            'actor_id' => $actor?->id, 'instructor_id' => $instructorId ?? ($actor?->isInstructor() ? $actor->id : null),
            'action' => $action, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'changes' => array_diff_key($changes, array_flip(['password', 'phone', 'whatsapp_phone', 'token', 'remember_token'])), 'reason' => $reason,
        ]);
    }
}
