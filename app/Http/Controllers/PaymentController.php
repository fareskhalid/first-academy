<?php

namespace App\Http\Controllers;

use App\Contracts\CourseAccess;
use App\Models\Enrollment;
use App\Models\PaymentProof;
use App\Services\PaymentWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function index(Request $request, CourseAccess $access)
    {
        abort_if($request->user()->isInstructor(), 403);
        $enrollments = $request->user()->enrollments()
            ->with([
                'offering.course', 'offering.semester', 'offering.paymentInstruction',
                'paymentProofs.reviewer', 'accessWaivers' => fn ($query) => $query->latest('id'),
            ])
            ->latest()->get();

        foreach ($enrollments as $enrollment) {
            $attended = $access->attendedCount($enrollment);
            $enrollment->setAttribute('remaining_free', max(0, (int) config('academic.free_attended_lessons', 2) - $attended));
            $enrollment->setAttribute('access_source', $access->source($enrollment, now()));
        }

        return view('student.payments', compact('enrollments'));
    }

    public function store(Request $request, Enrollment $enrollment, PaymentWorkflow $payments)
    {
        abort_if($request->user()->isInstructor(), 403);
        Gate::authorize('view', $enrollment);
        $request->merge([
            'claimed_amount' => $this->normalizeNumber((string) $request->input('claimed_amount')),
        ]);
        $data = $request->validate([
            'proof' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf'],
            'claimed_amount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $payments->submit($request->user(), $enrollment, $request->file('proof'), [
            'claimed_amount_minor' => $this->minorUnits($data['claimed_amount']),
            'payment_date' => $data['payment_date'],
        ]);

        return redirect()->route('student.payments.index')->with('status_key', 'payment_proof_submitted');
    }

    public function file(Request $request, PaymentProof $proof)
    {
        $proof->loadMissing('enrollment.offering');
        Gate::authorize('view', $proof);
        abort_unless(Storage::disk($proof->file_disk)->exists($proof->file_path), 404);

        $disposition = str_starts_with($proof->mime_type, 'image/') ? 'inline' : 'attachment';

        return Storage::disk($proof->file_disk)->response(
            $proof->file_path,
            basename($proof->original_name),
            [
                'Content-Type' => $proof->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            ],
            $disposition,
        );
    }

    private function normalizeNumber(string $value): string
    {
        return strtr(trim($value), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', ',' => '.',
        ]);
    }

    private function minorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
