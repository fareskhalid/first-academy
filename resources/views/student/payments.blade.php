<x-layout title="my_payments">
<div class="student-page payment-page">
    <header class="payment-hero student-payment-hero">
        <span class="payment-hero-icon"><x-icon name="wallet" :size="29" /></span>
        <div><p class="eyebrow"><x-t k="course_access" /></p><h1><x-t k="my_payments" /></h1><p><x-t k="student_payments_hint" /></p></div>
    </header>

    <div class="payment-course-list">
    @forelse($enrollments as $enrollment)
        @php($latest = $enrollment->paymentProofs->first())
        @php($instruction = $enrollment->offering->paymentInstruction)
        @php($state = match(true) {
            $latest?->status === 'approved' => 'payment_approved',
            $enrollment->access_source === 'waiver' => 'waived',
            $latest?->status === 'pending' => 'payment_pending',
            $latest?->status === 'rejected' => 'payment_rejected',
            $latest?->status === 'revoked' => 'payment_revoked',
            $enrollment->remaining_free > 0 => 'free_access',
            default => 'payment_required',
        })
        <article class="payment-course-card" id="enrollment-{{ $enrollment->id }}">
            <header class="payment-course-heading">
                <span><x-icon name="book" :size="22" /></span>
                <div><small>{{ $enrollment->offering->semester->display_name }} · {{ $enrollment->offering->course->code }}</small><h2>{{ $enrollment->offering->display_title }}</h2></div>
                <x-status :value="$state" />
            </header>

            <div class="payment-access-strip">
                <div><small><x-t k="free_attendance_remaining" /></small><strong>{{ $enrollment->remaining_free }}</strong></div>
                <p><x-t :k="$state.'_hint'" /></p>
            </div>

            @if($instruction?->is_active)
                <section class="payment-instructions">
                    <h3><x-icon name="wallet" :size="18" /><x-t k="payment_instructions" /></h3>
                    <p>{!! nl2br(e($instruction->display_instructions)) !!}</p>
                    @if($instruction->display_reference_instructions)<div><strong><x-t k="reference_instructions" /></strong><p>{!! nl2br(e($instruction->display_reference_instructions)) !!}</p></div>@endif
                </section>
            @else
                <div class="payment-info-note"><x-icon name="warning" :size="18" /><span><x-t k="payment_instructions_unavailable" /></span></div>
            @endif

            @if($latest?->status === 'rejected' || $latest?->status === 'revoked')
                <div class="payment-decision-note is-danger"><strong><x-t :k="$latest->status" /></strong><p>{{ $latest->status === 'rejected' ? $latest->review_reason : $latest->revocation_reason }}</p></div>
            @endif

            @if($instruction?->is_active && !in_array($latest?->status, ['pending', 'approved'], true) && $enrollment->status === 'enrolled')
                <details class="payment-upload" @if($errors->any() && old('enrollment_id') == $enrollment->id) open @endif>
                    <summary><span><x-icon name="upload" :size="18" /><x-t k="upload_payment_proof" /></span><x-icon name="arrow" :size="16" /></summary>
                    <form method="POST" action="{{ route('student.payments.store', $enrollment) }}" enctype="multipart/form-data" class="stack" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf<input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                        <div class="field"><label for="proof-{{ $enrollment->id }}"><x-t k="payment_proof_file" /></label><input id="proof-{{ $enrollment->id }}" name="proof" type="file" accept="image/jpeg,image/png,application/pdf" required><span class="muted"><x-t k="payment_file_hint" /></span></div>
                        <div class="grid two"><x-field name="claimed_amount" :id="'claimed-amount-'.$enrollment->id" label="claimed_amount" required inputmode="decimal" pattern="[0-9٠-٩۰-۹]+([.,٫][0-9٠-٩۰-۹]{1,2})?" maxlength="13" /><x-field name="currency" :id="'currency-'.$enrollment->id" label="currency" value="EGP" required minlength="3" maxlength="3" dir="ltr" /></div>
                        <div class="grid two"><x-field name="payment_date" :id="'payment-date-'.$enrollment->id" label="payment_date" type="date" required :max="now()->toDateString()" /><x-field name="payment_method" :id="'payment-method-'.$enrollment->id" label="payment_method" required maxlength="100" /></div>
                        <x-field name="payment_reference" :id="'payment-reference-'.$enrollment->id" label="payment_reference" maxlength="160" hint="payment_reference_optional" />
                        <button class="primary" :disabled="submitting"><span x-show="!submitting"><x-icon name="upload" :size="18" /><x-t k="submit_payment_proof" /></span><span x-cloak x-show="submitting"><x-t k="uploading" /></span></button>
                    </form>
                </details>
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
