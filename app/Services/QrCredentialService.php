<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\QrCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QrCredentialService
{
    public function issue(ClassSession $session): array
    {
        return DB::transaction(function () use ($session) {
            $session = ClassSession::lockForUpdate()->findOrFail($session->id);
            $this->assertDisplayable($session);

            $session->qrCredentials()->whereNull('revoked_at')->where('expires_at', '<=', now())->update(['revoked_at' => now()]);
            $token = Str::random(64);
            $shortCode = strtoupper(Str::random(6));
            $credential = $session->qrCredentials()->create([
                'revision' => $session->qr_revision,
                'token_hash' => hash('sha256', $token),
                'short_code_hash' => hash('sha256', $shortCode),
                'issued_at' => now(),
                'expires_at' => now()->addSeconds((int) config('academic.qr_token_seconds', 60)),
            ]);

            return compact('credential', 'token', 'shortCode');
        });
    }

    public function fromToken(string $token): QrCredential
    {
        $credential = $this->validQuery()->where('token_hash', hash('sha256', $token))->first();
        if (! $credential) throw ValidationException::withMessages(['attendance' => __('ui.qr_expired')]);

        return $credential;
    }

    public function fromShortCode(string $code): QrCredential
    {
        $credential = $this->validQuery()->where('short_code_hash', hash('sha256', strtoupper(trim($code))))->first();
        if (! $credential) throw ValidationException::withMessages(['code' => __('ui.qr_expired')]);

        return $credential;
    }

    public function revoke(ClassSession $session): void
    {
        $session->qrCredentials()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $session->intents()->whereNull('revoked_at')->whereNull('consumed_at')->update(['revoked_at' => now()]);
        $session->increment('qr_revision');
    }

    private function validQuery()
    {
        return QrCredential::with(['session.offering.course', 'session.group', 'session.lesson'])
            ->whereNull('revoked_at')->where('expires_at', '>', now())
            ->whereHas('session', fn ($query) => $query->whereIn('status', ['published', 'in_progress'])
                ->where('attendance_opens_at', '<=', now())->where('attendance_closes_at', '>', now()));
    }

    private function assertDisplayable(ClassSession $session): void
    {
        if (! in_array($session->status, ['published', 'in_progress'], true)
            || now()->lt($session->attendance_opens_at) || ! now()->lt($session->attendance_closes_at)) {
            throw ValidationException::withMessages(['session' => __('ui.attendance_window_closed')]);
        }
    }
}
