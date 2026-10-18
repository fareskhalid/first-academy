<x-layout title="my_payments">
@php
    $stateFor = static fn ($enrollment) => match (true) {
        $enrollment->paymentProofs->first()?->status === 'approved' => 'payment_approved',
        $enrollment->access_source === 'waiver' => 'waived',
        $enrollment->paymentProofs->first()?->status === 'pending' => 'payment_pending',
        $enrollment->paymentProofs->first()?->status === 'rejected' => 'payment_rejected',
        $enrollment->paymentProofs->first()?->status === 'revoked' => 'payment_revoked',
        $enrollment->remaining_free > 0 => 'free_access',
        default => 'payment_required',
    };
    $paymentStates = $enrollments->mapWithKeys(fn ($enrollment) => [$enrollment->id => $stateFor($enrollment)]);
    $accessCount = $paymentStates->filter(fn ($state) => in_array($state, ['payment_approved', 'waived', 'free_access'], true))->count();
    $reviewCount = $paymentStates->filter(fn ($state) => $state === 'payment_pending')->count();
    $actionCount = $paymentStates->filter(fn ($state) => in_array($state, ['payment_required', 'payment_rejected', 'payment_revoked'], true))->count();
@endphp
<div class="student-page payment-page student-payments-page">
    <header class="payment-hero student-payment-hero">
        <span class="payment-hero-icon"><x-icon name="wallet" :size="29" /></span>
        <div><p class="eyebrow"><x-t k="course_access" /></p><h1><x-t k="my_payments" /></h1><p><x-t k="student_payments_hint" /></p></div>
        <strong class="student-payment-total"><span>{{ $enrollments->count() }}</span><x-t k="payment_courses" /></strong>
    </header>

    @if($enrollments->isNotEmpty())
    <section class="student-payment-insights" aria-label="{{ __('ui.payment_overview') }}">
        <article><span><x-icon name="shield" :size="19" /></span><div><strong>{{ $accessCount }}</strong><small><x-t k="courses_with_access" /></small></div></article>
        <article class="is-review"><span><x-icon name="clipboard" :size="19" /></span><div><strong>{{ $reviewCount }}</strong><small><x-t k="payments_in_review" /></small></div></article>
        <article class="is-action"><span><x-icon name="warning" :size="19" /></span><div><strong>{{ $actionCount }}</strong><small><x-t k="payments_need_action" /></small></div></article>
    </section>
    @endif

    <div class="student-payment-heading"><div><p class="eyebrow"><x-t k="enrolled_courses" /></p><h2><x-t k="payment_overview" /></h2></div></div>

    <div class="payment-course-list student-payment-course-list">
    @forelse($enrollments as $enrollment)
        @php($latest = $enrollment->paymentProofs->first())
        @php($instruction = $enrollment->offering->paymentInstruction)
        @php($state = $paymentStates[$enrollment->id])
        @php($canSubmit = $instruction?->is_active && !in_array($latest?->status, ['pending', 'approved'], true) && $enrollment->status === 'enrolled')
        @php($stateIcon = match($state) { 'payment_approved' => 'check', 'payment_pending' => 'clipboard', 'waived', 'free_access' => 'shield', default => 'warning' })
        <article class="payment-course-card student-payment-course is-{{ $state }}" id="enrollment-{{ $enrollment->id }}">
            <header class="payment-course-heading">
                <span><x-icon name="book" :size="22" /></span>
                <div><small>{{ $enrollment->offering->semester->display_name }} · {{ $enrollment->offering->course->code }}</small><h2>{{ $enrollment->offering->display_title }}</h2></div>
            </header>

            <section class="student-payment-state">
                <span class="student-payment-state-icon"><x-icon :name="$stateIcon" :size="21" /></span>
                <div><small><x-t k="course_access" /></small><strong><x-t :k="$state" /></strong><p><x-t :k="$state.'_hint'" /></p></div>
                <div class="student-payment-free"><small><x-t k="free_attendance_remaining" /></small><strong>{{ $enrollment->remaining_free }}</strong></div>
            </section>

            @if($latest?->status === 'rejected' || $latest?->status === 'revoked')
                <div class="payment-decision-note is-danger"><x-icon name="warning" :size="18" /><div><strong><x-t k="instructor_note" /></strong><p>{{ $latest->status === 'rejected' ? $latest->review_reason : $latest->revocation_reason }}</p></div></div>
            @endif

            @if($canSubmit)
                <ol class="student-payment-journey" aria-label="{{ __('ui.payment_steps') }}">
                    <li class="is-ready"><span>1</span><x-t k="payment_instructions" /></li>
                    <li class="is-ready"><span>2</span><x-t k="receipt_details" /></li>
                    <li><span>3</span><x-t k="instructor_review" /></li>
                </ol>

                <section class="student-payment-instructions">
                    <header><span>1</span><div><small><x-t k="payment_step" /></small><h3><x-t k="payment_instructions" /></h3></div></header>
                    <div class="student-payment-instruction-copy">
                        <p>{!! nl2br(e($instruction->display_instructions)) !!}</p>
                        @if($instruction->display_reference_instructions)<div><strong><x-t k="reference_instructions" /></strong><p>{!! nl2br(e($instruction->display_reference_instructions)) !!}</p></div>@endif
                    </div>
                </section>

                <section class="student-payment-upload">
                    <header><span>2</span><div><small><x-t k="payment_step" /></small><h3><x-t k="upload_payment_proof" /></h3><p><x-t k="payment_submit_hint" /></p></div></header>
                    <form method="POST" action="{{ route('student.payments.store', $enrollment) }}" enctype="multipart/form-data" class="stack" x-data="{ submitting: false, fileName: '' }" @submit="submitting = true">
                        @csrf<input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                        <label class="student-payment-file" for="proof-{{ $enrollment->id }}">
                            <span><x-icon name="upload" :size="24" /></span>
                            <strong><x-t k="choose_receipt" /></strong>
                            <small x-text="fileName || @js(__('ui.payment_file_hint'))"><x-t k="payment_file_hint" /></small>
                            <input id="proof-{{ $enrollment->id }}" name="proof" type="file" accept="image/jpeg,image/png,application/pdf" required @change="fileName = $event.target.files[0]?.name || ''">
                        </label>
                        <div class="student-payment-fields"><x-field name="claimed_amount" :id="'claimed-amount-'.$enrollment->id" label="claimed_amount_egp" required inputmode="decimal" pattern="[0-9٠-٩۰-۹]+([.,٫][0-9٠-٩۰-۹]{1,2})?" maxlength="13" /><x-field name="payment_date" :id="'payment-date-'.$enrollment->id" label="payment_date" type="date" required :max="now()->toDateString()" /></div>
                        <div class="payment-fixed-note"><x-icon name="wallet" :size="17" /><span><x-t k="payment_fixed_details" /></span></div>
                        <button class="primary student-payment-submit" :disabled="submitting"><span x-show="!submitting"><x-icon name="upload" :size="18" /><x-t k="submit_payment_proof" /></span><span x-cloak x-show="submitting"><x-t k="uploading" /></span></button>
                    </form>
                </section>
            @elseif($instruction?->is_active)
                <details class="student-payment-instructions-compact">
                    <summary><span><x-icon name="wallet" :size="18" /><x-t k="payment_instructions" /></span><x-icon name="arrow" :size="16" /></summary>
                    <div><p>{!! nl2br(e($instruction->display_instructions)) !!}</p>@if($instruction->display_reference_instructions)<strong><x-t k="reference_instructions" /></strong><p>{!! nl2br(e($instruction->display_reference_instructions)) !!}</p>@endif</div>
                </details>
            @elseif(!in_array($latest?->status, ['pending', 'approved'], true) && $enrollment->status === 'enrolled')
                <div class="payment-info-note"><x-icon name="warning" :size="18" /><span><x-t k="payment_instructions_unavailable" /></span></div>
            @endif

            @if($enrollment->paymentProofs->isNotEmpty())
                <details class="payment-history"><summary><span><x-icon name="clipboard" :size="18" /><x-t k="submission_history" /> ({{ $enrollment->paymentProofs->count() }})</span><x-icon name="arrow" :size="16" /></summary>
                    <div class="payment-history-list">
                    @foreach($enrollment->paymentProofs as $proof)
                        <div><span><x-status :value="'payment_'.$proof->status" /><small>{{ $proof->created_at->timezone(config('academic.timezone'))->locale(app()->getLocale())->translatedFormat('j F Y, h:i A') }}</small></span><strong>{{ number_format($proof->claimed_amount_minor / 100, 2) }} {{ $proof->currency }}</strong><a href="{{ route('payment-proofs.file', $proof) }}"><x-icon name="download" :size="16" /><x-t k="view_proof" /></a></div>
                    @endforeach
                    </div>
                </details>
            @endif
        </article>
    @empty
        <div class="empty"><h2><x-t k="no_courses" /></h2><p class="muted"><x-t k="no_courses_hint" /></p></div>
    @endforelse
    </div>
</div>
</x-layout>
