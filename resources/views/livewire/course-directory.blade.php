<div class="student-directory">
<div class="student-directory-toolbar">
    <div class="student-search"><x-icon name="search" :size="20" /><label class="sr-only" for="course-search"><x-t k="search" /></label><input id="course-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" autocomplete="off" placeholder="{{ __('ui.search') }}"></div>
    <div class="student-directory-result"><span><x-t k="enrolled_courses" /></span><strong>{{ $offerings->total() }}</strong></div>
</div>
<div class="student-course-list" wire:loading.class="opacity-60">
@forelse($offerings as $offering)
@php($enrollment = $offering->enrollments->first())
@php($attendanceTotal = $enrollment->attended_count + $enrollment->absent_count)
@php($courseRate = $attendanceTotal > 0 ? (int) round(($enrollment->attended_count / $attendanceTotal) * 100) : null)
<a class="student-course-card student-course-card-link" href="{{ route('student.courses.show',$offering) }}" wire:key="course-{{ $offering->id }}">
    <div class="student-course-icon"><x-icon name="book" :size="23" /></div>
    <div class="student-course-copy"><span>{{ $offering->semester->display_name }} · {{ $offering->course->code }}</span><h3>{{ $offering->display_title }}</h3><p>{{ $enrollment->group?->display_name }} · {{ $offering->instructor->name }}</p><div class="student-course-progress"><span><i style="width: {{ $courseRate ?? 0 }}%"></i></span><small>{{ $courseRate === null ? __('ui.no_attendance_data') : $courseRate.'% '.__('ui.attendance_rate') }}</small></div></div>
    <div class="student-course-meta"><x-status :value="$enrollment->status" /><span class="student-course-open" aria-hidden="true"><x-icon name="arrow" :size="20" /></span></div>
</a>
@empty
<div class="empty"><h2><x-t k="no_courses" /></h2><p><x-t k="no_courses_hint" /></p></div>
@endforelse
</div>
<div class="mt-5">{{ $offerings->links() }}</div>
</div>
