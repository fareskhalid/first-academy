<x-layout title="class_session">
@php
    $localStart = $classSession->scheduled_start_at->timezone($classSession->timezone)->locale(app()->getLocale());
    $localEnd = $classSession->scheduled_end_at->timezone($classSession->timezone)->locale(app()->getLocale());
    $localNow = now($classSession->timezone);
    $attendanceByEnrollment = $classSession->attendances->keyBy('enrollment_id');
    $expectedCount = $classSession->rosterEntries->count();
    $checkedInCount = $classSession->attendances->whereIn('status', ['present', 'late'])->count();
    $lateCount = $classSession->attendances->where('status', 'late')->count();
    $absentCount = $classSession->attendances->where('status', 'absent')->count();
    $attendanceRate = $expectedCount > 0 ? min(100, (int) round(($checkedInCount / $expectedCount) * 100)) : 0;
    $durationSeconds = max(1, $classSession->scheduled_start_at->diffInSeconds($classSession->scheduled_end_at));
    $elapsedSeconds = max(0, min($durationSeconds, $classSession->scheduled_start_at->diffInSeconds(now(), false)));
    $sessionProgress = (int) round(($elapsedSeconds / $durationSeconds) * 100);
    $windowState = $localNow->lt($classSession->attendance_opens_at->timezone($classSession->timezone))
        ? 'upcoming'
        : ($localNow->gte($classSession->attendance_closes_at->timezone($classSession->timezone)) ? 'closed' : 'open');
@endphp

<div class="session-page">
<section class="session-hero" data-status="{{ $classSession->display_status }}">
    <time class="session-hero-date" datetime="{{ $classSession->scheduled_start_at->toIso8601String() }}">
        <span>{{ $localStart->translatedFormat('M') }}</span>
        <strong>{{ $localStart->format('d') }}</strong>
        <small>{{ $localStart->translatedFormat('D') }}</small>
    </time>
    <div class="session-hero-copy">
        <div class="eyebrow">{{ $classSession->offering->course->code }} · {{ $classSession->offering->display_title }}</div>
        <h1>{{ $classSession->lesson->display_title }}</h1>
        <div class="session-hero-meta">
            <span><x-icon name="users" :size="16" />{{ $classSession->group->display_name }}</span>
            <time datetime="{{ $classSession->scheduled_start_at->toIso8601String() }}"><x-icon name="calendar" :size="16" /><bdi>{{ $localStart->translatedFormat('Y-m-d · h:i A') }} — {{ $localEnd->translatedFormat('h:i A') }}</bdi></time>
        </div>
    </div>
    <div class="session-hero-state">
        <x-status :value="$classSession->display_status" />
        <strong>{{ (int) round($localStart->diffInMinutes($localEnd)) }} <x-t k="minutes_short" /></strong>
        <span><x-t k="lecture_duration" /></span>
    </div>
    <div class="session-progress" aria-label="{{ __('ui.session_progress') }}">
        <span><i style="width: {{ $sessionProgress }}%"></i></span>
        <small>{{ $sessionProgress }}%</small>
    </div>
</section>

<nav class="session-toolbar" aria-label="{{ __('ui.session_navigation') }}">
    <div>
        <a class="button secondary" href="{{ route('instructor.lectures') }}"><x-icon name="arrow" :size="17" /><x-t k="back_lectures" /></a>
        <a class="button secondary" href="{{ route('instructor.schedule', ['week' => $localStart->toDateString()]) }}"><x-icon name="calendar" :size="17" /><x-t k="back_schedule" /></a>
    </div>
    @if(!$classSession->has_ended && in_array($classSession->status, ['published','in_progress']))
        <a class="button session-qr-action" href="{{ route('instructor.sessions.qr', $classSession) }}"><x-icon name="qr" :size="18" /><x-t k="show_qr" /></a>
    @endif
</nav>

<section class="session-insight-grid" aria-label="{{ __('ui.session_insights') }}">
    @foreach([
        ['expected', $expectedCount, 'users'],
        ['checked_in', $checkedInCount, 'check'],
        ['late', $lateCount, 'calendar'],
        ['absences', $absentCount, 'clipboard'],
    ] as [$label, $value, $icon])
    <article>
        <span><x-icon :name="$icon" :size="19" /></span>
        <div><strong>{{ $value }}</strong><small><x-t :k="$label" /></small></div>
    </article>
    @endforeach
