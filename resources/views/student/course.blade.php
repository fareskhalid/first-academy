<x-layout title="course">
<div class="student-page">
<header class="student-page-header course-header">
    <a class="student-back" href="{{ route('dashboard') }}"><x-icon name="arrow" :size="19" /></a>
    <div><p class="eyebrow">{{ $offering->semester->display_name }} · {{ $offering->course->code }}</p><h1>{{ $offering->display_title }}</h1><p>{{ $offering->instructor->name }}</p></div>
    <x-status :value="$enrollment->status" />
</header>

<div class="student-summary-grid">
    <div><span><x-t k="group" /></span><strong>{{ $enrollment->group?->display_name }}</strong></div>
    <div><span><x-t k="remaining_free" /></span><strong>{{ $remainingFree }}</strong></div>
</div>

<section class="student-action-card">
    <div class="student-action-heading"><span><x-icon name="qr" :size="23" /></span><div><h2><x-t k="class_code" /></h2><p><x-t k="class_code_hint" /></p></div></div>
    <form method="POST" action="{{ route('attendance.code') }}" class="student-code-form" x-data="{ submitting: false }" @submit="submitting = true">@csrf
        <x-field name="code" label="short_code" required minlength="6" maxlength="6" dir="ltr" inputmode="numeric" autocomplete="off" />
        <button class="primary" :disabled="submitting"><span x-show="!submitting"><x-t k="continue_checkin" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
    </form>
</section>

@if($offering->course->display_description)
<section class="student-info-card"><h2><x-t k="details" /></h2><p>{{ $offering->course->display_description }}</p><div class="access-summary"><x-status :value="$accessSource ? 'access_available' : 'payment_required'" /></div></section>
@endif

<section class="student-content-section">
    <div class="student-section-heading"><h2><x-t k="published_schedule" /></h2></div>
    <div class="student-session-list">
    @forelse($sessions as $session)
    <article class="student-session-card"><div class="student-session-date"><strong>{{ $session->scheduled_start_at->timezone($offering->timezone)->format('d') }}</strong><span>{{ $session->scheduled_start_at->timezone($offering->timezone)->locale(app()->getLocale())->translatedFormat('M') }}</span></div><div><h3>{{ $session->lesson->display_title }}</h3><p>{{ $session->scheduled_start_at->timezone($offering->timezone)->locale(app()->getLocale())->translatedFormat('l · H:i') }}</p><span>{{ $session->location ?: __('ui.location_not_set') }}</span></div><x-status :value="$session->status" /></article>
    @empty<p class="muted"><x-t k="no_published_sessions" /></p>@endforelse
    </div>
</section>

<section class="student-info-card"><details><summary><x-t k="group_history" /></summary>
@foreach($enrollment->memberships as $membership)<p class="muted">{{ $membership->group->display_name }} · {{ $membership->starts_at->timezone($offering->timezone)->format('Y-m-d') }} — @if($membership->ends_at){{ $membership->ends_at->timezone($offering->timezone)->format('Y-m-d') }}@else<x-t k="present" />@endif</p>@endforeach
@if($enrollment->status === 'enrolled' && $offering->status === 'open' && $offering->groups->whereNull('archived_at')->count() > 1)
<details><summary><x-t k="request_transfer" /></summary><form method="POST" action="{{ route('student.transfer',$enrollment) }}" class="stack">@csrf<x-select name="group_id" label="group" required>@foreach($offering->groups->whereNull('archived_at')->where('id','!=',$enrollment->group_id) as $group)<option value="{{ $group->id }}">{{ $group->display_name }}</option>@endforeach</x-select><x-field name="reason" required maxlength="500" /><button class="primary"><x-t k="request_transfer" /></button></form></details>
@endif
@foreach($enrollment->transferRequests as $transfer)<p class="muted">{{ $transfer->group->display_name }} · <x-status :value="$transfer->status" /></p>@endforeach
</details></section>

<section class="student-content-section"><div class="student-section-heading"><h2><x-t k="attendance_history" /></h2></div>
<div class="student-attendance-list">@forelse($enrollment->attendances as $attendance)<div><span><strong>{{ $attendance->lesson->display_title }}</strong><small>{{ $attendance->session->scheduled_start_at->timezone($offering->timezone)->format('Y-m-d') }}</small></span><x-status :value="$attendance->status" /></div>@empty<p class="muted"><x-t k="no_attendance" /></p>@endforelse</div>
</section>
</div>
</x-layout>
