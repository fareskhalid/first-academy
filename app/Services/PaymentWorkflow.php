<?php

namespace App\Services;

use App\Models\AccessWaiver;
use App\Models\CourseEntitlement;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\PaymentProof;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notices;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentWorkflow
{
    public function submit(User $student, Enrollment $enrollment, UploadedFile $file, array $data): PaymentProof
    {
        abort_unless($enrollment->student_id === $student->id, 403);
        try {
            $path = $file->store('payment-proofs/'.$enrollment->id, 'local');
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['proof' => __('ui.payment_upload_failed')]);
        }
        if (! $path) {
            throw ValidationException::withMessages(['proof' => __('ui.payment_upload_failed')]);
        }

        try {
            return DB::transaction(function () use ($student, $enrollment, $file, $path, $data) {
                $enrollment = Enrollment::with(['offering.instructor'])->lockForUpdate()->findOrFail($enrollment->id);
                if ($enrollment->status !== 'enrolled' || $enrollment->offering->status !== 'open') {
                    throw ValidationException::withMessages(['proof' => __('ui.payment_enrollment_inactive')]);
                }
                if ($enrollment->paymentProofs()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['proof' => __('ui.payment_pending_exists')]);
                }

                $previous = $enrollment->paymentProofs()->whereIn('status', ['rejected', 'revoked'])->first();
                $proof = $enrollment->paymentProofs()->create([
                    'claimed_amount_minor' => $data['claimed_amount_minor'],
                    'currency' => PaymentProof::CURRENCY,
                    'payment_date' => $data['payment_date'],
                    'payment_method' => PaymentProof::METHOD,
                    'payment_reference' => null,
                    'supersedes_id' => $previous?->id,
                    'file_disk' => 'local',
                    'file_path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 240, ''),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'status' => 'pending',
                    'pending_slot' => 1,
                ]);

                Audit::record($student, 'payment_proof_submitted', $proof, [
                    'enrollment_id' => $enrollment->id,
                    'currency' => $proof->currency,
                    'claimed_amount_minor' => $proof->claimed_amount_minor,
                ], null, $enrollment->offering->instructor_id);
                Notices::sendOnce(
                    $enrollment->offering->instructor,
                    'payment-proof-submitted-'.$proof->id,
                    'payment_proof_submitted_notice',
                    ['student' => $student->name, 'course' => $this->offeringTitle($enrollment->offering, $enrollment->offering->instructor)],
                    route('instructor.payments.index', ['status' => 'pending']),
                );

                return $proof;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function review(User $instructor, PaymentProof $proof, string $decision, string $reason, ?int $confirmedAmountMinor = null): PaymentProof
    {
        return DB::transaction(function () use ($instructor, $proof, $decision, $reason, $confirmedAmountMinor) {
            $proof = PaymentProof::with(['enrollment.offering.semester', 'enrollment.student'])->lockForUpdate()->findOrFail($proof->id);
            abort_unless($proof->enrollment->offering->instructor_id === $instructor->id, 403);

            if ($proof->status === $decision) {
                return $proof;
            }
            if ($proof->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => __('ui.payment_already_reviewed')]);
            }

            $proof->update([
                'status' => $decision,
                'pending_slot' => null,
                'reviewed_by' => $instructor->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
                'confirmed_amount_minor' => $decision === 'approved' ? $confirmedAmountMinor : null,
                'confirmed_currency' => $decision === 'approved' ? PaymentProof::CURRENCY : null,
            ]);
            $proof->reviews()->create([
                'reviewer_id' => $instructor->id,
                'decision' => $decision,
                'confirmed_amount_minor' => $decision === 'approved' ? $confirmedAmountMinor : null,
                'confirmed_currency' => $decision === 'approved' ? PaymentProof::CURRENCY : null,
                'reason' => $reason,
            ]);

            if ($decision === 'approved') {
                $validUntil = Carbon::parse(
                    $proof->enrollment->offering->semester->ends_on->format('Y-m-d').' 23:59:59',
                    config('academic.timezone'),
                );
                CourseEntitlement::firstOrCreate(
                    ['source_type' => 'payment_proof', 'source_id' => $proof->id],
                    [
                        'enrollment_id' => $proof->enrollment_id,
                        'valid_from' => now(),
                        'valid_until' => $validUntil,
                    ],
                );
            }

            Audit::record($instructor, 'payment_proof_'.$decision, $proof, ['status' => $decision], $reason);
            Notices::sendOnce(
                $proof->enrollment->student,
                'payment-proof-'.$decision.'-'.$proof->id,
                'payment_proof_'.$decision.'_notice',
                ['course' => $this->offeringTitle($proof->enrollment->offering, $proof->enrollment->student), 'reason' => $reason],
                route('student.payments.index'),
            );

            return $proof;
        }, 3);
    }

    public function revoke(User $instructor, PaymentProof $proof, string $reason): PaymentProof
    {
        return DB::transaction(function () use ($instructor, $proof, $reason) {
            $proof = PaymentProof::with(['enrollment.offering', 'enrollment.student'])->lockForUpdate()->findOrFail($proof->id);
            abort_unless($proof->enrollment->offering->instructor_id === $instructor->id, 403);
            if ($proof->status === 'revoked') {
                return $proof;
            }
            if ($proof->status !== 'approved') {
                throw ValidationException::withMessages(['revocation' => __('ui.only_approved_payment_revoked')]);
            }

            $proof->update([
                'status' => 'revoked',
                'revoked_by' => $instructor->id,
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ]);
            $proof->reviews()->create([
                'reviewer_id' => $instructor->id,
                'decision' => 'revoked',
                'confirmed_amount_minor' => $proof->confirmed_amount_minor,
                'confirmed_currency' => $proof->confirmed_currency,
                'reason' => $reason,
            ]);
            CourseEntitlement::where('source_type', 'payment_proof')->where('source_id', $proof->id)
                ->whereNull('revoked_at')->update(['revoked_at' => now()]);

            Audit::record($instructor, 'payment_proof_revoked', $proof, ['status' => 'revoked'], $reason);
            Notices::sendOnce(
                $proof->enrollment->student,
                'payment-proof-revoked-'.$proof->id,
                'payment_proof_revoked_notice',
                ['course' => $this->offeringTitle($proof->enrollment->offering, $proof->enrollment->student), 'reason' => $reason],
                route('student.payments.index'),
            );

            return $proof;
        }, 3);
    }

    public function waive(User $instructor, Enrollment $enrollment, Carbon $validUntil, string $reason): AccessWaiver
    {
        return DB::transaction(function () use ($instructor, $enrollment, $validUntil, $reason) {
            $enrollment = Enrollment::with(['offering', 'student'])->lockForUpdate()->findOrFail($enrollment->id);
            abort_unless($enrollment->offering->instructor_id === $instructor->id, 403);
            if (! $validUntil->isFuture()) {
                throw ValidationException::withMessages(['valid_until' => __('ui.waiver_must_be_future')]);
            }

            $waiver = $enrollment->accessWaivers()->create([
                'issued_by' => $instructor->id,
                'scope' => 'course',
                'reason' => $reason,
                'valid_from' => now(),
                'valid_until' => $validUntil,
            ]);
            Audit::record($instructor, 'access_waiver_issued', $waiver, ['valid_until' => $validUntil->toIso8601String()], $reason);
            Notices::sendOnce(
                $enrollment->student,
                'access-waiver-issued-'.$waiver->id,
                'access_waiver_issued_notice',
                ['course' => $this->offeringTitle($enrollment->offering, $enrollment->student), 'until' => $validUntil->locale($enrollment->student->locale)->translatedFormat('j F Y, h:i A')],
                route('student.payments.index'),
            );

            return $waiver;
        }, 3);
    }

    public function revokeWaiver(User $instructor, AccessWaiver $waiver, string $reason): AccessWaiver
    {
        return DB::transaction(function () use ($instructor, $waiver, $reason) {
            $waiver = AccessWaiver::with(['enrollment.offering', 'enrollment.student'])->lockForUpdate()->findOrFail($waiver->id);
            abort_unless($waiver->enrollment->offering->instructor_id === $instructor->id, 403);
            if ($waiver->revoked_at) {
                return $waiver;
            }
            $waiver->update(['revoked_at' => now(), 'revoked_by' => $instructor->id, 'revocation_reason' => $reason]);
            Audit::record($instructor, 'access_waiver_revoked', $waiver, [], $reason);
            Notices::sendOnce(
                $waiver->enrollment->student,
                'access-waiver-revoked-'.$waiver->id,
                'access_waiver_revoked_notice',
                ['course' => $this->offeringTitle($waiver->enrollment->offering, $waiver->enrollment->student), 'reason' => $reason],
                route('student.payments.index'),
            );

            return $waiver;
        }, 3);
    }

    private function offeringTitle(CourseOffering $offering, User $recipient): string
    {
        return $recipient->locale === 'ar' ? $offering->title_ar : $offering->title;
    }
}