</section>

<div class="session-overview-grid">
    <section class="panel session-control-card">
        <div class="session-section-heading">
            <span><x-icon name="settings" :size="21" /></span>
            <div><h2><x-t k="lecture_controls" /></h2><p><x-t k="lecture_controls_hint" /></p></div>
        </div>

        @if(in_array($classSession->status, ['completed','cancelled']))
            <div class="session-final-state"><x-icon name="check" :size="22" /><div><strong><x-t k="session_finalized" /></strong><small><x-t k="session_finalized_hint" /></small></div></div>
        @else
            @if($classSession->display_status === 'ended')
                <div class="session-action-warning"><x-icon name="clipboard" :size="19" /><span><x-t k="ended_lecture_action_hint" /></span></div>
            @endif
            <form method="POST" action="{{ route('instructor.sessions.transition', $classSession) }}" class="session-action-form">
                @csrf
                <x-select name="action" label="action">
                    @if(in_array($classSession->status, ['published','in_progress']) && in_array($classSession->display_status, ['in_progress','ended']))<option value="complete">{{ __('ui.complete_class') }}</option>@endif
                    @if(in_array($classSession->status, ['draft','published']))<option value="cancel">{{ __('ui.cancel_class') }}</option>@endif
                    @if(in_array($classSession->status, ['published','in_progress']) && $classSession->display_status === 'in_progress')<option value="close_attendance">{{ __('ui.close_attendance') }}</option>@endif
                </x-select>
                <x-field name="reason" required maxlength="500" />
                <button class="primary"><x-icon name="check" :size="17" /><x-t k="apply_action" /></button>
            </form>
        @endif
    </section>

    <section class="panel session-timing-card">
        <div class="session-section-heading">
            <span><x-icon name="calendar" :size="21" /></span>
            <div><h2><x-t k="attendance_timing" /></h2><p><x-t k="attendance_timing_hint" /></p></div>
            <b class="session-window-state state-{{ $windowState }}"><x-t :k="'attendance_window_state_'.$windowState" /></b>
        </div>
        <div class="session-timeline">
            @foreach([
                ['opens', $classSession->attendance_opens_at, 'open'],
                ['late_after', $classSession->late_after_at, 'late'],
                ['closes', $classSession->attendance_closes_at, 'closed'],
            ] as [$label, $time, $state])
            <div class="session-timeline-step state-{{ $state }}">
                <span></span>
                <div><small><x-t :k="$label" /></small><strong><bdi>{{ $time->timezone($classSession->timezone)->locale(app()->getLocale())->translatedFormat('h:i A') }}</bdi></strong></div>
            </div>
            @endforeach
        </div>
        <div class="session-rate">
            <div class="session-rate-ring" style="--attendance-rate: {{ $attendanceRate * 3.6 }}deg"><span>{{ $attendanceRate }}%</span></div>
            <div><strong><x-t k="attendance_rate" /></strong><small><x-t k="attendance_rate_hint" :params="['checked' => $checkedInCount, 'expected' => $expectedCount]" /></small></div>
        </div>
    </section>
</div>

@if(!in_array($classSession->status, ['completed','cancelled']))
<details class="panel session-reschedule">
    <summary><span><x-icon name="calendar" :size="19" /><x-t k="reschedule" /></span><small><x-t k="reschedule_hint" /></small></summary>
    <form method="POST" action="{{ route('instructor.sessions.update', $classSession) }}" class="stack">
        @csrf @method('PUT')
        <div class="grid two">
            <x-select name="group_id" label="group">@foreach($classSession->offering->groups->whereNull('archived_at') as $group)<option value="{{ $group->id }}" @selected($group->id === $classSession->group_id)>{{ $group->display_name }}</option>@endforeach</x-select>
            <x-select name="lesson_id" label="lesson">@foreach($classSession->offering->lessons->whereNull('archived_at') as $lesson)<option value="{{ $lesson->id }}" @selected($lesson->id === $classSession->lesson_id)>{{ $lesson->position }}. {{ $lesson->display_title }}</option>@endforeach</x-select>
        </div>
        <div class="grid two">
            <x-field name="scheduled_start" type="datetime-local" :value="$localStart->format('Y-m-d\TH:i')" required />
            <x-field name="scheduled_end" type="datetime-local" :value="$localEnd->format('Y-m-d\TH:i')" required />
        </div>
        <x-field name="reason" id="reschedule-reason" required maxlength="500" />
        <x-field name="conflict_override_reason" id="reschedule-conflict-reason" maxlength="500" />
        <button class="primary"><x-icon name="check" :size="17" /><x-t k="save" /></button>
    </form>
