<x-layout title="my_courses">
<div class="student-page">
<section class="student-welcome">
    <div class="student-avatar"><x-icon name="graduation" :size="25" /></div>
    <div><p class="student-kicker"><x-t k="welcome" /></p><h1>{{ auth()->user()->name }}</h1><p><bdi>{{ auth()->user()->student_code }}</bdi></p></div>
</section>

@if($nextSession)
<a class="next-class-card" href="{{ route('student.courses.show',$nextSession->offering) }}">
    <span class="next-class-icon"><x-icon name="calendar" :size="24" /></span>
    <span class="next-class-copy"><span class="eyebrow"><x-t k="next_class" /></span><strong>{{ $nextSession->offering->display_title }}</strong><span>{{ $nextSession->lesson->display_title }} · {{ $nextSession->group->display_name }}</span><time>{{ $nextSession->scheduled_start_at->timezone($nextSession->timezone)->locale(app()->getLocale())->translatedFormat('l, Y-m-d · H:i') }}</time></span>
    <x-icon name="arrow" :size="20" />
</a>
@endif

<section class="student-action-card">
    <div class="student-action-heading"><span><x-icon name="qr" :size="23" /></span><div><h2><x-t k="enter_class_code" /></h2><p><x-t k="class_code_hint" /></p></div></div>
    <form method="POST" action="{{ route('attendance.code') }}" class="student-code-form" x-data="{ submitting: false }" @submit="submitting = true">@csrf
        <x-field name="code" label="short_code" required minlength="6" maxlength="6" dir="ltr" inputmode="numeric" autocomplete="off" />
        <button class="primary" :disabled="submitting"><span x-show="!submitting"><x-t k="continue_checkin" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
    </form>
</section>

<div class="student-section-heading"><div><p class="eyebrow"><x-t k="student_hint" /></p><h2><x-t k="my_courses" /></h2></div><a href="{{ route('student.courses') }}"><x-t k="view_all" /></a></div>
<div class="student-course-list">
@forelse($enrollments as $enrollment)
<article class="student-course-card">
    <div class="student-course-icon"><x-icon name="book" :size="23" /></div>
    <div class="student-course-copy"><span>{{ $enrollment->offering->semester->display_name }}</span><h3>{{ $enrollment->offering->display_title }}</h3><p>{{ $enrollment->group?->display_name }}</p></div>
    <div class="student-course-meta"><x-status :value="$enrollment->status" /><a href="{{ route('student.courses.show',$enrollment->offering) }}" aria-label="{{ __('ui.view_course') }}"><x-icon name="arrow" :size="20" /></a></div>
</article>
@empty
<div class="empty"><h2><x-t k="no_courses" /></h2><p class="muted"><x-t k="no_courses_hint" /></p></div>
@endforelse
</div>
</div>
</x-layout>
