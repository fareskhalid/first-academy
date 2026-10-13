<x-layout title="attendance_result">
<div class="student-page student-attendance-page">
<section class="student-result-card">
    <div class="student-result-icon"><x-icon name="check" :size="34" /></div>
    <p class="eyebrow"><x-t k="attendance_result" /></p>
    <h1><x-t :k="$duplicate ? 'already_recorded' : 'attendance_recorded'" /></h1>
    <p>{{ $attendance->session->offering->display_title }}</p>
    <strong>{{ $attendance->session->lesson->display_title }}</strong>
    <div class="student-result-status"><x-status :value="$attendance->status" /></div>
</section>
<section class="student-result-details">
    <div><span><x-icon name="calendar" :size="19" /></span><p><x-t k="recorded_at" /><strong><bdi>{{ $attendance->recorded_at->timezone($attendance->session->timezone)->locale(app()->getLocale())->translatedFormat('Y-m-d · h:i:s A') }}</bdi></strong></p></div>
    <div><span><x-icon name="shield" :size="19" /></span><p><x-t k="remaining_free" /><strong>{{ $remaining_free }}</strong></p></div>
</section>
<a class="button student-result-action" href="{{ route('student.courses.show',$attendance->session->course_offering_id) }}"><x-t k="view_course" /><x-icon name="arrow" :size="18" /></a>
</div>
</x-layout>
