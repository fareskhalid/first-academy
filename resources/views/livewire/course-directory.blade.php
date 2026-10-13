<div class="student-directory">
<div class="student-search"><x-icon name="search" :size="20" /><label class="sr-only" for="course-search"><x-t k="search" /></label><input id="course-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" autocomplete="off" placeholder="{{ __('ui.search') }}"></div>
<div class="student-course-list" wire:loading.class="opacity-60">
@forelse($offerings as $offering)
<article class="student-course-card" wire:key="course-{{ $offering->id }}">
    <div class="student-course-icon"><x-icon name="book" :size="23" /></div>
    <div class="student-course-copy"><span>{{ $offering->semester->display_name }} · {{ $offering->course->code }}</span><h3>{{ $offering->display_title }}</h3><p>{{ $offering->instructor->name }}</p></div>
    <div class="student-course-meta"><x-status :value="$offering->enrollments->first()->status" /><a href="{{ route('student.courses.show',$offering) }}" aria-label="{{ __('ui.view_course') }}"><x-icon name="arrow" :size="20" /></a></div>
</article>
@empty
<div class="empty"><h2><x-t k="no_courses" /></h2><p><x-t k="no_courses_hint" /></p></div>
@endforelse
</div>
<div class="mt-5">{{ $offerings->links() }}</div>
</div>