</details>
@endif

<section class="panel session-roster-panel">
    <div class="session-roster-heading">
        <div class="session-section-heading">
            <span><x-icon name="users" :size="21" /></span>
            <div><h2><x-t k="student_attendance" /></h2><p><x-t k="attendance_roster_hint" /></p></div>
        </div>
        <div class="session-roster-summary"><strong>{{ $checkedInCount }}</strong><span>/ {{ $expectedCount }}</span></div>
    </div>

    <div class="session-roster-list">
    @forelse($classSession->rosterEntries as $entry)
        @php($attendance = $attendanceByEnrollment->get($entry->enrollment_id))
        @php($attendanceStatus = $attendance?->status ?? $entry->status)
        <article class="session-roster-item" data-status="{{ $attendanceStatus }}">
            <div class="session-student-avatar">{{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($entry->enrollment->student->name, 0, 1)) }}</div>
            <div class="session-student-copy">
                <strong>{{ $entry->enrollment->student->name }}</strong>
                <span><bdi>{{ $entry->enrollment->student->student_code }}</bdi>@if($attendance?->observed_at) · <x-t k="observed_at" /> <bdi>{{ $attendance->observed_at->timezone($classSession->timezone)->locale(app()->getLocale())->translatedFormat('h:i A') }}</bdi>@endif</span>
            </div>
            <x-status :value="$attendanceStatus" />
            <details class="session-correction">
                <summary><x-icon name="settings" :size="16" /><x-t k="correct_attendance" /></summary>
                <form method="POST" action="{{ route('instructor.sessions.attendance.correct', $classSession) }}" class="session-correction-form">
                    @csrf
                    <input type="hidden" name="enrollment_id" value="{{ $entry->enrollment_id }}">
                    <x-select name="status" :id="'attendance-status-'.$entry->id">
                        @foreach(['present','late','absent','excused','void'] as $value)<option value="{{ $value }}" @selected($attendanceStatus === $value)>{{ __('ui.'.$value) }}</option>@endforeach
                    </x-select>
                    <x-field name="observed_at" :id="'attendance-observed-'.$entry->id" type="datetime-local" :value="$attendance?->observed_at?->timezone($classSession->timezone)->format('Y-m-d\TH:i')" />
                    <x-field name="reason" :id="'attendance-reason-'.$entry->id" required maxlength="500" />
                    <x-check name="payment_exception" :id="'attendance-payment-'.$entry->id" label="payment_exception" />
                    <button class="primary"><x-icon name="check" :size="16" /><x-t k="save_correction" /></button>
                </form>
            </details>
        </article>
    @empty
        <div class="session-roster-empty"><span><x-icon name="users" :size="24" /></span><div><strong><x-t k="no_roster_title" /></strong><small><x-t k="roster_captured_at_open" /></small></div></div>
    @endforelse
    </div>
</section>

<section class="panel session-makeup-card">
    <div class="session-section-heading">
        <span><x-icon name="shield" :size="21" /></span>
        <div><h2><x-t k="makeup_authorization" /></h2><p><x-t k="makeup_authorization_hint" /></p></div>
    </div>
    @if($eligibleEnrollments->isNotEmpty())
    <form method="POST" action="{{ route('instructor.sessions.makeup', $classSession) }}" class="session-makeup-form">
        @csrf
        <x-select name="enrollment_id" label="student" required>@foreach($eligibleEnrollments as $enrollment)<option value="{{ $enrollment->id }}">{{ $enrollment->student->name }} · {{ $enrollment->group->display_name }}</option>@endforeach</x-select>
        <x-field name="reason" id="makeup-reason" required maxlength="500" />
        <button class="secondary"><x-icon name="shield" :size="17" /><x-t k="authorize_makeup" /></button>
    </form>
    @else
        <p class="muted"><x-t k="no_eligible_students" /></p>
    @endif
</section>
</div>
</x-layout>
