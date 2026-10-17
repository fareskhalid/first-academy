<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\AccessWaiver;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\PaymentProof;
use App\Services\PaymentWorkflow;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'revoked'])],
            'offering' => ['nullable', 'integer'],
        ]);
        $offerings = CourseOffering::where('instructor_id', $request->user()->id)
            ->with('paymentInstruction')->latest()->get();
        $allowedOfferingIds = $offerings->pluck('id');
        if (isset($filters['offering']) && ! $allowedOfferingIds->contains((int) $filters['offering'])) {
            abort(403);
        }

        $base = $this->ownedProofs($request)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['offering'] ?? null, fn (Builder $query, int $offering) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('course_offering_id', $offering)));
        $proofs = (clone $base)->with(['enrollment.student', 'enrollment.offering', 'reviewer'])->latest()->paginate(20)->withQueryString();

        foreach ($proofs->getCollection() as $proof) {
            $duplicates = $this->ownedProofs($request)->whereKeyNot($proof->id)->where(function ($query) use ($proof) {
                $query->where('sha256', $proof->sha256);
                if ($proof->payment_reference) {
                    $query->orWhere('payment_reference', $proof->payment_reference);
                }
            })->exists();
            $proof->setAttribute('has_duplicate_warning', $duplicates);
        }

        $counts = $this->ownedProofs($request)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $approvedTotals = $this->ownedProofs($request)->where('status', 'approved')
            ->selectRaw('confirmed_currency, sum(confirmed_amount_minor) as aggregate')->groupBy('confirmed_currency')->pluck('aggregate', 'confirmed_currency');
        $overdueCount = $this->ownedProofs($request)->where('status', 'pending')->where('created_at', '<=', now()->subDay())->count();
        $enrollments = Enrollment::whereHas('offering', fn ($query) => $query->where('instructor_id', $request->user()->id))
            ->where('status', 'enrolled')->with(['student', 'offering', 'accessWaivers' => fn ($query) => $query->whereNull('revoked_at')->latest('id')])
            ->orderBy('course_offering_id')->get();

        return view('instructor.payments', compact('proofs', 'offerings', 'counts', 'approvedTotals', 'overdueCount', 'enrollments', 'filters'));
    }

    public function instructions(Request $request, CourseOffering $offering)
    {
        Gate::authorize('manage', $offering);
        $data = $request->validate([
            'instructions' => ['required', 'string', 'max:5000'],
            'instructions_ar' => ['required', 'string', 'max:5000'],
            'reference_instructions' => ['nullable', 'string', 'max:1000'],
            'reference_instructions_ar' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $instruction = $offering->paymentInstruction()->updateOrCreate([], $data + ['is_active' => $request->boolean('is_active')]);
        Audit::record($request->user(), 'payment_instructions_saved', $instruction, [], null, $offering->instructor_id);

        return back()->with('status_key', 'payment_instructions_saved');
    }

    public function review(Request $request, PaymentProof $proof, PaymentWorkflow $payments)
    {
        Gate::authorize('manage', $proof);
        $request->merge([
            'confirmed_amount' => $this->normalizeNumber((string) $request->input('confirmed_amount')),
            'confirmed_currency' => strtoupper((string) $request->input('confirmed_currency')),
        ]);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['required', 'string', 'max:500'],
            'confirmed_amount' => ['nullable', 'required_if:decision,approved', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'confirmed_currency' => ['nullable', 'required_if:decision,approved', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ]);
        $payments->review(
            $request->user(),
            $proof,
            $data['decision'],
            $data['reason'],
            $data['decision'] === 'approved' ? $this->minorUnits($data['confirmed_amount']) : null,
            $data['decision'] === 'approved' ? $data['confirmed_currency'] : null,
        );

        return back()->with('status_key', 'payment_review_saved');
    }

    public function revoke(Request $request, PaymentProof $proof, PaymentWorkflow $payments)
    {
        Gate::authorize('manage', $proof);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $payments->revoke($request->user(), $proof, $data['reason']);

        return back()->with('status_key', 'payment_access_revoked_saved');
    }

    public function waive(Request $request, Enrollment $enrollment, PaymentWorkflow $payments)
    {
        Gate::authorize('manage', $enrollment->offering);
        $data = $request->validate([
            'valid_until' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $payments->waive($request->user(), $enrollment, Carbon::parse($data['valid_until'], config('academic.timezone')), $data['reason']);

        return back()->with('status_key', 'waiver_saved');
    }

    public function revokeWaiver(Request $request, AccessWaiver $waiver, PaymentWorkflow $payments)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $payments->revokeWaiver($request->user(), $waiver, $data['reason']);

        return back()->with('status_key', 'waiver_revoked');
    }

    public function export(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'revoked'])],
            'offering' => ['nullable', 'integer'],
        ]);
        $query = $this->ownedProofs($request)->with(['enrollment.student', 'enrollment.offering', 'reviewer'])
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['offering'] ?? null, fn (Builder $builder, int $offering) => $builder->whereHas('enrollment', fn ($enrollment) => $enrollment->where('course_offering_id', $offering)));

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [__('ui.student_code'), __('ui.student_name'), __('ui.course'), __('ui.status'), __('ui.claimed_amount'), __('ui.claimed_currency'), __('ui.confirmed_amount'), __('ui.confirmed_currency'), __('ui.payment_date'), __('ui.payment_method'), __('ui.payment_reference'), __('ui.reviewed_by'), __('ui.reviewed_at')]);
            $query->orderBy('id')->chunkById(200, function ($proofs) use ($stream) {
                foreach ($proofs as $proof) {
                    fputcsv($stream, array_map([$this, 'csvValue'], [
                        $proof->enrollment->student->student_code,
                        $proof->enrollment->student->name,
                        $proof->enrollment->offering->display_title,
                        __('ui.'.$proof->status),
                        number_format($proof->claimed_amount_minor / 100, 2, '.', ''),
                        $proof->currency,
                        $proof->confirmed_amount_minor === null ? null : number_format($proof->confirmed_amount_minor / 100, 2, '.', ''),
                        $proof->confirmed_currency,
                        $proof->payment_date->format('Y-m-d'),
                        $proof->payment_method,
                        $proof->payment_reference,
                        $proof->reviewer?->name,
                        $proof->reviewed_at?->timezone(config('academic.timezone'))->format('Y-m-d h:i A'),
                    ]));
                }
            });
            fclose($stream);
        }, 'payment-proofs-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function ownedProofs(Request $request): Builder
    {
        return PaymentProof::query()->whereHas('enrollment.offering', fn ($query) => $query->where('instructor_id', $request->user()->id));
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'".$value : $value;
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
