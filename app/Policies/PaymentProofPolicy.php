<?php

namespace App\Policies;

use App\Models\PaymentProof;
use App\Models\User;

class PaymentProofPolicy
{
    public function view(User $user, PaymentProof $proof): bool
    {
        return $user->status === 'active' && (
            $proof->enrollment->student_id === $user->id
            || ($user->isInstructor() && $proof->enrollment->offering->instructor_id === $user->id)
        );
    }

    public function manage(User $user, PaymentProof $proof): bool
    {
        return $user->isInstructor()
            && $user->status === 'active'
            && $proof->enrollment->offering->instructor_id === $user->id;
    }
}
