<x-layout title="my_courses">
<div class="student-page student-dashboard">
<section class="student-welcome-card">
    <div class="student-welcome-main">
        <div class="student-avatar"><x-icon name="graduation" :size="27" /></div>
        <div><p class="student-kicker"><x-t k="welcome" /></p><h1>{{ auth()->user()->name }}</h1><p><x-t k="student_code" /> · <bdi>{{ auth()->user()->student_code }}</bdi></p></div>
    </div>
    <time datetime="{{ now()->toDateString() }}">{{ now()->locale(app()->getLocale())->translatedFormat('l، j F') }}</time>
</section>

<section class="student-dashboard-insights" aria-label="{{ __('ui.learning_overview') }}">
    <article><span><x-icon name="book" :size="20" /></span><div><strong>{{ $insights['active_courses'] }}</strong><small><x-t k="active_courses" /></small></div></article>
    <article><span><x-icon name="check" :size="20" /></span><div><strong>{{ $insights['attendance_rate'] === null ? '—' : $insights['attendance_rate'].'%' }}</strong><small><x-t k="attendance_rate" /></small></div></article>
    <article><span><x-icon name="calendar" :size="20" /></span><div><strong>{{ $insights['upcoming_classes'] }}</strong><small><x-t k="upcoming_classes" /></small></div></article>
</section>

@if($nextSession)
<a class="next-class-card" href="{{ route('student.courses.show',$nextSession->offering) }}">
    <span class="next-class-date"><strong>{{ $nextSession->scheduled_start_at->timezone($nextSession->timezone)->format('d') }}</strong><small>{{ $nextSession->scheduled_start_at->timezone($nextSession->timezone)->locale(app()->getLocale())->translatedFormat('M') }}</small></span>
    <span class="next-class-copy"><span class="eyebrow"><x-t k="next_class" /></span><strong>{{ $nextSession->offering->display_title }}</strong><span>{{ $nextSession->lesson->display_title }} · {{ $nextSession->group->display_name }}</span><time>{{ $nextSession->scheduled_start_at->timezone($nextSession->timezone)->locale(app()->getLocale())->translatedFormat('l · H:i') }}</time></span>
    <span class="next-class-arrow"><x-icon name="arrow" :size="20" /></span>
</a>
@else
<div class="student-no-next-class"><span><x-icon name="calendar" :size="22" /></span><div><strong><x-t k="schedule_clear" /></strong><small><x-t k="no_upcoming_class" /></small></div></div>
@endif

<section class="student-action-card student-checkin-card">
    <div class="student-action-heading"><span><x-icon name="qr" :size="23" /></span><div><h2><x-t k="enter_class_code" /></h2><p><x-t k="class_code_hint" /></p></div></div>
    <form method="POST" action="{{ route('attendance.code') }}" class="student-code-form" x-data="{ submitting: false }" @submit="submitting = true">@csrf
        <x-field name="code" label="short_code" required minlength="6" maxlength="6" dir="ltr" inputmode="numeric" autocomplete="off" />
        <button class="primary" :disabled="submitting"><span x-show="!submitting"><x-icon name="check" :size="18" /><x-t k="continue_checkin" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
    </form>
</section>

<div class="student-section-heading"><div><p class="eyebrow"><x-t k="your_progress" /></p><h2><x-t k="my_courses" /></h2></div><a href="{{ route('student.courses') }}"><x-t k="view_all" /><x-icon name="arrow" :size="16" /></a></div>
<div class="student-course-list">
@forelse($enrollments as $enrollment)
@php($attendanceTotal = $enrollment->attended_count + $enrollment->absent_count)
@php($courseRate = $attendanceTotal > 0 ? (int) round(($enrollment->attended_count / $attendanceTotal) * 100) : null)
<article class="student-course-card">
    <div class="student-course-icon"><x-icon name="book" :size="23" /></div>
    <div class="student-course-copy"><span>{{ $enrollment->offering->semester->display_name }} · {{ $enrollment->offering->course->code }}</span><h3>{{ $enrollment->offering->display_title }}</h3><p>{{ $enrollment->group?->display_name }}</p>
        <div class="student-course-progress"><span><i style="width: {{ $courseRate ?? 0 }}%"></i></span><small>{{ $courseRate === null ? __('ui.no_attendance_data') : $courseRate.'% '.__('ui.attendance_rate') }}</small></div>
    </div>
    <div class="student-course-meta"><x-status :value="$enrollment->status" /><a href="{{ route('student.courses.show',$enrollment->offering) }}" aria-label="{{ __('ui.view_course') }}"><x-icon name="arrow" :size="20" /></a></div>
</article>
@empty
<div class="empty"><h2><x-t k="no_courses" /></h2><p class="muted"><x-t k="no_courses_hint" /></p></div>
@endforelse
</div>
</div>
</x-layout>
