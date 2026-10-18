<x-layout title="payments">
<div class="payment-page instructor-payment-page">
    <header class="payment-hero">
        <span class="payment-hero-icon"><x-icon name="wallet" :size="31" /></span>
        <div><p class="eyebrow"><x-t k="course_access" /></p><h1><x-t k="payments" /></h1><p><x-t k="instructor_payments_hint" /></p></div>
        <a class="button payment-export" href="{{ route('instructor.payments.export', request()->only(['status','offering'])) }}"><x-icon name="download" :size="17" /><x-t k="export_csv" /></a>
    </header>

    <section class="payment-insights">
        <article><span><x-icon name="clipboard" :size="20" /></span><div><strong>{{ $counts['pending'] ?? 0 }}</strong><small><x-t k="pending_reviews" /></small></div></article>
        <article class="is-warning"><span><x-icon name="warning" :size="20" /></span><div><strong>{{ $overdueCount }}</strong><small><x-t k="overdue_reviews" /></small></div></article>
        <article class="is-success"><span><x-icon name="check" :size="20" /></span><div><strong>{{ $counts['approved'] ?? 0 }}</strong><small><x-t k="approved_proofs" /></small></div></article>
        <article><span><x-icon name="wallet" :size="20" /></span><div><strong>@forelse($approvedTotals as $currency=>$minor){{ number_format($minor / 100, 2) }} {{ $currency }}@if(!$loop->last)<br>@endif @empty—@endforelse</strong><small><x-t k="approved_claimed_totals" /></small></div></article>
    </section>

    <details class="panel payment-settings">
        <summary><span><x-icon name="settings" :size="19" /><x-t k="payment_instruction_settings" /></span><x-icon name="arrow" :size="16" /></summary>
        <div class="payment-instruction-grid">
        @forelse($offerings as $offering)
            <details><summary><strong>{{ $offering->display_title }}</strong><x-status :value="$offering->paymentInstruction?->is_active ? 'active' : 'inactive'" /></summary>
                <form method="POST" action="{{ route('instructor.payments.instructions', $offering) }}" class="stack" x-data="{ submitting: false }" @submit="submitting = true">@csrf @method('PUT')
                    <div class="grid two"><div class="field"><label for="instructions-{{ $offering->id }}"><x-t k="payment_instructions_en" /></label><textarea id="instructions-{{ $offering->id }}" name="instructions" required maxlength="5000" dir="ltr">{{ old('instructions', $offering->paymentInstruction?->instructions) }}</textarea></div><div class="field"><label for="instructions-ar-{{ $offering->id }}"><x-t k="payment_instructions_ar" /></label><textarea id="instructions-ar-{{ $offering->id }}" name="instructions_ar" required maxlength="5000" dir="rtl">{{ old('instructions_ar', $offering->paymentInstruction?->instructions_ar) }}</textarea></div></div>
                    <div class="grid two"><div class="field"><label for="reference-{{ $offering->id }}"><x-t k="reference_instructions_en" /></label><textarea id="reference-{{ $offering->id }}" name="reference_instructions" maxlength="1000" dir="ltr">{{ old('reference_instructions', $offering->paymentInstruction?->reference_instructions) }}</textarea></div><div class="field"><label for="reference-ar-{{ $offering->id }}"><x-t k="reference_instructions_ar" /></label><textarea id="reference-ar-{{ $offering->id }}" name="reference_instructions_ar" maxlength="1000" dir="rtl">{{ old('reference_instructions_ar', $offering->paymentInstruction?->reference_instructions_ar) }}</textarea></div></div>
                    <x-check name="is_active" label="show_payment_instructions" :checked="$offering->paymentInstruction?->is_active ?? true" />
                    <button class="primary" :disabled="submitting"><x-t k="save" /></button>
                </form>
            </details>
        @empty<p class="muted"><x-t k="no_courses" /></p>@endforelse
        </div>
    </details>

    <section class="panel payment-filter-panel">
        <form method="GET" class="payment-filters">
            <x-select name="status" label="status"><option value=""><x-t k="all_statuses" /></option>@foreach(['pending','approved','rejected','revoked'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)><x-t :k="$status" /></option>@endforeach</x-select>
            <x-select name="offering" label="course"><option value=""><x-t k="all_courses" /></option>@foreach($offerings as $offering)<option value="{{ $offering->id }}" @selected((int)($filters['offering'] ?? 0) === $offering->id)>{{ $offering->display_title }}</option>@endforeach</x-select>
            <button class="primary"><x-icon name="search" :size="17" /><x-t k="search_action" /></button>
            <a class="button secondary" href="{{ route('instructor.payments.index') }}"><x-t k="clear_filters" /></a>
        </form>
    </section>

    <div class="payment-review-list">
    @forelse($proofs as $proof)
        <article class="payment-review-card {{ $proof->status !== 'pending' ? 'is-reviewed' : '' }}">
            <header><div class="payment-student"><span>{{ mb_substr($proof->enrollment->student->name, 0, 1) }}</span><div><h2>{{ $proof->enrollment->student->name }}</h2><p><bdi>{{ $proof->enrollment->student->student_code }}</bdi> · {{ $proof->enrollment->offering->display_title }}</p></div></div><x-status :value="$proof->status" /></header>
            <div class="payment-proof-details">
                <div><small><x-t k="claimed_amount_egp" /></small><strong>{{ number_format($proof->claimed_amount_minor / 100, 2) }} EGP</strong></div>
                <div><small><x-t k="payment_date" /></small><strong>{{ $proof->payment_date->locale(app()->getLocale())->translatedFormat('j F Y') }}</strong></div>
            </div>
            @if($proof->has_duplicate_warning)<div class="payment-warning"><x-icon name="warning" :size="17" /><x-t k="duplicate_payment_warning" /></div>@endif
            <div class="payment-review-meta"><span><x-t k="submitted_at" /> · {{ $proof->created_at->timezone(config('academic.timezone'))->locale(app()->getLocale())->translatedFormat('j F Y, h:i A') }}</span><a class="button secondary" target="_blank" rel="noopener" href="{{ route('payment-proofs.file', $proof) }}"><x-icon name="download" :size="17" /><x-t k="inspect_proof" /></a></div>
            @if($proof->status === 'pending')
                <form method="POST" action="{{ route('instructor.payments.review', $proof) }}" class="payment-review-form" x-data="{ submitting: false }" @submit="submitting = true">@csrf
                    <x-field name="confirmed_amount" :id="'confirmed-amount-'.$proof->id" label="confirmed_amount_egp" :value="number_format($proof->claimed_amount_minor / 100, 2, '.', '')" inputmode="decimal" pattern="[0-9٠-٩۰-۹]+([.,٫][0-9٠-٩۰-۹]{1,2})?" maxlength="13" />
                    <x-field name="reason" :id="'review-reason-'.$proof->id" label="review_reason" required maxlength="500" />
                    <div><button class="primary" name="decision" value="approved" :disabled="submitting"><x-icon name="check" :size="17" /><x-t k="approve" /></button><button class="danger" name="decision" value="rejected" :disabled="submitting"><x-icon name="close" :size="17" /><x-t k="reject" /></button></div>
                </form>
            @else
                <div class="payment-decision-summary"><strong><x-t k="review_reason" /></strong><p>{{ $proof->review_reason ?: $proof->revocation_reason }}</p>@if($proof->reviewer)<small>{{ $proof->reviewer->name }} · {{ $proof->reviewed_at?->timezone(config('academic.timezone'))->locale(app()->getLocale())->translatedFormat('j F Y, h:i A') }}</small>@endif</div>
                @if($proof->status === 'approved')<details class="payment-revoke"><summary><x-t k="revoke_payment" /></summary><form method="POST" action="{{ route('instructor.payments.revoke',$proof) }}" class="stack">@csrf<x-field name="reason" :id="'revoke-reason-'.$proof->id" label="revocation_reason" required maxlength="500" /><button class="danger"><x-t k="confirm_revocation" /></button></form></details>@endif
            @endif
        </article>
    @empty<div class="empty"><h2><x-t k="no_payment_proofs" /></h2><p class="muted"><x-t k="no_payment_proofs_hint" /></p></div>@endforelse
    </div>
    <div class="mt-5">{{ $proofs->links('livewire.pagination') }}</div>

    <details class="panel waiver-panel">
        <summary><span><x-icon name="shield" :size="19" /><x-t k="temporary_waivers" /></span><x-icon name="arrow" :size="16" /></summary>
        <div class="waiver-grid">
        @foreach($enrollments as $enrollment)
            <details><summary><span><strong>{{ $enrollment->student->name }}</strong><small>{{ $enrollment->offering->display_title }}</small></span>@if($enrollment->accessWaivers->first())<x-status value="waived" />@endif</summary>
                @if($waiver = $enrollment->accessWaivers->first())
                    <p><x-t k="waiver_until" /> {{ $waiver->valid_until->timezone(config('academic.timezone'))->locale(app()->getLocale())->translatedFormat('j F Y, h:i A') }}</p><p class="muted">{{ $waiver->reason }}</p>
                    <form method="POST" action="{{ route('instructor.waivers.revoke',$waiver) }}" class="stack">@csrf<x-field name="reason" :id="'waiver-revoke-'.$waiver->id" label="revocation_reason" required maxlength="500" /><button class="danger"><x-t k="revoke_waiver" /></button></form>
                @else
                    <form method="POST" action="{{ route('instructor.waivers.store',$enrollment) }}" class="stack">@csrf<x-field name="valid_until" :id="'waiver-until-'.$enrollment->id" label="valid_until" type="datetime-local" required :min="now()->addMinute()->format('Y-m-d\TH:i')" /><x-field name="reason" :id="'waiver-reason-'.$enrollment->id" required maxlength="500" /><button class="primary"><x-t k="grant_waiver" /></button></form>
                @endif
            </details>
        @endforeach
        </div>
    </details>
</div>
</x-layout>
